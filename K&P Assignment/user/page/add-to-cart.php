<?php
require_once '../../_base.php';

// Explicitly start session
safe_session_start();

// Debug authentication state (for troubleshooting)
$auth_debug = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => isset($_SESSION) ? array_keys($_SESSION) : 'No session data',
    'user_key_exists' => isset($_SESSION['user']),
    'user_id_exists' => isset($_SESSION['user']) && isset($_SESSION['user']->user_id),
    'timestamp' => date('Y-m-d H:i:s')
];
error_log("Auth Debug in add-to-cart.php: " . json_encode($auth_debug));

// Improved check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    $error_message = 'Please log in to add items to your cart';
    error_log("Auth failure in add-to-cart.php: " . $error_message . " | Debug: " . json_encode($auth_debug));

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'redirect' => 'login.php',
        'debug' => $auth_debug
    ]);
    exit;
}

// Authentication successful - extract user data
$user_id = $_SESSION['user']->user_id;
$user_name = $_SESSION['user']->user_name ?? 'Unknown';
error_log("User authenticated in add-to-cart.php: $user_id ($user_name)");

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
$current_time = date('Y-m-d H:i:s'); // Current time (UTC)

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

    // If size is not provided, determine the best available size
    if (empty($size)) {
        // Try to find the most available size, prioritizing Medium
        $stm = $_db->prepare("
            SELECT size, product_stock FROM quantity 
            WHERE product_id = ? AND product_stock > 0
            ORDER BY CASE size 
                WHEN 'M' THEN 1 
                WHEN 'L' THEN 2 
                WHEN 'S' THEN 3 
                WHEN 'XL' THEN 4 
                WHEN 'XXL' THEN 5 
            END
            LIMIT 1
        ");
        $stm->execute([$product_id]);
        $available_size = $stm->fetch();

        if (!$available_size) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No sizes available for this product'
            ]);
            exit;
        }

        $size = $available_size->size;
    } else {
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
    }

    // Begin transaction
    $_db->beginTransaction();

    // Generate a unique cart ID
    function generateCartId()
    {
        return 'CART_' . date('YmdHis') . '_' . substr(uniqid(), -8);
    }

    // Check if product already in cart for this user and size
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
        $stm = $_db->prepare("
            SELECT product_stock FROM quantity 
            WHERE product_id = ? AND size = ?
        ");
        $stm->execute([$product_id, $size]);
        $stock = $stm->fetchColumn();

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

        $cartId = $cart_item->cart_id;
        $totalQuantity = $new_quantity;
    } else {
        // Create new cart item
        $cartId = generateCartId();

        $stm = $_db->prepare("
            INSERT INTO cart (cart_id, user_id, product_id, size, quantity, added_time) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stm->execute([$cartId, $user_id, $product_id, $size, $quantity, $current_time]);

        $totalQuantity = $quantity;
    }

    // Get total items in cart (count of items, not sum of quantities)
    $stm = $_db->prepare("
        SELECT COUNT(*) FROM cart WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $cartTotalItems = $stm->fetchColumn();

    // Get total quantity in cart (sum of quantities)
    $stm = $_db->prepare("
        SELECT SUM(quantity) FROM cart WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $cartTotalQuantity = $stm->fetchColumn();

    // Commit transaction
    $_db->commit();

    // Log the successful cart addition
    $username = $_SESSION['user']->user_name ?? 'Unknown';
    error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) added product $product_id (size: $size, qty: $quantity) to cart $cartId");

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'cartId' => $cartId,
        'product' => [
            'id' => $product_id,
            'name' => $product->product_name,
            'price' => $product->product_price,
            'size' => $size,
            'quantity' => $quantity
        ],
        'totalQuantity' => $totalQuantity,
        'cartTotalItems' => $cartTotalItems,
        'cartTotalQuantity' => $cartTotalQuantity,
        'cartCount' => $cartTotalItems // Added for backward compatibility
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
