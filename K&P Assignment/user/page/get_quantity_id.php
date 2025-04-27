<?php
require_once '../../_base.php';

// Ensure we're receiving a POST request
if (!is_post()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get product_id and size from the request
$product_id = post('product_id');
$size = post('size');

// Validate inputs
if (empty($product_id) || empty($size)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Product ID and size are required'
    ]);
    exit;
}

try {
    // Get the quantity_id for the product-size combination
    $stm = $_db->prepare("
        SELECT q.quantity_id, q.product_stock
        FROM quantity q
        WHERE q.product_id = ? AND q.size = ?
    ");
    $stm->execute([$product_id, $size]);
    $result = $stm->fetch();
    
    if (!$result) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Size not available for this product'
        ]);
        exit;
    }
    
    // Check if the size is in stock
    if ($result->product_stock <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'This size is out of stock'
        ]);
        exit;
    }
    
    // Return the quantity_id
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'quantity_id' => $result->quantity_id,
        'product_stock' => $result->product_stock
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching quantity_id: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}