<?php
/**
 * index.php (public)
 * Game application entry point
 * Follows PSR-12 coding standards.
 */

// Load bootstrap (autoloader and session initialization)
require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\GameController;

// Instantiate and invoke the GameController
$controller = new GameController();
$controller->index();
