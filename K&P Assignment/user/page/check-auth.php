<?php
require_once '../../_base.php';

// Ensure session is started
safe_session_start();

// Debug info - will help determine what's happening
$debug = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => isset($_SESSION) ? array_keys($_SESSION) : 'No session data',
    'user_key_exists' => isset($_SESSION['user']),
    'timestamp' => date('Y-m-d H:i:s')
];

// Check if user is logged in
$response = [
    'authenticated' => isset($_SESSION['user']) && !empty($_SESSION['user']->user_id),
    'userId' => isset($_SESSION['user']) ? $_SESSION['user']->user_id : null,
    'username' => isset($_SESSION['user']) ? $_SESSION['user']->user_name : null,
    'debug' => $debug
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;