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
    temp('error', 'An error occurred while retrieving your addresses.');
    redirect('shopping-bag.php');
}

// Handle checkout form submission
if (is_post() && isset($_POST['place_order'])) {
    $address_id = $_POST['address_id'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($address_id)) {
        $errors[] = 'Please select a delivery address';
    }
    
    // If validation passes, process order
    if (empty($errors)) {
        try {
            // Begin transaction
            $_db->beginTransaction();
            
            // 1. Generate order ID - ORXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(order_id, 3)) as max_id 
                FROM orders 
                WHERE order_id LIKE 'OR%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_order_id = intval($result->max_id) + 1;
            } else {
                $next_order_id = 1; // Start from 1 if no previous orders
            }
            $order_id = 'OR' . str_pad($next_order_id, 3, '0', STR_PAD_LEFT);
            
            // 2. Generate payment ID - PMXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(payment_id, 3)) as max_id 
                FROM payment 
                WHERE payment_id LIKE 'PM%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_payment_id = intval($result->max_id) + 1;
            } else {
                $next_payment_id = 1; // Start from 1 if no previous payments
            }
            $payment_id = 'PM' . str_pad($next_payment_id, 3, '0', STR_PAD_LEFT);
            
            // 3. Generate delivery ID - DVXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(delivery_id, 3)) as max_id 
                FROM delivery 
                WHERE delivery_id LIKE 'DV%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_delivery_id = intval($result->max_id) + 1;
            } else {
                $next_delivery_id = 1; // Start from 1 if no previous deliveries
            }
            $delivery_id = 'DV' . str_pad($next_delivery_id, 3, '0', STR_PAD_LEFT);
            
            // 4. Create delivery record
            $estimated_delivery_date = date('Y-m-d', strtotime('+7 days')); // 7 days from now
            
            $stm = $_db->prepare("
                INSERT INTO delivery (delivery_id, address_id, delivery_fee, delivery_status, estimated_date)
                VALUES (?, ?, ?, 'Processing', ?)
            ");
            $stm->execute([$delivery_id, $address_id, $shipping_fee, $estimated_delivery_date]);
            
            // 5. Create order record
            $stm = $_db->prepare("
                INSERT INTO orders (order_id, user_id, delivery_id, order_date, orders_status, order_subtotal, order_total)
                VALUES (?, ?, ?, NOW(), 'Pending', ?, ?)
            ");
            $stm->execute([$order_id, $user_id, $delivery_id, $subtotal, $total]);
            
            // 6. Insert order details
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
            
            // 7. Create payment record with pending status
            $stm = $_db->prepare("
                INSERT INTO payment (payment_id, order_id, tax, total_amount, payment_method, payment_status, payment_date)
                VALUES (?, ?, ?, ?, 'Pending', 'Pending', NOW())
            ");
            $stm->execute([$payment_id, $order_id, $tax, $total]);
            
            // 8. Clear user cart
            $stm = $_db->prepare("
                DELETE FROM cart 
                WHERE user_id = ?
            ");
            $stm->execute([$user_id]);
            
            // Commit transaction
            $_db->commit();
            
            // Redirect to payment page
            redirect('payment.php?order_id=' . $order_id);
            
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
                                    <i class="fas fa-plus"></i> Add new address
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Review Section -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <h2><i class="fas fa-shopping-bag"></i> Your Items</h2>
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
                        <span class="summary-label">Shipping:</span>
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
                            <i class="fas fa-truck"></i> Spend RM <?= number_format(100 - $subtotal, 2) ?> more for FREE shipping
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
                    
                    <button type="submit" form="checkout-form" name="place_order" id="place-order-btn" class="place-order-btn">
                        PLACE ORDER
                    </button>
                    
                    <div class="checkout-actions">
                        <a href="shopping-bag.php" class="back-to-cart">
                            <i class="fas fa-arrow-left"></i> Back to shopping bag
                        </a>
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
        const placeOrderBtn = document.getElementById('place-order-btn');
        
        checkoutForm.addEventListener('submit', function(e) {
            // Check if address is selected
            const addressSelected = document.querySelector('input[name="address_id"]:checked');
            if (!addressSelected) {
                e.preventDefault();
                alert('Please select a delivery address');
                return false;
            }
            
            // Show loading message
            placeOrderBtn.textContent = 'PROCESSING...';
            placeOrderBtn.disabled = true;
            
            // Additional check to prevent double submission
            if (this.submitted) {
                e.preventDefault();
                return false;
            }
            this.submitted = true;
            
            return true;
        });
    });
    </script>
</body>
</html>