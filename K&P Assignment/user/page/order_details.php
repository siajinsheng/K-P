<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view order details');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$order_id = req('id');

if (empty($order_id)) {
    temp('error', 'No order specified');
    redirect('profile.php#order-history');
}

// Get order details
try {
    // Get order header info
    $stm = $_db->prepare("
        SELECT o.*, d.delivery_status, d.estimated_date, d.delivered_date, 
               a.street, a.city, a.state, a.post_code, a.country,
               p.payment_method, p.payment_status, p.payment_date
        FROM orders o
        LEFT JOIN delivery d ON o.delivery_id = d.delivery_id
        LEFT JOIN address a ON d.address_id = a.address_id
        LEFT JOIN payment p ON p.order_id = o.order_id
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
    
} catch (PDOException $e) {
    error_log("Error retrieving order details: " . $e->getMessage());
    temp('error', 'Failed to retrieve order details');
    redirect('profile.php#order-history');
}

$page_title = "Order Details: " . $order_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/order-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="page-header">
            <h1>Order Details</h1>
            <div class="order-id">
                <span>Order #<?= htmlspecialchars($order_id) ?></span>
                <span class="order-date">Placed on <?= date('F d, Y', strtotime($order->order_date)) ?></span>
            </div>
        </div>
        
        <div class="order-status-bar">
            <div class="status-steps">
                <?php
                $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                $current_status = $order->orders_status;
                $current_index = array_search($current_status, $statuses);
                
                foreach ($statuses as $index => $status):
                    $is_active = $index <= $current_index;
                    $status_class = $is_active ? 'active' : '';
                ?>
                <div class="status-step <?= $status_class ?>">
                    <div class="status-icon">
                        <?php if ($is_active): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <i class="fas fa-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="status-label"><?= $status ?></div>
                    <?php if ($status === 'Delivered' && $is_active && $order->delivered_date): ?>
                        <div class="status-date">
                            <?= date('M d, Y', strtotime($order->delivered_date)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($index < count($statuses) - 1): ?>
                    <div class="status-line <?= $index < $current_index ? 'active' : '' ?>"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <?php if ($current_status === 'Cancelled'): ?>
            <div class="cancelled-status">
                <div class="cancelled-badge">
                    <i class="fas fa-times"></i> Order Cancelled
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="order-sections-container">
            <div class="order-details-section">
                <h2>Order Summary</h2>
                
                <div class="order-items">
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= $item->product_name ?>">
                            </div>
                            <div class="item-details">
                                <h3><?= htmlspecialchars($item->product_name) ?></h3>
                                <p class="item-type"><?= $item->product_type ?></p>
                                <div class="item-price-qty">
                                    <span class="item-price">RM <?= number_format($item->unit_price, 2) ?></span>
                                    <span class="item-qty">Qty: <?= $item->quantity ?></span>
                                </div>
                            </div>
                            <div class="item-total">
                                RM <?= number_format($item->unit_price * $item->quantity, 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <div class="summary-label">Subtotal</div>
                        <div class="summary-value">RM <?= number_format($order->order_subtotal, 2) ?></div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Shipping</div>
                        <div class="summary-value">RM <?= number_format($order->order_total - $order->order_subtotal, 2) ?></div>
                    </div>
                    <div class="summary-row total">
                        <div class="summary-label">Total</div>
                        <div class="summary-value">RM <?= number_format($order->order_total, 2) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="order-info-section">
                <div class="shipping-info">
                    <h2>Shipping Information</h2>
                    <div class="info-content">
                        <p><strong>Status:</strong> <?= $order->delivery_status ?></p>
                        <?php if ($order->estimated_date): ?>
                            <p><strong>Estimated Delivery:</strong> <?= date('F d, Y', strtotime($order->estimated_date)) ?></p>
                        <?php endif; ?>
                        
                        <div class="address">
                            <h3>Shipping Address</h3>
                            <p><?= htmlspecialchars($order->street) ?></p>
                            <p><?= htmlspecialchars($order->city) ?>, <?= htmlspecialchars($order->state) ?> <?= htmlspecialchars($order->post_code) ?></p>
                            <p><?= htmlspecialchars($order->country) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="payment-info">
                    <h2>Payment Information</h2>
                    <div class="info-content">
                        <p><strong>Payment Method:</strong> <?= $order->payment_method ?></p>
                        <p><strong>Payment Status:</strong> <?= $order->payment_status ?></p>
                        <?php if ($order->payment_date): ?>
                            <p><strong>Payment Date:</strong> <?= date('F d, Y h:i A', strtotime($order->payment_date)) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="order-actions">
                    <?php if ($order->orders_status === 'Delivered'): ?>
                        <a href="write-review.php?order=<?= $order_id ?>" class="btn secondary-btn">
                            <i class="fas fa-star"></i> Write a Review
                        </a>
                    <?php endif; ?>
                    
                    <a href="contact-support.php?order=<?= $order_id ?>" class="btn outline-btn">
                        <i class="fas fa-question-circle"></i> Need Help?
                    </a>
                    
                    <a href="profile.php#order-history" class="btn outline-btn">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>