<?php
require '../../_base.php';
auth(1, 'Manager', 'Admin'); // Allow only managers and admins

// Check if it's a POST request
if (!is_post()) {
    $_SESSION['error_message'] = 'Invalid request method';
    header('Location: product.php');
    exit;
}

// Get parameters
$category_name = $_POST['category_name'] ?? '';
// Note: category_description doesn't exist in your schema, but we'll keep it in case you plan to add it

// Validate input
if (empty($category_name)) {
    $_SESSION['error_message'] = 'Category name is required';
    header('Location: product.php');
    exit;
}

try {
    // Check if category already exists
    $check_query = "SELECT category_id FROM category WHERE category_name = ?";
    $check_stmt = $_db->prepare($check_query);
    $check_stmt->execute([$category_name]);

    if ($check_stmt->rowCount() > 0) {
        $_SESSION['error_message'] = 'Category already exists';
        header('Location: product.php');
        exit;
    }

    // Generate a unique category_id (using a simple format, adjust as needed)
    $category_id = 'CAT' . date('YmdHis') . rand(100, 999);

    // Check if ID already exists (unlikely but good practice)
    $id_check_query = "SELECT category_id FROM category WHERE category_id = ?";
    $id_check_stmt = $_db->prepare($id_check_query);
    $id_check_stmt->execute([$category_id]);

    // If ID exists, generate a new one (rare edge case)
    if ($id_check_stmt->rowCount() > 0) {
        $category_id = 'CAT' . date('YmdHis') . rand(1000, 9999);
    }

    // Insert new category (note: your schema shows only category_id and category_name)
    $query = "INSERT INTO category (category_id, category_name) VALUES (?, ?)";
    $stmt = $_db->prepare($query);
    $result = $stmt->execute([$category_id, $category_name]);

    if ($result) {
        $_SESSION['success_message'] = 'Category added successfully';
        
        // Log the successful addition - Fixed: don't use json_decode on an object
        $admin_id = 'Unknown';
        if (isset($_SESSION['admin_user'])) {
            $admin_user = $_SESSION['admin_user'];
            // Check if it's already an object or a JSON string
            $admin_id = is_object($admin_user) ? $admin_user->admin_id : 
                        (is_string($admin_user) ? json_decode($admin_user)->admin_id : 'Unknown');
        }
        error_log("New category added: {$category_name} (ID: {$category_id}) by {$admin_id}");
    } else {
        $_SESSION['error_message'] = 'Failed to add category';
    }
} catch (PDOException $e) {
    error_log("Error adding category: " . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred: ' . $e->getMessage();
}

header('Location: product.php');
exit;
?>