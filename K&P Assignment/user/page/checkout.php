<?php
require_once '../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to proceed with checkout');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Check if cart is empty
try {
    $stm = $_db->prepare("
        SELECT COUNT(*) FROM cart WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $cart_count = $stm->fetchColumn();
    
    if ($cart_count == 0) {
        temp('info', 'Your shopping bag is empty. Please add products before checkout.');
        redirect('shopping-bag.php');
    }
    
    // Get cart items with product details
    $stm = $_db->prepare("
        SELECT c.cart_id, c.product_id, c.quantity, c.size, c.added_time,
               p.product_name, p.product_price, p.product_pic1, p.product_status,
               q.product_stock
        FROM cart c
        JOIN product p ON c.product_id = p.product_id
        JOIN quantity q ON c.product_id = q.product_id AND c.size = q.size
        WHERE c.user_id = ?
        ORDER BY c.added_time DESC
    ");
    $stm->execute([$user_id]);
    $cart_items = $stm->fetchAll();
    
    // Check if all items are available and in stock
    $items_with_issues = [];
    foreach ($cart_items as $item) {
        // Check if product is available
        if ($item->product_status !== 'Available') {
            $items_with_issues[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'message' => 'This product is no longer available'
            ];
            continue;
        }
        
        // Check if enough stock
        if ($item->product_stock < $item->quantity) {
            $items_with_issues[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'message' => "Only {$item->product_stock} items available (you requested {$item->quantity})"
            ];
        }
    }
    
    if (!empty($items_with_issues)) {
        temp('error', 'Some items in your cart have issues. Please review your shopping bag before proceeding.');
        redirect('shopping-bag.php');
    }
    
    // Calculate totals
    $subtotal = 0;
    $total_items = 0;
    
    foreach ($cart_items as $item) {
        $item_total = $item->product_price * $item->quantity;
        $subtotal += $item_total;
        $total_items += $item->quantity;
    }
    
    // Determine shipping fee
    $shipping_fee = $subtotal >= 100 ? 0 : 10;
    
    // Calculate tax (6% GST)
    $tax_rate = 0.06;
    $tax = round($subtotal * $tax_rate, 2);
    
    // Calculate total
    $total = $subtotal + $shipping_fee + $tax;
    
} catch (PDOException $e) {
    error_log("Error checking cart: " . $e->getMessage());
    temp('error', 'An error occurred while processing your cart. Please try again.');
    redirect('shopping-bag.php');
}

// Get user's addresses
try {
    $stm = $_db->prepare("
        SELECT * FROM address
        WHERE user_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    $stm->execute([$user_id]);
    $addresses = $stm->fetchAll();
    
    if (empty($addresses)) {
        temp('info', 'You need to add a delivery address before checkout.');
        redirect('add_address.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    
    // Get default address
    $default_address = null;
    foreach ($addresses as $addr) {
        if ($addr->is_default) {
            $default_address = $addr;
            break;
        }
    }
    
    if (!$default_address) {
        $default_address = $addresses[0]; // Use first address if no default set
    }
} catch (PDOException $e) {
    error_log("Error fetching addresses: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving your addresses. Please try again.');
    redirect('shopping-bag.php');
}

// Handle checkout form submission
if (is_post() && isset($_POST['place_order'])) {
    $address_id = $_POST['address_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($address_id)) {
        $errors[] = 'Please select a delivery address';
    }
    
    if (empty($payment_method) || !in_array($payment_method, ['Credit Card', 'PayPal', 'Bank Transfer', 'Cash on Delivery'])) {
        $errors[] = 'Please select a valid payment method';
    }
    
    // If validation passes, process order
    if (empty($errors)) {
        try {
            // Begin transaction
            $_db->beginTransaction();
            
            // 1. Generate order ID
            $order_id = 'ORD_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
            
            // 2. Create delivery record
            $delivery_id = 'DEL_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
            $estimated_delivery_date = date('Y-m-d', strtotime('+7 days')); // 7 days from now
            
            $stm = $_db->prepare("
                INSERT INTO delivery (delivery_id, address_id, delivery_fee, delivery_status, estimated_date)
                VALUES (?, ?, ?, 'Processing', ?)
            ");
            $stm->execute([$delivery_id, $address_id, $shipping_fee, $estimated_delivery_date]);
            
            // 3. Create order record
            $stm = $_db->prepare("
                INSERT INTO orders (order_id, user_id, delivery_id, order_date, orders_status, order_subtotal, order_total)
                VALUES (?, ?, ?, NOW(), 'Pending', ?, ?)
            ");
            $stm->execute([$order_id, $user_id, $delivery_id, $subtotal, $total]);
            
            // 4. Insert order details
            $stm = $_db->prepare("
                INSERT INTO order_details (order_id, product_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $stm->execute([
                    $order_id,
                    $item->product_id,
                    $item->quantity,
                    $item->product_price
                ]);
                
                // Update product stock
                $update_stock = $_db->prepare("
                    UPDATE quantity 
                    SET product_stock = product_stock - ?, 
                        product_sold = product_sold + ? 
                    WHERE product_id = ? AND size = ?
                ");
                $update_stock->execute([$item->quantity, $item->quantity, $item->product_id, $item->size]);
            }
            
            // 5. Create payment record
            $payment_id = 'PAY_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
            $payment_status = ($payment_method === 'Cash on Delivery') ? 'Pending' : 'Completed';
            
            $stm = $_db->prepare("
                INSERT INTO payment (payment_id, order_id, tax, total_amount, payment_method, payment_status, payment_date)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stm->execute([$payment_id, $order_id, $tax, $total, $payment_method, $payment_status]);
            
            // 6. Clear user cart
            $stm = $_db->prepare("
                DELETE FROM cart 
                WHERE user_id = ?
            ");
            $stm->execute([$user_id]);
            
            // Commit transaction
            $_db->commit();
            
            // Redirect to order confirmation page
            redirect('order_confirmation.php?order_id=' . $order_id);
            
        } catch (PDOException $e) {
            // Rollback on error
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Order processing error: " . $e->getMessage());
            $errors[] = 'An error occurred while processing your order. Please try again.';
        }
    }
}

// Get any messages from session
$success_message = temp('success');
$error_message = temp('error');
$info_message = temp('info');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Checkout</title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Checkout</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= $info_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-main">
                <form method="post" id="checkout-form">
                    <!-- Delivery Address Section -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <h2><i class="fas fa-map-marker-alt"></i> Delivery Address</h2>
                        </div>
                        
                        <div class="address-selection">
                            <?php foreach ($addresses as $address): ?>
                                <div class="address-option">
                                    <input type="radio" 
                                           name="address_id" 
                                           id="address_<?= $address->address_id ?>" 
                                           value="<?= $address->address_id ?>"
                                           <?= ($address->is_default ? 'checked' : '') ?>>
                                    <label for="address_<?= $address->address_id ?>">
                                        <div class="address-label">
                                            <span><?= htmlspecialchars($address->address_name) ?></span>
                                            <?php if ($address->is_default): ?>
                                                <span class="default-badge">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="address-details">
                                            <p><strong><?= htmlspecialchars($address->recipient_name) ?></strong></p>
                                            <p><?= htmlspecialchars($address->phone) ?></p>
                                            <p>
                                                <?= htmlspecialchars($address->address_line1) ?>
                                                <?= $address->address_line2 ? ', ' . htmlspecialchars($address->address_line2) : '' ?>
                                            </p>
                                            <p>
                                                <?= htmlspecialchars($address->city) ?>, 
                                                <?= htmlspecialchars($address->state) ?>, 
                                                <?= htmlspecialchars($address->post_code) ?>
                                            </p>
                                            <p><?= htmlspecialchars($address->country) ?></p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="add-address-btn">
                                <a href="add_address.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                    <i class="fas fa-plus"></i> Add New Address
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method Section -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                        </div>
                        
                        <div class="payment-methods">
                            <div class="payment-option">
                                <input type="radio" name="payment_method" id="payment_credit_card" value="Credit Card">
                                <label for="payment_credit_card">
                                    <div class="payment-icon">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Credit Card</span>
                                        <span class="payment-description">Pay with Visa, Mastercard, or American Express</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-option">
                                <input type="radio" name="payment_method" id="payment_paypal" value="PayPal">
                                <label for="payment_paypal">
                                    <div class="payment-icon">
                                        <i class="fab fa-paypal"></i>
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">PayPal</span>
                                        <span class="payment-description">Pay with your PayPal account</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-option">
                                <input type="radio" name="payment_method" id="payment_bank_transfer" value="Bank Transfer">
                                <label for="payment_bank_transfer">
                                    <div class="payment-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Bank Transfer</span>
                                        <span class="payment-description">Pay directly from your bank account</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-option">
                                <input type="radio" name="payment_method" id="payment_cod" value="Cash on Delivery">
                                <label for="payment_cod">
                                    <div class="payment-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Cash on Delivery</span>
                                        <span class="payment-description">Pay when you receive your order</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Review Section -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <h2><i class="fas fa-shopping-basket"></i> Order Review</h2>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($cart_items as $item): ?>
                                <?php $item_total = $item->product_price * $item->quantity; ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                                    </div>
                                    <div class="item-info">
                                        <h4><?= htmlspecialchars($item->product_name) ?></h4>
                                        <p>Size: <?= $item->size ?></p>
                                        <p>Quantity: <?= $item->quantity ?></p>
                                    </div>
                                    <div class="item-price">
                                        <p>RM <?= number_format($item->product_price, 2) ?> × <?= $item->quantity ?></p>
                                        <p class="item-total">RM <?= number_format($item_total, 2) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="checkout-summary">
                <div class="summary-header">
                    <h2>Order Summary</h2>
                </div>
                
                <div class="summary-body">
                    <div class="summary-item">
                        <span class="summary-label">Subtotal (<?= $total_items ?> items):</span>
                        <span class="summary-value">RM <?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Shipping Fee:</span>
                        <span class="summary-value">
                            <?php if ($shipping_fee > 0): ?>
                                RM <?= number_format($shipping_fee, 2) ?>
                            <?php else: ?>
                                <span class="free-shipping">Free</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($shipping_fee > 0): ?>
                        <div class="free-shipping-message">
                            <i class="fas fa-truck"></i> Spend RM <?= number_format(100 - $subtotal, 2) ?> more for FREE shipping!
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-item">
                        <span class="summary-label">Tax (6% GST):</span>
                        <span class="summary-value">RM <?= number_format($tax, 2) ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value">RM <?= number_format($total, 2) ?></span>
                    </div>
                    
                    <button type="submit" form="checkout-form" name="place_order" class="place-order-btn">
                        <i class="fas fa-check"></i> Place Order
                    </button>
                    
                    <div class="checkout-actions">
                        <a href="shopping-bag.php" class="back-to-cart">
                            <i class="fas fa-arrow-left"></i> Back to Shopping Bag
                        </a>
                    </div>
                    
                    <div class="secure-checkout">
                        <div class="secure-checkout-header">
                            <i class="fas fa-lock"></i> Secure Checkout
                        </div>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-paypal"></i>
                            <i class="fab fa-cc-amex"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation before submission
        const checkoutForm = document.getElementById('checkout-form');
        
        checkoutForm.addEventListener('submit', function(e) {
            // Check if address is selected
            const addressSelected = document.querySelector('input[name="address_id"]:checked');
            if (!addressSelected) {
                e.preventDefault();
                alert('Please select a delivery address');
                return false;
            }
            
            // Check if payment method is selected
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentSelected) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
            
            // Additional validation can be added here
            return true;
        });
    });
    </script>
</body>
</html>