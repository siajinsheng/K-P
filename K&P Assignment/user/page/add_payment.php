<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to add a payment method');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$page_title = "Add Payment Method";
$errors = [];
$success = false;

// Handle form submission
if (is_post()) {
    $payment_type = post('payment_type');
    $is_default = !empty(post('is_default')) ? 1 : 0;

    try {
        // Start transaction
        $_db->beginTransaction();

        // Generate a unique method ID
        function generateMethodId()
        {
            return 'PM_' . date('YmdHis') . '_' . substr(uniqid(), -8);
        }
        $method_id = generateMethodId();

        // If setting this as default, reset all other payment methods
        if ($is_default) {
            $stm = $_db->prepare("UPDATE payment_method SET is_default = 0 WHERE user_id = ?");
            $stm->execute([$user_id]);
        }

        // Process based on payment type
        if ($payment_type === 'credit_card') {
            // Validate credit card input
            $card_number = post('card_number');
            $card_name = post('card_name');
            $expiry_month = post('expiry_month');
            $expiry_year = post('expiry_year');
            $cvv = post('cvv');
            $card_type = post('card_type');

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

            // If no errors, insert the credit card
            if (empty($errors)) {
                $last_four = substr($clean_card_number, -4);

                // Detect card type if not provided
                if (empty($card_type)) {
                    // First digit of card number
                    $first_digit = substr($clean_card_number, 0, 1);

                    // Simple card type detection based on first digits
                    if ($first_digit === '4') {
                        $card_type = 'Visa';
                    } elseif (in_array(substr($clean_card_number, 0, 2), ['51', '52', '53', '54', '55'])) {
                        $card_type = 'MasterCard';
                    } elseif (in_array(substr($clean_card_number, 0, 2), ['34', '37'])) {
                        $card_type = 'American Express';
                    } else {
                        $card_type = 'Credit Card';
                    }
                }

                $stm = $_db->prepare("
                    INSERT INTO payment_method (
                        method_id, user_id, method_type, card_type, last_four, 
                        cardholder_name, expiry_month, expiry_year, is_default
                    ) VALUES (?, ?, 'Credit Card', ?, ?, ?, ?, ?, ?)
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

                $success = true;
            }
        } elseif ($payment_type === 'paypal') {
            // Validate PayPal input
            $paypal_email = post('paypal_email');

            // Validate email
            if (empty($paypal_email) || !is_email($paypal_email)) {
                $errors['paypal_email'] = "Please enter a valid PayPal email address";
            }

            // If no errors, insert the PayPal account
            if (empty($errors)) {
                $stm = $_db->prepare("
                    INSERT INTO payment_method (
                        method_id, user_id, method_type, paypal_email, is_default
                    ) VALUES (?, ?, 'PayPal', ?, ?)
                ");
                $stm->execute([$method_id, $user_id, $paypal_email, $is_default]);

                $success = true;
            }
        } else {
            $errors['payment_type'] = "Please select a valid payment method type";
        }

        if ($success) {
            // Commit transaction
            $_db->commit();

            // Set success message and redirect
            temp('success', 'Payment method added successfully');
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

        error_log("Error adding payment method: " . $e->getMessage());
        $errors['db'] = "An error occurred while saving your payment method. Please try again.";
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
        <h1 class="page-title">Add Payment Method</h1>

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
            <div class="payment-method-tabs">
                <div class="tab-headers">
                    <button class="tab-header active" data-tab="credit-card">
                        <i class="fas fa-credit-card"></i> Credit/Debit Card
                    </button>
                    <button class="tab-header" data-tab="paypal">
                        <i class="fab fa-paypal"></i> PayPal
                    </button>
                </div>

                <div class="tab-content">
                    <!-- Credit/Debit Card Tab -->
                    <div class="tab-panel active" id="credit-card-panel">
                        <form method="post" class="payment-form" id="credit-card-form">
                            <input type="hidden" name="payment_type" value="credit_card">

                            <div class="card-icons">
                                <i class="fab fa-cc-visa card-icon active" id="visa-icon"></i>
                                <i class="fab fa-cc-mastercard card-icon active" id="mastercard-icon"></i>
                                <i class="fab fa-cc-amex card-icon active" id="amex-icon"></i>
                                <input type="hidden" name="card_type" id="card_type" value="">
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
                                        value="<?= post('card_number') ?>">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <?php if (isset($errors['card_number'])): ?>
                                    <div class="error-message"><?= $errors['card_number'] ?></div>
                                <?php endif; ?>
                                <div class="form-hint">Enter your 16-digit card number</div>
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
                                    value="<?= post('card_name') ?>">
                                <?php if (isset($errors['card_name'])): ?>
                                    <div class="error-message"><?= $errors['card_name'] ?></div>
                                <?php endif; ?>
                                <div class="form-hint">Enter the name as it appears on your card</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Expiry Date</label>
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
                                    <div class="form-hint">MM / YYYY</div>
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
                                            class="<?= isset($errors['cvv']) ? 'error-field' : '' ?>">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <?php if (isset($errors['cvv'])): ?>
                                        <div class="error-message"><?= $errors['cvv'] ?></div>
                                    <?php endif; ?>
                                    <div class="form-hint">3 or 4 digits on the back of your card</div>
                                </div>
                            </div>

                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="is_default_card" name="is_default" value="1">
                                <label for="is_default_card" class="checkbox-label">Set as default payment method</label>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn primary-btn">
                                    <i class="fas fa-save"></i> Save Card
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

                    <!-- PayPal Tab -->
                    <div class="tab-panel" id="paypal-panel">
                        <form method="post" class="payment-form" id="paypal-form">
                            <input type="hidden" name="payment_type" value="paypal">

                            <div class="paypal-icon">
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
                                        autocomplete="email"
                                        class="<?= isset($errors['paypal_email']) ? 'error-field' : '' ?>"
                                        value="<?= post('paypal_email') ?>">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <?php if (isset($errors['paypal_email'])): ?>
                                    <div class="error-message"><?= $errors['paypal_email'] ?></div>
                                <?php endif; ?>
                                <div class="form-hint">Enter the email address linked to your PayPal account</div>
                            </div>

                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="is_default_paypal" name="is_default" value="1">
                                <label for="is_default_paypal" class="checkbox-label">Set as default payment method</label>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn primary-btn">
                                    <i class="fas fa-save"></i> Save PayPal
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
                </div>
            </div>
        </div>
    </div>

    <?php include('../footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabHeaders = document.querySelectorAll('.tab-header');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabHeaders.forEach(h => h.classList.remove('active'));
                    tabPanels.forEach(p => p.classList.remove('active'));

                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Show corresponding panel
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-panel').classList.add('active');
                });
            });

            // Format card number with spaces every 4 digits
            const cardNumberInput = document.getElementById('card_number');
            const cardTypeInput = document.getElementById('card_type');

            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    // Remove all non-digit characters
                    let value = this.value.replace(/\D/g, '');

                    // Insert a space after every 4 digits
                    value = value.replace(/(\d{4})(?=\d)/g, '$1 ');

                    // Update the input value
                    this.value = value;

                    // Detect card type and highlight icon
                    const cardIcons = document.querySelectorAll('.card-icon');
                    cardIcons.forEach(icon => {
                        icon.classList.remove('active');
                    });

                    const digits = value.replace(/\s/g, '');

                    // Simple card type detection and icon highlighting
                    let detectedType = '';

                    if (/^4/.test(digits)) {
                        document.getElementById('visa-icon').classList.add('active');
                        detectedType = 'Visa';
                    } else if (/^5[1-5]/.test(digits)) {
                        document.getElementById('mastercard-icon').classList.add('active');
                        detectedType = 'MasterCard';
                    } else if (/^3[47]/.test(digits)) {
                        document.getElementById('amex-icon').classList.add('active');
                        detectedType = 'American Express';
                    } else {
                        // If no card type detected or input empty, activate all icons
                        cardIcons.forEach(icon => {
                            icon.classList.add('active');
                        });
                        detectedType = '';
                    }

                    // Update hidden card type field
                    if (cardTypeInput) {
                        cardTypeInput.value = detectedType;
                    }
                });
            }

            // CVV input - numbers only
            const cvvInput = document.getElementById('cvv');
            if (cvvInput) {
                cvvInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').substring(0, 4);
                });

                // Toggle CVV visibility
                const toggleCvv = document.querySelector('.toggle-cvv');
                if (toggleCvv) {
                    toggleCvv.addEventListener('click', function() {
                        if (cvvInput.type === 'password') {
                            cvvInput.type = 'text';
                            this.classList.remove('fa-eye-slash');
                            this.classList.add('fa-eye');
                        } else {
                            cvvInput.type = 'password';
                            this.classList.remove('fa-eye');
                            this.classList.add('fa-eye-slash');
                        }
                    });
                }
            }

            // Form validation
            const creditCardForm = document.getElementById('credit-card-form');
            const paypalForm = document.getElementById('paypal-form');

            if (creditCardForm) {
                creditCardForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    const errors = {};

                    // Validate card number
                    const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                    if (!cardNumber || cardNumber.length < 13 || cardNumber.length > 19 || !/^\d+$/.test(cardNumber)) {
                        errors.cardNumber = "Please enter a valid card number";
                        isValid = false;
                    }

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

                    // Validate CVV
                    const cvv = document.getElementById('cvv').value.trim();
                    if (!cvv || !/^\d{3,4}$/.test(cvv)) {
                        errors.cvv = "Please enter a valid CVV/CVC code";
                        isValid = false;
                    }

                    // If there are errors, prevent form submission
                    if (!isValid) {
                        e.preventDefault();

                        // Display error messages
                        Object.keys(errors).forEach(key => {
                            const message = errors[key];
                            const field = document.getElementById(key === 'expiryMonth' ? 'expiry_month' :
                                key === 'expiryYear' ? 'expiry_year' :
                                key === 'cardNumber' ? 'card_number' :
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

            if (paypalForm) {
                paypalForm.addEventListener('submit', function(e) {
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
                                    field.parentNode.insertBefore(errorDiv, field.nextSibling);
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