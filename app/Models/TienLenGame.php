<?php
/**
 * TienLenGame.php
 * Core game engine for Tien Len (Vietnamese Cards)
 * Follows PSR-12 coding standards.
 */

namespace App\Models;

class TienLenGame
{
    public $hands = [];              // Array of 4 player hands (0: Human, 1: Bot 1, 2: Bot 2, 3: Bot 3)
    public $current_trick = null;    // null or ['cards' => [], 'type' => '', 'highest_card' => ID, 'played_by' => 0-3]
    public $current_turn = 0;        // Index of player whose turn it is
    public $passed = [];             // Array of booleans [false, false, false, false]
    public $winner_order = [];       // Order of players who finished
    public $starter_card_needed = false; // True if the very first move must contain 3♠ (ID = 0)
    public $history = [];            // Round history log for UI display
    public $game_over = false;
    public $white_win_player = null; // ID of player who won instantly, if any
    public $white_win_reason = '';   // Reason for white win
    public $stinky_players = [];     // Players who hold a 2 when the game ends

    public function __construct()
    {
        $this->resetState();
    }

    public function resetState()
    {
        $this->hands = [[], [], [], []];
        $this->current_trick = null;
        $this->current_turn = 0;
        $this->passed = [false, false, false, false];
        $this->winner_order = [];
        $this->starter_card_needed = false;
        $this->history = [];
        $this->game_over = false;
        $this->white_win_player = null;
        $this->white_win_reason = '';
        $this->stinky_players = [];
    }

    // Card Helper Functions
    public static function getCardValue($id)
    {
        // ID ranges 0..51. Value ranges 3..15 (3 to 2, where J=11, Q=12, K=13, A=14, 2=15)
        return 3 + intdiv($id, 4);
    }

    public static function getCardSuit($id)
    {
        // Suit ranges 0..3: 0 = Spades (♠), 1 = Clubs (♣), 2 = Diamonds (♦), 3 = Hearts (♥)
        return $id % 4;
    }

    public static function getCardName($id)
    {
        $values = [
            3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9',
            10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K', 14 => 'A', 15 => '2'
        ];
        $suits = [0 => '♠', 1 => '♣', 2 => '♦', 3 => '♥'];
        return $values[self::getCardValue($id)] . $suits[self::getCardSuit($id)];
    }

    public static function sortCards(&$cardIds)
    {
        sort($cardIds, SORT_NUMERIC);
    }

    // Check for Instant Wins (White Wins / Tới Trắng)
    public function checkWhiteWin($hand)
    {
        if (count($hand) !== 13) {
            return null;
        }

        // 1. Dragon Straight (3 to Ace: consecutive values 3..14)
        $values = [];
        foreach ($hand as $card) {
            $values[self::getCardValue($card)] = true;
        }
        $dragon_straight = true;
        for ($v = 3; $v <= 14; $v++) {
            if (!isset($values[$v])) {
                $dragon_straight = false;
                break;
            }
        }
        if ($dragon_straight) {
            // Collect the cards forming the straight
            $straight_cards = [];
            for ($v = 3; $v <= 14; $v++) {
                foreach ($hand as $card) {
                    if (self::getCardValue($card) === $v) {
                        $straight_cards[] = $card;
                        break;
                    }
                }
            }
            return ['reason' => 'បៀរខ្សែនាគរាជ (Dragon Straight 3-A)', 'cards' => $straight_cards];
        }

        // 2. Four 2s (all 2s in hand: IDs 48, 49, 50, 51)
        $twos = 0;
        foreach ($hand as $card) {
            if (self::getCardValue($card) === 15) {
                $twos++;
            }
        }
        if ($twos === 4) {
            return ['reason' => 'ការ៉េលេខ ២ (Four 2s)', 'cards' => [48, 49, 50, 51]];
        }

        // 3. 6 Pairs
        $value_counts = array_count_values(array_map([self::class, 'getCardValue'], $hand));
        $pairs_count = 0;
        foreach ($value_counts as $val => $count) {
            $pairs_count += intdiv($count, 2);
        }
        if ($pairs_count >= 6) {
            return ['reason' => 'បៀរគូទាំង ៦ គូ (6 Pairs)', 'cards' => $hand];
        }

        // 4. Same color (13 Red or 13 Black)
        $red_count = 0;
        $black_count = 0;
        foreach ($hand as $card) {
            $suit = self::getCardSuit($card);
            if ($suit === 2 || $suit === 3) {
                $red_count++;
            } else {
                $black_count++;
            }
        }
        if ($red_count === 13) {
            return ['reason' => 'បៀរពណ៌ក្រហមទាំងអស់ (All Red Cards)', 'cards' => $hand];
        }
        if ($black_count === 13) {
            return ['reason' => 'បៀរពណ៌ខ្មៅទាំងអស់ (All Black Cards)', 'cards' => $hand];
        }

        return null;
    }

