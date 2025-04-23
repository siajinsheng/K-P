<?php
// Start output buffering at the very beginning
ob_start();

require '../../_base.php';
auth('admin', 'staff');

if (!is_post()) {
    temp('error', 'Invalid request method');
    redirect('category.php');
}

$category_id = post('category_id');

if (empty($category_id)) {
    temp('error', 'Category ID is required');
    redirect('category.php');
}

try {
    // First check if the category has any products
    $check_query = "SELECT COUNT(*) FROM product WHERE category_id = ?";
    $check_stmt = $_db->prepare($check_query);
    $check_stmt->execute([$category_id]);
    $product_count = $check_stmt->fetchColumn();
    
    if ($product_count > 0) {
        temp('error', 'Cannot delete category: It contains ' . $product_count . ' products');
        redirect('category.php');
    }
    
    // If no products, proceed with deletion
    $delete_query = "DELETE FROM category WHERE category_id = ?";
    $delete_stmt = $_db->prepare($delete_query);
    $delete_stmt->execute([$category_id]);
    
    if ($delete_stmt->rowCount() > 0) {
        temp('success', 'Category deleted successfully');
    } else {
        temp('error', 'Category not found');
    }
    
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
}

redirect('category.php');

// Flush the output buffer
ob_end_flush();
?>