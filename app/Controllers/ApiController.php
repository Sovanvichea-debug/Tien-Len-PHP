<?php
/**
 * ApiController.php
 * Handles AJAX requests for singleplayer and multiplayer room actions.
 * Follows PSR-12 coding standards.
 */

namespace App\Controllers;

use App\Models\TienLenGame;
use App\Models\Room;

class ApiController
{
    /**
     * Dispatch the request action to the appropriate handler.
     */
    public function handleRequest(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $action = $_GET['action'] ?? '';
        $roomCode = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
        $playerId = isset($_GET['player_id']) ? trim($_GET['player_id']) : '';
        $playerName = isset($_GET['name']) ? trim($_GET['name']) : '';
        $playerAvatar = isset($_GET['avatar']) ? trim($_GET['avatar']) : '';

        // Multiplayer operations
        if ($roomCode !== '' || $action === 'create_room' || $action === 'join_room') {
            $this->handleMultiplayerRequest($action, $roomCode, $playerId, $playerName, $playerAvatar);
        } else {
            // Offline singleplayer operations
            $this->handleSingleplayerRequest($action);
        }
    }

    /**
     * Handle online multiplayer room operations.
     */
    private function handleMultiplayerRequest(string $action, string $roomCode, string $playerId, string $playerName, string $playerAvatar): void
    {
        switch ($action) {
            case 'create_room':
                $nameError = $this->validatePlayerName($playerName);
                if ($nameError !== null) {
                    echo json_encode(['success' => false, 'message' => $nameError]);
                    break;
                }
                $avatarFilename = $this->resolveAvatarFilename($playerAvatar);
                $code = Room::createRoom($playerId, $playerName, $avatarFilename);
                $room = Room::getRoom($code);
                echo $this->getRoomStateResponse($room, $playerId, ['success' => true, 'message' => 'បន្ទប់ត្រូវបានបង្កើតរួចរាល់!']);
                break;

            case 'join_room':
                if ($roomCode === '') {
                    echo json_encode(['success' => false, 'message' => 'សូមបញ្ជាក់លេខកូដបន្ទប់!']);
                    break;
                }
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេងនេះឡើយ!']);
                    break;
                }
                $nameError = $this->validatePlayerName($playerName);
                if ($nameError !== null) {
                    echo json_encode(['success' => false, 'message' => $nameError]);
                    break;
                }

                // Check case-insensitive duplicate name in room
                foreach ($room['players'] as $p) {
                    if ($p['id'] !== $playerId && strcasecmp($p['name'], $playerName) === 0) {
                        echo json_encode(['success' => false, 'message' => 'ឈ្មោះនេះមានរួចហើយនៅក្នុងបន្ទប់លេង!']);
                        break 2;
                    }
                }

                // Check if player is already registered in the room
                $existingSeat = -1;
                foreach ($room['players'] as $p) {
                    if ($p['id'] === $playerId) {
                        $existingSeat = $p['seat'];
                        break;
                    }
                }

                if ($existingSeat !== -1) {
                    echo $this->getRoomStateResponse($room, $playerId, ['success' => true, 'message' => 'បានចូលរួមឡើងវិញជោគជ័យ!']);
                    break;
                }

                if ($room['status'] !== 'waiting') {
                    echo json_encode(['success' => false, 'message' => 'ហ្គេមកំពុងលេងរួចហើយ មិនអាចចូលរួមបានទេ!']);
                    break;
                }

                if (count($room['players']) >= 4) {
                    echo json_encode(['success' => false, 'message' => 'បន្ទប់លេងនេះពេញហើយ!']);
                    break;
                }

                // Find first available seat
                $occupiedSeats = array_column($room['players'], 'seat');
                $newSeat = 0;
                for ($s = 0; $s < 4; $s++) {
                    if (!in_array($s, $occupiedSeats, true)) {
                        $newSeat = $s;
                        break;
                    }
                }

                $avatarFilename = $this->resolveAvatarFilename($playerAvatar);
                $room['players'][] = [
                    'id' => $playerId,
                    'name' => $playerName,
                    'seat' => $newSeat,
                    'is_bot' => false,
                    'active' => true,
                    'avatar' => $avatarFilename
                ];

                Room::saveRoom($roomCode, $room['status'], $room['players'], $room['game']);
                
                // Reload room
                $room = Room::getRoom($roomCode);
                echo $this->getRoomStateResponse($room, $playerId, ['success' => true, 'message' => 'ចូលរួមបន្ទប់លេងជោគជ័យ!']);
                break;

            case 'room_status':
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេង!']);
                    break;
                }

