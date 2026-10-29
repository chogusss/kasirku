<?php
declare(strict_types=1);

/**
 * Application entry point.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (appIsAuthenticated()) {
    appRedirect(appRoleHome());
}

// Render the login page at the root URL while its form posts to login.php.
require __DIR__ . '/login.php';
