<?php
require '../../_base.php';

// Clear admin-specific cookies
setcookie('admin_id', '', time() - 3600, '/');
setcookie('admin_token', '', time() - 3600, '/');

// Use the common logout function from _base.php, redirecting to admin login
logout('login.php');
?>