    // Start a new game
    public function startNewGame($previousWinner = null)
    {
        $this->resetState();

        // 1. Create and shuffle deck
        $deck = range(0, 51);
        shuffle($deck);

        // 2. Deal 13 cards to each of the 4 players
        for ($i = 0; $i < 4; $i++) {
            $this->hands[$i] = array_slice($deck, $i * 13, 13);
            self::sortCards($this->hands[$i]);
        }

        // 3. Check for instant wins (White Wins)
        // Check starting from the player who has 3♠ (ID = 0) or previous winner
        $starter = 0;
        if ($previousWinner === null) {
            for ($i = 0; $i < 4; $i++) {
                if (in_array(0, $this->hands[$i], true)) {
                    $starter = $i;
                    break;
                }
            }
        } else {
            $starter = $previousWinner;
        }

        // Check players in order of turn starting from $starter
        for ($offset = 0; $offset < 4; $offset++) {
            $p = ($starter + $offset) % 4;
            $win = $this->checkWhiteWin($this->hands[$p]);
            if ($win !== null) {
                $this->white_win_player = $p;
                $this->white_win_reason = $win['reason'];
                $this->winner_order[] = $p;
                $this->game_over = true;
                $this->logHistory(
                    $p,
                    'white_win',
                    $win['cards'],
                    "ឈ្នះផ្តាច់ភ្លាមៗ (White Win) ដោយសារមាន " . $win['reason']
                );
                
                // Add remaining players in order of card count
                $remaining = [];
                for ($j = 0; $j < 4; $j++) {
                    if ($j !== $p) {
                        $remaining[] = ['id' => $j, 'count' => count($this->hands[$j])];
                    }
                }
                usort($remaining, function ($a, $b) {
                    return $a['count'] <=> $b['count'];
                });
                foreach ($remaining as $rem) {
                    $this->winner_order[] = $rem['id'];
                }
                
                $this->checkStinkyPlayers();
                return;
            }
        }

        // 4. Set first turn
        $this->current_turn = $starter;
        if ($previousWinner === null) {
            $this->starter_card_needed = true; // Must play 3♠ in the first trick
        }
    }

    // Check if the game has ended and flag stinky players
    private function checkStinkyPlayers()
    {
        $this->stinky_players = [];
        foreach ($this->hands as $p => $hand) {
            if ($p !== $this->winner_order[0]) { // Non-winners
                $has_two = false;
                foreach ($hand as $card) {
                    if (self::getCardValue($card) === 15) {
                        $has_two = true;
                        break;
                    }
                }
                if ($has_two) {
                    $this->stinky_players[] = $p;
                }
            }
        }
    }

    // Log action to history
    public function logHistory($playerId, $action, $cards, $desc)
    {
        $cardNames = array_map([self::class, 'getCardName'], $cards);
        $this->history[] = [
            'player' => $playerId,
            'action' => $action,
            'cards' => $cards,
            'cardNames' => $cardNames,
            'desc' => $desc,
            'timestamp' => time()
        ];
    }