                // Server-side Bot Turn Auto-Execution loop
                $game = $room['game'];
                $players = $room['players'];
                $stateChanged = false;

                if ($room['status'] === 'playing' && !$game->game_over) {
                    // If it is a bot's turn, execute it automatically on the server
                    while (!$game->game_over && $this->isSeatBot($players, $game->current_turn)) {
                        $botSeat = $game->current_turn;
                        $botMove = $game->getBotMove($botSeat);
                        
                        if ($botMove !== null && count($botMove) > 0) {
                            $game->playCards($botSeat, $botMove);
                        } else {
                            $game->passTurn($botSeat);
                        }
                        $stateChanged = true;
                    }
                }

                if ($stateChanged) {
                    Room::saveRoom($roomCode, $room['status'], $players, $game);
                    // Reload
                    $room = Room::getRoom($roomCode);
                }

                echo $this->getRoomStateResponse($room, $playerId);
                break;

            case 'start_room_game':
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេង!']);
                    break;
                }

                // Only seat 0 (host) can start the game
                $mySeat = -1;
                foreach ($room['players'] as $p) {
                    if ($p['id'] === $playerId) {
                        $mySeat = $p['seat'];
                        break;
                    }
                }
                if ($mySeat !== 0) {
                    echo json_encode(['success' => false, 'message' => 'មានតែម្ចាស់បន្ទប់ទេដែលអាចចាប់ផ្តើមហ្គេមបាន!']);
                    break;
                }

                // Fill empty seats with Bots
                $occupiedSeats = array_column($room['players'], 'seat');
                $botNames = ['Bot 1', 'Bot 2', 'Bot 3']; // No emoji prefix, starts with "Bot"
                $botIndex = 0;
                for ($s = 0; $s < 4; $s++) {
                    if (!in_array($s, $occupiedSeats, true)) {
                        // Randomly assign avatar_male.png or avatar_female.png
                        $botAvatar = (rand(0, 1) === 0) ? 'avatar_male.png' : 'avatar_female.png';
                        $room['players'][] = [
                            'id' => 'bot_' . $s,
                            'name' => $botNames[$botIndex++],
                            'seat' => $s,
                            'is_bot' => true,
                            'active' => true,
                            'avatar' => 'public/assets/images/' . $botAvatar
                        ];
                    }
                }

                // Sort players array by seat index for consistent display
                usort($room['players'], static function ($a, $b) {
                    return $a['seat'] <=> $b['seat'];
                });

                // Start game
                $game = $room['game'];
                $game->startNewGame();
                $room['status'] = 'playing';

                Room::saveRoom($roomCode, $room['status'], $room['players'], $game);
                
                // Reload and return
                $room = Room::getRoom($roomCode);
                echo $this->getRoomStateResponse($room, $playerId, ['success' => true, 'message' => 'ហ្គេមបានចាប់ផ្តើម!']);
                break;

            case 'room_play':
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេង!']);
                    break;
                }

                // Find player seat
                $mySeat = -1;
                foreach ($room['players'] as $p) {
                    if ($p['id'] === $playerId) {
                        $mySeat = $p['seat'];
                        break;
                    }
                }

                $input = json_decode(file_get_contents('php://input'), true);
                $cards = isset($input['cards']) ? array_map('intval', $input['cards']) : [];
                
                $game = $room['game'];
                $res = $game->playCards($mySeat, $cards);
                
                Room::saveRoom($roomCode, $room['status'], $room['players'], $game);
                
                // Reload
                $room = Room::getRoom($roomCode);
                echo $this->getRoomStateResponse($room, $playerId, $res);
                break;

            case 'room_pass':
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេង!']);
                    break;
                }

                // Find player seat
                $mySeat = -1;
                foreach ($room['players'] as $p) {
                    if ($p['id'] === $playerId) {
                        $mySeat = $p['seat'];
                        break;
                    }
                }

                $game = $room['game'];
                $res = $game->passTurn($mySeat);
                
                Room::saveRoom($roomCode, $room['status'], $room['players'], $game);
                
                // Reload
                $room = Room::getRoom($roomCode);
                echo $this->getRoomStateResponse($room, $playerId, $res);
                break;

            case 'room_suggest':
                $room = Room::getRoom($roomCode);
                if (!$room) {
                    echo json_encode(['success' => false, 'message' => 'រកមិនឃើញបន្ទប់លេង!', 'cards' => []]);
                    break;
                }

                // Find player seat
                $mySeat = -1;
                foreach ($room['players'] as $p) {
                    if ($p['id'] === $playerId) {
                        $mySeat = $p['seat'];
                        break;
                    }
                }

                $game = $room['game'];
                if ($game->game_over) {
                    echo json_encode(['success' => false, 'message' => 'ហ្គេមបានបញ្ចប់ហើយ!', 'cards' => []]);
                    break;
                }
                if ($game->current_turn !== $mySeat) {
                    echo json_encode(['success' => false, 'message' => 'មិនមែនជាវេនរបស់អ្នកទេ!', 'cards' => []]);
                    break;
                }

                $suggestion = $game->getBotMove($mySeat);
                echo json_encode([
                    'success' => true,
                    'cards' => $suggestion !== null ? $suggestion : []
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'សកម្មភាពបន្ទប់មិនត្រឹមត្រូវ']);
                break;
        }
    }

    /**
     * Handle offline singleplayer operations.
     */
    private function handleSingleplayerRequest(string $action): void
    {
        // Initialize or load game in session
        if (!isset($_SESSION['tienlen_game'])) {
            $_SESSION['tienlen_game'] = new TienLenGame();
        }
        /** @var TienLenGame $game */
        $game = $_SESSION['tienlen_game'];

        switch ($action) {
            case 'start':
                $prevWinner = null;
                if (!empty($game->winner_order)) {
                    $prevWinner = $game->winner_order[0];
                }
                $game->startNewGame($prevWinner);
                $_SESSION['tienlen_game'] = $game;
                echo $this->getGameStateResponse($game, ['success' => true, 'message' => 'ហ្គេមថ្មីបានចាប់ផ្តើម!']);
                break;

            case 'status':
                echo $this->getGameStateResponse($game);
                break;

            case 'play':
                $input = json_decode(file_get_contents('php://input'), true);
                $cards = isset($input['cards']) ? array_map('intval', $input['cards']) : [];
                
                $res = $game->playCards(0, $cards);
                $_SESSION['tienlen_game'] = $game;
                echo $this->getGameStateResponse($game, $res);
                break;

            case 'pass':
                $res = $game->passTurn(0);
                $_SESSION['tienlen_game'] = $game;
                echo $this->getGameStateResponse($game, $res);
                break;

            case 'bot':
                if ($game->game_over) {
                    echo $this->getGameStateResponse($game, ['success' => false, 'message' => 'ហ្គេមបានបញ្ចប់ហើយ!']);
                    break;
                }

                $botId = $game->current_turn;
                if ($botId === 0) {
                    echo $this->getGameStateResponse($game, ['success' => false, 'message' => 'ជាវេនរបស់មនុស្សលេង!']);
                    break;
                }

                $botMove = $game->getBotMove($botId);
                $playRes = null;

                if ($botMove !== null && count($botMove) > 0) {
                    $playRes = $game->playCards($botId, $botMove);
                } else {
                    $playRes = $game->passTurn($botId);
                }

                $_SESSION['tienlen_game'] = $game;
                echo $this->getGameStateResponse($game, array_merge($playRes, [
                    'bot_id' => $botId,
                    'played_cards' => $botMove
                ]));
                break;

            case 'suggest':
                if ($game->game_over) {
                    echo json_encode(['success' => false, 'message' => 'ហ្គេមបានបញ្ចប់ហើយ!', 'cards' => []]);
                    break;
                }
                if ($game->current_turn !== 0) {
                    echo json_encode(['success' => false, 'message' => 'មិនមែនជាវេនរបស់អ្នកទេ!', 'cards' => []]);
                    break;
                }
                $suggestion = $game->getBotMove(0);
                echo json_encode([
                    'success' => true,
                    'cards' => $suggestion !== null ? $suggestion : []
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    }

    /**
     * Helper to determine if seat is a bot.
     */
    private function isSeatBot(array $players, int $seat): bool
    {
        foreach ($players as $p) {
            if ($p['seat'] === $seat) {
                return isset($p['is_bot']) && $p['is_bot'];
            }
        }
        return true; // default to bot if seat not occupied
    }

    /**
     * Format multiplayer room state for client response.
     */
    private function getRoomStateResponse(array $room, string $playerId, ?array $lastActionResult = null): string
    {
        $game = $room['game'];
        $players = $room['players'];
        
        // Find client's seat
        $mySeat = -1;
        foreach ($players as $p) {
            if ($p['id'] === $playerId) {
                $mySeat = $p['seat'];
                break;
            }
        }

        // Prepare hands info
        $handsInfo = [];
        for ($s = 0; $s < 4; $s++) {
            if ($game->game_over) {
                // Expose all cards if game is over
                $handsInfo[$s] = isset($game->hands[$s]) ? $game->hands[$s] : [];
            } else {
                // Expose cards only to the owner, count for others
                if ($s === $mySeat) {
                    $handsInfo[$s] = isset($game->hands[$s]) ? $game->hands[$s] : [];
                } else {
                    $handsInfo[$s] = isset($game->hands[$s]) ? count($game->hands[$s]) : 0;
                }
            }
        }

        // Build full avatar paths safely on the server side
        $processedPlayers = [];
        foreach ($players as $p) {
            $avatarFile = isset($p['avatar']) ? basename($p['avatar']) : 'avatar_user.png';
            $whitelist = ['avatar_user.png', 'avatar_male.png', 'avatar_female.png'];
            if (!in_array($avatarFile, $whitelist, true)) {
                $avatarFile = 'avatar_user.png';
            }
            $p['avatar'] = 'public/assets/images/' . $avatarFile;
            $processedPlayers[] = $p;
        }

        $response = [
            'mode' => 'multiplayer',
            'room_code' => $room['room_code'],
            'room_status' => $room['status'],
            'players' => $processedPlayers,
            'my_seat' => $mySeat,
            'game_over' => $game->game_over,
            'hands' => $handsInfo,
            'current_trick' => $game->current_trick,
            'current_turn' => $game->current_turn,
            'passed' => $game->passed,
            'winner_order' => $game->winner_order,
            'starter_card_needed' => $game->starter_card_needed,
            'white_win_player' => $game->white_win_player,
            'white_win_reason' => $game->white_win_reason,
            'stinky_players' => $game->stinky_players,
            'history' => array_slice($game->history, -15)
        ];

        if ($lastActionResult !== null) {
            $response['result'] = $lastActionResult;
        }

        return json_encode($response);
    }

    /**
     * Format local singleplayer state for response.
     */
    private function getGameStateResponse(TienLenGame $game, ?array $lastActionResult = null): string
    {
        $handsInfo = [];
        if ($game->game_over) {
            $handsInfo = [
                0 => $game->hands[0],
                1 => $game->hands[1],
                2 => $game->hands[2],
                3 => $game->hands[3]
            ];
        } else {
            $handsInfo = [
                0 => $game->hands[0],
                1 => count($game->hands[1]),
                2 => count($game->hands[2]),
                3 => count($game->hands[3])
            ];
        }

        $response = [
            'mode' => 'singleplayer',
            'game_over' => $game->game_over,
            'hands' => $handsInfo,
            'current_trick' => $game->current_trick,
            'current_turn' => $game->current_turn,
            'passed' => $game->passed,
            'winner_order' => $game->winner_order,
            'starter_card_needed' => $game->starter_card_needed,
            'white_win_player' => $game->white_win_player,
            'white_win_reason' => $game->white_win_reason,
            'stinky_players' => $game->stinky_players,
            'history' => array_slice($game->history, -15)
        ];

        if ($lastActionResult !== null) {
            $response['result'] = $lastActionResult;
        }

        return json_encode($response);
    }

    /**
     * Validate player name according to validation rules.
     *
     * @param string $name
     * @return string|null Error message, or null if valid
     */
    private function validatePlayerName(string $name): ?string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'Display name cannot be empty.';
        }
        if (strlen($trimmed) > 20) {
            return 'Display name cannot exceed 20 characters.';
        }
        if (stripos($trimmed, 'bot') === 0) {
            return "Names starting with 'Bot' are reserved for AI players.";
        }
        return null;
    }

    /**
     * Whitelist and resolve avatar filename.
     *
     * @param string $avatar
     * @return string Whitelisted filename
     */
    private function resolveAvatarFilename(string $avatar): string
    {
        $filename = basename($avatar);
        $whitelist = ['avatar_user.png', 'avatar_male.png', 'avatar_female.png'];
        if (in_array($filename, $whitelist, true)) {
            return $filename;
        }
        return 'avatar_user.png'; // default fallback
    }
}
