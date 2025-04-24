<?php
require '../../_base.php';
auth('admin', 'staff');

// Set content type to JSON
header('Content-Type: application/json');

// Ensure it's a POST request
if (!is_post()) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get current user's role
$currentUserRole = $_SESSION['user']->role;

// Get POST data (from JSON body)
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order_id']) || !isset($data['new_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$orderId = $data['order_id'];
$newStatus = $data['new_status'];

// Validate order exists
$checkOrderQuery = "SELECT o.orders_status FROM orders o WHERE o.order_id = ?";
$checkStmt = $_db->prepare($checkOrderQuery);
$checkStmt->execute([$orderId]);
$orderData = $checkStmt->fetch();

if (!$orderData) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$currentStatus = $orderData->orders_status;

// Validate status transition is allowed
// Define allowed transitions
$allowedTransitions = [
    'Pending' => ['Processing', 'Cancelled'],
    'Processing' => ['Shipped', 'Cancelled'],
    'Shipped' => ['Delivered', 'Cancelled'],
    'Delivered' => [], // Terminal state, no transitions allowed
    'Cancelled' => []  // Terminal state, no transitions allowed
];

// Role-based permission check
if ($currentUserRole === 'staff') {
    // Staff can only do specific transitions
    if (
        !($currentStatus === 'Pending' && $newStatus === 'Processing') && 
        !($currentStatus === 'Processing' && $newStatus === 'Shipped')
    ) {
        echo json_encode([
            'success' => false, 
            'message' => 'Staff users can only change status from Pending to Processing or from Processing to Shipped'
        ]);
        exit;
    }
}

// Check if the transition is allowed
if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
    echo json_encode([
        'success' => false, 
        'message' => "Cannot change status from $currentStatus to $newStatus"
    ]);
    exit;
}

// Update status and update delivery status for specific transitions
try {
    $_db->beginTransaction();
    
    // Update order status
    $updateOrderQuery = "UPDATE orders o SET o.orders_status = ? WHERE o.order_id = ?";
    $updateOrderStmt = $_db->prepare($updateOrderQuery);
    $updateOrderStmt->execute([$newStatus, $orderId]);
    
    // For specific transitions, update delivery status too
    if ($newStatus === 'Shipped') {
        // When order is shipped, update delivery status to "Out for Delivery"
        $updateDeliveryQuery = "UPDATE delivery d 
                              JOIN orders o ON d.delivery_id = o.delivery_id 
                              SET d.delivery_status = 'Out for Delivery' 
                              WHERE o.order_id = ?";
        $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
        $updateDeliveryStmt->execute([$orderId]);
        
        // Set estimated delivery date to 3 days from now if not already set
        $updateEstimatedDateQuery = "UPDATE delivery d 
                                   JOIN orders o ON d.delivery_id = o.delivery_id 
                                   SET d.estimated_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                                   WHERE o.order_id = ? AND d.estimated_date IS NULL";
        $updateEstimatedDateStmt = $_db->prepare($updateEstimatedDateQuery);
        $updateEstimatedDateStmt->execute([$orderId]);
    } 
    elseif ($newStatus === 'Delivered') {
        // When order is delivered, update delivery status to "Delivered" and set delivered date
        $updateDeliveryQuery = "UPDATE delivery d 
                              JOIN orders o ON d.delivery_id = o.delivery_id 
                              SET d.delivery_status = 'Delivered', 
                                  d.delivered_date = CURDATE()
                              WHERE o.order_id = ?";
        $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
        $updateDeliveryStmt->execute([$orderId]);
    }
    elseif ($newStatus === 'Cancelled') {
        // When order is cancelled, update delivery status to "Failed"
        $updateDeliveryQuery = "UPDATE delivery d 
                              JOIN orders o ON d.delivery_id = o.delivery_id 
                              SET d.delivery_status = 'Failed'
                              WHERE o.order_id = ?";
        $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
        $updateDeliveryStmt->execute([$orderId]);
    }
    
    $_db->commit();
    
    echo json_encode(['success' => true, 'message' => "Order status updated to $newStatus"]);
} 
catch (Exception $e) {
    $_db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>