    // Analyze if card combination is legal and return details
    public static function analyzeCombination($cardIds)
    {
        $count = count($cardIds);
        if ($count === 0) {
            return null;
        }

        $cards = $cardIds;
        self::sortCards($cards);

        // Map values and suits
        $vals = [];
        $suits = [];
        foreach ($cards as $c) {
            $vals[] = self::getCardValue($c);
            $suits[] = self::getCardSuit($c);
        }

        // 1. Single
        if ($count === 1) {
            return [
                'type' => 'single',
                'highest_card' => $cards[0],
                'cards' => $cards
            ];
        }

        // 2. Pair
        if ($count === 2) {
            if ($vals[0] === $vals[1]) {
                return [
                    'type' => 'pair',
                    'highest_card' => $cards[1], // sorted, so this has the higher suit
                    'cards' => $cards
                ];
            }
            return null;
        }

        // 3. Triple
        if ($count === 3) {
            if ($vals[0] === $vals[1] && $vals[1] === $vals[2]) {
                return [
                    'type' => 'triple',
                    'highest_card' => $cards[2],
                    'cards' => $cards
                ];
            }
        }

        // 4. Four of a kind
        if ($count === 4) {
            if ($vals[0] === $vals[1] && $vals[1] === $vals[2] && $vals[2] === $vals[3]) {
                return [
                    'type' => 'four_of_a_kind',
                    'highest_card' => $cards[3],
                    'cards' => $cards
                ];
            }
        }

        // 5. Sequences (3 or more cards)
        // No 2s (value 15) allowed in sequences
        $has_two = in_array(15, $vals, true);
        if (!$has_two && $count >= 3) {
            $is_seq = true;
            for ($i = 1; $i < $count; $i++) {
                if ($vals[$i] !== $vals[$i - 1] + 1) {
                    $is_seq = false;
                    break;
                }
            }
            if ($is_seq) {
                return [
                    'type' => 'sequence',
                    'highest_card' => $cards[$count - 1], // highest card in sequence determines strength
                    'cards' => $cards
                ];
            }
        }

        // 6. 3 Pairs of sequences (6 cards)
        if ($count === 6) {
            if ($vals[0] === $vals[1] &&
                $vals[2] === $vals[3] &&
                $vals[4] === $vals[5] &&
                $vals[2] === $vals[0] + 1 &&
                $vals[4] === $vals[2] + 1) {
                return [
                    'type' => 'bomb_3pair',
                    'highest_card' => $cards[5],
                    'cards' => $cards
                ];
            }
        }

        // 7. 4 Pairs of sequences (8 cards)
        if ($count === 8) {
            if ($vals[0] === $vals[1] &&
                $vals[2] === $vals[3] &&
                $vals[4] === $vals[5] &&
                $vals[6] === $vals[7] &&
                $vals[2] === $vals[0] + 1 &&
                $vals[4] === $vals[2] + 1 &&
                $vals[6] === $vals[4] + 1) {
                return [
                    'type' => 'bomb_4pair',
                    'highest_card' => $cards[7],
                    'cards' => $cards
                ];
            }
        }

        return null;
    }

    // Check if Play beats Trick
    public static function canBeat($play, $trick)
    {
        if ($trick === null) {
            return true; // Empty table
        }

        $pType = $play['type'];
        $tType = $trick['type'];
        $pHighest = $play['highest_card'];
        $tHighest = $trick['highest_card'];

        $pVal = self::getCardValue($pHighest);
        $tVal = self::getCardValue($tHighest);

        // Same combination type
        if ($pType === $tType) {
            // Sequences must be of the same length
            if ($pType === 'sequence' && count($play['cards']) !== count($trick['cards'])) {
                return false;
            }
            // Normal beat: highest card ID must be larger
            return $pHighest > $tHighest;
        }

        // Special cuts (bombs on 2s)
        // 1. Cutting a single 2
        if ($tType === 'single' && $tVal === 15) {
            return in_array($pType, ['bomb_3pair', 'four_of_a_kind', 'bomb_4pair'], true);
        }

        // 2. Cutting a pair of 2s
        if ($tType === 'pair' && $tVal === 15) {
            return $pType === 'bomb_4pair';
        }

        // 3. Over-cutting bombs
        if ($tType === 'bomb_3pair') {
            return in_array($pType, ['four_of_a_kind', 'bomb_4pair'], true);
        }

        if ($tType === 'four_of_a_kind') {
            return $pType === 'bomb_4pair';
        }

        return false;
    }

