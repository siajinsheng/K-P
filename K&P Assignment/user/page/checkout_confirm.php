<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to continue');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Check if checkout data exists in session
if (!isset($_SESSION['checkout_data']) || $_SESSION['checkout_data']['payment_option'] !== 'saved' || empty($_SESSION['checkout_data']['payment_method_id'])) {
    temp('error', 'Invalid checkout session');
    redirect('checkout.php');
    exit;
}

$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;
$user_email = $_SESSION['user']->user_Email; // Get user email for receipt
$page_title = "Order Confirmation";

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');
$errors = [];

// Get checkout data from session
$checkout_data = $_SESSION['checkout_data'];
$cart_items = $checkout_data['cart_items'];
$address_id = $checkout_data['address_id'];
$subtotal = $checkout_data['subtotal'];
$tax = $checkout_data['tax'];
$delivery_fee = $checkout_data['delivery_fee'];
$total = $checkout_data['total'];
$payment_method_id = $checkout_data['payment_method_id'];

error_log("[$username] Starting order confirmation with saved payment method $payment_method_id");

// Get address and payment method details
try {
    // Get address details
    $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
    $stm->execute([$address_id, $user_id]);
    $address = $stm->fetch();
    
    if (!$address) {
        error_log("[$username] Invalid address ID: $address_id");
        temp('error', 'Invalid address selected');
        redirect('checkout.php');
        exit;
    }
    
    // Get payment method details
    $stm = $_db->prepare("SELECT * FROM payment_method WHERE method_id = ? AND user_id = ?");
    $stm->execute([$payment_method_id, $user_id]);
    $payment_method = $stm->fetch();
    
    if (!$payment_method) {
        error_log("[$username] Invalid payment method ID: $payment_method_id");
        temp('error', 'Invalid payment method selected');
        redirect('checkout.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching checkout confirmation details for user $username: " . $e->getMessage());
    error_log("SQL State: " . $e->errorInfo[0] . ", Error Code: " . $e->errorInfo[1] . ", Message: " . $e->errorInfo[2]);
    temp('error', 'An error occurred while processing your order');
    redirect('checkout.php');
    exit;
}

// Helper function to generate sequential IDs in the format XXNNN
function generate_id($table, $id_field, $prefix, $pad_length = 3) {
    global $_db;
    
    try {
        $stm = $_db->prepare("SELECT $id_field FROM $table ORDER BY $id_field DESC LIMIT 1");
        $stm->execute();
        $last_id = $stm->fetchColumn();
        
        if ($last_id && preg_match('/' . $prefix . '(\d+)/', $last_id, $matches)) {
            $next_num = (int)$matches[1] + 1;
        } else {
            $next_num = 1;
        }
        
        return $prefix . sprintf('%0' . $pad_length . 'd', $next_num);
    } catch (Exception $e) {
        error_log("Error generating ID: " . $e->getMessage());
        // Return a fallback ID with timestamp to ensure uniqueness
        return $prefix . date('YmdHis');
    }
}

// Process order confirmation
if (is_post() && isset($_POST['place_order'])) {
    try {
        error_log("[$username] Processing order placement with saved payment method");
        // Begin transaction
        $_db->beginTransaction();
        
        // Generate IDs
        $order_id = generate_id('orders', 'order_id', 'OR');
        $payment_id = generate_id('payment', 'payment_id', 'PM');
        $delivery_id = generate_id('delivery', 'delivery_id', 'DV');
        
        error_log("[$username] Generated IDs: Order=$order_id, Payment=$payment_id, Delivery=$delivery_id");
        
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
            ) VALUES (?, ?, ?, NOW(), 'Pending', ?, ?)
        ");
        $stm->execute([
            $order_id, 
            $user_id, 
            $delivery_id, 
            $subtotal,
            $total
        ]);
        
        // Determine payment method type
        $payment_method_type = $payment_method->method_type;
        
        // Insert payment record
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
            $payment_method_type,
            0 // Discount (not implemented)
        ]);
        
        // Insert order details for each item and track all items for email
        $order_details_items = [];
        foreach ($cart_items as $item) {
            $stm = $_db->prepare("
                INSERT INTO order_details (
                    order_id, product_id, quantity_id, quantity, unit_price
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stm->execute([
                $order_id,
                $item->product_id,
                $item->quantity_id,
                $item->quantity,
                $item->product_price
            ]);
            
            // Update product stock
            $stm = $_db->prepare("
                UPDATE quantity 
                SET product_stock = product_stock - ? 
                WHERE quantity_id = ?
            ");
            $stm->execute([$item->quantity, $item->quantity_id]);
            
            // Save item to send in email
            $order_item = new stdClass();
            $order_item->product_name = $item->product_name;
            $order_item->quantity = $item->quantity;
            $order_item->unit_price = $item->product_price;
            $order_item->size = $item->size;
            $order_details_items[] = $order_item;
        }
        
        // Clear cart
        $stm = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stm->execute([$user_id]);
        
        // Commit transaction
        $_db->commit();
        error_log("[$username] Order placed successfully: $order_id");
        
        // Get order details for email
        $stm = $_db->prepare("
            SELECT o.*, d.estimated_date 
            FROM orders o
            JOIN delivery d ON o.delivery_id = d.delivery_id
            WHERE o.order_id = ?
        ");
        $stm->execute([$order_id]);
        $order = $stm->fetch();
        
        // Format payment method display name
        $payment_method_display = $payment_method->method_type;
        if ($payment_method->method_type === 'Credit Card') {
            $card_type = isset($payment_method->card_type) ? $payment_method->card_type : 'Credit Card';
            $payment_method_display = $card_type . " (...".htmlspecialchars($payment_method->last_four).")";
        } elseif ($payment_method->method_type === 'PayPal') {
            $payment_method_display = 'PayPal (' . htmlspecialchars($payment_method->paypal_email) . ')';
        }
        
        // Send receipt email to user
        send_receipt_email($user_email, $username, $order, $order_details_items, $address, $payment_method_display);
        
        // Clear checkout data from session
        unset($_SESSION['checkout_data']);
        
        // Redirect to order confirmation page
        temp('success', 'Your order has been placed successfully!');
        redirect('order_confirmation.php?order_id=' . $order_id);
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        
        error_log("Order processing error for user $username: " . $e->getMessage());
        error_log("SQL State: " . $e->errorInfo[0] . ", Error Code: " . $e->errorInfo[1] . ", Message: " . $e->errorInfo[2]);
        $errors['db'] = "An error occurred while processing your order. Please try again.";
    }
}

// Current date and time
$current_date = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Order Confirmation</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= $info_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors['db'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $errors['db'] ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-steps">
            <div class="step completed">
                <div class="step-number">1</div>
                <div class="step-text">Shopping Bag</div>
            </div>
            <div class="step-connector"></div>
            <div class="step completed">
                <div class="step-number">2</div>
                <div class="step-text">Checkout</div>
            </div>
            <div class="step-connector"></div>
            <div class="step active">
                <div class="step-number">3</div>
                <div class="step-text">Payment</div>
            </div>
        </div>
        
        <div class="checkout-content">
            <div class="checkout-main">
                <div class="checkout-section">
                    <h2 class="section-title">
                        <i class="fas fa-check-circle"></i> Review Your Order
                    </h2>
                    
                    <div class="confirmation-message">
                        <p>Please review your order details below and click "Place Order" to complete your purchase.</p>
                    </div>
                    
                    <div class="checkout-summary">
                        <h3>Order Summary</h3>
                        <div class="checkout-summary-row">
                            <span>Subtotal:</span>
                            <span>RM <?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="checkout-summary-row">
                            <span>Shipping:</span>
                            <span>
                                <?php if ($delivery_fee > 0): ?>
                                    RM <?= number_format($delivery_fee, 2) ?>
                                <?php else: ?>
                                    <span style="color: #4caf50;">Free</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="checkout-summary-row">
                            <span>Tax (6%):</span>
                            <span>RM <?= number_format($tax, 2) ?></span>
                        </div>
                        <div class="checkout-summary-row total">
                            <span>Total:</span>
                            <span>RM <?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="checkout-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Payment Method
                    </h2>
                    
                    <div class="payment-method-summary">
                        <?php if ($payment_method->method_type === 'Credit Card'): ?>
                            <div class="card-details">
                                <?php
                                $card_icon = 'fa-credit-card';
                                if (isset($payment_method->card_type)) {
                                    if ($payment_method->card_type === 'Visa') {
                                        $card_icon = 'fa-cc-visa';
                                    } elseif ($payment_method->card_type === 'MasterCard') {
                                        $card_icon = 'fa-cc-mastercard';
                                    } elseif ($payment_method->card_type === 'American Express') {
                                        $card_icon = 'fa-cc-amex';
                                    }
                                }
                                ?>
                                <div class="card-type">
                                    <i class="fab <?= $card_icon ?>"></i>
                                    <span><?= isset($payment_method->card_type) ? htmlspecialchars($payment_method->card_type) : 'Credit Card' ?></span>
                                </div>
                                <div class="card-number">•••• •••• •••• <?= htmlspecialchars($payment_method->last_four) ?></div>
                                <div class="card-expiry">Expires: <?= sprintf('%02d', $payment_method->expiry_month) ?>/<?= $payment_method->expiry_year ?></div>
                                <div class="cardholder"><?= htmlspecialchars($payment_method->cardholder_name) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="paypal-details">
                                <div class="paypal-logo">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </div>
                                <div class="paypal-email"><?= htmlspecialchars($payment_method->paypal_email) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="checkout-section">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Shipping Address
                    </h2>
                    
                    <div class="shipping-address-summary">
                        <p><strong><?= htmlspecialchars($address->recipient_name) ?></strong></p>
                        <p><?= htmlspecialchars($address->address_line1) ?></p>
                        <?php if (!empty($address->address_line2)): ?>
                            <p><?= htmlspecialchars($address->address_line2) ?></p>
                        <?php endif; ?>
                        <p>
                            <?= htmlspecialchars($address->city) ?>, 
                            <?= htmlspecialchars($address->state) ?> 
                            <?= htmlspecialchars($address->post_code) ?>
                        </p>
                        <p>Phone: <?= htmlspecialchars($address->phone) ?></p>
                    </div>
                </div>
                
                <form method="post" id="confirmation-form" action="checkout_confirm.php">
                    <div class="checkout-actions">
                        <a href="checkout.php" class="btn outline-btn back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Checkout
                        </a>
                        
                        <button type="submit" name="place_order" class="btn primary-btn">
                            Place Order <i class="fas fa-check"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="checkout-sidebar">
                <div class="order-summary">
                    <h2 class="summary-title">Order Items</h2>
                    
                    <div class="summary-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="summary-item">
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
                    
                    <div class="order-date">
                        <?= $current_date ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form submission protection to prevent double-submission
        const form = document.getElementById('confirmation-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent normal form submission
                
                // Disable the button to prevent double submission
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
                
                // Create a custom form submission
                const customForm = document.createElement('form');
                customForm.method = 'POST';
                customForm.action = 'checkout_confirm.php';
                
                // Add hidden field for place_order
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = 'place_order';
                hiddenField.value = '1';
                customForm.appendChild(hiddenField);
                
                // Add form to document and submit
                document.body.appendChild(customForm);
                customForm.submit();
            });
        }
        
        // Error handling and form resubmission
        const errorMessage = document.querySelector('.alert-danger');
        if (errorMessage) {
            const submitButton = document.querySelector('button[type="submit"]');
            if (submitButton) {
                // Re-enable the button if there was an error
                submitButton.disabled = false;
                submitButton.innerHTML = 'Place Order <i class="fas fa-check"></i>';
            }
        }
    });
    </script>
</body>
</html>