<?php
require_once '../../_base.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to your cart',
        'redirect' => 'login.php'
    ]);
    exit;
}

// Check if request is POST
if (!is_post()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get form data
$product_id = post('product_id');
$size = post('size', ''); // Size is optional for products page, required for product details page
$quantity = (int) post('quantity', 1);
$user_id = $_SESSION['user']->user_id;
$current_time = date('Y-m-d H:i:s'); // Current timestamp

// Log request data for debugging
error_log("Add to cart request - User: $user_id, Product: $product_id, Size: $size, Quantity: $quantity, Time: $current_time");

// Validate product ID
if (empty($product_id)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required',
        'field' => 'product_id'
    ]);
    exit;
}

// Validate quantity
if ($quantity < 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Quantity must be at least 1',
        'field' => 'quantity'
    ]);
    exit;
}

// Validate size if provided
if (!empty($size) && !in_array($size, ['S', 'M', 'L', 'XL', 'XXL'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid size selected',
        'field' => 'size'
    ]);
    exit;
}

try {
    // Check if product exists and is available
    $stm = $_db->prepare("
        SELECT * FROM product WHERE product_id = ? AND product_status = 'Available'
    ");
    $stm->execute([$product_id]);
    $product = $stm->fetch();
    
    if (!$product) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or is not available'
        ]);
        exit;
    }
    
    // If size is not provided, select a default size
    if (empty($size)) {
        // Try to get medium size first
        $stm = $_db->prepare("
            SELECT size, product_stock FROM quantity 
            WHERE product_id = ? AND size = 'M' AND product_stock > 0
        ");
        $stm->execute([$product_id]);
        $size_data = $stm->fetch();
        
        if (!$size_data) {
            // If medium not available, get any available size
            $stm = $_db->prepare("
                SELECT size, product_stock FROM quantity 
                WHERE product_id = ? AND product_stock > 0
                ORDER BY FIELD(size, 'M', 'L', 'S', 'XL', 'XXL')
                LIMIT 1
            ");
            $stm->execute([$product_id]);
            $size_data = $stm->fetch();
            
            if (!$size_data) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'No sizes available for this product'
                ]);
                exit;
            }
        }
        
        $size = $size_data->size;
    }
    
    // Check stock for the selected size
    $stm = $_db->prepare("
        SELECT product_stock FROM quantity 
        WHERE product_id = ? AND size = ?
    ");
    $stm->execute([$product_id, $size]);
    $stock = $stm->fetchColumn();
    
    if ($stock === false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Selected size is not available',
            'field' => 'size'
        ]);
        exit;
    }
    
    if ($stock < $quantity) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Only $stock items available in this size",
            'field' => 'quantity'
        ]);
        exit;
    }
    
    // Generate a unique cart ID
    function generateCartId() {
        return 'CART_' . uniqid();
    }
    
    // Begin transaction
    $_db->beginTransaction();
    
    // Check if same product and size already in cart
    $stm = $_db->prepare("
        SELECT cart_id, quantity FROM cart 
        WHERE user_id = ? AND product_id = ? AND size = ?
    ");
    $stm->execute([$user_id, $product_id, $size]);
    $cart_item = $stm->fetch();
    
    if ($cart_item) {
        // Update existing cart item
        $new_quantity = $cart_item->quantity + $quantity;
        
        // Check stock again with new quantity
        if ($new_quantity > $stock) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Cannot add $quantity more. You already have {$cart_item->quantity} in your cart and only $stock are available.",
                'field' => 'quantity'
            ]);
            $_db->rollBack();
            exit;
        }
        
        $stm = $_db->prepare("
            UPDATE cart SET quantity = ?, added_time = ? WHERE cart_id = ?
        ");
        $stm->execute([$new_quantity, $current_time, $cart_item->cart_id]);
        
        error_log("Updated cart item: {$cart_item->cart_id} - New quantity: $new_quantity");
        
        $totalQuantity = $new_quantity;
    } else {
        // Create new cart item
        $cart_id = generateCartId();
        
        $stm = $_db->prepare("
            INSERT INTO cart (cart_id, user_id, product_id, size, quantity, added_time) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stm->execute([$cart_id, $user_id, $product_id, $size, $quantity, $current_time]);
        
        error_log("Added new cart item: $cart_id");
        
        $totalQuantity = $quantity;
    }
    
    // Commit transaction
    $_db->commit();
    
    // Get total items in cart
    $stm = $_db->prepare("
        SELECT COUNT(*) as item_count, SUM(quantity) as total_quantity FROM cart 
        WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $cart_stats = $stm->fetch();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'product_name' => $product->product_name,
        'size' => $size,
        'quantity' => $quantity,
        'totalQuantity' => $totalQuantity,
        'cartCount' => $cart_stats->item_count,
        'cartTotalItems' => $cart_stats->total_quantity
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($_db->inTransaction()) {
        $_db->rollBack();
    }
    
    error_log("Add to cart error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding the product to cart',
        'error' => $e->getMessage()
    ]);
}