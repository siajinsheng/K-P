<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to edit a payment method');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$page_title = "Edit Payment Method";
$errors = [];
$success = false;

// Get payment method ID from URL
$method_id = req('id');

if (empty($method_id)) {
    temp('error', 'Payment method ID is required');
    redirect('profile.php#payment-methods');
    exit;
}

// Fetch payment method data
try {
    $stm = $_db->prepare("
        SELECT * FROM payment_method 
        WHERE method_id = ? AND user_id = ?
    ");
    $stm->execute([$method_id, $user_id]);
    $payment_method = $stm->fetch();
    
    if (!$payment_method) {
        temp('error', 'Payment method not found or you do not have permission to edit it');
        redirect('profile.php#payment-methods');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching payment method: " . $e->getMessage());
    temp('error', 'An error occurred while loading the payment method');
    redirect('profile.php#payment-methods');
    exit;
}

// Handle form submission
if (is_post()) {
    $is_default = !empty(post('is_default')) ? 1 : 0;

    try {
        // Start transaction
        $_db->beginTransaction();

        // If setting this as default, reset all other payment methods
        if ($is_default && !$payment_method->is_default) {
            $stm = $_db->prepare("UPDATE payment_method SET is_default = 0 WHERE user_id = ?");
            $stm->execute([$user_id]);
        }

        // Process based on payment type
        if ($payment_method->method_type === 'Credit Card') {
            // Validate credit card input
            $card_name = post('card_name');
            $expiry_month = post('expiry_month');
            $expiry_year = post('expiry_year');
            
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
            
            // If no errors, update the credit card
            if (empty($errors)) {
                $stm = $_db->prepare("
                    UPDATE payment_method 
                    SET cardholder_name = ?, expiry_month = ?, expiry_year = ?, is_default = ?, updated_at = NOW()
                    WHERE method_id = ? AND user_id = ?
                ");
                $stm->execute([
                    $card_name, 
                    $expiry_month, 
                    $expiry_year, 
                    $is_default,
                    $method_id,
                    $user_id
                ]);
                
                $success = true;
            }
        } elseif ($payment_method->method_type === 'PayPal') {
            // Validate PayPal input
            $paypal_email = post('paypal_email');
            
            // Validate email
            if (empty($paypal_email) || !is_email($paypal_email)) {
                $errors['paypal_email'] = "Please enter a valid PayPal email address";
            }
            
            // If no errors, update the PayPal account
            if (empty($errors)) {
                $stm = $_db->prepare("
                    UPDATE payment_method 
                    SET paypal_email = ?, is_default = ?, updated_at = NOW()
                    WHERE method_id = ? AND user_id = ?
                ");
                $stm->execute([$paypal_email, $is_default, $method_id, $user_id]);
                
                $success = true;
            }
        }
        
        if ($success) {
            // Commit transaction
            $_db->commit();
            
            // Set success message and redirect
            temp('success', 'Payment method updated successfully');
            redirect('profile.php#payment-methods');
            exit;
        } else {
            // Rollback if there were errors
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
        }
    } catch (PDOException $e) {
        // Rollback if there was an exception
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        
        error_log("Error updating payment method: " . $e->getMessage());
        $errors['db'] = "An error occurred while updating your payment method. Please try again.";
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
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/checkout_payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Edit Payment Method</h1>
        
        <?php if (!empty($errors['db'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $errors['db'] ?>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="profile.php#payment-methods">
                <i class="fas fa-arrow-left"></i> Back to Payment Methods
            </a>
        </div>
        
        <div class="payment-method-form-container">
            <?php if ($payment_method->method_type === 'Credit Card'): ?>
                <!-- Credit/Debit Card Form -->
                <div class="payment-panel">
                    <div class="payment-panel-header">
                        <div class="card-type">
                            <?php
                            $card_icon = 'fa-credit-card';
                            if ($payment_method->card_type === 'Visa') {
                                $card_icon = 'fa-cc-visa';
                            } elseif ($payment_method->card_type === 'MasterCard') {
                                $card_icon = 'fa-cc-mastercard';
                            } elseif ($payment_method->card_type === 'American Express') {
                                $card_icon = 'fa-cc-amex';
                            }
                            ?>
                            <i class="fab <?= $card_icon ?>"></i>
                            <span><?= htmlspecialchars($payment_method->card_type) ?></span>
                        </div>
                        <div class="card-number">•••• •••• •••• <?= htmlspecialchars($payment_method->last_four) ?></div>
                    </div>
                    
                    <form method="post" class="payment-form" id="edit-card-form">
                        <div class="form-group">
                            <label for="card_name">Cardholder Name</label>
                            <input 
                                type="text" 
                                id="card_name" 
                                name="card_name" 
                                placeholder="John Smith"
                                autocomplete="cc-name"
                                class="<?= isset($errors['card_name']) ? 'error-field' : '' ?>"
                                value="<?= post('card_name') ?: $payment_method->cardholder_name ?>"
                            >
                            <?php if (isset($errors['card_name'])): ?>
                                <div class="error-message"><?= $errors['card_name'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <div class="card-expiry-selects">
                                    <select id="expiry_month" name="expiry_month" class="<?= isset($errors['expiry_month']) || isset($errors['expiry']) ? 'error-field' : '' ?>">
                                        <?php foreach ($months as $month): ?>
                                            <option value="<?= $month ?>" <?= (post('expiry_month') ?: $payment_method->expiry_month) == $month ? 'selected' : '' ?>>
                                                <?= sprintf('%02d', $month) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select id="expiry_year" name="expiry_year" class="<?= isset($errors['expiry_year']) || isset($errors['expiry']) ? 'error-field' : '' ?>">
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?= $year ?>" <?= (post('expiry_year') ?: $payment_method->expiry_year) == $year ? 'selected' : '' ?>>
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
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="is_default" name="is_default" value="1" <?= $payment_method->is_default ? 'checked' : '' ?>>
                            <label for="is_default" class="checkbox-label">Set as default payment method</label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn primary-btn">
                                <i class="fas fa-save"></i> Update Card
                            </button>
                            <a href="profile.php#payment-methods" class="btn outline-btn">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                        
                        <div class="secure-payment-note">
                            <i class="fas fa-lock"></i> Your payment information is secure and encrypted
                        </div>
                    </form>
                </div>
            <?php elseif ($payment_method->method_type === 'PayPal'): ?>
                <!-- PayPal Form -->
                <div class="payment-panel">
                    <div class="payment-panel-header">
                        <div class="card-type">
                            <i class="fab fa-paypal"></i>
                            <span>PayPal</span>
                        </div>
                    </div>
                    
                    <form method="post" class="payment-form" id="edit-paypal-form">
                        <div class="form-group">
                            <label for="paypal_email">PayPal Email Address</label>
                            <div class="input-with-icon">
                                <input 
                                    type="email" 
                                    id="paypal_email" 
                                    name="paypal_email" 
                                    placeholder="your.email@example.com" 
                                    autocomplete="email"
                                    class="<?= isset($errors['paypal_email']) ? 'error-field' : '' ?>"
                                    value="<?= post('paypal_email') ?: $payment_method->paypal_email ?>"
                                >
                                <i class="fas fa-envelope"></i>
                            </div>
                            <?php if (isset($errors['paypal_email'])): ?>
                                <div class="error-message"><?= $errors['paypal_email'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="is_default" name="is_default" value="1" <?= $payment_method->is_default ? 'checked' : '' ?>>
                            <label for="is_default" class="checkbox-label">Set as default payment method</label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn primary-btn">
                                <i class="fas fa-save"></i> Update PayPal
                            </button>
                            <a href="profile.php#payment-methods" class="btn outline-btn">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                        
                        <div class="secure-payment-note">
                            <i class="fas fa-lock"></i> Your payment information is secure and encrypted
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Card form validation
        const editCardForm = document.getElementById('edit-card-form');
        if (editCardForm) {
            editCardForm.addEventListener('submit', function(e) {
                let isValid = true;
                const errors = {};
                
                // Validate cardholder name
                const cardName = document.getElementById('card_name').value.trim();
                if (!cardName || cardName.length < 3) {
                    errors.cardName = "Please enter the cardholder name";
                    isValid = false;
                }
                
                // Validate expiry date
                const expiryMonth = document.getElementById('expiry_month').value;
                const expiryYear = document.getElementById('expiry_year').value;
                const currentMonth = new Date().getMonth() + 1; // getMonth() is 0-based
                const currentYear = new Date().getFullYear();
                
                if (!expiryMonth) {
                    errors.expiryMonth = "Please select an expiry month";
                    isValid = false;
                }
                
                if (!expiryYear) {
                    errors.expiryYear = "Please select an expiry year";
                    isValid = false;
                }
                
                if (expiryMonth && expiryYear) {
                    if (expiryYear < currentYear || (expiryYear == currentYear && expiryMonth < currentMonth)) {
                        errors.expiry = "The card has expired";
                        isValid = false;
                    }
                }
                
                // If there are errors, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                    
                    // Display error messages
                    Object.keys(errors).forEach(key => {
                        const message = errors[key];
                        const field = document.getElementById(key === 'expiryMonth' ? 'expiry_month' : 
                                                               key === 'expiryYear' ? 'expiry_year' : 
                                                               key === 'cardName' ? 'card_name' : key);
                        if (field) {
                            field.classList.add('error-field');
                            
                            // Create or update error message
                            let errorDiv = field.parentNode.querySelector('.error-message');
                            if (!errorDiv) {
                                errorDiv = document.createElement('div');
                                errorDiv.className = 'error-message';
                                field.parentNode.insertBefore(errorDiv, field.nextSibling);
                            }
                            errorDiv.textContent = message;
                        }
                    });
                }
            });
        }
        
        // PayPal form validation
        const editPaypalForm = document.getElementById('edit-paypal-form');
        if (editPaypalForm) {
            editPaypalForm.addEventListener('submit', function(e) {
                let isValid = true;
                const errors = {};
                
                // Validate PayPal email
                const paypalEmail = document.getElementById('paypal_email').value.trim();
                if (!paypalEmail || !/^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/.test(paypalEmail)) {
                    errors.paypalEmail = "Please enter a valid PayPal email address";
                    isValid = false;
                }
                
                // If there are errors, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                    
                    // Display error messages
                    Object.keys(errors).forEach(key => {
                        const message = errors[key];
                        const field = document.getElementById(key === 'paypalEmail' ? 'paypal_email' : key);
                        if (field) {
                            field.classList.add('error-field');
                            
                            // Create or update error message
                            let errorDiv = field.parentNode.querySelector('.error-message');
                            if (!errorDiv) {
                                errorDiv = document.createElement('div');
                                errorDiv.className = 'error-message';
                                if (field.nextElementSibling && field.nextElementSibling.tagName === 'I') {
                                    field.parentNode.insertBefore(errorDiv, field.nextElementSibling.nextSibling);
                                } else {
                                    field.parentNode.insertBefore(errorDiv, field.nextSibling);
                                }
                            }
                            errorDiv.textContent = message;
                        }
                    });
                }
            });
        }
    });
    </script>
</body>
</html>