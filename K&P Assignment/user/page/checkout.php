<?php
require_once '../../_base.php';

// Start session explicitly
safe_session_start();

// Log the session status for debugging
error_log("Checkout - Session ID: " . session_id());

// Improved authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    error_log("Checkout - Auth failed: " . (isset($_SESSION['user']) ? "User object exists but no user_id" : "No user in session"));
    temp('info', 'Please log in to continue');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Authentication successful - proceed with the rest of the code
$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;

error_log("Checkout - User authenticated: $username (ID: $user_id)");

// Check if checkout data exists in session
if (!isset($_SESSION['checkout_data'])) {
    error_log("Checkout - Missing checkout data for user $user_id");
    temp('error', 'Please review your shopping bag first');
    redirect('shopping-bag.php');
    exit;
}

// Get checkout data from session
$checkout_data = $_SESSION['checkout_data'];
$cart_items = $checkout_data['cart_items'];
$address_id = $checkout_data['address_id'];
$subtotal = $checkout_data['subtotal'];
$tax = $checkout_data['tax'];
$delivery_fee = $checkout_data['delivery_fee'];
$total = $checkout_data['total'];

// Helper function to generate sequential IDs in the format XXnnn
function generate_id($table, $id_field, $prefix, $pad_length = 3) {
    global $_db;
    
    $stm = $_db->prepare("SELECT $id_field FROM $table ORDER BY $id_field DESC LIMIT 1");
    $stm->execute();
    $last_id = $stm->fetchColumn();
    
    if ($last_id && preg_match('/' . $prefix . '(\d+)/', $last_id, $matches)) {
        $next_num = (int)$matches[1] + 1;
    } else {
        $next_num = 1;
    }
    
    return sprintf('%s%0' . $pad_length . 'd', $prefix, $next_num);
}

