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

// Define allowed transitions for each role
$allowedTransitions = [
    'admin' => [
        'Pending' => ['Processing', 'Cancelled'],
        'Processing' => ['Shipped', 'Cancelled'],
        'Shipped' => ['Delivered', 'Cancelled'],
        'Delivered' => [], // Terminal state, no transitions allowed
        'Cancelled' => []  // Terminal state, no transitions allowed
    ],
    'staff' => [
        'Pending' => ['Processing'],
        'Processing' => ['Shipped'],
        'Shipped' => [], // Staff cannot mark as Delivered
        'Delivered' => [],
        'Cancelled' => []
    ]
];

// Check if the transition is allowed for the current role
$role = $currentUserRole === 'admin' ? 'admin' : 'staff';
if (!isset($allowedTransitions[$role][$currentStatus]) || !in_array($newStatus, $allowedTransitions[$role][$currentStatus])) {
    echo json_encode([
        'success' => false, 
        'message' => "You don't have permission to change status from $currentStatus to $newStatus"
    ]);
    exit;
}

// Define mapping between order status and delivery status
$deliveryStatusMapping = [
    'Pending' => 'Processing',
    'Processing' => 'Processing',
    'Shipped' => 'Out for Delivery',
    'Delivered' => 'Delivered',
    'Cancelled' => 'Failed'
];

// Update status and update delivery status for specific transitions
try {
    $_db->beginTransaction();
    
    // Update order status
    $updateOrderQuery = "UPDATE orders o SET o.orders_status = ? WHERE o.order_id = ?";
    $updateOrderStmt = $_db->prepare($updateOrderQuery);
    $updateOrderStmt->execute([$newStatus, $orderId]);
    
    // Always update the delivery status based on the mapping
    $newDeliveryStatus = $deliveryStatusMapping[$newStatus];
    $updateDeliveryQuery = "UPDATE delivery d 
                          JOIN orders o ON d.delivery_id = o.delivery_id 
                          SET d.delivery_status = ?
                          WHERE o.order_id = ?";
    $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
    $updateDeliveryStmt->execute([$newDeliveryStatus, $orderId]);
    
    // Handle additional status-specific updates
    if ($newStatus === 'Shipped') {
        // Set estimated delivery date to 3 days from now if not already set
        $updateEstimatedDateQuery = "UPDATE delivery d 
                                   JOIN orders o ON d.delivery_id = o.delivery_id 
                                   SET d.estimated_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                                   WHERE o.order_id = ? AND d.estimated_date IS NULL";
        $updateEstimatedDateStmt = $_db->prepare($updateEstimatedDateQuery);
        $updateEstimatedDateStmt->execute([$orderId]);
    } 
    elseif ($newStatus === 'Delivered') {
        // When order is delivered, set delivered date
        $updateDeliveryDateQuery = "UPDATE delivery d 
                                  JOIN orders o ON d.delivery_id = o.delivery_id 
                                  SET d.delivered_date = CURDATE()
                                  WHERE o.order_id = ?";
        $updateDeliveryDateStmt = $_db->prepare($updateDeliveryDateQuery);
        $updateDeliveryDateStmt->execute([$orderId]);
    }
    elseif ($newStatus === 'Cancelled') {
        // Also update payment status to "Refunded" if order was cancelled by admin
        $updatePaymentQuery = "UPDATE payment p
                             JOIN orders o ON p.order_id = o.order_id 
                             SET p.payment_status = 'Refunded'
                             WHERE o.order_id = ?";
        $updatePaymentStmt = $_db->prepare($updatePaymentQuery);
        $updatePaymentStmt->execute([$orderId]);
    }
    
    $_db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Order status updated to $newStatus and delivery status updated to $newDeliveryStatus"
    ]);
} 
catch (Exception $e) {
    $_db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>