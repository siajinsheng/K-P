<?php
require_once '../../_base.php';

// Start session explicitly
safe_session_start();

// Log the session status for debugging
error_log("Shopping bag - Session ID: " . session_id());
error_log("Shopping bag - User in session: " . (isset($_SESSION['user']) ? "Yes ({$_SESSION['user']->user_name})" : "No"));

// Improved authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    error_log("Shopping bag - Auth failed: " . (isset($_SESSION['user']) ? "User object exists but no user_id" : "No user in session"));
    temp('info', 'Please log in to view your shopping bag');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Authentication successful - proceed with the rest of the code
$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;

error_log("Shopping bag - User authenticated: $username (ID: $user_id)");

// Helper function to generate sequential IDs in the format XXnnn
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
    
    return sprintf('%s%0' . $pad_length . 'd', $prefix, $next_num);
}

// CONTINUE TO CHECKOUT Action - Create order, payment, delivery records
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'continue_checkout') {
    try {
        $_db->beginTransaction();
        
        // Fetch cart items
        $stm = $_db->prepare("
            SELECT c.*, p.product_price, p.product_name 
            FROM cart c
            JOIN product p ON c.product_id = p.product_id
            WHERE c.user_id = ?
        ");
        $stm->execute([$user_id]);
        $cart_items = $stm->fetchAll();
        
        if (empty($cart_items)) {
            temp('error', 'Your shopping bag is empty');
            redirect('shopping-bag.php');
            exit;
        }
        
        // Check if all items are in stock
        $all_in_stock = true;
        $items_with_issues = [];
        
        foreach ($cart_items as $item) {
            // Get stock information
            $stm = $_db->prepare("
                SELECT product_stock FROM quantity 
                WHERE product_id = ? AND size = ?
            ");
            $stm->execute([$item->product_id, $item->size]);
            $stock = $stm->fetchColumn();
            
            if ($stock < $item->quantity) {
                $all_in_stock = false;
                $items_with_issues[] = $item->product_name;
            }
        }
        
        if (!$all_in_stock) {
            $error_msg = 'Some items are out of stock: ' . implode(', ', $items_with_issues);
            temp('error', $error_msg);
            redirect('shopping-bag.php');
            exit;
        }
        
        // Get default address
        $stm = $_db->prepare("SELECT address_id FROM address WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $stm->execute([$user_id]);
        $address_id = $stm->fetchColumn();
        
        // If no default address, get first available
        if (!$address_id) {
            $stm = $_db->prepare("SELECT address_id FROM address WHERE user_id = ? LIMIT 1");
            $stm->execute([$user_id]);
            $address_id = $stm->fetchColumn();
        }
        
        if (!$address_id) {
            temp('error', 'Please add a delivery address first');
            redirect('address.php?redirect=shopping-bag.php');
            exit;
        }
        
        // Calculate subtotal and total
        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += $item->product_price * $item->quantity;
        }
        
        // Get address details for delivery fee calculation
        $stm = $_db->prepare("SELECT state FROM address WHERE address_id = ?");
        $stm->execute([$address_id]);
        $state = $stm->fetchColumn();
        
        // Set delivery fee based on state (East Malaysia higher fee)
        $delivery_fee = in_array($state, ['Sabah', 'Sarawak', 'Labuan']) ? 40 : 20;
        
        // Calculate tax (6%)
        $tax = round($subtotal * 0.06, 2);
        
        // Calculate total (no discount for now)
        $order_total = $subtotal + $tax + $delivery_fee;
        $discount = 0; // No discount applied
        
        // Generate IDs in required format
        $order_id = generate_id('orders', 'order_id', 'OR');
        $payment_id = generate_id('payment', 'payment_id', 'PM');
        $delivery_id = generate_id('delivery', 'delivery_id', 'DV');
        
        error_log("Generated IDs - Order: $order_id, Payment: $payment_id, Delivery: $delivery_id");
        
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
            $order_total
        ]);
        
        // Insert payment record
        $stm = $_db->prepare("
            INSERT INTO payment (
                payment_id, order_id, tax, 
                total_amount, payment_method, payment_status, 
                payment_date, discount
            ) VALUES (?, ?, ?, ?, '', 'Pending', NOW(), ?)
        ");
        $stm->execute([
            $payment_id,
            $order_id,
            $tax,
            $order_total,
            $discount
        ]);
        
        // Insert order details for each cart item
        foreach ($cart_items as $item) {
            $stm = $_db->prepare("
                INSERT INTO order_details (
                    order_id, product_id, quantity, unit_price
                ) VALUES (?, ?, ?, ?)
            ");
            $stm->execute([
                $order_id,
                $item->product_id,
                $item->quantity,
                $item->product_price
            ]);
        }
        
        // Clear cart items
        $stm = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stm->execute([$user_id]);
        
        // Commit all database changes
        $_db->commit();
        
        // Store order information in session for checkout page
        $_SESSION['checkout_order_id'] = $order_id;
        $_SESSION['checkout_payment_id'] = $payment_id;
        $_SESSION['checkout_delivery_id'] = $delivery_id;
        
        // Log the successful checkout
        error_log("User $username ($user_id) checked out successfully. Order ID: $order_id");
        
        // Redirect to checkout confirmation page
        temp('success', 'Your order has been placed!');
        redirect('checkout.php');
        exit;
    } catch (Exception $e) {
        // Roll back all changes if something went wrong
        $_db->rollBack();
        error_log("Checkout error: " . $e->getMessage());
        temp('error', 'An error occurred during checkout. Please try again.');
        redirect('shopping-bag.php');
        exit;
    }
}

// Handle remove item action
if (isset($_GET['remove']) && is_get()) {
    $cart_id = $_GET['remove'];
    
    try {
        // Get product details before removing (for logging)
        $stm = $_db->prepare("
            SELECT c.product_id, c.size, c.quantity, p.product_name 
            FROM cart c 
            JOIN product p ON c.product_id = p.product_id
            WHERE c.cart_id = ? AND c.user_id = ?
        ");
        $stm->execute([$cart_id, $user_id]);
        $item_to_remove = $stm->fetch();
        
        if ($item_to_remove) {
            // Remove item
            $stm = $_db->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
            $stm->execute([$cart_id, $user_id]);
            
            // Log the removal
            error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) removed product {$item_to_remove->product_id} ({$item_to_remove->product_name})");
            
            temp('success', 'Item removed from your shopping bag');
        }
    } catch (PDOException $e) {
        error_log("Error removing cart item: " . $e->getMessage());
        temp('error', 'Failed to remove item from your shopping bag');
    }
    
    // Redirect to remove the GET parameter
    redirect('shopping-bag.php');
}

// Handle update quantity action
if (is_post() && isset($_POST['update_cart'])) {
    $updated = false;
    $errors = [];
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'quantity_') === 0) {
            $cart_id = substr($key, 9); // Get cart_id from input name (quantity_XXX)
            $quantity = (int)$value;
            
            if ($quantity < 1) {
                $errors[] = "Quantity must be at least 1";
                continue;
            }
            
            try {
                // Get current cart item info
                $stm = $_db->prepare("
                    SELECT c.product_id, c.size, c.quantity, p.product_name 
                    FROM cart c
                    JOIN product p ON c.product_id = p.product_id
                    WHERE c.cart_id = ? AND c.user_id = ?
                ");
                $stm->execute([$cart_id, $user_id]);
                $current_item = $stm->fetch();
                
                if ($current_item) {
                    // Skip update if quantity hasn't changed
                    if ($current_item->quantity == $quantity) {
                        continue;
                    }
                    
                    // Check stock
                    $stm = $_db->prepare("
                        SELECT product_stock FROM quantity 
                        WHERE product_id = ? AND size = ?
                    ");
                    $stm->execute([$current_item->product_id, $current_item->size]);
                    $stock = $stm->fetchColumn();
                    
                    if ($stock < $quantity) {
                        $errors[] = "Not enough stock available for {$current_item->product_name} (only $stock available)";
                        continue;
                    }
                    
                    // Update quantity
                    $stm = $_db->prepare("
                        UPDATE cart SET quantity = ?, added_time = NOW() WHERE cart_id = ? AND user_id = ?
                    ");
                    $stm->execute([$quantity, $cart_id, $user_id]);
                    
                    // Log the update
                    error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) updated quantity of product {$current_item->product_id}");
                    
                    $updated = true;
                }
            } catch (PDOException $e) {
                error_log("Error updating cart quantity: " . $e->getMessage());
                $errors[] = "Failed to update quantity for one of your items";
            }
        }
    }
    
    if ($updated && empty($errors)) {
        temp('success', 'Your shopping bag has been updated');
    } elseif (!empty($errors)) {
        foreach ($errors as $error) {
            temp('error', $error);
        }
    }
    
    // Redirect to remove the POST data
    redirect('shopping-bag.php');
}

// Get cart items with product details
try {
    $stm = $_db->prepare("
        SELECT c.cart_id, c.product_id, c.quantity, c.size, c.added_time,
               p.product_name, p.product_price, p.product_pic1, p.product_status,
               q.product_stock
        FROM cart c
        JOIN product p ON c.product_id = p.product_id
        JOIN quantity q ON c.product_id = q.product_id AND c.size = q.size
        WHERE c.user_id = ?
        ORDER BY c.added_time DESC
    ");
    $stm->execute([$user_id]);
    $cart_items = $stm->fetchAll();
    
    // Calculate totals
    $subtotal = 0;
    $total_items = 0;
    $items_with_issues = [];
    
    foreach ($cart_items as $item) {
        // Check if product is available
        if ($item->product_status !== 'Available') {
            $items_with_issues[] = [
                'cart_id' => $item->cart_id,
                'message' => 'This product is no longer available'
            ];
            continue;
        }
        
        // Check if enough stock
        if ($item->product_stock < $item->quantity) {
            $items_with_issues[] = [
                'cart_id' => $item->cart_id,
                'message' => "Only {$item->product_stock} items available"
            ];
        }
        
        $item_total = $item->product_price * $item->quantity;
        $subtotal += $item_total;
        $total_items += $item->quantity;
    }
    
    // Determine shipping fee
    $shipping_fee = $subtotal >= 100 ? 0 : 10;
    
    // Calculate total
    $total = $subtotal + $shipping_fee;
    
} catch (PDOException $e) {
    error_log("Error fetching cart items: " . $e->getMessage());
    $cart_items = [];
    $subtotal = 0;
    $shipping_fee = 0;
    $total = 0;
    $total_items = 0;
    $items_with_issues = [];
    temp('error', 'An error occurred while retrieving your shopping bag');
}

// Get success/error messages
$success_message = temp('success');
$error_message = temp('error');

// Current time display
$current_time = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Shopping Bag</title>
    <link rel="stylesheet" href="../css/shopping-bag.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Shopping Bag</h1>
        
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
        
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h2>Your shopping bag is empty</h2>
                <p>Looks like you haven't added anything to your bag yet.</p>
                <a href="products.php" class="continue-shopping-btn">CONTINUE SHOPPING</a>
            </div>
        <?php else: ?>
            <div class="shopping-bag-container">
                <div class="cart-items">
                    <form method="post" id="update-cart-form">
                        <?php foreach ($cart_items as $item): ?>
                            <?php 
                            $item_total = $item->product_price * $item->quantity;
                            $has_issue = false;
                            $issue_message = '';
                            
                            foreach ($items_with_issues as $issue) {
                                if ($issue['cart_id'] === $item->cart_id) {
                                    $has_issue = true;
                                    $issue_message = $issue['message'];
                                    break;
                                }
                            }
                            ?>
                            <div class="cart-item <?= $has_issue ? 'has-issue' : '' ?>">
                                <div class="cart-item-image">
                                    <a href="product-details.php?id=<?= $item->product_id ?>">
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                                    </a>
                                </div>
                                <div class="cart-item-details">
                                    <h3 class="cart-item-name">
                                        <a href="product-details.php?id=<?= $item->product_id ?>">
                                            <?= htmlspecialchars($item->product_name) ?>
                                        </a>
                                    </h3>
                                    <p class="cart-item-meta">Size: <?= $item->size ?></p>
                                    <p class="cart-item-meta">Product ID: <?= $item->product_id ?></p>
                                    
                                    <div class="cart-item-price">
                                        RM <?= number_format($item->product_price, 2) ?>
                                    </div>
                                    
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-btn minus" data-input="quantity_<?= $item->cart_id ?>">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               name="quantity_<?= $item->cart_id ?>" 
                                               id="quantity_<?= $item->cart_id ?>" 
                                               value="<?= $item->quantity ?>" 
                                               min="1" 
                                               max="<?= $item->product_stock ?>" 
                                               <?= $has_issue ? 'disabled' : '' ?>>
                                        <button type="button" class="quantity-btn plus" data-input="quantity_<?= $item->cart_id ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    
                                    <?php if ($has_issue): ?>
                                        <p class="cart-item-issue">
                                            <i class="fas fa-exclamation-triangle"></i> <?= $issue_message ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="cart-item-actions">
                                    <a href="shopping-bag.php?remove=<?= $item->cart_id ?>" 
                                       class="remove-item" 
                                       title="Remove item"
                                       data-product="<?= htmlspecialchars($item->product_name) ?>"
                                       onclick="return confirmRemove(this)">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-update">
                            <button type="submit" name="update_cart" value="1" class="update-cart-btn">
                                <i class="fas fa-sync-alt"></i> UPDATE BAG
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-item">
                        <span class="summary-label">Items (<?= $total_items ?>):</span>
                        <span class="summary-value">RM <?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Shipping:</span>
                        <span class="summary-value">
                            <?php if ($shipping_fee > 0): ?>
                                RM <?= number_format($shipping_fee, 2) ?>
                            <?php else: ?>
                                <span class="free-shipping">Free</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($shipping_fee > 0): ?>
                        <div class="free-shipping-message">
                            <i class="fas fa-truck"></i> Spend RM <?= number_format(100 - $subtotal, 2) ?> more for FREE shipping
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value">RM <?= number_format($total, 2) ?></span>
                    </div>
                    
                    <!-- Modified to use POST method for checkout process -->
                    <form method="post" action="shopping-bag.php">
                        <input type="hidden" name="action" value="continue_checkout">
                        <button type="submit" class="checkout-btn" <?= !empty($items_with_issues) ? 'disabled' : '' ?>>
                            CONTINUE TO CHECKOUT
                        </button>
                    </form>
                    
                    <?php if (!empty($items_with_issues)): ?>
                        <div class="checkout-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please resolve the issues with your items before proceeding
                        </div>
                    <?php endif; ?>
                    
                    <div class="continue-shopping">
                        <a href="products.php" class="continue-shopping-link">
                            <i class="fas fa-arrow-left"></i> Continue shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quantity controls
        const quantityBtns = document.querySelectorAll('.quantity-btn');
        
        quantityBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const input = document.getElementById(this.getAttribute('data-input'));
                let value = parseInt(input.value);
                let min = parseInt(input.getAttribute('min') || 1);
                let max = parseInt(input.getAttribute('max') || 10);
                
                if (this.classList.contains('minus') && value > min) {
                    input.value = value - 1;
                } else if (this.classList.contains('plus') && value < max) {
                    input.value = value + 1;
                }
                
                // Trigger change event to update form
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            });
        });
        
        // Validate quantity inputs on change
        const quantityInputs = document.querySelectorAll('.quantity-controls input');
        
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                let value = parseInt(this.value);
                let min = parseInt(this.getAttribute('min') || 1);
                let max = parseInt(this.getAttribute('max') || 10);
                
                if (isNaN(value) || value < min) {
                    this.value = min;
                } else if (value > max) {
                    this.value = max;
                }
            });
        });
        
        // Auto submit form on enter in quantity field
        quantityInputs.forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('update-cart-form').submit();
                }
            });
        });
    });
    
    // Confirm remove item
    function confirmRemove(link) {
        const productName = link.getAttribute('data-product');
        return confirm(`Remove "${productName}" from your shopping bag?`);
    }
    </script>
</body>
</html>