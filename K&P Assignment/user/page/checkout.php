<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to checkout');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name; // Added username for logging
$page_title = "Checkout";

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');

// Check if we have items in the cart
try {
    error_log("[$username] Starting checkout process for user ID: $user_id");
    
    // Get cart items with product details
    $stm = $_db->prepare("
        SELECT c.*, p.product_name, p.product_pic1, p.product_price, q.quantity_id 
        FROM cart c 
        JOIN product p ON c.product_id = p.product_id 
        LEFT JOIN quantity q ON q.product_id = p.product_id AND q.size = c.size
        WHERE c.user_id = ?
    ");
    $stm->execute([$user_id]);
    $cart_items = $stm->fetchAll();
    
    if (empty($cart_items)) {
        error_log("[$username] Cart is empty. Redirecting to shopping bag.");
        temp('info', 'Your shopping bag is empty. Please add items before proceeding to checkout.');
        redirect('shopping-bag.php');
        exit;
    }
    
    error_log("[$username] Cart items found: " . count($cart_items));
    
    // Get user addresses
    $stm = $_db->prepare("
        SELECT * FROM address
        WHERE user_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    $stm->execute([$user_id]);
    $addresses = $stm->fetchAll();
    
    if (empty($addresses)) {
        error_log("[$username] No addresses found. Redirecting to add address page.");
        temp('info', 'Please add a delivery address before proceeding to checkout.');
        redirect('add_address.php?redirect=checkout.php');
        exit;
    }
    
    error_log("[$username] Addresses found: " . count($addresses));
    
    // Get saved payment methods
    $stm = $_db->prepare("
        SELECT * FROM payment_method
        WHERE user_id = ?
        ORDER BY is_default DESC, created_at DESC
    ");
    $stm->execute([$user_id]);
    $payment_methods = $stm->fetchAll();
    
    error_log("[$username] Payment methods found: " . count($payment_methods));
    
    // Calculate cart totals
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item->quantity * $item->product_price;
    }
    
    // Calculate tax (6%)
    $tax = round($subtotal * 0.06, 2);
    
    // Calculate delivery fee based on user's default address
    $delivery_fee = 20; // Default delivery fee
    
    if (!empty($addresses)) {
        $default_address = null;
        foreach ($addresses as $address) {
            if ($address->is_default) {
                $default_address = $address;
                break;
            }
        }
        
        // If no default address, use the first one
        if (!$default_address && !empty($addresses)) {
            $default_address = $addresses[0];
        }
        
        // Set delivery fee based on state
        if ($default_address && isset($default_address->state) && in_array($default_address->state, ['Sabah', 'Sarawak', 'Labuan'])) {
            $delivery_fee = 40; // Higher fee for East Malaysia
        }
    }
    
    // Calculate total
    $total = $subtotal + $tax + $delivery_fee;
    
    // Store cart data in session for later use
    $_SESSION['checkout_data'] = [
        'cart_items' => $cart_items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'delivery_fee' => $delivery_fee,
        'total' => $total
    ];
    
    error_log("[$username] Checkout data stored in session. Subtotal: $subtotal, Tax: $tax, Delivery: $delivery_fee, Total: $total");
    
} catch (PDOException $e) {
    error_log("Error in checkout process for user $username: " . $e->getMessage());
    error_log("SQL State: " . $e->errorInfo[0] . ", Error Code: " . $e->errorInfo[1] . ", Message: " . $e->errorInfo[2]);
    temp('error', 'An error occurred during checkout. Please try again.');
    redirect('shopping-bag.php');
    exit;
}

// Handle form submission to proceed to payment
if (is_post() && isset($_POST['proceed_to_payment'])) {
    // Validate form submission
    $address_id = post('address_id');
    $payment_option = post('payment_option');
    $payment_method_id = post('payment_method_id');
    $payment_type = post('payment_type');
    
    error_log("[$username] Form submitted: address_id=$address_id, payment_option=$payment_option, payment_method_id=$payment_method_id, payment_type=$payment_type");
    
    try {
        if (empty($address_id)) {
            error_log("[$username] No address selected.");
            temp('error', 'Please select a delivery address');
            redirect('checkout.php');
            exit;
        }
        
        if (empty($payment_option) || !in_array($payment_option, ['saved', 'new'])) {
            error_log("[$username] No payment option selected.");
            temp('error', 'Please select a payment option');
            redirect('checkout.php');
            exit;
        }
        
        if ($payment_option === 'saved' && empty($payment_method_id)) {
            error_log("[$username] No saved payment method selected.");
            temp('error', 'Please select a saved payment method');
            redirect('checkout.php');
            exit;
        }
        
        if ($payment_option === 'new' && empty($payment_type)) {
            error_log("[$username] No payment type selected for new payment.");
            temp('error', 'Please select a payment type');
            redirect('checkout.php');
            exit;
        }
        
        // Verify address belongs to user
        $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ? AND user_id = ?");
        $stm->execute([$address_id, $user_id]);
        $address = $stm->fetch();
        
        if (!$address) {
            error_log("[$username] Invalid address selected: $address_id");
            temp('error', 'Invalid address selected');
            redirect('checkout.php');
            exit;
        }
        
        // Update checkout data with selected address
        $_SESSION['checkout_data']['address_id'] = $address_id;
        
        // Recalculate delivery fee if needed based on selected address
        if (isset($address->state) && in_array($address->state, ['Sabah', 'Sarawak', 'Labuan'])) {
            $delivery_fee = 40;
        } else {
            $delivery_fee = 20;
        }
        
        $_SESSION['checkout_data']['delivery_fee'] = $delivery_fee;
        $_SESSION['checkout_data']['total'] = $subtotal + $tax + $delivery_fee;
        
        // If using saved payment method, verify it belongs to the user
        if ($payment_option === 'saved') {
            $stm = $_db->prepare("SELECT * FROM payment_method WHERE method_id = ? AND user_id = ?");
            $stm->execute([$payment_method_id, $user_id]);
            $payment_method = $stm->fetch();
            
            if (!$payment_method) {
                error_log("[$username] Invalid payment method selected: $payment_method_id");
                temp('error', 'Invalid payment method selected');
                redirect('checkout.php');
                exit;
            }
            
            $_SESSION['checkout_data']['payment_method_id'] = $payment_method_id;
            $_SESSION['checkout_data']['payment_option'] = 'saved';
            
            // Store payment type from the saved method
            $_SESSION['checkout_data']['payment_type'] = $payment_method->method_type;
            
            error_log("[$username] Using saved payment method ID: $payment_method_id, type: {$payment_method->method_type}");
            
            // Redirect to confirmation page
            redirect('checkout_confirm.php');
            exit;
        } else {
            // New payment method
            if (!in_array($payment_type, ['Credit Card', 'PayPal'])) {
                error_log("[$username] Invalid payment type: $payment_type");
                temp('error', 'Invalid payment type');
                redirect('checkout.php');
                exit;
            }
            
            $_SESSION['checkout_data']['payment_type'] = $payment_type;
            $_SESSION['checkout_data']['payment_option'] = 'new';
            
            // Check if user wants to save this payment method
            $save_payment = isset($_POST['save_payment']) ? 1 : 0;
            $_SESSION['checkout_data']['save_payment'] = $save_payment;
            
            error_log("[$username] Using new payment method type: $payment_type, save to profile: $save_payment");
            
            // Redirect to payment page to enter new payment details
            redirect('checkout_payment.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Error processing checkout form for user $username: " . $e->getMessage());
        temp('error', 'An error occurred during checkout. Please try again.');
        redirect('checkout.php');
        exit;
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
        <h1 class="page-title">Checkout</h1>
        
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
            <div class="step active">
                <div class="step-number">2</div>
                <div class="step-text">Checkout</div>
            </div>
            <div class="step-connector"></div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">Payment</div>
            </div>
        </div>
        
        <div class="checkout-content">
            <div class="checkout-main">
                <form method="post" id="checkout-form">
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <span class="section-number">1</span>
                            Delivery Address
                        </h2>
                        
                        <div class="address-selection">
                            <?php foreach ($addresses as $index => $address): ?>
                                <div class="address-option">
                                    <input type="radio" name="address_id" id="address_<?= $address->address_id ?>" value="<?= $address->address_id ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                    <label for="address_<?= $address->address_id ?>" class="address-card">
                                        <div class="address-header">
                                            <div class="address-name"><?= htmlspecialchars($address->address_name) ?></div>
                                            <?php if ($address->is_default): ?>
                                                <div class="default-badge">Default</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="address-content">
                                            <p class="recipient"><?= htmlspecialchars($address->recipient_name) ?></p>
                                            <p class="phone"><?= htmlspecialchars($address->phone) ?></p>
                                            <p class="address-line">
                                                <?= htmlspecialchars($address->address_line1) ?>
                                                <?= !empty($address->address_line2) ? ', ' . htmlspecialchars($address->address_line2) : '' ?>
                                            </p>
                                            <p class="location">
                                                <?= htmlspecialchars($address->city) ?>, 
                                                <?= htmlspecialchars($address->state) ?>,
                                                <?= htmlspecialchars($address->post_code) ?>
                                            </p>
                                            <p class="country"><?= htmlspecialchars($address->country) ?></p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="address-actions">
                                <a href="add_address.php?redirect=checkout.php" class="btn secondary-btn">
                                    <i class="fas fa-plus"></i> Add New Address
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <span class="section-number">2</span>
                            Payment Method
                        </h2>
                        
                        <div class="payment-selection">
                            <div class="payment-options">
                                <?php if (!empty($payment_methods)): ?>
                                    <div class="payment-option">
                                        <input type="radio" name="payment_option" id="saved_payment" value="saved" checked>
                                        <label for="saved_payment" class="option-label">
                                            <span>Use a saved payment method</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_option" id="new_payment" value="new" <?= empty($payment_methods) ? 'checked' : '' ?>>
                                    <label for="new_payment" class="option-label">
                                        <span>Add a new payment method</span>
                                    </label>
                                </div>
                            </div>
                            
                            <?php if (!empty($payment_methods)): ?>
                                <div class="saved-payment-methods" id="saved-payment-section">
                                    <?php foreach ($payment_methods as $index => $method): ?>
                                        <div class="payment-method-option">
                                            <input type="radio" name="payment_method_id" id="method_<?= $method->method_id ?>" value="<?= $method->method_id ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                            <label for="method_<?= $method->method_id ?>" class="payment-card">
                                                <div class="payment-card-header">
                                                    <?php if ($method->method_type === 'Credit Card'): ?>
                                                        <?php
                                                        $card_icon = 'fa-credit-card';
                                                        if (isset($method->card_type)) {
                                                            if ($method->card_type === 'Visa') {
                                                                $card_icon = 'fa-cc-visa';
                                                            } elseif ($method->card_type === 'MasterCard') {
                                                                $card_icon = 'fa-cc-mastercard';
                                                            } elseif ($method->card_type === 'American Express') {
                                                                $card_icon = 'fa-cc-amex';
                                                            }
                                                        }
                                                        ?>
                                                        <div class="card-icon">
                                                            <i class="fab <?= $card_icon ?>"></i>
                                                            <span><?= isset($method->card_type) ? htmlspecialchars($method->card_type) : 'Credit Card' ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card-icon">
                                                            <i class="fab fa-paypal"></i>
                                                            <span>PayPal</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($method->is_default): ?>
                                                        <div class="default-badge">Default</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="payment-card-details">
                                                    <?php if ($method->method_type === 'Credit Card'): ?>
                                                        <div class="card-number">•••• •••• •••• <?= htmlspecialchars($method->last_four) ?></div>
                                                        <div class="card-expiry">Expires: <?= sprintf('%02d', $method->expiry_month) ?>/<?= $method->expiry_year ?></div>
                                                    <?php else: ?>
                                                        <div class="paypal-email"><?= htmlspecialchars($method->paypal_email) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="payment-actions">
                                        <a href="add_payment.php?redirect=checkout.php" class="btn secondary-btn">
                                            <i class="fas fa-plus"></i> Add New Payment Method
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="new-payment-methods" id="new-payment-section" <?= !empty($payment_methods) ? 'style="display: none;"' : '' ?>>
                                <div class="payment-method-types">
                                    <div class="payment-type-option">
                                        <input type="radio" name="payment_type" id="credit_card" value="Credit Card" checked>
                                        <label for="credit_card" class="payment-type-label">
                                            <i class="fas fa-credit-card"></i>
                                            <span>Credit / Debit Card</span>
                                        </label>
                                    </div>
                                    
                                    <div class="payment-type-option">
                                        <input type="radio" name="payment_type" id="paypal" value="PayPal">
                                        <label for="paypal" class="payment-type-label">
                                            <i class="fab fa-paypal"></i>
                                            <span>PayPal</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="save-payment-option">
                                    <input type="checkbox" name="save_payment" id="save_payment" value="1" checked>
                                    <label for="save_payment">Save this payment method for future orders</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkout-actions">
                        <a href="shopping-bag.php" class="btn outline-btn back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Bag
                        </a>
                        
                        <button type="submit" name="proceed_to_payment" class="btn primary-btn">
                            Continue to Payment <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
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
                            <span>RM <?= number_format($delivery_fee, 2) ?></span>
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
        // Toggle payment sections based on selection
        const savedPaymentOption = document.getElementById('saved_payment');
        const newPaymentOption = document.getElementById('new_payment');
        const savedPaymentSection = document.getElementById('saved-payment-section');
        const newPaymentSection = document.getElementById('new-payment-section');
        
        if (savedPaymentOption && newPaymentOption) {
            savedPaymentOption.addEventListener('change', function() {
                if (this.checked) {
                    savedPaymentSection.style.display = 'block';
                    newPaymentSection.style.display = 'none';
                }
            });
            
            newPaymentOption.addEventListener('change', function() {
                if (this.checked) {
                    savedPaymentSection.style.display = 'none';
                    newPaymentSection.style.display = 'block';
                }
            });
        }
        
        // Form validation
        const checkoutForm = document.getElementById('checkout-form');
        checkoutForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check if address is selected
            const addressRadios = document.querySelectorAll('input[name="address_id"]');
            let addressSelected = false;
            addressRadios.forEach(radio => {
                if (radio.checked) {
                    addressSelected = true;
                }
            });
            
            if (!addressSelected) {
                alert('Please select a delivery address');
                isValid = false;
            }
            
            // Check if payment option is selected
            const paymentOption = document.querySelector('input[name="payment_option"]:checked');
            if (!paymentOption) {
                alert('Please select a payment option');
                isValid = false;
            } else {
                // If using saved payment, check if a method is selected
                if (paymentOption.value === 'saved') {
                    const methodRadios = document.querySelectorAll('input[name="payment_method_id"]');
                    let methodSelected = false;
                    methodRadios.forEach(radio => {
                        if (radio.checked) {
                            methodSelected = true;
                        }
                    });
                    
                    if (!methodSelected) {
                        alert('Please select a payment method');
                        isValid = false;
                    }
                } else if (paymentOption.value === 'new') {
                    // For new payment, check if type is selected
                    const typeRadios = document.querySelectorAll('input[name="payment_type"]');
                    let typeSelected = false;
                    typeRadios.forEach(radio => {
                        if (radio.checked) {
                            typeSelected = true;
                        }
                    });
                    
                    if (!typeSelected) {
                        alert('Please select a payment type');
                        isValid = false;
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>