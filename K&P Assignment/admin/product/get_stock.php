<?php
require '../../_base.php';
auth('admin', 'staff');

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$product_id = $_GET['product_id'];

try {
    $stmt = $_db->prepare("SELECT * FROM quantity WHERE product_id = ? ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL')");
    $stmt->execute([$product_id]);
    $stock = $stmt->fetchAll();
    
    // Check for low stock items
    $low_stock = [];
    foreach ($stock as $item) {
        if ($item->product_stock < 10) {
            $low_stock[] = $item;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'stock' => $stock,
        'has_low_stock' => count($low_stock) > 0,
        'low_stock' => $low_stock
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}