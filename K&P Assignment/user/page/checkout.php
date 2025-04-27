<?php
require_once '../../_base.php';

// Define Stripe keys
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51RISsNFxdKIHFmkels1EXKIkW89B9Uze2ZIpRHAPi543xSzAbWwffGC4hxO0aB4B55h4wFmRrXJP2suVnp4H0M0m00gX8BR3Wm');

// Ensure session is started and user is authenticated
safe_session_start();

// Debug logging
error_log("Is post: " . (is_post() ? 'true' : 'false') . ", proceed_to_payment: " . (isset($_POST['proceed_to_payment']) ? 'true' : 'false'));
if (is_post()) {
    error_log("POST data: " . print_r($_POST, true));
}

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to checkout');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;
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
        SELECT c.*, p.product_name, p.product_pic1, p.product_price, q.quantity_id, q.size
        FROM cart c 
        JOIN product p ON c.product_id = p.product_id 
        LEFT JOIN quantity q ON c.quantity_id = q.quantity_id
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
    
    // Get saved credit cards
    $stm = $_db->prepare("
        SELECT * FROM payment_method
        WHERE user_id = ? AND method_type = 'Credit Card'
        ORDER BY is_default DESC, created_at DESC
    ");
    $stm->execute([$user_id]);
    $credit_cards = $stm->fetchAll();
    
    error_log("[$username] Payment methods found: " . count($credit_cards) . " credit cards");
    
    // Calculate cart totals
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item->quantity * $item->product_price;
    }
    
    // Calculate tax (6%)
    $tax = round($subtotal * 0.06, 2);
    
    // Calculate delivery fee based on user's default address
    // Free shipping for orders over RM100
    if ($subtotal >= 100) {
        $delivery_fee = 0; // Free delivery
        error_log("[$username] Free delivery applied (order > RM100)");
    } else {
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
            if ($default_address && isset($default_address->state) && 
                in_array($default_address->state, ['Sabah', 'Sarawak', 'Labuan'])) {
                $delivery_fee = 40; // Higher fee for East Malaysia
            } else {
                $delivery_fee = 20; // Standard fee for West Malaysia
            }
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
    $payment_method_type = post('payment_method_type');
    $payment_method_id = post('payment_method_id');
    $save_payment = isset($_POST['save_payment']) ? 1 : 0;
    
    error_log("[$username] Form submitted: address_id=$address_id, payment_method_type=$payment_method_type, payment_method_id=$payment_method_id, save_payment=$save_payment");
    
    try {
        if (empty($address_id)) {
            error_log("[$username] No address selected.");
            temp('error', 'Please select a delivery address');
            redirect('checkout.php');
            exit;
        }
        
        if (empty($payment_method_type) || !in_array($payment_method_type, ['Credit Card', 'Stripe'])) {
            error_log("[$username] No payment method type selected.");
            temp('error', 'Please select a payment method type (Credit Card or Stripe)');
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
        
        // Recalculate delivery fee based on selected address
        if ($subtotal >= 100) {
            $delivery_fee = 0; // Free delivery
        } else {
            // Set delivery fee based on state
            $delivery_fee = (isset($address->state) && 
                          in_array($address->state, ['Sabah', 'Sarawak', 'Labuan'])) ? 40 : 20;
        }
        
        $_SESSION['checkout_data']['delivery_fee'] = $delivery_fee;
        $_SESSION['checkout_data']['total'] = $subtotal + $tax + $delivery_fee;
        
        // Process based on payment method type
        if ($payment_method_type === 'Credit Card') {
            if (!empty($payment_method_id)) {
                // Using saved card
                $stm = $_db->prepare("SELECT * FROM payment_method WHERE method_id = ? AND user_id = ? AND method_type = 'Credit Card'");
                $stm->execute([$payment_method_id, $user_id]);
                $payment_method = $stm->fetch();
                
                if (!$payment_method) {
                    error_log("[$username] Invalid credit card selected: $payment_method_id");
                    temp('error', 'Invalid credit card selected');
                    redirect('checkout.php');
                    exit;
                }
                
                $_SESSION['checkout_data']['payment_method_id'] = $payment_method_id;
                $_SESSION['checkout_data']['payment_option'] = 'saved';
                $_SESSION['checkout_data']['payment_type'] = 'Credit Card';
                
                error_log("[$username] Using saved credit card ID: $payment_method_id");
                
                // Redirect to confirmation page
                redirect('checkout_confirm.php');
                exit;
            }
        } else if ($payment_method_type === 'Stripe') {
            // Stripe payment
            $_SESSION['checkout_data']['payment_type'] = 'Stripe';
            
            error_log("[$username] Using Stripe payment");
            
            // Redirect to Stripe page
            redirect('checkout_stripe.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Error processing checkout form for user $username: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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
                <form method="post" id="checkout-form" action="checkout.php">
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
                        </div>
                            
                        <div class="address-actions">
                            <a href="add_address.php?redirect=checkout.php" class="btn secondary-btn">
                                <i class="fas fa-plus"></i> Add New Address
                            </a>
                        </div>
                    </div>
                    
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <span class="section-number">2</span>
                            Payment Method
                        </h2>
                        
                        <!-- First, select payment method type -->
                        <div class="payment-method-types">
                            <div class="payment-type-option">
                                <input type="radio" name="payment_method_type" id="type_credit_card" value="Credit Card" checked>
                                <label for="type_credit_card" class="payment-type-label">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Saved Cards</span>
                                </label>
                            </div>
                            
                            <div class="payment-type-option">
                                <input type="radio" name="payment_method_type" id="type_stripe" value="Stripe">
                                <label for="type_stripe" class="payment-type-label">
                                    <i class="fab fa-stripe"></i>
                                    <span>Pay with Stripe</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-options-container" style="margin-top: 20px;">
                            <!-- Credit Card options - Combined layout -->
                            <div id="credit_card_options" class="payment-options-section">
                                <div class="payment-methods-grid">
                                    
                                    <!-- Saved cards -->
                                    <?php foreach ($credit_cards as $card): ?>
                                        <div class="payment-method-card">
                                            <label for="card_<?= $card->method_id ?>">
                                                <input type="radio" id="card_<?= $card->method_id ?>" name="payment_method_id" value="<?= $card->method_id ?>">
                                                <div class="card-header">
                                                    <?php
                                                    $card_icon = 'fa-credit-card';
                                                    if (isset($card->card_type)) {
                                                        if ($card->card_type === 'Visa') {
                                                            $card_icon = 'fa-cc-visa';
                                                        } elseif ($card->card_type === 'MasterCard') {
                                                            $card_icon = 'fa-cc-mastercard';
                                                        } elseif ($card->card_type === 'American Express') {
                                                            $card_icon = 'fa-cc-amex';
                                                        }
                                                    }
                                                    ?>
                                                    <div class="card-icon">
                                                        <i class="fab <?= $card_icon ?>"></i>
                                                        <span><?= isset($card->card_type) ? htmlspecialchars($card->card_type) : 'Credit Card' ?></span>
                                                    </div>
                                                    <?php if ($card->is_default): ?>
                                                        <div class="default-badge">Default</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-content">
                                                    <div>•••• •••• •••• <?= htmlspecialchars($card->last_four) ?></div>
                                                    <div>Expires: <?= sprintf('%02d', $card->expiry_month) ?>/<?= $card->expiry_year ?></div>
                                                    <div><?= htmlspecialchars($card->cardholder_name) ?></div>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div id="stripe_options" class="payment-options-section" style="display: none;">
                                <div class="stripe-info">
                                    <div class="stripe-logo" style="text-align: center; margin-bottom: 20px;">
                                        <i class="fab fa-stripe" style="font-size: 40px; color: #6772e5;"></i>
                                    </div>
                                    <div class="stripe-message" style="text-align: center; margin-bottom: 20px;">
                                        <p>Pay securely using Stripe's payment platform.</p>
                                    </div>
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
        console.log('DOM loaded, initializing checkout functionality');
        
        // Payment method type selection (Credit Card or Stripe)
        const creditCardRadio = document.getElementById('type_credit_card');
        const stripeRadio = document.getElementById('type_stripe');
        const creditCardOptions = document.getElementById('credit_card_options');
        const stripeOptions = document.getElementById('stripe_options');
        
        function showCreditCardOptions() {
            creditCardOptions.style.display = 'block';
            stripeOptions.style.display = 'none';
            console.log('Showing Credit Card options');
        }
        
        function showStripeOptions() {
            creditCardOptions.style.display = 'none';
            stripeOptions.style.display = 'block';
            console.log('Showing Stripe options');
        }
        
        if (creditCardRadio && stripeRadio) {
            creditCardRadio.addEventListener('change', function() {
                if (this.checked) {
                    showCreditCardOptions();
                }
            });
            
            stripeRadio.addEventListener('change', function() {
                if (this.checked) {
                    showStripeOptions();
                }
            });
            
            // Initialize the default view
            if (creditCardRadio.checked) {
                showCreditCardOptions();
            } else if (stripeRadio.checked) {
                showStripeOptions();
            }
        }
        
        // Credit Card selection styling
        const paymentMethodCards = document.querySelectorAll('.payment-method-card');
        const saveCardOption = document.getElementById('save_card_option');
        const newCardRadio = document.getElementById('new_card');
        
        // Select the first card by default (new card)
        if (newCardRadio) {
            newCardRadio.checked = true;
            newCardRadio.closest('.payment-method-card').classList.add('selected');
            if (saveCardOption) {
                saveCardOption.style.display = 'block';
            }
        }
        
        paymentMethodCards.forEach(card => {
            const radioInput = card.querySelector('input[type="radio"]');
            
            // Add click event to the entire card
            card.addEventListener('click', function() {
                radioInput.checked = true;
                
                // Remove 'selected' class from all cards
                paymentMethodCards.forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Add 'selected' class to clicked card
                this.classList.add('selected');
                
                // Show save option only for new card
                if (saveCardOption) {
                    if (radioInput.id === 'new_card') {
                        saveCardOption.style.display = 'block';
                    } else {
                        saveCardOption.style.display = 'none';
                    }
                }
            });
        });
        
        // Form validation and direct submission
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            console.log('Checkout form found, adding submission handler');
            
            checkoutForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission
                
                let isValid = true;
                console.log('Form submitted, validating...');
                
                // Log form data
                const formData = new FormData(this);
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Check if address is selected
                const addressRadios = document.querySelectorAll('input[name="address_id"]');
                let addressSelected = false;
                let selectedAddressId = '';
                
                addressRadios.forEach(radio => {
                    if (radio.checked) {
                        addressSelected = true;
                        selectedAddressId = radio.value;
                    }
                });
                
                if (!addressSelected) {
                    alert('Please select a delivery address');
                    isValid = false;
                    console.log('Validation failed: No address selected');
                    return;
                }
                
                // Check if payment method type is selected
                const paymentMethodType = document.querySelector('input[name="payment_method_type"]:checked');
                if (!paymentMethodType) {
                    alert('Please select a payment method type');
                    isValid = false;
                    console.log('Validation failed: No payment method type selected');
                    return;
                }
                
                // Create our custom form submission
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'checkout.php';
                form.style.display = 'none';
                
                // Add hidden fields
                const addHiddenField = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                };
                
                // Add the proceed_to_payment flag
                addHiddenField('proceed_to_payment', '1');
                
                // Add the address_id
                addHiddenField('address_id', selectedAddressId);
                
                // Add payment_method_type
                addHiddenField('payment_method_type', paymentMethodType.value);
                
                // If Credit Card is selected, check for payment_method_id
                if (paymentMethodType.value === 'Credit Card') {
                    const paymentMethodRadio = document.querySelector('input[name="payment_method_id"]:checked');
                    if (paymentMethodRadio) {
                        addHiddenField('payment_method_id', paymentMethodRadio.value);
                    } else {
                        addHiddenField('payment_method_id', '');
                    }
                    
                    // Check if save_payment is checked for new card
                    const savePaymentCheckbox = document.getElementById('save_payment');
                    if (savePaymentCheckbox && savePaymentCheckbox.checked) {
                        addHiddenField('save_payment', '1');
                    }
                }
                
                // Add the form to the document and submit it
                document.body.appendChild(form);
                
                // Disable the button to prevent double submission
                const submitBtn = checkoutForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
                
                console.log('Form validation passed, submitting custom form...');
                form.submit();
            });
        } else {
            console.error('Checkout form not found!');
        }
    });
    </script>
</body>
</html>