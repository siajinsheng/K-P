<?php
// Start output buffering at the very beginning
ob_start();

require '../../_base.php';
auth('admin', 'staff');

// Debug: Log the request method and data
error_log("Delete category request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

if (!is_post()) {
    temp('error', 'Invalid request method');
    redirect('category.php');
}

$category_id = post('category_id');

if (empty($category_id)) {
    temp('error', 'Category ID is required');
    redirect('category.php');
}

// Log the category ID being deleted
error_log("Attempting to delete category ID: " . $category_id);

try {
    // First check if the category exists
    $check_exists = "SELECT COUNT(*) FROM category WHERE category_id = ?";
    $exists_stmt = $_db->prepare($check_exists);
    $exists_stmt->execute([$category_id]);
    $category_exists = $exists_stmt->fetchColumn() > 0;
    
    if (!$category_exists) {
        temp('error', 'Category not found');
        redirect('category.php');
    }
    
    // Then check if the category has any products
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
    
    $rows_affected = $delete_stmt->rowCount();
    error_log("Rows affected by delete: " . $rows_affected);
    
    if ($rows_affected > 0) {
        temp('success', 'Category deleted successfully');
    } else {
        temp('error', 'Failed to delete category');
    }
    
} catch (PDOException $e) {
    error_log("Delete category error: " . $e->getMessage());
    temp('error', 'Database error: ' . $e->getMessage());
}

redirect('category.php');

// Flush the output buffer
ob_end_flush();
?>