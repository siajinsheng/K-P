<?php
require '../../_base.php';
auth('admin', 'staff');

// Only allow POST requests
if (!is_post()) {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data from request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || !isset($input['new_status'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$order_id = $input['order_id'];
$new_status = $input['new_status'];

// Validate status
$valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $_db->beginTransaction();

    // Update order status
    $stm = $_db->prepare("UPDATE orders SET orders_status = ? WHERE order_id = ?");
    $stm->execute([$new_status, $order_id]);

    // If status is cancelled, handle payment status update
    if ($new_status === 'Cancelled') {
        $stm = $_db->prepare("UPDATE payment SET payment_status = 'Refunded' WHERE order_id = ? AND payment_status = 'Completed'");
        $stm->execute([$order_id]);
    }

    // If status is delivered, update delivery information
    if ($new_status === 'Delivered') {
        $stm = $_db->prepare("
            UPDATE delivery d 
            JOIN orders o ON d.delivery_id = o.delivery_id 
            SET d.delivery_status = 'Delivered', d.delivered_date = CURDATE() 
            WHERE o.order_id = ?
        ");
        $stm->execute([$order_id]);
    }

    // Commit transaction
    $_db->commit();

    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $_db->rollBack();
    
    // Log error
    error_log("Error updating order status: " . $e->getMessage());
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}