<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to continue');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Check if checkout data exists in session and payment type is Credit Card
if (!isset($_SESSION['checkout_data']) || $_SESSION['checkout_data']['payment_option'] !== 'new' || $_SESSION['checkout_data']['payment_type'] !== 'Credit Card') {
    temp('error', 'Invalid checkout session');
    redirect('checkout.php');
    exit;
}

$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;
$page_title = "Card Payment";

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
$save_payment = $checkout_data['save_payment'] ?? 0;

error_log("[$username] Starting new card payment process");

// Get address details
try {
    $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
    $stm->execute([$address_id, $user_id]);
    $address = $stm->fetch();
    
    if (!$address) {
        error_log("[$username] Invalid address ID: $address_id");
        temp('error', 'Invalid address selected');
        redirect('checkout.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching address for user $username: " . $e->getMessage());
    temp('error', 'An error occurred while processing your payment');
    redirect('checkout.php');
    exit;
}

// Helper function to generate sequential IDs
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

// Process card payment
if (is_post() && isset($_POST['process_payment'])) {
    // Get card details from form
    $card_type = post('card_type');
    $card_number = post('card_number');
    $expiry_month = post('expiry_month');
    $expiry_year = post('expiry_year');
    $cvv = post('cvv');
    $cardholder_name = post('cardholder_name');
    $save_card = isset($_POST['save_card']) ? 1 : 0;
    
    error_log("[$username] Processing new card payment: card_type=$card_type, save_card=$save_card");
    
    // Validate card details
    if (empty($card_type)) {
        $errors['card_type'] = 'Please select a card type';
    }
    
    if (empty($card_number) || strlen(preg_replace('/\D/', '', $card_number)) < 13) {
        $errors['card_number'] = 'Please enter a valid card number';
    }
    
    if (empty($expiry_month) || empty($expiry_year)) {
        $errors['expiry'] = 'Please enter a valid expiry date';
    } else {
        // Check if card is expired
        $current_month = date('n');
        $current_year = date('Y');
        
        if ($expiry_year < $current_year || ($expiry_year == $current_year && $expiry_month < $current_month)) {
            $errors['expiry'] = 'Your card has expired';
        }
    }
    
    if (empty($cvv) || !is_numeric($cvv) || strlen($cvv) < 3) {
        $errors['cvv'] = 'Please enter a valid CVV';
    }
    
    if (empty($cardholder_name)) {
        $errors['cardholder_name'] = 'Please enter the cardholder name';
    }
    
    // If no errors, process the payment
    if (empty($errors)) {
        try {
            error_log("[$username] Card validation passed, processing order");
            
            // Begin transaction
            $_db->beginTransaction();
            
            // Get last 4 digits of card number
            $last_four = substr(preg_replace('/\D/', '', $card_number), -4);
            
            // Generate IDs
            $payment_method_id = null;
            
            // Save card if requested
            if ($save_card) {
                $payment_method_id = generate_id('payment_method', 'method_id', 'PM');
                
                $stm = $_db->prepare("
                    INSERT INTO payment_method (
                        method_id, user_id, method_type, card_type, 
                        last_four, expiry_month, expiry_year, 
                        cardholder_name, is_default, created_at
                    ) VALUES (?, ?, 'Credit Card', ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                // Check if this is the first saved card (make default)
                $stm2 = $_db->prepare("SELECT COUNT(*) FROM payment_method WHERE user_id = ? AND method_type = 'Credit Card'");
                $stm2->execute([$user_id]);
                $is_first_card = $stm2->fetchColumn() == 0;
                
                $stm->execute([
                    $payment_method_id,
                    $user_id,
                    $card_type,
                    $last_four,
                    $expiry_month,
                    $expiry_year,
                    $cardholder_name,
                    $is_first_card ? 1 : 0
                ]);
                
                error_log("[$username] Saved new payment method: $payment_method_id");
            }
            
            // Generate order IDs
            $order_id = generate_id('orders', 'order_id', 'OR');
            $payment_id = generate_id('payment', 'payment_id', 'PAY');
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
            
            // Insert payment record
            $stm = $_db->prepare("
                INSERT INTO payment (
                    payment_id, order_id, tax, 
                    total_amount, payment_method, payment_status, 
                    payment_date, discount
                ) VALUES (?, ?, ?, ?, 'Credit Card', 'Completed', NOW(), ?)
            ");
            $stm->execute([
                $payment_id,
                $order_id,
                $tax,
                $total,
                0 // Discount (not implemented)
            ]);
            
            // Insert order details for each item and track for email receipt
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
                
                // Create item object for email receipt
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
            
            // Get the complete order data for email receipt
            $stm = $_db->prepare("
                SELECT o.*, d.estimated_date 
                FROM orders o
                JOIN delivery d ON o.delivery_id = d.delivery_id
                WHERE o.order_id = ?
            ");
            $stm->execute([$order_id]);
            $order = $stm->fetch();
            
            // Format payment method display
            $payment_method_display = "$card_type (...$last_four)";
            
            // Send receipt email
            $user_email = $_SESSION['user']->user_Email;
            send_receipt_email($user_email, $username, $order, $order_details_items, $address, $payment_method_display);
            error_log("[$username] Receipt email sent for order $order_id");
            
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
            $errors['db'] = "An error occurred while processing your payment. Please try again.";
        }
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
    <style>
        .card-payment-container {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .card-payment-form {
            margin-top: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        
        .form-group {
            margin: 10px;
            flex: 1 0 200px;
        }
        
        .form-group.full-width {
            flex: 1 0 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #000;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
        }
        
        .card-types {
            display: flex;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        .card-type-option {
            flex: 0 0 auto;
        }
        
        .card-type-option input[type="radio"] {
            display: none;
        }
        
        .card-type-label {
            display: block;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .card-type-option input[type="radio"]:checked + .card-type-label {
            border-color: #000;
            background-color: #f9f9f9;
        }
        
        .card-type-label i {
            font-size: 24px;
            margin-right: 5px;
            vertical-align: middle;
        }
        
        .expiry-inputs {
            display: flex;
            gap: 10px;
        }
        
        .expiry-inputs select {
            flex: 1;
        }
        
        .cvv-group {
            position: relative;
        }
        
        .cvv-help {
            position: absolute;
            right: 10px;
            top: 40px;
            cursor: pointer;
            color: #666;
        }
        
        .save-card-option {
            margin-top: 20px;
        }
        
        .error-message {
            color: #d9534f;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .card-icons {
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .card-icons i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .form-actions {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Card Payment</h1>
        
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
                <div class="card-payment-container">
                    <h2><i class="fas fa-credit-card"></i> Enter Card Details</h2>
                    
                    <form method="post" id="card-payment-form" class="card-payment-form">
                        <!-- Card type selection -->
                        <div class="form-group">
                            <label>Card Type</label>
                            <div class="card-types">
                                <div class="card-type-option">
                                    <input type="radio" name="card_type" id="visa" value="Visa" checked>
                                    <label for="visa" class="card-type-label">
                                        <i class="fab fa-cc-visa"></i> Visa
                                    </label>
                                </div>
                                <div class="card-type-option">
                                    <input type="radio" name="card_type" id="mastercard" value="MasterCard">
                                    <label for="mastercard" class="card-type-label">
                                        <i class="fab fa-cc-mastercard"></i> MasterCard
                                    </label>
                                </div>
                                <div class="card-type-option">
                                    <input type="radio" name="card_type" id="amex" value="American Express">
                                    <label for="amex" class="card-type-label">
                                        <i class="fab fa-cc-amex"></i> Amex
                                    </label>
                                </div>
                            </div>
                            <?php if (!empty($errors['card_type'])): ?>
                                <div class="error-message"><?= $errors['card_type'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card details -->
                        <div class="form-group full-width">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                            <?php if (!empty($errors['card_number'])): ?>
                                <div class="error-message"><?= $errors['card_number'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiry">Expiry Date</label>
                                <div class="expiry-inputs">
                                    <select id="expiry_month" name="expiry_month">
                                        <option value="">Month</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>"><?= sprintf('%02d', $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select id="expiry_year" name="expiry_year">
                                        <option value="">Year</option>
                                        <?php $current_year = date('Y'); ?>
                                        <?php for ($i = $current_year; $i <= $current_year + 10; $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php if (!empty($errors['expiry'])): ?>
                                    <div class="error-message"><?= $errors['expiry'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group cvv-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                <span class="cvv-help" title="3 or 4 digits on the back of your card">
                                    <i class="fas fa-question-circle"></i>
                                </span>
                                <?php if (!empty($errors['cvv'])): ?>
                                    <div class="error-message"><?= $errors['cvv'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="cardholder_name">Cardholder Name</label>
                            <input type="text" id="cardholder_name" name="cardholder_name" placeholder="Name as it appears on card">
                            <?php if (!empty($errors['cardholder_name'])): ?>
                                <div class="error-message"><?= $errors['cardholder_name'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="save-card-option">
                            <input type="checkbox" id="save_card" name="save_card" <?= $save_payment ? 'checked' : '' ?>>
                            <label for="save_card">Save this card for future orders</label>
                        </div>
                        
                        <div class="card-icons">
                            <p><i class="fas fa-lock"></i> Your payment information is secure. We use encryption to protect your data.</p>
                        </div>
                        
                        <div class="form-actions">
                            <a href="checkout.php" class="btn outline-btn back-btn">
                                <i class="fas fa-arrow-left"></i> Back to Checkout
                            </a>
                            <button type="submit" name="process_payment" class="btn primary-btn">
                                Pay RM <?= number_format($total, 2) ?> <i class="fas fa-lock"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="checkout-sidebar">
                <div class="order-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    
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
                    
                    <div class="summary-totals">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>RM <?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span>
                                <?php if ($delivery_fee > 0): ?>
                                    RM <?= number_format($delivery_fee, 2) ?>
                                <?php else: ?>
                                    <span style="color: #4caf50; font-weight: 500;">Free</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (6%)</span>
                            <span>RM <?= number_format($tax, 2) ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span>RM <?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Credit card number formatting
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                // Remove all non-digits
                let value = this.value.replace(/\D/g, '');
                
                // Add spaces after every 4 digits
                let formattedValue = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
                
                // Update the input value
                this.value = formattedValue;
            });
        }
        
        // CVV input validation (numbers only)
        const cvvInput = document.getElementById('cvv');
        if (cvvInput) {
            cvvInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
            });
        }
        
        // Form validation
        const cardForm = document.getElementById('card-payment-form');
        if (cardForm) {
            cardForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Card type validation
                const cardType = document.querySelector('input[name="card_type"]:checked');
                if (!cardType) {
                    alert('Please select a card type');
                    isValid = false;
                }
                
                // Card number validation
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                if (cardNumber.length < 13 || cardNumber.length > 19 || !/^\d+$/.test(cardNumber)) {
                    alert('Please enter a valid card number');
                    isValid = false;
                }
                
                // Expiry validation
                const expiryMonth = document.getElementById('expiry_month').value;
                const expiryYear = document.getElementById('expiry_year').value;
                if (!expiryMonth || !expiryYear) {
                    alert('Please select a valid expiry date');
                    isValid = false;
                } else {
                    // Check if card is expired
                    const currentDate = new Date();
                    const currentMonth = currentDate.getMonth() + 1; // JavaScript months are 0-indexed
                    const currentYear = currentDate.getFullYear();
                    
                    if (expiryYear < currentYear || (expiryYear == currentYear && expiryMonth < currentMonth)) {
                        alert('Your card has expired');
                        isValid = false;
                    }
                }
                
                // CVV validation
                const cvv = document.getElementById('cvv').value;
                if (cvv.length < 3 || !(/^\d+$/.test(cvv))) {
                    alert('Please enter a valid CVV');
                    isValid = false;
                }
                
                // Cardholder name validation
                const cardholderName = document.getElementById('cardholder_name').value.trim();
                if (!cardholderName) {
                    alert('Please enter the cardholder name');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                } else {
                    // Disable the button to prevent double submission
                    const submitBtn = document.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
                }
            });
        }
    });
    </script>
</body>
</html>