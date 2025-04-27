<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view your orders');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$username = $_SESSION['user']->user_name;
$page_title = "Order Confirmation";

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');

// Check if order ID is provided
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;

if (!$order_id) {
    temp('error', 'Invalid order reference');
    redirect('account.php?tab=orders');
    exit;
}

// Fetch order details
try {
    // Get order details
    $stm = $_db->prepare("
        SELECT o.*, d.delivery_status, d.estimated_date, d.address_id, 
               p.payment_method, p.payment_status
        FROM orders o
        JOIN delivery d ON o.delivery_id = d.delivery_id
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found');
        redirect('account.php?tab=orders');
        exit;
    }
    
    // Get order items
    $stm = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1, q.size
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        LEFT JOIN quantity q ON od.quantity_id = q.quantity_id
        WHERE od.order_id = ?
    ");
    $stm->execute([$order_id]);
    $order_items = $stm->fetchAll();
    
    // Get address details
    $stm = $_db->prepare("SELECT * FROM address WHERE address_id = ?");
    $stm->execute([$order->address_id]);
    $address = $stm->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching order details for user $username: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving your order details');
    redirect('account.php?tab=orders');
    exit;
}

// Format dates
$order_date = date('F j, Y, g:i a', strtotime($order->order_date));
$estimated_date = date('F j, Y', strtotime($order->estimated_date));

// Calculate order totals
$subtotal = $order->order_subtotal;
$delivery_fee = $order->order_total - $subtotal - ($subtotal * 0.06);
$tax = $subtotal * 0.06;
$total = $order->order_total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirmation-container {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .confirmation-icon {
            font-size: 60px;
            color: #4caf50;
            margin-bottom: 20px;
        }
        
        .confirmation-title {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .order-number {
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .confirmation-message {
            margin-bottom: 30px;
            color: #666;
        }
        
        .order-details-container {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 992px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .detail-card {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .detail-card-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .detail-card-content {
            font-size: 14px;
            color: #666;
        }
        
        .detail-card-content p {
            margin: 5px 0;
        }
        
        .detail-highlight {
            color: #000;
            font-weight: 500;
        }
        
        .order-items-list {
            margin-bottom: 30px;
        }
        
        .order-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            position: relative;
            margin-right: 15px;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .item-meta {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .item-price {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .order-totals {
            margin-top: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .total-row.final {
            font-size: 18px;
            font-weight: 500;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="confirmation-title">Thank you for your order!</h1>
            <p class="order-number">Order Number: <strong><?= $order_id ?></strong></p>
            <div class="confirmation-message">
                <p>Your order has been placed and a confirmation has been sent to your email.</p>
                <p>You can track your order status in your <a href="account.php?tab=orders">account orders section</a>.</p>
            </div>
        </div>
        
        <div class="order-details-container">
            <h2 class="section-title">Order Details</h2>
            
            <div class="order-details-grid">
                <div class="detail-card">
                    <h3 class="detail-card-title"><i class="fas fa-calendar-alt"></i> Order Information</h3>
                    <div class="detail-card-content">
                        <p>Order Date: <span class="detail-highlight"><?= $order_date ?></span></p>
                        <p>Order Status: <span class="detail-highlight"><?= $order->orders_status ?></span></p>
                        <p>Payment Method: <span class="detail-highlight"><?= $order->payment_method ?></span></p>
                        <p>Payment Status: <span class="detail-highlight"><?= $order->payment_status ?></span></p>
                    </div>
                </div>
                
                <div class="detail-card">
                    <h3 class="detail-card-title"><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                    <div class="detail-card-content">
                        <p>Shipping Status: <span class="detail-highlight"><?= $order->delivery_status ?></span></p>
                        <p>Estimated Delivery: <span class="detail-highlight"><?= $estimated_date ?></span></p>
                    </div>
                </div>
                
                <div class="detail-card">
                    <h3 class="detail-card-title"><i class="fas fa-map-marker-alt"></i> Shipping Address</h3>
                    <div class="detail-card-content">
                        <?php if ($address): ?>
                            <p><?= htmlspecialchars($address->recipient_name) ?></p>
                            <p><?= htmlspecialchars($address->address_line1) ?></p>
                            <?php if (!empty($address->address_line2)): ?>
                                <p><?= htmlspecialchars($address->address_line2) ?></p>
                            <?php endif; ?>
                            <p><?= htmlspecialchars($address->city) ?>, <?= htmlspecialchars($address->state) ?> <?= htmlspecialchars($address->post_code) ?></p>
                            <p><?= htmlspecialchars($address->country) ?></p>
                        <?php else: ?>
                            <p>Shipping address not available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <h2 class="section-title">Order Items</h2>
            
            <div class="order-items-list">
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <div class="item-image">
                            <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                        </div>
                        <div class="item-details">
                            <h3 class="item-name"><?= htmlspecialchars($item->product_name) ?></h3>
                            <p class="item-meta">Size: <?= $item->size ?></p>
                            <div class="item-price">
                                <span>RM <?= number_format($item->unit_price, 2) ?> × <?= $item->quantity ?></span>
                                <span>RM <?= number_format($item->unit_price * $item->quantity, 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>RM <?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping</span>
                    <span>
                        <?php if ($delivery_fee > 0): ?>
                            RM <?= number_format($delivery_fee, 2) ?>
                        <?php else: ?>
                            <span style="color: #4caf50;">Free</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="total-row">
                    <span>Tax (6%)</span>
                    <span>RM <?= number_format($tax, 2) ?></span>
                </div>
                <div class="total-row final">
                    <span>Total</span>
                    <span>RM <?= number_format($total, 2) ?></span>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <a href="products.php" class="btn outline-btn">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <a href="account.php?tab=orders" class="btn primary-btn">
                <i class="fas fa-list"></i> View All Orders
            </a>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>