<?php
$_title = 'K&P - Admin Logout';
require '../../_base.php';

// Ensure session is started
safe_session_start();

// Store user info for logging before logout
$user_name = isset($_SESSION['user']) ? $_SESSION['user']->user_name : 'Unknown user';
$user_id = isset($_SESSION['user']) ? $_SESSION['user']->user_id : 'Unknown ID';
$role = isset($_SESSION['user']) ? $_SESSION['user']->role : 'Unknown role';

// Check if user was actually logged in
$was_logged_in = isset($_SESSION['user']);

// Clear all session variables
$_SESSION = array();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookies
setcookie('admin_user_id', '', time() - 3600, '/');
setcookie('admin_remember_token', '', time() - 3600, '/');

// Log the logout event
if ($was_logged_in) {
    error_log("Admin logout: {$user_name} ({$user_id}) logged out from {$role} role");
}

// Set success message for next page
temp('success', 'You have been successfully logged out.');

// Redirect to login page
redirect('./login.php');
?>