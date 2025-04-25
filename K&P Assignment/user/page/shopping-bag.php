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
            error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) removed product {$item_to_remove->product_id} ({$item_to_remove->product_name}, size: {$item_to_remove->size}, qty: {$item_to_remove->quantity})");
            
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
                    error_log("[" . date('Y-m-d H:i:s') . "] User $username ($user_id) updated quantity of product {$current_item->product_id} ({$current_item->product_name}, size: {$current_item->size}) from {$current_item->quantity} to $quantity");
                    
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
        <h1 class="page-title">Your Shopping Bag</h1>
        
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
                <p>Looks like you haven't added any products to your bag yet.</p>
                <a href="products.php" class="continue-shopping-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="shopping-bag-container">
                <div class="cart-items">
                    <form method="post" id="update-cart-form">
                        <div class="cart-header">
                            <div class="cart-item-header">Product</div>
                            <div class="cart-price-header">Price</div>
                            <div class="cart-quantity-header">Quantity</div>
                            <div class="cart-total-header">Total</div>
                            <div class="cart-actions-header"></div>
                        </div>
                        
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
                                <div class="cart-item-info">
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
                                        <p class="cart-item-meta">Added: <?= date('M d, Y H:i', strtotime($item->added_time)) ?></p>
                                        <?php if ($has_issue): ?>
                                            <p class="cart-item-issue">
                                                <i class="fas fa-exclamation-triangle"></i> <?= $issue_message ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="cart-item-price" data-label="Price:">
                                    RM <?= number_format($item->product_price, 2) ?>
                                </div>
                                
                                <div class="cart-item-quantity" data-label="Quantity:">
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
                                </div>
                                
                                <div class="cart-item-total" data-label="Total:">
                                    RM <?= number_format($item_total, 2) ?>
                                </div>
                                
                                <div class="cart-item-actions">
                                    <a href="shopping-bag.php?remove=<?= $item->cart_id ?>" 
                                       class="remove-item" 
                                       title="Remove item"
                                       data-product="<?= htmlspecialchars($item->product_name) ?>"
                                       onclick="return confirmRemove(this)">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-update">
                            <button type="submit" name="update_cart" value="1" class="update-cart-btn">
                                <i class="fas fa-sync-alt"></i> Update Cart
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
                            <i class="fas fa-truck"></i> Spend RM <?= number_format(100 - $subtotal, 2) ?> more for FREE shipping!
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value">RM <?= number_format($total, 2) ?></span>
                    </div>
                    
                    <a href="checkout.php" class="checkout-btn" <?= !empty($items_with_issues) ? 'disabled' : '' ?>>
                        <i class="fas fa-lock"></i> Proceed to Checkout
                    </a>
                    
                    <?php if (!empty($items_with_issues)): ?>
                        <div class="checkout-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please resolve the issues with your cart items before proceeding to checkout.
                        </div>
                    <?php endif; ?>
                    
                    <div class="continue-shopping">
                        <a href="products.php" class="continue-shopping-link">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                    
                    <div class="secure-checkout">
                        <div class="secure-checkout-header">
                            <i class="fas fa-shield-alt"></i> Secure Checkout
                        </div>
                        <div class="payment-methods">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-paypal"></i>
                        </div>
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
                
                // Highlight changed row
                const cartItem = input.closest('.cart-item');
                cartItem.classList.add('quantity-changed');
                setTimeout(() => {
                    cartItem.classList.remove('quantity-changed');
                }, 2000);
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
        return confirm(`Are you sure you want to remove "${productName}" from your shopping bag?`);
    }
    </script>
</body>
</html>