    // Make a play
    public function playCards($playerId, $cardIds)
    {
        if ($this->game_over) {
            return ['success' => false, 'message' => 'ហ្គេមបានបញ្ចប់ហើយ!'];
        }

        // 1. Verify player turn (unless playing a 4-pair sequence bomb which can cut out-of-turn)
        $play = self::analyzeCombination($cardIds);
        if (!$play) {
            return ['success' => false, 'message' => 'ឈុតបៀរនេះមិនត្រឹមត្រូវតាមច្បាប់ទេ!'];
        }

        $is_out_of_turn_bomb = false;
        if ($this->current_trick !== null && $play['type'] === 'bomb_4pair') {
            // Check if it's cutting a 2, a pair of 2s, or another bomb
            $tVal = self::getCardValue($this->current_trick['highest_card']);
            $tType = $this->current_trick['type'];
            if (($tType === 'single' && $tVal === 15) ||
                ($tType === 'pair' && $tVal === 15) ||
                in_array($tType, ['bomb_3pair', 'four_of_a_kind', 'bomb_4pair'], true)) {
                $is_out_of_turn_bomb = true;
            }
        }

        if (!$is_out_of_turn_bomb && $this->current_turn !== $playerId) {
            return ['success' => false, 'message' => 'មិនមែនជាវេនរបស់អ្នកឡើយ!'];
        }

        if (!$is_out_of_turn_bomb && $this->passed[$playerId]) {
            return ['success' => false, 'message' => 'អ្នកបានចម្លងរួចហើយ មិនអាចលេងក្នុងជុំនេះទៀតទេ!'];
        }

        // 2. Verify player has these cards
        foreach ($cardIds as $cid) {
            if (!in_array($cid, $this->hands[$playerId], true)) {
                return ['success' => false, 'message' => 'អ្នកគ្មានសន្លឹកបៀរនេះក្នុងដៃឡើយ!'];
            }
        }

        // 3. First move of first game must include 3♠ (ID = 0)
        if ($this->starter_card_needed) {
            if (!in_array(0, $cardIds, true)) {
                return ['success' => false, 'message' => 'ក្តារដំបូងបង្អស់ត្រូវតែមានសន្លឹក 3♠️ (បីប៊ិច) ទៅជាមួយ!'];
            }
        }

        // 4. Validate if play beats current trick
        if (!$this->canBeat($play, $this->current_trick)) {
            return ['success' => false, 'message' => 'បៀររបស់អ្នកមិនអាចកាត់បៀរនៅលើតុបានឡើយ!'];
        }

        // Action approved! Remove cards from player's hand
        $this->hands[$playerId] = array_values(array_diff($this->hands[$playerId], $cardIds));
        $this->starter_card_needed = false;

        // If played out of turn, force the turn and unpass the cutter
        if ($is_out_of_turn_bomb) {
            $this->passed[$playerId] = false;
            $this->current_turn = $playerId;
        }

        // Set the table trick
        $this->current_trick = [
            'cards' => $cardIds,
            'type' => $play['type'],
            'highest_card' => $play['highest_card'],
            'played_by' => $playerId
        ];

        // Log history
        $action_desc = "ទម្លាក់ " . count($cardIds) . " សន្លឹក (" . implode(', ', array_map([self::class, 'getCardName'], $cardIds)) . ")";
        $this->logHistory($playerId, 'play', $cardIds, $action_desc);

        // Check if player finished (emptied hand)
        if (count($this->hands[$playerId]) === 0) {
            if (!in_array($playerId, $this->winner_order, true)) {
                $this->winner_order[] = $playerId;
            }
            
            $place = count($this->winner_order);
            $placeNames = [1 => 'លេខ ១', 2 => 'លេខ ២', 3 => 'លេខ ៣', 4 => 'លេខ ៤'];
            $placeStr = isset($placeNames[$place]) ? $placeNames[$place] : "លេខ $place";
            $this->logHistory($playerId, 'finish', [], "អស់បៀរពីដៃ ($placeStr)");
            
            if (count($this->winner_order) >= 3) {
                // Game over! Determine 4th place
                $lastPlayer = -1;
                for ($i = 0; $i < 4; $i++) {
                    if (!in_array($i, $this->winner_order, true)) {
                        $lastPlayer = $i;
                        break;
                    }
                }
                if ($lastPlayer !== -1) {
                    $this->winner_order[] = $lastPlayer;
                    $this->logHistory($lastPlayer, 'finish', [], "អស់បៀរចុងក្រោយគេ ($placeNames[4])");
                }
                
                $this->game_over = true;
                $this->checkStinkyPlayers();
                return ['success' => true, 'message' => 'អស់បៀរពីដៃ! ហ្គេមបានបញ្ចប់។'];
            } else {
                // Game continues for other players
                $this->moveToNextTurn();
                return ['success' => true, 'message' => 'អស់បៀរពីដៃ!'];
            }
        }

        // Move to the next player
        $this->moveToNextTurn();

        return ['success' => true, 'message' => 'លេងបានជោគជ័យ!'];
    }

