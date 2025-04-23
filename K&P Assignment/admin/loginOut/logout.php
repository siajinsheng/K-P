<?php
require '../../_base.php';

// Log the logout action if a user is logged in
if (isset($_SESSION['user'])) {
    error_log("Admin logout: {$_SESSION['user']->user_name} ({$_SESSION['user']->user_id}) logged out");
}

// Clear all session data and cookies
logout('login.php');