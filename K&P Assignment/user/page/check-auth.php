<?php
require_once '../../_base.php';

// Check if user is logged in
$response = [
    'authenticated' => isset($_SESSION['user']),
    'userId' => isset($_SESSION['user']) ? $_SESSION['user']->user_id : null
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;