    // Pass turn
    public function passTurn($playerId)
    {
        if ($this->game_over) {
            return ['success' => false, 'message' => 'ហ្គេមបានបញ្ចប់ហើយ!'];
        }

        if ($this->current_turn !== $playerId) {
            return ['success' => false, 'message' => 'មិនមែនជាវេនរបស់អ្នកឡើយ!'];
        }

        if ($this->current_trick === null) {
            return ['success' => false, 'message' => 'អ្នកកំពុងបើកជុំថ្មី មិនអាចចម្លង/រំលងបានឡើយ!'];
        }

        $this->passed[$playerId] = true;
        $this->logHistory($playerId, 'pass', [], "ចម្លង (Pass)");

        $this->moveToNextTurn();

        return ['success' => true, 'message' => 'រំលងបានជោគជ័យ!'];
    }

    // Clockwise shift of turn to next active, non-passed player
    private function moveToNextTurn()
    {
        $next = $this->current_turn;
        
        while (true) {
            $next = ($next + 1) % 4;
            
            // If we looped back to the player who played the trick, it means everyone else passed.
            // Reset round!
            if ($this->current_trick !== null && $next === $this->current_trick['played_by']) {
                $this->current_trick = null;
                $this->passed = [false, false, false, false];
                // The winner of the round opens the new round, but if they finished, the turn moves clockwise
                if (count($this->hands[$next]) === 0) {
                    // Loop to next non-empty hand
                    while (count($this->hands[$next]) === 0) {
                        $next = ($next + 1) % 4;
                    }
                }
                $this->current_turn = $next;
                $this->logHistory($next, 'new_round', [], "បានសិទ្ធិបើកជុំថ្មី");
                break;
            }

            // A player is active if they still have cards and have not passed
            $has_cards = count($this->hands[$next]) > 0;
            $not_passed = !$this->passed[$next];

            if ($has_cards && $not_passed) {
                $this->current_turn = $next;
                break;
            }
        }
    }

    // Helper: Cartesian Product for generating sequences
    private static function cartesianProduct($arrays)
    {
        $result = [[]];
        foreach ($arrays as $key => $values) {
            $append = [];
            foreach ($result as $product) {
                foreach ($values as $item) {
                    $newProduct = $product;
                    $newProduct[] = $item;
                    $append[] = $newProduct;
                }
            }
            $result = $append;
        }
        return $result;
    }

    // AI logic: select a move for a bot
    public function getBotMove($botId)
    {
        $hand = $this->hands[$botId];
        $trick = $this->current_trick;

        // Group cards in hand by value
        $groups = [];
        foreach ($hand as $card) {
            $val = self::getCardValue($card);
            $groups[$val][] = $card;
        }

        // If bot must play first of first game, they must include 3♠ (ID = 0)
        $mustIncludeThreeOfSpades = $this->starter_card_needed && (in_array(0, $hand, true));

        // Case 1: Bot opens the round (empty table)
        if ($trick === null) {
            return $this->botLeadPlay($botId, $hand, $groups, $mustIncludeThreeOfSpades);
        }

        // Case 2: Bot must beat current trick
        return $this->botDefendPlay($botId, $hand, $groups, $trick);
    }

