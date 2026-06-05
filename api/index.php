<?php
/**
 * index.php (api)
 * AJAX requests API entry point
 * Follows PSR-12 coding standards.
 */

// Load bootstrap (autoloader and session initialization)
require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ApiController;

// Instantiate and invoke the ApiController
$controller = new ApiController();
$controller->handleRequest();
