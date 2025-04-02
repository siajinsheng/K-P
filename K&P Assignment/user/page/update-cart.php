<?php
require_once '../../_base.php';

// Initialize the response array
$response = [
    'success' => false,
    'message' => '',
    'currentQuantity' => 0,
    'itemTotal' => 0,
    'subtotal' => 0,
    'shippingFee' => 0,
    'total' => 0,
    'cartCount' => 0
];

// Check if the user is logged in
if (!isset($_SESSION['user'])) {
    $response['message'] = 'You must be logged in to update your cart';
    echo json_encode($response);
    exit;
}

// Check if it's a POST request
if (!is_post()) {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get form data
$cart_id = post('cart_id');
$action = post('action');

// Validate cart_id
if (empty($cart_id)) {
    $response['message'] = 'Cart ID is required';
    echo json_encode($response);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user']->user_id;

try {
    // Begin transaction
    $_db->beginTransaction();
    
    // Check if the cart item exists and belongs to the user
    $stm = $_db->prepare("
        SELECT c.*, p.product_price 
        FROM cart c
        JOIN product p ON c.product_id = p.product_id
        WHERE c.cart_id = ? AND c.user_id = ?
    ");
    $stm->execute([$cart_id, $user_id]);
    $cart_item = $stm->fetch();
    
    if (!$cart_item) {
        $response['message'] = 'Cart item not found';
        echo json_encode($response);
        $_db->rollBack();
        exit;
    }
    
    // Perform action based on request
    if ($action === 'update') {
        $quantity = (int)post('quantity');
        
        // Validate quantity
        if ($quantity < 1) {
            $response['message'] = 'Quantity must be at least 1';
            $response['currentQuantity'] = $cart_item->quantity;
            echo json_encode($response);
            $_db->rollBack();
            exit;
        }
        
        if ($quantity > 10) {
            $response['message'] = 'Maximum quantity allowed is 10';
            $response['currentQuantity'] = $cart_item->quantity;
            echo json_encode($response);
            $_db->rollBack();
            exit;
        }
        
        // Check if the size exists and has enough stock
        if (!empty($cart_item->size)) {
            $stm = $_db->prepare("
                SELECT product_stock 
                FROM quantity 
                WHERE product_id = ? AND size = ?
            ");
            $stm->execute([$cart_item->product_id, $cart_item->size]);
            $product_stock = $stm->fetchColumn();
            
            if ($product_stock < $quantity) {
                $response['message'] = 'Not enough stock available. Only ' . $product_stock . ' in stock.';
                $response['currentQuantity'] = $cart_item->quantity;
                echo json_encode($response);
                $_db->rollBack();
                exit;
            }
        }
        
        // Update cart item quantity
        $stm = $_db->prepare("
            UPDATE cart 
            SET quantity = ?, added_time = NOW() 
            WHERE cart_id = ?
        ");
        $stm->execute([$quantity, $cart_id]);
        
        // Set current quantity for response
        $response['currentQuantity'] = $quantity;
        
    } elseif ($action === 'remove') {
        // Remove cart item
        $stm = $_db->prepare("DELETE FROM cart WHERE cart_id = ?");
        $stm->execute([$cart_id]);
    } else {
        $response['message'] = 'Invalid action';
        echo json_encode($response);
        $_db->rollBack();
        exit;
    }
    
    // Calculate cart totals for response
    $stm = $_db->prepare("
        SELECT c.cart_id, c.product_id, c.quantity, p.product_price
        FROM cart c
        JOIN product p ON c.product_id = p.product_id
        WHERE c.user_id = ?
    ");
    $stm->execute([$user_id]);
    $cart_items = $stm->fetchAll();
    
    // Calculate subtotal
    $subtotal = 0;
    $cart_count = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item->product_price * $item->quantity;
        $cart_count += $item->quantity;
    }
    
    // Calculate shipping fee
    $shipping_fee = ($subtotal >= 100) ? 0 : 10;
    
    // Calculate total
    $total = $subtotal + $shipping_fee;
    
    // Set values for response
    $response['itemTotal'] = $cart_item->product_price * ($action === 'update' ? $quantity : $cart_item->quantity);
    $response['subtotal'] = $subtotal;
    $response['shippingFee'] = $shipping_fee;
    $response['total'] = $total;
    $response['cartCount'] = $cart_count;
    
    // Commit transaction
    $_db->commit();
    
    $response['success'] = true;
    $response['message'] = $action === 'update' ? 'Cart updated successfully' : 'Item removed from cart';
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $_db->rollBack();
    
    $response['message'] = 'An error occurred while updating the cart';
    // Log the error for debugging (not shown to users)
    error_log("Update cart error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
?>