    // Bot logic: Decide what combination to lead with
    private function botLeadPlay($botId, $hand, $groups, $mustIncludeThree)
    {
        // If must play 3♠, find all valid plays containing card 0 and pick the smallest
        if ($mustIncludeThree) {
            // Try single 3♠
            $candidates = [[0]];

            // Try pairs with 3
            if (isset($groups[3]) && count($groups[3]) >= 2) {
                foreach ($groups[3] as $c) {
                    if ($c !== 0) {
                        $candidates[] = [0, $c];
                    }
                }
            }

            // Try triples with 3
            if (isset($groups[3]) && count($groups[3]) >= 3) {
                $candidates[] = array_slice($groups[3], 0, 3);
            }

            // Try sequences starting with 3♠ (must include 3, value 3)
            // Let's find sequences of length 3, 4, 5... containing 3
            for ($len = 3; $len <= 5; $len++) {
                $seqs = $this->findAllSequencesOfLength($groups, $len);
                foreach ($seqs as $s) {
                    if (in_array(0, $s, true)) {
                        $candidates[] = $s;
                    }
                }
            }

            // Return the preferred candidate (longest sequence, or triple, or pair, or single)
            // Let's sort candidates: favor sequences, then triples, then pairs, then single
            usort($candidates, function ($a, $b) {
                $aAnal = self::analyzeCombination($a);
                $bAnal = self::analyzeCombination($b);
                $types = ['single' => 1, 'pair' => 2, 'triple' => 3, 'sequence' => 4];
                return $types[$bAnal['type']] <=> $types[$aAnal['type']];
            });

            return $candidates[0];
        }

        // Bot is free to play anything.
        // Heuristic: Find the lowest card in hand and play a combination containing it.
        $lowest_card = $hand[0];
        $lowest_val = self::getCardValue($lowest_card);

        // 1. Try to play a sequence containing the lowest value
        for ($len = 5; $len >= 3; $len--) {
            $seqs = $this->findAllSequencesOfLength($groups, $len);
            foreach ($seqs as $s) {
                if (in_array($lowest_card, $s, true)) {
                    return $s;
                }
            }
        }

        // 2. Try to play triple of the lowest value
        if (count($groups[$lowest_val]) >= 3) {
            return $groups[$lowest_val];
        }

        // 3. Try to play pair of the lowest value
        if (count($groups[$lowest_val]) >= 2) {
            return $groups[$lowest_val];
        }

        // 4. Play it as a single
        return [$lowest_card];
    }

    // Find all sequences of a specific length from grouped values
    private function findAllSequencesOfLength($groups, $length)
    {
        $sequences = [];
        // Max value in sequence is Ace (14). 2s (15) cannot be in a sequence
        for ($start = 3; $start <= 14 - $length + 1; $start++) {
            $valid = true;
            $arrays = [];
            for ($i = 0; $i < $length; $i++) {
                $val = $start + $i;
                if (!isset($groups[$val])) {
                    $valid = false;
                    break;
                }
                $arrays[] = $groups[$val];
            }
            if ($valid) {
                $products = self::cartesianProduct($arrays);
                foreach ($products as $p) {
                    $sequences[] = $p;
                }
            }
        }
        return $sequences;
    }

