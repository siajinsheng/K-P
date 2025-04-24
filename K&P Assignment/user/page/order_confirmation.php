<?php
require_once '../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view your order');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Check if order_id is provided
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;

if (empty($order_id)) {
    redirect('profile.php#order-history');
}

try {
    // Get order information
    $stm = $_db->prepare("
        SELECT o.*, d.*, p.payment_method, p.payment_status, p.payment_id,
               a.recipient_name, a.phone, a.address_line1, a.address_line2, a.city, a.state, a.post_code, a.country
        FROM orders o
        JOIN delivery d ON o.delivery_id = d.delivery_id
        LEFT JOIN payment p ON o.order_id = p.order_id
        LEFT JOIN address a ON d.address_id = a.address_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or does not belong to your account');
        redirect('profile.php#order-history');
    }
    
    // Get order items
    $stm = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1, p.product_type
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    $stm->execute([$order_id]);
    $order_items = $stm->fetchAll();
    
    // Calculate summary
    $subtotal = $order->order_subtotal;
    $shipping_fee = $order->delivery_fee;
    $tax = $order->total_price - $order->order_subtotal - $shipping_fee;
    $total = $order->total_price;
    
} catch (PDOException $e) {
    error_log("Error retrieving order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving order information');
    redirect('profile.php#order-history');
}

// Set page title
$page_title = "Order Confirmation - " . $order_id;

// Get any messages from session
$success_message = temp('success') ?? 'Your order has been placed successfully!';
$error_message = temp('error');
$info_message = temp('info');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/order-confirmation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Order Confirmed!</h1>
                <p>Your order has been placed successfully. Thank you for shopping with K&P!</p>
            </div>
            
            <div class="confirmation-details">
                <div class="order-info">
                    <div class="info-item">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value"><?= $order_id ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Order Date:</span>
                        <span class="info-value"><?= date('F d, Y h:i A', strtotime($order->order_date)) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value"><?= $order->payment_method ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Status:</span>
                        <span class="info-value status-badge status-<?= strtolower($order->payment_status) ?>">
                            <?= $order->payment_status ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Order Status:</span>
                        <span class="info-value status-badge status-<?= strtolower($order->orders_status) ?>">
                            <?= $order->orders_status ?>
                        </span>
                    </div>
                </div>
                
                <div class="confirmation-sections">
                    <!-- Shipping Information -->
                    <div class="confirmation-section">
                        <h2><i class="fas fa-shipping-fast"></i> Shipping Information</h2>
                        <div class="section-content">
                            <p><strong><?= htmlspecialchars($order->recipient_name) ?></strong></p>
                            <p><?= htmlspecialchars($order->phone) ?></p>
                            <p>
                                <?= htmlspecialchars($order->address_line1) ?>
                                <?= $order->address_line2 ? ', ' . htmlspecialchars($order->address_line2) : '' ?>
                            </p>
                            <p>
                                <?= htmlspecialchars($order->city) ?>, 
                                <?= htmlspecialchars($order->state) ?>, 
                                <?= htmlspecialchars($order->post_code) ?>
                            </p>
                            <p><?= htmlspecialchars($order->country) ?></p>
                            
                            <div class="delivery-info">
                                <p><strong>Delivery Status:</strong> <?= $order->delivery_status ?></p>
                                <p><strong>Estimated Delivery:</strong> <?= date('F d, Y', strtotime($order->estimated_date)) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="confirmation-section">
                        <h2><i class="fas fa-list-ul"></i> Order Summary</h2>
                        <div class="section-content">
                            <div class="order-items">
                                <?php foreach ($order_items as $item): ?>
                                    <?php $item_total = $item->unit_price * $item->quantity; ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                                        </div>
                                        <div class="item-info">
                                            <h4><?= htmlspecialchars($item->product_name) ?></h4>
                                            <p>Quantity: <?= $item->quantity ?></p>
                                            <p>Price: RM <?= number_format($item->unit_price, 2) ?></p>
                                        </div>
                                        <div class="item-total">
                                            RM <?= number_format($item_total, 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-summary">
                                <div class="summary-row">
                                    <span class="summary-label">Subtotal:</span>
                                    <span class="summary-value">RM <?= number_format($subtotal, 2) ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Shipping:</span>
                                    <span class="summary-value">
                                        <?php if ($shipping_fee > 0): ?>
                                            RM <?= number_format($shipping_fee, 2) ?>
                                        <?php else: ?>
                                            <span class="free-shipping">Free</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Tax (6% GST):</span>
                                    <span class="summary-value">RM <?= number_format($tax, 2) ?></span>
                                </div>
                                <div class="summary-row total">
                                    <span class="summary-label">Total:</span>
                                    <span class="summary-value">RM <?= number_format($total, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="confirmation-section">
                        <h2><i class="fas fa-credit-card"></i> Payment Information</h2>
                        <div class="section-content">
                            <div class="payment-details">
                                <p><strong>Payment Method:</strong> <?= $order->payment_method ?></p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="status-badge status-<?= strtolower($order->payment_status) ?>">
                                        <?= $order->payment_status ?>
                                    </span>
                                </p>
                                <p><strong>Transaction ID:</strong> <?= $order->payment_id ?></p>
                                
                                <?php if ($order->payment_method === 'Cash on Delivery'): ?>
                                    <div class="cod-instructions">
                                        <p>Please prepare <strong>RM <?= number_format($total, 2) ?></strong> in cash for delivery.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="confirmation-actions">
                <div class="action-group">
                    <a href="profile.php#order-history" class="btn secondary-btn">
                        <i class="fas fa-list"></i> View All Orders
                    </a>
                    <a href="products.php" class="btn primary-btn">
                        <i class="fas fa-shopping-bag"></i> Continue Shopping
                    </a>
                </div>
                
                <div class="receipt-action">
                    <a href="generate_receipt.php?order_id=<?= $order_id ?>" class="btn outline-btn" target="_blank">
                        <i class="fas fa-file-invoice"></i> Download Receipt
                    </a>
                </div>
            </div>
            
            <div class="confirmation-footer">
                <div class="support-info">
                    <p>If you have any questions about your order, please contact our customer service:</p>
                    <p><i class="fas fa-envelope"></i> support@kpfashion.com</p>
                    <p><i class="fas fa-phone"></i> +60 3-1234 5678</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>