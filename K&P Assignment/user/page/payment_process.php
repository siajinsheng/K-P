<?php
require_once '../../_base.php';

// Start session explicitly
safe_session_start();

// Log the session status for debugging
error_log("Payment Process - Session ID: " . session_id());

// Improved authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    error_log("Payment Process - Auth failed: " . (isset($_SESSION['user']) ? "User object exists but no user_id" : "No user in session"));
    temp('info', 'Please log in to continue');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Authentication successful - proceed with the rest of the code
$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;

// Check if there's a payment session in progress
if (!isset($_SESSION['payment_process']) || empty($_SESSION['payment_process']['order_id'])) {
    // If no payment in process, redirect to shopping bag
    temp('error', 'Invalid payment session. Please try again.');
    redirect('shopping-bag.php');
    exit;
}

// Get payment info from session
$payment_data = $_SESSION['payment_process'];
$order_id = $payment_data['order_id'];
$payment_id = $payment_data['payment_id'];
$total = $payment_data['total'];
$payment_method = $payment_data['payment_method'];

// Check the payment method to determine the flow
if ($payment_method !== 'Credit Card') {
    temp('error', 'Invalid payment method. Please try again.');
    redirect('checkout.php');
    exit;
}

// Process payment form submission
if (is_post() && isset($_POST['process_payment'])) {
    // Get form data
    $card_number = post('card_number');
    $card_name = post('card_name');
    $expiry_month = post('expiry_month');
    $expiry_year = post('expiry_year');
    $cvv = post('cvv');
    
    // Basic validation
    $errors = [];
    
    // Validate card number (simple check: 16 digits)
    if (!preg_match('/^[0-9]{16}$/', str_replace(' ', '', $card_number))) {
        $errors['card_number'] = 'Please enter a valid 16-digit card number';
    }
    
    // Validate card name
    if (empty($card_name) || strlen($card_name) < 3) {
        $errors['card_name'] = 'Please enter the cardholder name';
    }
    
    // Validate expiry date
    $current_year = date('Y');
    $current_month = date('m');
    
    if (!is_numeric($expiry_month) || $expiry_month < 1 || $expiry_month > 12) {
        $errors['expiry_month'] = 'Please select a valid month';
    }
    
    if (!is_numeric($expiry_year) || $expiry_year < $current_year || $expiry_year > ($current_year + 10)) {
        $errors['expiry_year'] = 'Please select a valid year';
    }
    
    // Check if card is expired
    if (($expiry_year == $current_year && $expiry_month < $current_month)) {
        $errors['expiry'] = 'Your card has expired';
    }
    
    // Validate CVV
    if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
        $errors['cvv'] = 'Please enter a valid CVV code';
    }
    
    // If there are validation errors, display them
    if (!empty($errors)) {
        // Store errors in session to display them
        $_SESSION['payment_errors'] = $errors;
    } else {
        try {
            // In a real application, this would connect to a payment gateway
            // For this simulation, we'll assume the payment was successful
            
            // Start transaction
            $_db->beginTransaction();
            
            // Update payment status to completed
            $stm = $_db->prepare("
                UPDATE payment 
                SET payment_status = 'Completed',
                    payment_date = NOW()
                WHERE payment_id = ?
            ");
            $stm->execute([$payment_id]);
            
            // Update order status to confirmed
            $stm = $_db->prepare("
                UPDATE orders 
                SET orders_status = 'Confirmed' 
                WHERE order_id = ?
            ");
            $stm->execute([$order_id]);
            
            // Save the last 4 digits of the card (for reference)
            $last_four = substr(str_replace(' ', '', $card_number), -4);
            
            // In a production system, we would create a payment transaction record here
            // For now, we'll just log it
            error_log("Payment processed for order $order_id, amount: $total, last four: $last_four");
            
            // Commit changes
            $_db->commit();
            
            // Clear payment process session
            unset($_SESSION['payment_process']);
            unset($_SESSION['payment_errors']);
            
            // Redirect to success page
            temp('success', 'Your payment was processed successfully!');
            redirect('order-confirmation.php?order_id=' . $order_id);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Payment processing error: " . $e->getMessage());
            temp('error', 'An error occurred while processing your payment. Please try again.');
        }
    }
}

// Get any errors from session
$errors = $_SESSION['payment_errors'] ?? [];

// Get order details for display
try {
    $stm = $_db->prepare("
        SELECT o.*, p.total_amount, p.tax
        FROM orders o
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        throw new Exception("Order not found");
    }
} catch (Exception $e) {
    error_log("Payment process error: " . $e->getMessage());
    temp('error', 'An error occurred while loading payment details');
    redirect('checkout.php');
    exit;
}

// Current year and the next 10 years for expiry date options
$current_year = date('Y');
$years = range($current_year, $current_year + 10);
$months = range(1, 12);

// Get success/error messages
$success_message = temp('success');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Payment</title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-form {
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group input:focus {
            border-color: #000;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-group .error {
            color: #d32f2f;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .card-icons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .card-icon {
            font-size: 24px;
            color: #999;
        }
        
        .card-icon.active {
            color: #000;
        }
        
        .process-payment-btn {
            display: block;
            width: 100%;
            background-color: #000;
            color: #fff;
            border: none;
            padding: 15px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .process-payment-btn:hover {
            background-color: #333;
        }
        
        .card-expiry-selects {
            display: flex;
            gap: 10px;
        }
        
        .card-expiry-selects select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            flex-grow: 1;
        }
        
        .secure-payment-note {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            justify-content: center;
        }
        
        .secure-payment-note i {
            margin-right: 8px;
            color: #4caf50;
        }
        
        .order-details-summary {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid #000;
        }
        
        .order-details-summary p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .order-details-summary .highlight {
            font-weight: 600;
        }
    </style>
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
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <div class="payment-container">
            <div class="order-details-summary">
                <p><span class="highlight">Order #:</span> <?= $order_id ?></p>
                <p><span class="highlight">Total Amount:</span> RM <?= number_format($order->total_amount, 2) ?></p>
                <p><span class="highlight">Payment Method:</span> Credit Card</p>
                <p><span class="highlight">Date:</span> <?= date('Y-m-d H:i:s') ?></p>
            </div>
            
            <div class="payment-form">
                <div class="card-icons">
                    <i class="fab fa-cc-visa card-icon active" id="visa-icon"></i>
                    <i class="fab fa-cc-mastercard card-icon active" id="mastercard-icon"></i>
                    <i class="fab fa-cc-amex card-icon active" id="amex-icon"></i>
                </div>
                
                <form method="post" id="payment-form">
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input 
                            type="text" 
                            id="card_number" 
                            name="card_number" 
                            placeholder="1234 5678 9012 3456"
                            maxlength="19" 
                            autocomplete="cc-number"
                            value="<?= post('card_number') ?>"
                        >
                        <?php if (isset($errors['card_number'])): ?>
                            <div class="error"><?= $errors['card_number'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_name">Cardholder Name</label>
                        <input 
                            type="text" 
                            id="card_name" 
                            name="card_name" 
                            placeholder="John Doe"
                            autocomplete="cc-name"
                            value="<?= post('card_name') ?>"
                        >
                        <?php if (isset($errors['card_name'])): ?>
                            <div class="error"><?= $errors['card_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 2">
                            <label for="expiry">Expiry Date</label>
                            <div class="card-expiry-selects">
                                <select id="expiry_month" name="expiry_month">
                                    <option value="" disabled selected>Month</option>
                                    <?php foreach ($months as $month): ?>
                                        <option value="<?= $month ?>" <?= post('expiry_month') == $month ? 'selected' : '' ?>>
                                            <?= sprintf('%02d', $month) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <select id="expiry_year" name="expiry_year">
                                    <option value="" disabled selected>Year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= $year ?>" <?= post('expiry_year') == $year ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (isset($errors['expiry_month'])): ?>
                                <div class="error"><?= $errors['expiry_month'] ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($errors['expiry_year'])): ?>
                                <div class="error"><?= $errors['expiry_year'] ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($errors['expiry'])): ?>
                                <div class="error"><?= $errors['expiry'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group" style="flex: 1">
                            <label for="cvv">CVV</label>
                            <input 
                                type="password" 
                                id="cvv" 
                                name="cvv" 
                                placeholder="123"
                                maxlength="4" 
                                autocomplete="cc-csc"
                                value="<?= post('cvv') ?>"
                            >
                            <?php if (isset($errors['cvv'])): ?>
                                <div class="error"><?= $errors['cvv'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="process_payment" class="process-payment-btn">
                        PAY RM <?= number_format($order->total_amount, 2) ?>
                    </button>
                    
                    <div class="secure-payment-note">
                        <i class="fas fa-lock"></i> Your payment information is secure and encrypted
                    </div>
                </form>
            </div>
            
            <div class="payment-actions">
                <a href="checkout.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Checkout
                </a>
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
        
        // Validate form before submission
        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                let isValid = true;
                const errors = {};
                
                // Validate card number
                const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                if (!/^\d{16}$/.test(cardNumber)) {
                    errors.cardNumber = 'Please enter a valid 16-digit card number';
                    isValid = false;
                }
                
                // Validate card name
                const cardName = document.getElementById('card_name').value.trim();
                if (cardName === '' || cardName.length < 3) {
                    errors.cardName = 'Please enter the cardholder name';
                    isValid = false;
                }
                
                // Validate expiry date
                const expiryMonth = document.getElementById('expiry_month').value;
                const expiryYear = document.getElementById('expiry_year').value;
                
                if (!expiryMonth || !expiryYear) {
                    errors.expiry = 'Please select expiry date';
                    isValid = false;
                } else {
                    const currentDate = new Date();
                    const expiryDate = new Date(expiryYear, expiryMonth - 1);
                    
                    if (expiryDate < currentDate) {
                        errors.expiry = 'Card has expired';
                        isValid = false;
                    }
                }
                
                // Validate CVV
                const cvv = document.getElementById('cvv').value;
                if (!/^\d{3,4}$/.test(cvv)) {
                    errors.cvv = 'Please enter a valid CVV code';
                    isValid = false;
                }
                
                // If there are errors, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                    
                    // Show client-side errors (in a real application)
                    console.error('Form validation errors:', errors);
                    
                    // You could add visual feedback for errors here
                }
            });
        }
    });
    </script>
</body>
</html>