    // Bot logic: Decide what card(s) to play to beat the trick
    private function botDefendPlay($botId, $hand, $groups, $trick)
    {
        $tType = $trick['type'];
        $tVal = self::getCardValue($trick['highest_card']);
        $tCount = count($trick['cards']);

        $candidates = [];

        // 1. Find standard counters of the SAME type and SAME count
        if ($tType === 'single') {
            foreach ($hand as $card) {
                if ($card > $trick['highest_card']) {
                    $candidates[] = [$card];
                }
            }
        } elseif ($tType === 'pair') {
            foreach ($groups as $val => $cards) {
                if (count($cards) >= 2) {
                    // Check all pairs in this group
                    for ($i = 0; $i < count($cards); $i++) {
                        for ($j = $i + 1; $j < count($cards); $j++) {
                            $pair = [$cards[$i], $cards[$j]];
                            $pAnal = self::analyzeCombination($pair);
                            if (self::canBeat($pAnal, $trick)) {
                                $candidates[] = $pair;
                            }
                        }
                    }
                }
            }
        } elseif ($tType === 'triple') {
            foreach ($groups as $val => $cards) {
                if (count($cards) >= 3) {
                    // Just take the combination of 3 cards
                    for ($i = 0; $i < count($cards); $i++) {
                        for ($j = $i + 1; $j < count($cards); $j++) {
                            for ($k = $j + 1; $k < count($cards); $k++) {
                                $triple = [$cards[$i], $cards[$j], $cards[$k]];
                                $pAnal = self::analyzeCombination($triple);
                                if (self::canBeat($pAnal, $trick)) {
                                    $candidates[] = $triple;
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($tType === 'sequence') {
            $seqs = $this->findAllSequencesOfLength($groups, $tCount);
            foreach ($seqs as $s) {
                $pAnal = self::analyzeCombination($s);
                if (self::canBeat($pAnal, $trick)) {
                    $candidates[] = $s;
                }
            }
        }

        // 2. Find special counters (bombs) to cut 2s or lower bombs
        $bombs = $this->findAllBombs($groups);
        foreach ($bombs as $bomb) {
            $bAnal = self::analyzeCombination($bomb);
            if (self::canBeat($bAnal, $trick)) {
                $candidates[] = $bomb;
            }
        }

        if (empty($candidates)) {
            return null; // Pass
        }

        // Heuristic: Play the lowest value candidate to save high cards
        // Sort candidates by their highest card ID ascending
        usort($candidates, function ($a, $b) {
            $aAnal = self::analyzeCombination($a);
            $bAnal = self::analyzeCombination($b);
            return $aAnal['highest_card'] <=> $bAnal['highest_card'];
        });

        // Let's check if the bot should be reluctant to play a 2 on a low card
        // If table is a single low card (e.g. value <= 8) and we are about to play a 2,
        // we might prefer playing a lower single card if available.
        $chosen = $candidates[0];
        $cAnal = self::analyzeCombination($chosen);
        if ($tType === 'single' && $tVal <= 8 && self::getCardValue($cAnal['highest_card']) === 15) {
            // Look if we have a non-2 card that beats the table
            foreach ($candidates as $cand) {
                $candAnal = self::analyzeCombination($cand);
                if (self::getCardValue($candAnal['highest_card']) < 15) {
                    return $cand; // Play this non-2 instead!
                }
            }
        }

        return $chosen;
    }

    // Find all bombs (3-pair sequence, four of a kind, 4-pair sequence) in hand
    private function findAllBombs($groups)
    {
        $bombs = [];

        // 1. Four of a kind
        foreach ($groups as $val => $cards) {
            if (count($cards) === 4) {
                $bombs[] = $cards;
            }
        }

        // 2. 3-pair sequences (consecutive pairs)
        for ($start = 3; $start <= 12; $start++) {
            if (isset($groups[$start], $groups[$start+1], $groups[$start+2]) &&
                count($groups[$start]) >= 2 &&
                count($groups[$start+1]) >= 2 &&
                count($groups[$start+2]) >= 2) {
                // Get lowest 2 cards from each group to build the bomb
                $bomb = array_merge(
                    array_slice($groups[$start], 0, 2),
                    array_slice($groups[$start+1], 0, 2),
                    array_slice($groups[$start+2], 0, 2)
                );
                $bombs[] = $bomb;
            }
        }

        // 3. 4-pair sequences
        for ($start = 3; $start <= 11; $start++) {
            if (isset($groups[$start], $groups[$start+1], $groups[$start+2], $groups[$start+3]) &&
                count($groups[$start]) >= 2 &&
                count($groups[$start+1]) >= 2 &&
                count($groups[$start+2]) >= 2 &&
                count($groups[$start+3]) >= 2) {
                $bomb = array_merge(
                    array_slice($groups[$start], 0, 2),
                    array_slice($groups[$start+1], 0, 2),
                    array_slice($groups[$start+2], 0, 2),
                    array_slice($groups[$start+3], 0, 2)
                );
                $bombs[] = $bomb;
            }
        }

        return $bombs;
    }
}
