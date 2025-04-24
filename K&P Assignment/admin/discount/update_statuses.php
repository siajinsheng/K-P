<?php
require_once '../../_base.php';
auth('admin', 'staff');

// Get current date
$today = date('Y-m-d');

try {
    // Update Active discounts
    $stm = $_db->prepare("
        UPDATE discount 
        SET status = 'Active' 
        WHERE '$today' >= start_date AND '$today' <= end_date
    ");
    $stm->execute();
    
    // Update Upcoming discounts
    $stm = $_db->prepare("
        UPDATE discount 
        SET status = 'Upcoming' 
        WHERE '$today' < start_date
    ");
    $stm->execute();
    
    // Update Expired discounts
    $stm = $_db->prepare("
        UPDATE discount 
        SET status = 'Expired' 
        WHERE '$today' > end_date
    ");
    $stm->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}