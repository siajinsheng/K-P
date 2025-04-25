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
$current_timestamp = date('Y-m-d H:i:s'); // Current timestamp: 2025-04-25 13:42:12

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
    
    // Get order details for display
    $stm = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    $stm->execute([$order_id]);
    $order_items = $stm->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving order details');
    redirect('shopping-bag.php');
}

// Handle payment form submission
if (is_post() && isset($_POST['process_payment'])) {
    // In a real-world scenario, you would integrate with a payment gateway here
    // For now, we'll simulate a successful payment
    
    try {
        // Begin transaction
        $_db->beginTransaction();
        
        // Update payment record
        $stm = $_db->prepare("
            UPDATE payment 
            SET payment_status = 'Completed', payment_date = ?
            WHERE payment_id = ?
        ");
        $stm->execute([$current_timestamp, $order->payment_id]);
        
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
?>

<!-- Credit Card Payment Form HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Complete Your Payment</title>
    <link rel="stylesheet" href="../css/payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>
    
    <div class="container">
        <h1 class="page-title">Complete Your Payment</h1>
        
        <?php
        $success_message = temp('success');
        $error_message = temp('error');
        $info_message = temp('info');
        
        if ($success_message): ?>
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
        
        <!-- Order Summary -->
        <div class="order-summary">
            <h2>Order Information</h2>
            <div class="summary-details">
                <div class="summary-row">
                    <span class="label">Order ID:</span>
                    <span class="value"><?= $order_id ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Date:</span>
                    <span class="value"><?= date('F j, Y', strtotime($order->order_date)) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Payment Method:</span>
                    <span class="value"><?= $order->payment_method ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Subtotal:</span>
                    <span class="value">RM <?= number_format($order->order_subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Tax (6% GST):</span>
                    <span class="value">RM <?= number_format($order->tax, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Total:</span>
                    <span class="value">RM <?= number_format($order->total_amount, 2) ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($order->payment_method === 'Credit Card'): ?>
            <!-- Credit Card Visualization -->
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
                                <div class="value" id="name-display">Your Name</div>
                            </div>
                            <div class="card-expiry">
                                <div class="label">Expires</div>
                                <div class="value" id="expiry-display">MM/YY</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-back">
                        <div class="card-stripe"></div>
                        <div class="card-signature">
                            <div class="signature-line"></div>
                            <div class="cvv" id="cvv-display">123</div>
                        </div>
                        <div class="card-info">
                            This card is property of K&P Fashion. Authorized use only.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="payment-form-container">
                <form id="payment-form" method="post">
                    <div class="form-group">
                        <label for="card_holder">Cardholder Name</label>
                        <input type="text" id="card_holder" name="card_holder" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <div class="input-with-icon">
                            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_month">Expiry Date</label>
                            <div class="expiry-inputs">
                                <select id="expiry_month" name="expiry_month" required>
                                    <option value="">MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="expiry-separator">/</span>
                                <select id="expiry_year" name="expiry_year" required>
                                    <option value="">YY</option>
                                    <?php for ($i = date('y'); $i <= date('y') + 10; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <div class="input-with-icon">
                                <input type="password" id="cvv" name="cvv" placeholder="123" required>
                                <i class="fas fa-question-circle" id="cvv-info"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-actions">
                        <button type="submit" name="process_payment" class="btn pay-btn">
                            <i class="fas fa-lock"></i> Pay RM <?= number_format($order->total_amount, 2) ?>
                        </button>
                        <a href="checkout.php" class="btn cancel-btn">Cancel</a>
                    </div>
                    
                    <div class="secure-payment">
                        <i class="fas fa-shield-alt"></i> This is a secure 256-bit SSL encrypted payment
                    </div>
                </form>
            </div>
            
        <?php elseif ($order->payment_method === 'PayPal'): ?>
            <!-- PayPal Payment UI -->
            <div class="payment-form-container">
                <div class="paypal-container">
                    <div class="paypal-logo">
                        <i class="fab fa-paypal"></i> PayPal
                    </div>
                    <p>You'll be redirected to PayPal to complete your payment securely.</p>
                    <form id="payment-form" method="post">
                        <div class="payment-actions">
                            <button type="submit" name="process_payment" class="btn pay-btn">
                                <i class="fab fa-paypal"></i> Continue to PayPal
                            </button>
                            <a href="checkout.php" class="btn cancel-btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('../footer.php'); ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($order->payment_method === 'Credit Card'): ?>
        // Credit Card visualization
        const card = document.querySelector('.credit-card');
        const cvvInfo = document.getElementById('cvv-info');
        const cardNumber = document.getElementById('card_number');
        const cardHolder = document.getElementById('card_holder');
        const expiryMonth = document.getElementById('expiry_month');
        const expiryYear = document.getElementById('expiry_year');
        const cvv = document.getElementById('cvv');
        
        // Card number formatting and display
        cardNumber.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '').substring(0, 16);
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i % 4 === 0 && i > 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            this.value = formattedValue;
            
            document.getElementById('card-number-display').textContent = 
                formattedValue || '•••• •••• •••• ••••';
        });
        
        // Card holder name display
        cardHolder.addEventListener('input', function() {
            document.getElementById('name-display').textContent = 
                this.value || 'Your Name';
        });
        
        // Expiry date display
        function updateExpiry() {
            const month = expiryMonth.value ? String(expiryMonth.value).padStart(2, '0') : 'MM';
            const year = expiryYear.value || 'YY';
            document.getElementById('expiry-display').textContent = `${month}/${year}`;
        }
        
        expiryMonth.addEventListener('change', updateExpiry);
        expiryYear.addEventListener('change', updateExpiry);
        
        // CVV focus flips the card
        cvv.addEventListener('focus', function() {
            card.classList.add('flip');
            document.getElementById('cvv-display').textContent = '•••';
        });
        
        cvv.addEventListener('blur', function() {
            card.classList.remove('flip');
            document.getElementById('cvv-display').textContent = 
                this.value ? '•'.repeat(this.value.length) : '123';
        });
        
        cvv.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '').substring(0, 4);
            this.value = value;
            document.getElementById('cvv-display').textContent = 
                value ? '•'.repeat(value.length) : '•••';
        });
        
        // CVV info tooltip
        cvvInfo.addEventListener('mouseover', function() {
            card.classList.add('flip');
        });
        
        cvvInfo.addEventListener('mouseout', function() {
            if (document.activeElement !== cvv) {
                card.classList.remove('flip');
            }
        });
        
        // Form submission
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const payBtn = document.querySelector('.pay-btn');
            payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            payBtn.disabled = true;
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>