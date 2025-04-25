<?php
require_once '../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to proceed with payment');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    temp('error', 'Invalid order ID');
    redirect('shopping-bag.php');
}

$order_id = $_GET['order_id'];

// Verify that this order belongs to the current user
try {
    $stm = $_db->prepare("
        SELECT o.*, p.payment_id, p.tax, p.total_amount, p.payment_method, p.payment_status
        FROM orders o
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or access denied');
        redirect('shopping-bag.php');
    }
    
    // Ensure payment is still pending
    if ($order->payment_status !== 'Pending') {
        temp('info', 'This order has already been paid for');
        redirect('order_confirmation.php?order_id=' . $order_id);
    }
    
    // Ensure payment method is valid
    if (!in_array($order->payment_method, ['Credit Card', 'PayPal'])) {
        temp('error', 'Invalid payment method');
        redirect('checkout.php');
    }
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving order details');
    redirect('shopping-bag.php');
}

// Handle payment form submission
if (is_post() && isset($_POST['process_payment'])) {
    // In a real-world scenario, you would integrate with a payment gateway here
    
    try {
        // Begin transaction
        $_db->beginTransaction();
        
        // Update payment record
        $stm = $_db->prepare("
            UPDATE payment 
            SET payment_status = 'Completed', payment_date = NOW() 
            WHERE payment_id = ?
        ");
        $stm->execute([$order->payment_id]);
        
        // Update order status
        $stm = $_db->prepare("
            UPDATE orders 
            SET orders_status = 'Processing' 
            WHERE order_id = ?
        ");
        $stm->execute([$order_id]);
        
        // Commit transaction
        $_db->commit();
        
        // Success message and redirect to order confirmation
        temp('success', 'Payment processed successfully. Your order is now being processed.');
        redirect('order_confirmation.php?order_id=' . $order_id);
        
    } catch (PDOException $e) {
        // Rollback on error
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        
        error_log("Payment processing error: " . $e->getMessage());
        temp('error', 'An error occurred while processing your payment. Please try again.');
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
    <title>K&P - Payment</title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }
        
        .payment-form {
            flex: 3;
            min-width: 300px;
        }
        
        .payment-summary {
            flex: 1;
            min-width: 300px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .payment-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .payment-section-header {
            padding: 20px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .payment-section-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .payment-section-header h2 i {
            margin-right: 10px;
            color: #4a6fa5;
        }
        
        .payment-section-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #4a6fa5;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .card-icons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 24px;
            color: #666;
        }
        
        .card-icon {
            transition: color 0.3s;
        }
        
        .card-icon.active {
            color: #4a6fa5;
        }
        
        .process-payment-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px;
            background-color: #4a6fa5;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .process-payment-btn:hover {
            background-color: #3a5a85;
        }
        
        .summary-header h2 {
            margin: 0 0 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            font-size: 20px;
            color: #333;
        }
        
        .order-id-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
            color: #4a6fa5;
            text-align: center;
        }
        
        .payment-method-display {
            background-color: #f0f7ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .payment-method-display i {
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Complete Your Payment</h1>
        
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
        
        <div class="payment-container">
            <div class="payment-form">
                <form method="post" id="payment-form">
                    <?php if ($order->payment_method === 'Credit Card'): ?>
                        <div class="payment-section">
                            <div class="payment-section-header">
                                <h2><i class="fas fa-credit-card"></i> Credit Card Payment</h2>
                            </div>
                            <div class="payment-section-body">
                                <div class="card-icons">
                                    <i class="fab fa-cc-visa card-icon active"></i>
                                    <i class="fab fa-cc-mastercard card-icon active"></i>
                                    <i class="fab fa-cc-amex card-icon active"></i>
                                    <i class="fab fa-cc-discover card-icon active"></i>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cardholder_name">Cardholder Name</label>
                                    <input type="text" id="cardholder_name" name="cardholder_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" name="cvv" placeholder="XXX" required>
                                    </div>
                                </div>
                                
                                <div class="payment-section-header">
                                    <h2><i class="fas fa-shield-alt"></i> Billing Address</h2>
                                </div>
                                <div class="payment-section-body">
                                    <div class="form-group">
                                        <label for="billing_name">Full Name</label>
                                        <input type="text" id="billing_name" name="billing_name" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="billing_address">Address</label>
                                        <input type="text" id="billing_address" name="billing_address" required>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="billing_city">City</label>
                                            <input type="text" id="billing_city" name="billing_city" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="billing_state">State</label>
                                            <input type="text" id="billing_state" name="billing_state" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="billing_postcode">Postal Code</label>
                                            <input type="text" id="billing_postcode" name="billing_postcode" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="billing_country">Country</label>
                                            <input type="text" id="billing_country" name="billing_country" value="Malaysia" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($order->payment_method === 'PayPal'): ?>
                        <div class="payment-section">
                            <div class="payment-section-header">
                                <h2><i class="fab fa-paypal"></i> PayPal Payment</h2>
                            </div>
                            <div class="payment-section-body">
                                <p>You'll be redirected to PayPal to complete your payment.</p>
                                <p>After completing payment on PayPal, you'll be returned to our website.</p>
                                
                                <div style="text-align: center; margin: 30px 0;">
                                    <img src="../images/paypal-button.png" alt="PayPal Button" style="max-width: 250px;">
                                </div>
                                
                                <p>Note: Click the "Process Payment" button below to be redirected to PayPal.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="process_payment" class="process-payment-btn">
                        <i class="fas fa-lock"></i> Process Payment
                    </button>
                </form>
            </div>
            
            <div class="payment-summary">
                <div class="summary-header">
                    <h2>Order Summary</h2>
                </div>
                
                <div class="order-id-display">
                    Order ID: <?= $order_id ?>
                </div>
                
                <div class="payment-method-display">
                    <?php if ($order->payment_method === 'Credit Card'): ?>
                        <i class="fas fa-credit-card"></i>
                    <?php elseif ($order->payment_method === 'PayPal'): ?>
                        <i class="fab fa-paypal"></i>
                    <?php endif; ?>
                    Payment Method: <?= $order->payment_method ?>
                </div>
                
                <div class="summary-body">
                    <div class="summary-item">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value">RM <?= number_format($order->order_subtotal, 2) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Shipping Fee:</span>
                        <span class="summary-value">
                            <?php $shipping_fee = $order->order_total - $order->order_subtotal - $order->tax; ?>
                            <?php if ($shipping_fee > 0): ?>
                                RM <?= number_format($shipping_fee, 2) ?>
                            <?php else: ?>
                                <span class="free-shipping">Free</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Tax (6% GST):</span>
                        <span class="summary-value">RM <?= number_format($order->tax, 2) ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value">RM <?= number_format($order->total_amount, 2) ?></span>
                    </div>
                    
                    <div class="secure-checkout">
                        <div class="secure-checkout-header">
                            <i class="fas fa-lock"></i> Secure Payment
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

    <?php if ($order->payment_method === 'Credit Card'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Card number formatting
        const cardInput = document.getElementById('card_number');
        cardInput.addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove all non-digits
            value = value.replace(/\D/g, '');
            
            // Add space after every 4 digits
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            
            // Limit to 16 digits plus spaces
            value = value.substring(0, 19);
            
            e.target.value = value;
        });
        
        // Expiry date formatting (MM/YY)
        const expiryInput = document.getElementById('expiry_date');
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove all non-digits
            value = value.replace(/\D/g, '');
            
            // Add slash after first 2 digits
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            }
            
            // Limit to 4 digits plus slash
            value = value.substring(0, 5);
            
            e.target.value = value;
        });
        
        // CVV formatting (limit to 3-4 digits)
        const cvvInput = document.getElementById('cvv');
        cvvInput.addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove all non-digits
            value = value.replace(/\D/g, '');
            
            // Limit to 4 digits
            value = value.substring(0, 4);
            
            e.target.value = value;
        });
        
        // Form validation
        const paymentForm = document.getElementById('payment-form');
        paymentForm.addEventListener('submit', function(e) {
            const cardNumber = cardInput.value.replace(/\s/g, '');
            const expiry = expiryInput.value;
            const cvv = cvvInput.value;
            
            let isValid = true;
            
            // Basic validation
            if (cardNumber.length < 13 || cardNumber.length > 16) {
                alert('Please enter a valid card number');
                isValid = false;
            }
            
            if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                alert('Please enter a valid expiry date (MM/YY)');
                isValid = false;
            }
            
            if (cvv.length < 3) {
                alert('Please enter a valid CVV');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.process-payment-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            return true;
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>