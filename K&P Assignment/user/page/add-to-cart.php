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
$quantity_id = post('quantity_id'); // Changed: Now receiving quantity_id instead of size
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

// Validate quantity_id if provided
if (empty($quantity_id)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Please select a size',
        'field' => 'quantity_id'
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

    // Check quantity_id exists and has available stock
    $stm = $_db->prepare("
        SELECT q.quantity_id, q.size, q.product_stock 
        FROM quantity q
        WHERE q.quantity_id = ? AND q.product_id = ?
    ");
    $stm->execute([$quantity_id, $product_id]);
    $available_size = $stm->fetch();

    if (!$available_size) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Selected size is not available',
            'field' => 'quantity_id'
        ]);
        exit;
    }

    if ($available_size->product_stock < $quantity) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Only {$available_size->product_stock} items available in this size",
            'field' => 'quantity'
        ]);
        exit;
    }

    // Begin transaction
    $_db->beginTransaction();

    // Generate a unique cart ID
    function generateCartId()
    {
        return 'CART_' . date('YmdHis') . '_' . substr(uniqid(), -8);
    }

    // Check if product already in cart for this user and quantity_id
    $stm = $_db->prepare("
        SELECT cart_id, quantity FROM cart 
        WHERE user_id = ? AND product_id = ? AND quantity_id = ?
    ");
    $stm->execute([$user_id, $product_id, $quantity_id]);
    $cart_item = $stm->fetch();

    if ($cart_item) {
        // Update existing cart item
        $new_quantity = $cart_item->quantity + $quantity;

        // Check stock again with new quantity
        if ($new_quantity > $available_size->product_stock) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Cannot add $quantity more. You already have {$cart_item->quantity} in your cart and only {$available_size->product_stock} are available.",
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
            INSERT INTO cart (cart_id, user_id, product_id, quantity_id, quantity, added_time) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stm->execute([$cartId, $user_id, $product_id, $quantity_id, $quantity, $current_time]);

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
    error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) added product $product_id (size: {$available_size->size}, qty: $quantity) to cart $cartId");

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
            'size' => $available_size->size,
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