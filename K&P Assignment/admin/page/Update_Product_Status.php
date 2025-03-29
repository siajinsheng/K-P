<?php
require '../../_base.php';
auth(0, 1);

header('Content-Type: application/json');

try {
    // Validate input
    $product_id = $_POST['product_id'] ?? null;
    $new_status = $_POST['product_status'] ?? null;

    if (!$product_id || !$new_status) {
        throw new Exception('Invalid input');
    }

    // Check current product stock
    $stock_check_query = "SELECT product_stock FROM product WHERE product_id = ?";
    $stock_stmt = $_db->prepare($stock_check_query);
    $stock_stmt->execute([$product_id]);
    $product = $stock_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Prevent activating product with 0 stock
    if ($new_status === 'Active' && $product['product_stock'] == 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot activate product with 0 stock. Update stock first.'
        ]);
        exit;
    }

    // Prepare and execute update query
    $update_query = "UPDATE product SET product_status = ? WHERE product_id = ?";
    $update_stmt = $_db->prepare($update_query);
    $result = $update_stmt->execute([$new_status, $product_id]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update status');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}