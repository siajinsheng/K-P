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

// CONTINUE TO CHECKOUT Action - Prepare for checkout
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'continue_checkout') {
    try {
        error_log("[$username] Starting checkout process from shopping bag");
        
        // Fetch cart items
        $stm = $_db->prepare("
            SELECT c.*, p.product_price, p.product_name, p.product_pic1, q.quantity_id, q.size
            FROM cart c
            JOIN product p ON c.product_id = p.product_id
            LEFT JOIN quantity q ON c.quantity_id = q.quantity_id
            WHERE c.user_id = ?
        ");
        $stm->execute([$user_id]);
        $cart_items = $stm->fetchAll();
        
        if (empty($cart_items)) {
            error_log("[$username] Cart is empty, redirecting back to shopping bag");
            temp('error', 'Your shopping bag is empty');
            redirect('shopping-bag.php');
            exit;
        }
        
        error_log("[$username] Found " . count($cart_items) . " items in cart");
        
        // Check if all items are in stock
        $all_in_stock = true;
        $items_with_issues = [];
        
        foreach ($cart_items as $item) {
            // Get stock information
            $stm = $_db->prepare("
                SELECT product_stock FROM quantity 
                WHERE quantity_id = ?
            ");
            $stm->execute([$item->quantity_id]);
            $stock = $stm->fetchColumn();
            
            if ($stock < $item->quantity) {
                $all_in_stock = false;
                $items_with_issues[] = $item->product_name;
                error_log("[$username] Item {$item->product_name} has insufficient stock: requested {$item->quantity}, available $stock");
            }
        }
        
        if (!$all_in_stock) {
            $error_msg = 'Some items are out of stock: ' . implode(', ', $items_with_issues);
            error_log("[$username] Stock check failed: $error_msg");
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
            error_log("[$username] No delivery address found");
            temp('error', 'Please add a delivery address first');
            redirect('add_address.php?redirect=shopping-bag.php');
            exit;
        }
        
        error_log("[$username] Using address ID: $address_id");
        
        // Calculate subtotal and total
        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += $item->product_price * $item->quantity;
        }
        
        // Get address details for delivery fee calculation
        $stm = $_db->prepare("SELECT state FROM address WHERE address_id = ?");
        $stm->execute([$address_id]);
        $state = $stm->fetchColumn();
        
        // Modified delivery fee calculation
        // Free shipping for orders over RM100
        if ($subtotal >= 100) {
            $delivery_fee = 0; // Free delivery
            error_log("[$username] Free delivery applied (order > RM100)");
        } else {
            // Set delivery fee based on state (East Malaysia higher fee)
            $delivery_fee = in_array($state, ['Sabah', 'Sarawak', 'Labuan']) ? 40 : 20;
            error_log("[$username] Delivery fee set to RM$delivery_fee for state: $state");
        }
        
        // Calculate tax (6%)
        $tax = round($subtotal * 0.06, 2);
        
        // Calculate total (no discount for now)
        $order_total = $subtotal + $tax + $delivery_fee;
        
        // Store checkout data in session
        $_SESSION['checkout_data'] = [
            'cart_items' => $cart_items,
            'address_id' => $address_id,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'delivery_fee' => $delivery_fee,
            'total' => $order_total,
            'prepared_at' => time()
        ];
        
        error_log("[$username] Checkout data saved to session: subtotal=$subtotal, tax=$tax, delivery=$delivery_fee, total=$order_total");
        
        // Redirect to checkout page
        error_log("[$username] Redirecting to checkout.php");
        redirect('checkout.php');
        exit;
    } catch (Exception $e) {
        error_log("[$username] Checkout preparation error: " . $e->getMessage());
        error_log("[$username] Error trace: " . $e->getTraceAsString());
        temp('error', 'An error occurred while preparing checkout. Please try again.');
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
            SELECT c.product_id, q.size, c.quantity, p.product_name 
            FROM cart c 
            JOIN product p ON c.product_id = p.product_id
            JOIN quantity q ON c.quantity_id = q.quantity_id
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
                    SELECT c.product_id, c.quantity_id, c.quantity, p.product_name 
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
                        WHERE quantity_id = ?
                    ");
                    $stm->execute([$current_item->quantity_id]);
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
        SELECT c.cart_id, c.product_id, c.quantity, c.quantity_id, c.added_time,
               p.product_name, p.product_price, p.product_pic1, p.product_status,
               q.size, q.product_stock
        FROM cart c
        JOIN product p ON c.product_id = p.product_id
        JOIN quantity q ON c.quantity_id = q.quantity_id
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
    
    // Modified shipping fee logic - free for orders over RM100
    $shipping_fee = $subtotal >= 100 ? 0 : 20;
    
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
                    
                    <!-- Modified to use direct form with POST method for checkout process -->
                    <form method="post" action="shopping-bag.php" id="checkout-form">
                        <input type="hidden" name="action" value="continue_checkout">
                        <button type="submit" name="checkout_button" id="checkout-button" class="checkout-btn" <?= !empty($items_with_issues) ? 'disabled' : '' ?>>
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

        // Add extra debugging for the checkout button form submission
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                console.log('Checkout form submitted');
                // Add a short delay to make sure the browser registers the submission
                setTimeout(function() {
                    const checkoutButton = document.getElementById('checkout-button');
                    if (checkoutButton) {
                        checkoutButton.disabled = true;
                        checkoutButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
                    }
                }, 100);
            });
        }
    });
    
    // Confirm remove item
    function confirmRemove(link) {
        const productName = link.getAttribute('data-product');
        return confirm(`Remove "${productName}" from your shopping bag?`);
    }
    </script>
</body>
</html>