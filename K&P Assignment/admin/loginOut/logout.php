<?php
// Include base file for access to functions and database
require '../../_base.php';

// Start session safely
safe_session_start();

// Log the logout action if a user was logged in
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']->user_id;
    $username = $_SESSION['user']->user_name;
    error_log("User logout: ID: $user_id, Username: $username");
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Delete any admin auth cookies if they exist
setcookie('admin_user_id', '', time() - 3600, '/');
setcookie('admin_remember_token', '', time() - 3600, '/');

// Set a temporary message
temp('info', 'You have been successfully logged out.');

// Redirect to login page
header('Location: login.php');
exit();
?>