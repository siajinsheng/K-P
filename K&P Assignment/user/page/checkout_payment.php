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
if (!isset($_SESSION['checkout_data']) || $_SESSION['checkout_data']['payment_option'] !== 'new') {
    temp('error', 'Invalid checkout session');
    redirect('checkout.php');
    exit;
}

$user_id = $_SESSION['user']->user_id;
$page_title = "Payment Details";

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
$payment_type = $checkout_data['payment_type'];
$save_payment = isset($_SESSION['checkout_data']['save_payment']) ? $_SESSION['checkout_data']['save_payment'] : 0;

// Get the user's address
try {
    $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
    $stm->execute([$address_id, $user_id]);
    $address = $stm->fetch();
    
    if (!$address) {
        temp('error', 'Invalid address');
        redirect('checkout.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching address: " . $e->getMessage());
    temp('error', 'An error occurred while processing your payment');
    redirect('checkout.php');
    exit;
}

// Helper function to generate sequential IDs in the format XXNNN
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
    
    return $prefix . sprintf('%0' . $pad_length . 'd', $next_num);
}

// Process payment form submission
if (is_post() && isset($_POST['complete_payment'])) {
    $save_to_profile = !empty($_POST['save_to_profile']) ? 1 : 0;
    $is_default = !empty($_POST['make_default']) ? 1 : 0;
    
    try {
        // Payment type specific validation
        if ($payment_type === 'Credit Card') {
            // Validate credit card input
            $card_number = post('card_number');
            $card_name = post('card_name');
            $expiry_month = post('expiry_month');
            $expiry_year = post('expiry_year');
            $cvv = post('cvv');
            
            // Validate card number
            $clean_card_number = str_replace(' ', '', $card_number);
            if (empty($clean_card_number) || strlen($clean_card_number) < 13 || strlen($clean_card_number) > 19 || !ctype_digit($clean_card_number)) {
                $errors['card_number'] = "Please enter a valid card number";
            }
            
            // Validate cardholder name
            if (empty($card_name) || strlen($card_name) < 3) {
                $errors['card_name'] = "Please enter a valid cardholder name";
            }
            
            // Validate expiry date
            $current_month = date('n');
            $current_year = date('Y');
            
            if (empty($expiry_month) || !is_numeric($expiry_month) || $expiry_month < 1 || $expiry_month > 12) {
                $errors['expiry_month'] = "Please select a valid expiry month";
            }
            
            if (empty($expiry_year) || !is_numeric($expiry_year)) {
                $errors['expiry_year'] = "Please select a valid expiry year";
            } elseif ($expiry_year < $current_year || ($expiry_year == $current_year && $expiry_month < $current_month)) {
                $errors['expiry'] = "The card has expired";
            }
            
            // Validate CVV
            if (empty($cvv) || !is_numeric($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
                $errors['cvv'] = "Please enter a valid CVV/CVC code";
            }
        } elseif ($payment_type === 'PayPal') {
            // Validate PayPal input
            $paypal_email = post('paypal_email');
            
            // Validate email
            if (empty($paypal_email) || !is_email($paypal_email)) {
                $errors['paypal_email'] = "Please enter a valid PayPal email address";
            }
        } else {
            $errors['payment_type'] = "Invalid payment type";
        }
        
        if (empty($errors)) {
            // Begin transaction
            $_db->beginTransaction();
            
            // Generate IDs
            $order_id = generate_id('orders', 'order_id', 'OR');
            $payment_id = generate_id('payment', 'payment_id', 'PM');
            $delivery_id = generate_id('delivery', 'delivery_id', 'DV');
            
            // If saving payment to profile, generate method ID and add to payment_method table
            if ($save_to_profile) {
                $method_id = generate_id('payment_method', 'method_id', 'MT');
                
                // Detect card type based on card number
                if ($payment_type === 'Credit Card') {
                    $card_type = 'Credit Card';
                    $first_digit = substr($clean_card_number, 0, 1);
                    
                    if ($first_digit === '4') {
                        $card_type = 'Visa';
                    } elseif (in_array(substr($clean_card_number, 0, 2), ['51', '52', '53', '54', '55'])) {
                        $card_type = 'MasterCard';
                    } elseif (in_array(substr($clean_card_number, 0, 2), ['34', '37'])) {
                        $card_type = 'American Express';
                    }
                    
                    $last_four = substr($clean_card_number, -4);
                    
                    // If setting as default, reset all other methods
                    if ($is_default) {
                        $stm = $_db->prepare("UPDATE payment_method SET is_default = 0 WHERE user_id = ?");
                        $stm->execute([$user_id]);
                    }
                    
                    // Insert card details into payment_method table
                    $stm = $_db->prepare("
                        INSERT INTO payment_method (
                            method_id, user_id, method_type, card_type, last_four,
                            cardholder_name, expiry_month, expiry_year, is_default,
                            created_at, updated_at
                        ) VALUES (?, ?, 'Credit Card', ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stm->execute([
                        $method_id, 
                        $user_id, 
                        $card_type,
                        $last_four,
                        $card_name,
                        $expiry_month,
                        $expiry_year,
                        $is_default
                    ]);
                } elseif ($payment_type === 'PayPal') {
                    // If setting as default, reset all other methods
                    if ($is_default) {
                        $stm = $_db->prepare("UPDATE payment_method SET is_default = 0 WHERE user_id = ?");
                        $stm->execute([$user_id]);
                    }
                    
                    // Insert PayPal details into payment_method table
                    $stm = $_db->prepare("
                        INSERT INTO payment_method (
                            method_id, user_id, method_type, paypal_email, is_default,
                            created_at, updated_at
                        ) VALUES (?, ?, 'PayPal', ?, ?, NOW(), NOW())
                    ");
                    $stm->execute([
                        $method_id, 
                        $user_id, 
                        $paypal_email,
                        $is_default
                    ]);
                }
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
                $payment_type,
                0 // Discount (not implemented)
            ]);
            
            // Insert order details for each item
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
            }
            
            // Clear cart
            $stm = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stm->execute([$user_id]);
            
            // Commit transaction
            $_db->commit();
            
            // Clear checkout data from session
            unset($_SESSION['checkout_data']);
            
            // Redirect to order confirmation page
            temp('success', 'Your order has been placed successfully!');
            redirect('order_confirmation.php?order_id=' . $order_id);
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        
        error_log("Payment processing error: " . $e->getMessage());
        $errors['db'] = "An error occurred while processing your payment: " . $e->getMessage();
    }
}

// Get years for expiry date dropdown
$current_year = date('Y');
$years = range($current_year, $current_year + 15);
$months = range(1, 12);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="../css/checkout_payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Payment Details</h1>
        
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
        
        <div class="checkout-grid">
            <div class="checkout-main">
                <div class="checkout-summary">
                    <h3>Order Summary</h3>
                    <div class="checkout-summary-row">
                        <span>Subtotal:</span>
                        <span>RM <?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="checkout-summary-row">
                        <span>Shipping:</span>
                        <span>RM <?= number_format($delivery_fee, 2) ?></span>
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
                
                <div class="payment-form">
                    <div class="payment-method-header">
                        <?php if ($payment_type === 'Credit Card'): ?>
                            <i class="fas fa-credit-card"></i>
                            <span>Credit Card Payment</span>
                        <?php else: ?>
                            <i class="fab fa-paypal"></i>
                            <span>PayPal Payment</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($errors['db'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $errors['db'] ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" id="payment-form">
                        <?php if ($payment_type === 'Credit Card'): ?>
                            <div class="card-icons">
                                <i class="fab fa-cc-visa card-icon active" id="visa-icon"></i>
                                <i class="fab fa-cc-mastercard card-icon active" id="mastercard-icon"></i>
                                <i class="fab fa-cc-amex card-icon active" id="amex-icon"></i>
                            </div>
                            
                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <div class="input-with-icon">
                                    <input 
                                        type="text" 
                                        id="card_number" 
                                        name="card_number" 
                                        placeholder="1234 5678 9012 3456"
                                        maxlength="19" 
                                        autocomplete="cc-number"
                                        class="<?= isset($errors['card_number']) ? 'error-field' : '' ?>"
                                        value="<?= post('card_number') ?>"
                                    >
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <?php if (isset($errors['card_number'])): ?>
                                    <div class="error-message"><?= $errors['card_number'] ?></div>
                                <?php endif; ?>
                                <div class="format-hint">Enter your 16-digit card number</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="card_name">Cardholder Name</label>
                                <input 
                                    type="text" 
                                    id="card_name" 
                                    name="card_name" 
                                    placeholder="John Smith"
                                    autocomplete="cc-name"
                                    class="<?= isset($errors['card_name']) ? 'error-field' : '' ?>"
                                    value="<?= post('card_name') ?>"
                                >
                                <?php if (isset($errors['card_name'])): ?>
                                    <div class="error-message"><?= $errors['card_name'] ?></div>
                                <?php endif; ?>
                                <div class="format-hint">Enter the name as it appears on your card</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry">Expiry Date</label>
                                    <div class="card-expiry-selects">
                                        <select id="expiry_month" name="expiry_month" class="<?= isset($errors['expiry_month']) || isset($errors['expiry']) ? 'error-field' : '' ?>">
                                            <option value="" disabled selected>Month</option>
                                            <?php foreach ($months as $month): ?>
                                                <option value="<?= $month ?>" <?= post('expiry_month') == $month ? 'selected' : '' ?>>
                                                    <?= sprintf('%02d', $month) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select id="expiry_year" name="expiry_year" class="<?= isset($errors['expiry_year']) || isset($errors['expiry']) ? 'error-field' : '' ?>">
                                            <option value="" disabled selected>Year</option>
                                            <?php foreach ($years as $year): ?>
                                                <option value="<?= $year ?>" <?= post('expiry_year') == $year ? 'selected' : '' ?>>
                                                    <?= $year ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if (isset($errors['expiry_month'])): ?>
                                        <div class="error-message"><?= $errors['expiry_month'] ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($errors['expiry_year'])): ?>
                                        <div class="error-message"><?= $errors['expiry_year'] ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($errors['expiry'])): ?>
                                        <div class="error-message"><?= $errors['expiry'] ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cvv">CVV/CVC</label>
                                    <div class="input-with-icon">
                                        <input 
                                            type="password" 
                                            id="cvv" 
                                            name="cvv" 
                                            placeholder="123"
                                            maxlength="4" 
                                            autocomplete="cc-csc"
                                            class="<?= isset($errors['cvv']) ? 'error-field' : '' ?>"
                                        >
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <?php if (isset($errors['cvv'])): ?>
                                        <div class="error-message"><?= $errors['cvv'] ?></div>
                                    <?php endif; ?>
                                    <div class="format-hint">3 or 4 digits on the back of your card</div>
                                </div>
                            </div>
                            
                        <?php elseif ($payment_type === 'PayPal'): ?>
                            <div class="paypal-section">
                                <div class="paypal-logo">
                                    <i class="fab fa-paypal fa-3x"></i>
                                </div>
                                
                                <div class="form-group">
                                    <label for="paypal_email">PayPal Email Address</label>
                                    <div class="input-with-icon">
                                        <input 
                                            type="email" 
                                            id="paypal_email" 
                                            name="paypal_email" 
                                            placeholder="your.email@example.com"
                                            class="<?= isset($errors['paypal_email']) ? 'error-field' : '' ?>"
                                            value="<?= post('paypal_email') ?>"
                                        >
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <?php if (isset($errors['paypal_email'])): ?>
                                        <div class="error-message"><?= $errors['paypal_email'] ?></div>
                                    <?php endif; ?>
                                    <div class="format-hint">Enter the email address linked to your PayPal account</div>
                                </div>
                                
                                <div class="paypal-info">
                                    <p>You will be redirected to PayPal to complete your payment securely.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="save-payment-options">
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="save_to_profile" name="save_to_profile" value="1" checked>
                                <label for="save_to_profile" class="checkbox-label">Save this payment method for future orders</label>
                            </div>
                            
                            <div class="form-group checkbox-group" id="default_payment_group">
                                <input type="checkbox" id="make_default" name="make_default" value="1">
                                <label for="make_default" class="checkbox-label">Set as default payment method</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="complete_payment" class="process-payment-btn">
                            PAY RM <?= number_format($total, 2) ?>
                        </button>
                        
                        <div class="secure-payment-note">
                            <i class="fas fa-lock"></i> Your payment information is secure and encrypted
                        </div>
                    </form>
                </div>
                
                <div class="checkout-actions">
                    <a href="checkout.php" class="back-to-bag">
                        <i class="fas fa-arrow-left"></i> Back to Checkout
                    </a>
                </div>
            </div>
            
            <div class="checkout-sidebar">
                <div class="order-summary">
                    <h2 class="summary-title">Shipping Address</h2>
                    
                    <div class="address-details">
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
                    
                    <div class="summary-divider"></div>
                    
                    <h2 class="summary-title">Order Items</h2>
                    
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
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Format card number with spaces every 4 digits
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                // Remove all non-digit characters
                let value = this.value.replace(/\D/g, '');
                // Insert a space after every 4 digits
                value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
                // Update the input value
                this.value = value;
                
                // Detect card type and highlight icon
                const cardIcons = {
                    'visa': /^4/,
                    'mastercard': /^5[1-5]/,
                    'amex': /^3[47]/
                };
                
                const digits = this.value.replace(/\D/g, '');
                
                // Reset all icons to inactive
                document.querySelectorAll('.card-icon').forEach(icon => {
                    icon.classList.remove('active');
                });
                
                // Activate the matching icon
                for (const [card, pattern] of Object.entries(cardIcons)) {
                    if (pattern.test(digits)) {
                        document.getElementById(`${card}-icon`).classList.add('active');
                    }
                }
                
                // If no card type detected or input empty, activate all icons
                if (digits === '' || !Object.values(cardIcons).some(pattern => pattern.test(digits))) {
                    document.querySelectorAll('.card-icon').forEach(icon => {
                        icon.classList.add('active');
                    });
                }
            });
        }
        
        // Toggle save payment options
        const savePaymentCheckbox = document.getElementById('save_to_profile');
        const defaultPaymentGroup = document.getElementById('default_payment_group');
        
        if (savePaymentCheckbox && defaultPaymentGroup) {
            savePaymentCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    defaultPaymentGroup.style.display = 'block';
                } else {
                    defaultPaymentGroup.style.display = 'none';
                    document.getElementById('make_default').checked = false;
                }
            });
            
            // Initialize on page load
            defaultPaymentGroup.style.display = savePaymentCheckbox.checked ? 'block' : 'none';
        }
        
        // Form validation
        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous error messages
                document.querySelectorAll('.error-message').forEach(el => {
                    el.remove();
                });
                
                document.querySelectorAll('.error-field').forEach(el => {
                    el.classList.remove('error-field');
                });
                
                <?php if ($payment_type === 'Credit Card'): ?>
                // Validate card number
                const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                if (!cardNumber || cardNumber.length < 13 || cardNumber.length > 19 || !/^\d+$/.test(cardNumber)) {
                    isValid = false;
                    cardNumberInput.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please enter a valid card number';
                    cardNumberInput.parentNode.insertBefore(errorDiv, cardNumberInput.nextSibling);
                }
                
                // Validate cardholder name
                const cardName = document.getElementById('card_name');
                if (!cardName.value.trim() || cardName.value.trim().length < 3) {
                    isValid = false;
                    cardName.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please enter a valid cardholder name';
                    cardName.parentNode.insertBefore(errorDiv, cardName.nextSibling);
                }
                
                // Validate expiry date
                const expiryMonth = document.getElementById('expiry_month');
                const expiryYear = document.getElementById('expiry_year');
                const currentMonth = new Date().getMonth() + 1; // getMonth() is 0-based
                const currentYear = new Date().getFullYear();
                
                if (!expiryMonth.value) {
                    isValid = false;
                    expiryMonth.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please select an expiry month';
                    expiryMonth.parentNode.parentNode.insertBefore(errorDiv, expiryMonth.parentNode.nextSibling);
                }
                
                if (!expiryYear.value) {
                    isValid = false;
                    expiryYear.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please select an expiry year';
                    expiryYear.parentNode.parentNode.insertBefore(errorDiv, expiryYear.parentNode.nextSibling);
                }
                
                if (expiryMonth.value && expiryYear.value) {
                    if (expiryYear.value < currentYear || (expiryYear.value == currentYear && expiryMonth.value < currentMonth)) {
                        isValid = false;
                        expiryMonth.classList.add('error-field');
                        expiryYear.classList.add('error-field');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.innerText = 'The card has expired';
                        expiryYear.parentNode.parentNode.insertBefore(errorDiv, expiryYear.parentNode.nextSibling);
                    }
                }
                
                // Validate CVV
                const cvv = document.getElementById('cvv');
                if (!cvv.value || !/^\d{3,4}$/.test(cvv.value)) {
                    isValid = false;
                    cvv.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please enter a valid CVV/CVC code';
                    cvv.parentNode.insertBefore(errorDiv, cvv.nextSibling);
                }
                <?php elseif ($payment_type === 'PayPal'): ?>
                // Validate PayPal email
                const paypalEmail = document.getElementById('paypal_email');
                if (!paypalEmail.value || !/^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/.test(paypalEmail.value)) {
                    isValid = false;
                    paypalEmail.classList.add('error-field');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerText = 'Please enter a valid PayPal email address';
                    paypalEmail.parentNode.insertBefore(errorDiv, paypalEmail.nextSibling);
                }
                <?php endif; ?>
                
                if (!isValid) {
                    e.preventDefault();
                } else {
                    // Show loading state
                    const submitButton = this.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
            });
        }
    });
    </script>
</body>
</html>