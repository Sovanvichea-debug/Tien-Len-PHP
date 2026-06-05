<?php
/**
 * GameController.php
 * Handles frontend view loading for the Tien Len game board
 * Follows PSR-12 coding standards.
 */

namespace App\Controllers;

class GameController
{
    /**
     * Render the main game application view.
     */
    public function index(): void
    {
        // Load the view file relative to this controller
        require_once __DIR__ . '/../Views/game.php';
    }
}
