<?php
/**
 * Room.php
 * Active rooms database management model class
 * Follows PSR-12 coding standards.
 */

namespace App\Models;

use App\Config\Database;
use PDO;

class Room
{
    /**
     * Initialize rooms table and run cleanup of stale rooms.
     */
    public static function init(): void
    {
        $pdo = Database::connect();
        
        // Create rooms table if it doesn't exist
        $query = "CREATE TABLE IF NOT EXISTS rooms (
            room_code VARCHAR(10) PRIMARY KEY,
            status VARCHAR(20),
            players LONGTEXT,
            game_state LONGTEXT,
            created_at INT,
            last_activity INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($query);
        
        // Clean up stale inactive rooms (older than 2 hours)
        $cleanupTime = time() - 7200;
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE last_activity < :cleanup_time");
        $stmt->bindValue(':cleanup_time', $cleanupTime, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Generate a unique room code.
     *
     * @param int $length
     * @return string
     */
    public static function generateRoomCode(int $length = 4): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit ambiguous chars like I, O, 0, 1
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Create a new online multiplayer room.
     *
     * @param string $playerId
     * @param string $playerName
     * @return string
     */
    public static function createRoom(string $playerId, string $playerName, string $playerAvatar): string
    {
        self::init();
        $pdo = Database::connect();
        
        // Generate unique code
        $code = '';
        while (true) {
            $code = self::generateRoomCode();
            $stmt = $pdo->prepare("SELECT 1 FROM rooms WHERE room_code = :code");
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            $stmt->execute();
            if (!$stmt->fetch()) {
                break;
            }
        }
        
        // Initial player (host is seat 0)
        $players = [
            [
                'id' => $playerId,
                'name' => $playerName,
                'seat' => 0,
                'is_bot' => false,
                'active' => true,
                'avatar' => $playerAvatar // Store only whitelisted filename (e.g. avatar_user.png)
            ]
        ];
        
        // New game engine instance
        $game = new TienLenGame();
        $serializedGame = base64_encode(serialize($game));
        
        $now = time();
        $stmt = $pdo->prepare("INSERT INTO rooms (room_code, status, players, game_state, created_at, last_activity) 
                              VALUES (:code, 'waiting', :players, :game_state, :now, :now)");
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->bindValue(':players', json_encode($players), PDO::PARAM_STR);
        $stmt->bindValue(':game_state', $serializedGame, PDO::PARAM_STR);
        $stmt->bindValue(':now', $now, PDO::PARAM_INT);
        $stmt->execute();
        
        return $code;
    }

    /**
     * Fetch room info and game state.
     *
     * @param string $code
     * @return array|false
     */
    public static function getRoom(string $code)
    {
        self::init();
        $pdo = Database::connect();
        
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = :code");
        $stmt->bindValue(':code', strtoupper($code), PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        
        if ($row) {
            $row['players'] = json_decode($row['players'], true);
            // Unserialize game object
            $row['game'] = unserialize(base64_decode($row['game_state']));
        }
        return $row;
    }

    /**
     * Save active room players list and game engine state.
     *
     * @param string $code
     * @param string $status
     * @param array $players
     * @param TienLenGame $game
     */
    public static function saveRoom(string $code, string $status, array $players, TienLenGame $game): void
    {
        $pdo = Database::connect();
        $serializedGame = base64_encode(serialize($game));
        $now = time();
        
        $stmt = $pdo->prepare("UPDATE rooms SET status = :status, players = :players, game_state = :game, last_activity = :now WHERE room_code = :code");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':players', json_encode($players), PDO::PARAM_STR);
        $stmt->bindValue(':game', $serializedGame, PDO::PARAM_STR);
        $stmt->bindValue(':now', $now, PDO::PARAM_INT);
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
    }
}
