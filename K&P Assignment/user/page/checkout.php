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

// Verify checkout session data exists
if (!isset($_SESSION['checkout_order_id']) || 
    !isset($_SESSION['checkout_payment_id']) || 
    !isset($_SESSION['checkout_delivery_id'])) {
    
    error_log("Checkout - Missing checkout session data for user $user_id");
    temp('error', 'Invalid checkout session. Please try again.');
    redirect('shopping-bag.php');
    exit;
}

// Get checkout data from session
$order_id = $_SESSION['checkout_order_id'];
$payment_id = $_SESSION['checkout_payment_id'];
$delivery_id = $_SESSION['checkout_delivery_id'];

// Handle form submission to complete checkout
if (is_post() && isset($_POST['complete_checkout'])) {
    try {
        $_db->beginTransaction();
        
        // Update payment method
        $payment_method = $_POST['payment_method'];
        if (!in_array($payment_method, ['Credit Card', 'Online Banking', 'Cash on Delivery', 'E-Wallet'])) {
            throw new Exception("Invalid payment method");
        }
        
        $stm = $_db->prepare("UPDATE payment SET payment_method = ? WHERE payment_id = ?");
        $stm->execute([$payment_method, $payment_id]);
        
        // Update delivery address if changed
        if (isset($_POST['address_id']) && !empty($_POST['address_id'])) {
            $address_id = $_POST['address_id'];
            
            // Verify address belongs to user
            $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
            $stm->execute([$address_id, $user_id]);
            $address = $stm->fetch();
            
            if (!$address) {
                throw new Exception("Invalid address selected");
            }
            
            // Update delivery record with new address
            $stm = $_db->prepare("UPDATE delivery SET address_id = ? WHERE delivery_id = ?");
            $stm->execute([$address_id, $delivery_id]);
            
            // Recalculate delivery fee based on state
            $delivery_fee = in_array($address->state, ['Sabah', 'Sarawak', 'Labuan']) ? 40 : 20;
            
            $stm = $_db->prepare("UPDATE delivery SET delivery_fee = ? WHERE delivery_id = ?");
            $stm->execute([$delivery_fee, $delivery_id]);
            
            // Update order total with new delivery fee
            $stm = $_db->prepare("
                SELECT order_subtotal FROM orders WHERE order_id = ?
            ");
            $stm->execute([$order_id]);
            $order_subtotal = $stm->fetchColumn();
            
            $stm = $_db->prepare("
                SELECT tax FROM payment WHERE payment_id = ?
            ");
            $stm->execute([$payment_id]);
            $tax = $stm->fetchColumn();
            
            $new_total = $order_subtotal + $tax + $delivery_fee;
            
            $stm = $_db->prepare("
                UPDATE orders SET order_total = ? WHERE order_id = ?
            ");
            $stm->execute([$new_total, $order_id]);
            
            $stm = $_db->prepare("
                UPDATE payment SET total_amount = ? WHERE payment_id = ?
            ");
            $stm->execute([$new_total, $payment_id]);
        }
        
        // Update order status
        $stm = $_db->prepare("UPDATE orders SET orders_status = 'Confirmed' WHERE order_id = ?");
        $stm->execute([$order_id]);
        
        // Update stock quantities
        $stm = $_db->prepare("
            SELECT od.product_id, od.quantity, od.size 
            FROM order_details od 
            WHERE od.order_id = ?
        ");
        $stm->execute([$order_id]);
        $items = $stm->fetchAll();
        
        foreach ($items as $item) {
            $stm = $_db->prepare("
                UPDATE quantity 
                SET product_stock = product_stock - ? 
                WHERE product_id = ? AND size = ?
            ");
            $stm->execute([$item->quantity, $item->product_id, $item->size]);
        }
        
        // Clear cart items if any remain
        $stm = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stm->execute([$user_id]);
        
        // Commit all database changes
        $_db->commit();
        
        // Clear checkout session data
        unset($_SESSION['checkout_order_id']);
        unset($_SESSION['checkout_payment_id']);
        unset($_SESSION['checkout_delivery_id']);
        unset($_SESSION['checkout_all_addresses']);
        
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

// Get order details
try {
    $stm = $_db->prepare("
        SELECT o.*, d.delivery_id, d.address_id, d.delivery_fee,
               d.delivery_status, d.estimated_date,
               p.payment_id, p.tax, p.total_amount, p.payment_method,
               p.payment_status, p.discount
        FROM orders o
        JOIN delivery d ON o.delivery_id = d.delivery_id
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        throw new Exception("Order not found");
    }
    
    // Get order items
    $stm = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    $stm->execute([$order_id]);
    $order_items = $stm->fetchAll();
    
    // Get user addresses
    $stm = $_db->prepare("
        SELECT * FROM address WHERE user_id = ? ORDER BY is_default DESC
    ");
    $stm->execute([$user_id]);
    $addresses = $stm->fetchAll();
    
    // Get current delivery address details
    $stm = $_db->prepare("
        SELECT * FROM address WHERE address_id = ?
    ");
    $stm->execute([$order->address_id]);
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
                                               <?= $address->address_id == $order->address_id ? 'checked' : '' ?>>
                                        <label for="address_<?= $address->address_id ?>" class="address-card">
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
                                
                                <div class="add-new-address">
                                    <a href="address.php?redirect=checkout.php" class="add-address-btn">
                                        <i class="fas fa-plus-circle"></i> Add New Address
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h2 class="section-title">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </h2>
                            
                            <div class="payment-options">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="payment_cod" value="Cash on Delivery" checked>
                                    <label for="payment_cod" class="payment-card">
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
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="payment_banking" value="Online Banking">
                                    <label for="payment_banking" class="payment-card">
                                        <i class="fas fa-university payment-icon"></i>
                                        <span>Online Banking</span>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="payment_ewallet" value="E-Wallet">
                                    <label for="payment_ewallet" class="payment-card">
                                        <i class="fas fa-wallet payment-icon"></i>
                                        <span>E-Wallet</span>
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
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                                        <span class="item-quantity"><?= $item->quantity ?></span>
                                    </div>
                                    <div class="item-details">
                                        <h3 class="item-name"><?= htmlspecialchars($item->product_name) ?></h3>
                                        <div class="item-price">RM <?= number_format($item->unit_price, 2) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="cost-summary">
                            <div class="cost-item">
                                <span class="cost-label">Subtotal:</span>
                                <span class="cost-value">RM <?= number_format($order->order_subtotal, 2) ?></span>
                            </div>
                            
                            <div class="cost-item">
                                <span class="cost-label">Shipping:</span>
                                <span class="cost-value">RM <?= number_format($order->delivery_fee, 2) ?></span>
                            </div>
                            
                            <div class="cost-item">
                                <span class="cost-label">Tax (6%):</span>
                                <span class="cost-value">RM <?= number_format($order->tax, 2) ?></span>
                            </div>
                            
                            <?php if ($order->discount > 0): ?>
                                <div class="cost-item discount">
                                    <span class="cost-label">Discount:</span>
                                    <span class="cost-value">-RM <?= number_format($order->discount, 2) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="total-cost">
                            <span class="total-label">Total:</span>
                            <span class="total-value">RM <?= number_format($order->total_amount, 2) ?></span>
                        </div>
                        
                        <div class="order-id">
                            Order #<?= $order_id ?>
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
        addressRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.address-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                if (this.checked) {
                    this.parentElement.querySelector('.address-card').classList.add('selected');
                }
            });
            
            // Initialize selected class
            if (radio.checked) {
                radio.parentElement.querySelector('.address-card').classList.add('selected');
            }
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
            
            // Initialize selected class
            if (radio.checked) {
                radio.parentElement.querySelector('.payment-card').classList.add('selected');
            }
        });
    });
    </script>
</body>
</html>