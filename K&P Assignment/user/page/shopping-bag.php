<?php
require_once '../../_base.php';
// Set page variables
$page_title = "Shopping Bag";

// Check if user is logged in, if not, redirect to login page
safe_session_start();
if (!isset($_SESSION['user'])) {
    temp('info', 'Please log in to view your shopping bag');
    redirect('login.php');
}

$user_id = $_SESSION['user']->user_id;

// Handle actions (remove item, update quantity)
if (is_post()) {
    // Remove item
    if (isset($_POST['remove_item'])) {
        $cart_id = $_POST['remove_item'];
        $stm = $_db->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $stm->execute([$cart_id, $user_id]);
        redirect('shopping-bag.php');
    }
    
    // Update quantity
    if (isset($_POST['update_quantity'])) {
        $cart_id = $_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $stm = $_db->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
            $stm->execute([$quantity, $cart_id, $user_id]);
        }
        redirect('shopping-bag.php');
    }
}

// Get cart items
$stm = $_db->prepare("
    SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.product_price, p.product_pic1
    FROM cart c
    JOIN product p ON c.product_id = p.product_id
    WHERE c.user_id = ?
    ORDER BY c.added_time DESC
");
$stm->execute([$user_id]);
$cart_items = $stm->fetchAll();

// Calculate totals
$subtotal = 0;
$shipping = 10.00; // Default shipping fee
$total = 0;

foreach ($cart_items as $item) {
    $subtotal += $item->product_price * $item->quantity;
}

$total = $subtotal + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/shopping-bag.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="cart-container">
        <h1>Your Shopping Bag</h1>
        
        <?php if (count($cart_items) > 0): ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= $item->product_name ?>">
                            </div>
                            
                            <div class="item-details">
                                <h3><?= $item->product_name ?></h3>
                                <p class="item-price">RM <?= number_format($item->product_price, 2) ?></p>
                                
                                <div class="item-actions">
                                    <form method="post" class="quantity-form">
                                        <input type="hidden" name="cart_id" value="<?= $item->cart_id ?>">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn decrease">-</button>
                                            <input type="number" name="quantity" value="<?= $item->quantity ?>" min="1" class="quantity-input">
                                            <button type="button" class="quantity-btn increase">+</button>
                                        </div>
                                        <button type="submit" name="update_quantity" class="update-btn">Update</button>
                                    </form>
                                    
                                    <form method="post" class="remove-form">
                                        <button type="submit" name="remove_item" value="<?= $item->cart_id ?>" class="remove-btn">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="item-total">
                                <span>Total</span>
                                <p>RM <?= number_format($item->product_price * $item->quantity, 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>RM <?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>RM <?= number_format($shipping, 2) ?></span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>RM <?= number_format($total, 2) ?></span>
                    </div>
                    
                    <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                    <a href="products.php" class="continue-shopping">Continue Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-bag"></i>
                <h2>Your bag is empty</h2>
                <p>Looks like you haven't added anything to your bag yet!</p>
                <a href="products.php" class="continue-shopping">Go Shopping</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
        // Quantity controls
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentNode.querySelector('.quantity-input');
                let value = parseInt(input.value);
                
                if (this.classList.contains('decrease')) {
                    value = value > 1 ? value - 1 : 1;
                } else {
                    value = value + 1;
                }
                
                input.value = value;
            });
        });
    </script>
</body>
</html>