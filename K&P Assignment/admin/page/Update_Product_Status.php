<?php
require '../../_base.php';
auth(1, 0); // Allow only managers and admins

// Check if it's a POST request
if (!is_post()) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$product_id = $_POST['product_id'] ?? '';
$product_status = $_POST['product_status'] ?? '';

// Validate input
if (empty($product_id) || empty($product_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input: Product ID and status required']);
    exit;
}

// Validate product exists
try {
    $check_query = "SELECT product_id FROM product WHERE product_id = ?";
    $check_stmt = $_db->prepare($check_query);
    $check_stmt->execute([$product_id]);
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Error checking product existence: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during validation']);
    exit;
}

// Validate status against database enum values
$valid_statuses = ['Available', 'Out of Stock', 'Discontinued'];
if (!in_array($product_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status. Must be one of: ' . implode(', ', $valid_statuses)]);
    exit;
}

try {
    // Update the status
    $query = "UPDATE product SET product_status = ? WHERE product_id = ?";
    $stmt = $_db->prepare($query);
    $result = $stmt->execute([$product_status, $product_id]);

    if ($result && $stmt->rowCount() > 0) {
        // Log the status change
        $admin_id = isset($_SESSION['admin_user']) ? json_decode($_SESSION['admin_user'])->admin_id : 'Unknown';
        error_log("Product status updated: Product ID {$product_id} set to {$product_status} by {$admin_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product status updated successfully',
            'product_id' => $product_id,
            'new_status' => $product_status
        ]);
    } else if ($result && $stmt->rowCount() === 0) {
        // Product exists but no changes were made (status was the same)
        echo json_encode(['success' => true, 'message' => 'No changes required - status already set']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} catch (PDOException $e) {
    error_log("Error updating product status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}