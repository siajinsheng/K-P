<?php
require_once '../../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to proceed with payment');
    redirect('../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Check if order_id is provided
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;

if (empty($order_id)) {
    redirect('../profile.php#order-history');
}

// Verify order belongs to this user
try {
    $stm = $_db->prepare("
        SELECT o.*, p.payment_id, p.total_amount 
        FROM orders o
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or does not belong to your account');
        redirect('../profile.php#order-history');
    }
    
    // Get user's name and email for auto-filling
    $stm = $_db->prepare("SELECT user_name, user_Email FROM user WHERE user_id = ?");
    $stm->execute([$user_id]);
    $user = $stm->fetch();
    
} catch (PDOException $e) {
    error_log("Error verifying order: " . $e->getMessage());
    temp('error', 'An error occurred while processing your order');
    redirect('../profile.php#order-history');
}

// Process credit card payment
if (is_post() && isset($_POST['process_payment'])) {
    $card_number = $_POST['card_number'] ?? '';
    $card_name = $_POST['card_name'] ?? '';
    $expiry_month = $_POST['expiry_month'] ?? '';
    $expiry_year = $_POST['expiry_year'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    $errors = [];
    
    // Basic validation
    if (empty($card_number) || strlen(preg_replace('/\D/', '', $card_number)) < 12) {
        $errors[] = 'Please enter a valid card number';
    }
    
    if (empty($card_name)) {
        $errors[] = 'Please enter the name on card';
    }
    
    if (empty($expiry_month) || empty($expiry_year)) {
        $errors[] = 'Please enter a valid expiry date';
    } else {
        // Check if card is expired
        $current_year = date('Y');
        $current_month = date('m');
        
        if ((int)$expiry_year < $current_year || 
            ((int)$expiry_year == $current_year && (int)$expiry_month < $current_month)) {
            $errors[] = 'The card has expired';
        }
    }
    
    if (empty($cvv) || strlen($cvv) < 3) {
        $errors[] = 'Please enter a valid CVV';
    }
    
    // If validation passes, process payment
    if (empty($errors)) {
        try {
            // In a real app, here you would integrate with a payment processor

            // For this simulation, just update the payment status to completed
            $stm = $_db->prepare("
                UPDATE payment
                SET payment_status = 'Completed'
                WHERE payment_id = ?
            ");
            $stm->execute([$order->payment_id]);
            
            // Redirect to confirmation page
            temp('success', 'Payment processed successfully!');
            redirect('../order_confirmation.php?order_id=' . $order_id);
            
        } catch (PDOException $e) {
            error_log("Error processing payment: " . $e->getMessage());
            $errors[] = 'An error occurred while processing your payment. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Credit Card Payment</title>
    <link rel="stylesheet" href="../../css/payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Credit Card Payment</h1>
        
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

        <div class="payment-container">
            <div class="order-summary">
                <h2>Order Summary</h2>
                <div class="summary-details">
                    <div class="summary-row">
                        <span class="label">Order Number:</span>
                        <span class="value"><?= $order_id ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Amount:</span>
                        <span class="value">RM <?= number_format($order->total_amount, 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="payment-form-container">
                <div class="card-container">
                    <div class="credit-card">
                        <div class="card-front">
                            <div class="card-logo">
                                <i class="fab fa-cc-visa"></i>
                                <i class="fab fa-cc-mastercard"></i>
                            </div>
                            <div class="card-number" id="card-number-display">•••• •••• •••• ••••</div>
                            <div class="card-details">
                                <div class="card-holder">
                                    <div class="label">Card Holder</div>
                                    <div class="value" id="card-name-display">YOUR NAME</div>
                                </div>
                                <div class="card-expiry">
                                    <div class="label">Expires</div>
                                    <div class="value" id="card-expiry-display">MM/YY</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-back">
                            <div class="card-stripe"></div>
                            <div class="card-signature">
                                <div class="signature-line"></div>
                                <div class="cvv" id="card-cvv-display">•••</div>
                            </div>
                            <div class="card-info">
                                For customer service, call: +60 3-1234 5678
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" id="payment-form">
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <div class="input-with-icon">
                            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number">
                            <i class="far fa-credit-card"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_name">Name on Card</label>
                        <input type="text" id="card_name" name="card_name" placeholder="John Doe" value="<?= htmlspecialchars($user->user_name) ?>" autocomplete="cc-name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_month">Expiry Date</label>
                            <div class="expiry-inputs">
                                <select id="expiry_month" name="expiry_month">
                                    <option value="">MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="expiry-separator">/</span>
                                <select id="expiry_year" name="expiry_year">
                                    <option value="">YY</option>
                                    <?php 
                                    $current_year = (int)date('Y');
                                    for ($i = $current_year; $i <= $current_year + 10; $i++): 
                                        $year_short = substr($i, 2, 2);
                                    ?>
                                        <option value="<?= $i ?>"><?= $year_short ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <div class="input-with-icon">
                                <input type="password" id="cvv" name="cvv" placeholder="123" maxlength="4" autocomplete="cc-csc">
                                <i class="fas fa-question-circle" title="3-4 digit security code on the back of your card"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-actions">
                        <button type="submit" name="process_payment" class="btn pay-btn">
                            Pay RM <?= number_format($order->total_amount, 2) ?>
                        </button>
                        <a href="../order_confirmation.php?order_id=<?= $order_id ?>" class="btn cancel-btn">
                            Cancel
                        </a>
                    </div>
                    
                    <div class="secure-payment">
                        <i class="fas fa-lock"></i> Your payment information is secure
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include('../../footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cardNumber = document.getElementById('card_number');
            const cardNumberDisplay = document.getElementById('card-number-display');
            const cardName = document.getElementById('card_name');
            const cardNameDisplay = document.getElementById('card-name-display');
            const expiryMonth = document.getElementById('expiry_month');
            const expiryYear = document.getElementById('expiry_year');
            const expiryDisplay = document.getElementById('card-expiry-display');
            const cvv = document.getElementById('cvv');
            const cvvDisplay = document.getElementById('card-cvv-display');
            const creditCard = document.querySelector('.credit-card');
            
            // Format card number input
            cardNumber.addEventListener('input', function(e) {
                let value = this.value.replace(/\D/g, '');
                let formattedValue = '';
                
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
                
                this.value = formattedValue.substring(0, 19);
                
                // Update display
                if (value.length > 0) {
                    cardNumberDisplay.textContent = formattedValue;
                } else {
                    cardNumberDisplay.textContent = '•••• •••• •••• ••••';
                }
            });
            
            // Update name display
            cardName.addEventListener('input', function() {
                if (this.value.trim()) {
                    cardNameDisplay.textContent = this.value.toUpperCase();
                } else {
                    cardNameDisplay.textContent = 'YOUR NAME';
                }
            });
            
            // Update expiry display
            function updateExpiry() {
                const month = expiryMonth.value;
                const year = expiryYear.value ? expiryYear.value.substring(2) : '';
                
                if (month && year) {
                    expiryDisplay.textContent = `${month}/${year}`;
                } else {
                    expiryDisplay.textContent = 'MM/YY';
                }
            }
            
            expiryMonth.addEventListener('change', updateExpiry);
            expiryYear.addEventListener('change', updateExpiry);
            
            // Update CVV display and flip card
            cvv.addEventListener('focus', function() {
                creditCard.classList.add('flip');
            });
            
            cvv.addEventListener('blur', function() {
                creditCard.classList.remove('flip');
            });
            
            cvv.addEventListener('input', function() {
                if (this.value) {
                    cvvDisplay.textContent = '•'.repeat(this.value.length);
                } else {
                    cvvDisplay.textContent = '•••';
                }
            });
            
            // Form validation
            document.getElementById('payment-form').addEventListener('submit', function(e) {
                const cardNumberVal = cardNumber.value.replace(/\s/g, '');
                if (cardNumberVal.length < 12) {
                    e.preventDefault();
                    alert('Please enter a valid card number');
                    return;
                }
                
                if (cardName.value.trim() === '') {
                    e.preventDefault();
                    alert('Please enter the name on card');
                    return;
                }
                
                if (!expiryMonth.value || !expiryYear.value) {
                    e.preventDefault();
                    alert('Please enter a valid expiry date');
                    return;
                }
                
                if (cvv.value.length < 3) {
                    e.preventDefault();
                    alert('Please enter a valid CVV');
                    return;
                }
            });
        });
    </script>
</body>
</html>