// Handle form submission to complete checkout
if (is_post() && isset($_POST['complete_checkout'])) {
    try {
        $_db->beginTransaction();
        
        // Generate IDs in required format
        $order_id = generate_id('orders', 'order_id', 'OR');
        $payment_id = generate_id('payment', 'payment_id', 'PM');
        $delivery_id = generate_id('delivery', 'delivery_id', 'DV');
        
        error_log("Generated IDs - Order: $order_id, Payment: $payment_id, Delivery: $delivery_id");
        
        // Get payment method from form
        $payment_method = $_POST['payment_method'];
        if (!in_array($payment_method, ['Credit Card', 'Online Banking', 'Cash on Delivery', 'E-Wallet'])) {
            throw new Exception("Invalid payment method");
        }
        
        // Update delivery address if changed
        if (isset($_POST['address_id']) && !empty($_POST['address_id'])) {
            $selected_address_id = $_POST['address_id'];
            
            // Verify address belongs to user
            $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
            $stm->execute([$selected_address_id, $user_id]);
            $address = $stm->fetch();
            
            if (!$address) {
                throw new Exception("Invalid address selected");
            }
            
            // Use selected address and recalculate delivery fee if needed
            $address_id = $selected_address_id;
            $delivery_fee = in_array($address->state, ['Sabah', 'Sarawak', 'Labuan']) ? 40 : 20;
            $total = $subtotal + $tax + $delivery_fee;
        }
        
        // Set estimated delivery date (3 days from now)
        $estimated_date = date('Y-m-d', strtotime('+3 days'));
        
        // Insert delivery record
        $stm = $_db->prepare("
            INSERT INTO delivery (
                delivery_id, address_id, delivery_fee, 
                delivery_status, estimated_date
            ) VALUES (?, ?, ?, 'Processing', ?)
        ");
        $stm->execute([$delivery_id, $address_id, $delivery_fee, $estimated_date]);
        
        // Insert order record
        $stm = $_db->prepare("
            INSERT INTO orders (
                order_id, user_id, delivery_id, 
                order_date, orders_status, order_subtotal, order_total
            ) VALUES (?, ?, ?, NOW(), 'Confirmed', ?, ?)
        ");
        $stm->execute([
            $order_id, 
            $user_id, 
            $delivery_id, 
            $subtotal,
            $total
        ]);
        
        // Insert payment record with selected payment method
        $stm = $_db->prepare("
            INSERT INTO payment (
                payment_id, order_id, tax, 
                total_amount, payment_method, payment_status, 
                payment_date, discount
            ) VALUES (?, ?, ?, ?, ?, 'Completed', NOW(), ?)
        ");
        $stm->execute([
            $payment_id,
            $order_id,
            $tax,
            $total,
            $payment_method,
            0 // Discount (not implemented)
        ]);
        
        // Insert order details for each cart item
        foreach ($cart_items as $item) {
            $stm = $_db->prepare("
                INSERT INTO order_details (
                    order_id, product_id, quantity, unit_price, size
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stm->execute([
                $order_id,
                $item->product_id,
                $item->quantity,
                $item->product_price,
                $item->size
            ]);
            
            // Update stock quantity
            $stm = $_db->prepare("
                UPDATE quantity 
                SET product_stock = product_stock - ? 
                WHERE product_id = ? AND size = ?
            ");
            $stm->execute([$item->quantity, $item->product_id, $item->size]);
        }
        
        // Clear cart items
        $stm = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stm->execute([$user_id]);
        
        // Commit all database changes
        $_db->commit();
        
        // Clear checkout data from session
        unset($_SESSION['checkout_data']);
        
        // Log the successful order
        error_log("User $username ($user_id) completed checkout. Order ID: $order_id, Payment method: $payment_method");
        
        // Redirect to order confirmation page
        temp('success', 'Your order has been placed!');
        redirect('order-confirmation.php?order_id=' . $order_id);
        exit;
        
    } catch (Exception $e) {
        $_db->rollBack();
        error_log("Checkout completion error: " . $e->getMessage());
        temp('error', 'An error occurred while processing your order. Please try again.');
    }
}

// Get user addresses
try {
    $stm = $_db->prepare("
        SELECT * FROM address WHERE user_id = ? ORDER BY is_default DESC
    ");
    $stm->execute([$user_id]);
    $addresses = $stm->fetchAll();
    
    // Get current delivery address details
    $stm = $_db->prepare("
        SELECT * FROM address WHERE address_id = ?
    ");
    $stm->execute([$address_id]);
    $delivery_address = $stm->fetch();
    
} catch (Exception $e) {
    error_log("Checkout error: " . $e->getMessage());
    temp('error', 'An error occurred while loading checkout details');
    redirect('shopping-bag.php');
    exit;
}

// Get success/error messages
$success_message = temp('success');
$error_message = temp('error');
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
        
        <div class="checkout-container">
            <div class="checkout-steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    <div class="step-text">Shopping Bag</div>
                </div>
                <div class="step-connector"></div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-text">Checkout</div>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">Confirmation</div>
                </div>
            </div>
            
            <div class="checkout-grid">
                <div class="checkout-main">
                    <form method="post" id="checkout-form">
                        <div class="checkout-section">
                            <h2 class="section-title">
                                <i class="fas fa-map-marker-alt"></i> Shipping Address
                            </h2>
                            
                            <div class="address-options">
                                <?php foreach ($addresses as $address): ?>
                                    <div class="address-option">
                                        <input type="radio" 
                                               name="address_id" 
                                               id="address_<?= $address->address_id ?>" 
                                               value="<?= $address->address_id ?>"
                                               <?= $address->address_id == $address_id ? 'checked' : '' ?>>
                                        <label for="address_<?= $address->address_id ?>" class="address-card <?= $address->address_id == $address_id ? 'selected' : '' ?>">
                                            <div class="address-header">
                                                <span class="address-name"><?= htmlspecialchars($address->address_name) ?></span>
                                                <?php if ($address->is_default): ?>
                                                    <span class="default-badge">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="address-details">
                                                <p><?= htmlspecialchars($address->address_line1) ?></p>
                                                <?php if (!empty($address->address_line2)): ?>
                                                    <p><?= htmlspecialchars($address->address_line2) ?></p>
                                                <?php endif; ?>
                                                <p>
                                                    <?= htmlspecialchars($address->postcode) ?> 
                                                    <?= htmlspecialchars($address->city) ?>, 
                                                    <?= htmlspecialchars($address->state) ?>
                                                </p>
                                                <p>Phone: <?= htmlspecialchars($address->phone) ?></p>
                                            </div>
                                            
                                            <div class="address-shipping-info">
                                                <?php if (in_array($address->state, ['Sabah', 'Sarawak', 'Labuan'])): ?>
                                                    <span class="shipping-fee">Shipping: RM 40.00 (East Malaysia)</span>
                                                <?php else: ?>
                                                    <span class="shipping-fee">Shipping: RM 20.00 (West Malaysia)</span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="add-new-address">
                                <a href="address.php?redirect=checkout.php" class="add-address-btn">
                                    <i class="fas fa-plus-circle"></i> Add New Address
                                </a>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h2 class="section-title">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </h2>
                            
                            <div class="payment-options">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="payment_cod" value="Cash on Delivery" checked>
                                    <label for="payment_cod" class="payment-card selected">
                                        <i class="fas fa-money-bill-wave payment-icon"></i>
                                        <span>Cash on Delivery</span>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="payment_card" value="Credit Card">
                                    <label for="payment_card" class="payment-card">
                                        <i class="fas fa-credit-card payment-icon"></i>
                                        <span>Credit Card</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkout-actions">
                            <a href="shopping-bag.php" class="back-to-bag">
                                <i class="fas fa-arrow-left"></i> Back to Shopping Bag
                            </a>
                            <button type="submit" name="complete_checkout" value="1" class="place-order-btn">
                                PLACE ORDER <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="checkout-sidebar">
                    <div class="order-summary">
                        <h2 class="summary-title">Order Summary</h2>
                        
                        <div class="order-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                                        <span class="item-quantity"><?= $item->quantity ?></span>
                                    </div>
                                    <div class="item-details">
                                        <h3 class="item-name"><?= htmlspecialchars($item->product_name) ?></h3>
                                        <p class="item-size">Size: <?= $item->size ?></p>
                                        <div class="item-price">RM <?= number_format($item->product_price, 2) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="cost-summary">
                            <div class="cost-item">
                                <span class="cost-label">Subtotal:</span>
                                <span class="cost-value">RM <?= number_format($subtotal, 2) ?></span>
                            </div>
                            
                            <div class="cost-item">
                                <span class="cost-label">Shipping:</span>
                                <span class="cost-value">RM <?= number_format($delivery_fee, 2) ?></span>
                            </div>
                            
                            <div class="cost-item">
                                <span class="cost-label">Tax (6%):</span>
                                <span class="cost-value">RM <?= number_format($tax, 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="total-cost">
                            <span class="total-label">Total:</span>
                            <span id="total-value" class="total-value">RM <?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle address selection
        const addressRadios = document.querySelectorAll('input[name="address_id"]');
        const totalValueElem = document.getElementById('total-value');
        const subtotal = <?= $subtotal ?>;
        const tax = <?= $tax ?>;
        
        addressRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.address-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                if (this.checked) {
                    const card = this.parentElement.querySelector('.address-card');
                    card.classList.add('selected');
                    
                    // Update shipping cost based on selected address
                    const shippingInfo = card.querySelector('.shipping-fee').textContent;
                    let shippingCost = 20; // Default West Malaysia
                    
                    if (shippingInfo.includes('40.00')) {
                        shippingCost = 40; // East Malaysia
                    }
                    
                    // Update total
                    const newTotal = subtotal + tax + shippingCost;
                    totalValueElem.textContent = 'RM ' + newTotal.toFixed(2);
                }
            });
        });
        
        // Toggle payment method selection
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                if (this.checked) {
                    this.parentElement.querySelector('.payment-card').classList.add('selected');
                }
            });
        });
    });
    </script>
</body>
</html>