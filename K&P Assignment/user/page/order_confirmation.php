<?php
require_once '../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view order details');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    temp('error', 'Invalid order ID');
    redirect('shopping-bag.php');
}

$order_id = $_GET['order_id'];

// Get order details
try {
    $stm = $_db->prepare("
        SELECT o.*, d.delivery_id, d.delivery_fee, d.delivery_status, d.estimated_date,
               a.recipient_name, a.address_line1, a.address_line2, a.city, a.state, a.post_code, a.country,
               p.payment_method, p.payment_status, p.tax
        FROM orders o
        JOIN delivery d ON o.delivery_id = d.delivery_id
        JOIN address a ON d.address_id = a.address_id
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or access denied');
        redirect('shopping-bag.php');
    }
    
    // Get order items
    $stm = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    $stm->execute([$order_id]);
    $order_items = $stm->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving order details');
    redirect('shopping-bag.php');
}

// Get success/error messages
$success_message = temp('success');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Order Confirmation</title>
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-confirmation {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .confirmation-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .confirmation-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .order-id {
            font-size: 18px;
            color: #4a6fa5;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .order-details {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .detail-section {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
        }
        
        .detail-section h3 {
            margin: 0 0 15px;
            font-size: 18px;
            color: #333;
        }
        
        .address-details,
        .payment-details {
            line-height: 1.6;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-processing {
            background-color: #b8daff;
            color: #004085;
        }
        
        .status-shipped {
            background-color: #c3e6cb;
            color: #155724;
        }
        
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .view-orders {
            background-color: #4a6fa5;
            color: #fff;
        }
        
        .view-orders:hover {
            background-color: #3a5a85;
        }
        
        .continue-shopping {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .continue-shopping:hover {
            background-color: #e9ecef;
        }
        
        .action-button i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Order Confirmation</h1>
        
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

        <div class="order-confirmation">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="confirmation-title">Thank you for your order!</h2>
            <p class="order-id">Order ID: <?= $order_id ?></p>
            <p>A confirmation email has been sent to your registered email address.</p>
        </div>

        <div class="order-details">
            <div class="detail-section">
                <h3><i class="fas fa-shopping-bag"></i> Order Summary</h3>
                <div class="order-summary">
                    <div class="summary-item">
                        <span class="summary-label">Order Date:</span>
                        <span class="summary-value"><?= date('M d, Y H:i', strtotime($order->order_date)) ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Order Status:</span>
                        <span class="summary-value">
                            <span class="status-badge status-<?= strtolower($order->orders_status) ?>">
                                <?= $order->orders_status ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Payment Method:</span>
                        <span class="summary-value"><?= $order->payment_method ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Payment Status:</span>
                        <span class="summary-value">
                            <span class="status-badge status-<?= strtolower($order->payment_status) ?>">
                                <?= $order->payment_status ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-map-marker-alt"></i> Shipping Details</h3>
                <div class="address-details">
                    <p><strong>Recipient:</strong> <?= htmlspecialchars($order->recipient_name) ?></p>
                    <p>
                        <?= htmlspecialchars($order->address_line1) ?>
                        <?= $order->address_line2 ? ', ' . htmlspecialchars($order->address_line2) : '' ?><br>
                        <?= htmlspecialchars($order->city) ?>, <?= htmlspecialchars($order->state) ?> <?= htmlspecialchars($order->post_code) ?><br>
                        <?= htmlspecialchars($order->country) ?>
                    </p>
                    <p><strong>Delivery Status:</strong> 
                        <span class="status-badge status-<?= strtolower($order->delivery_status) ?>">
                            <?= $order->delivery_status ?>
                        </span>
                    </p>
                    <p><strong>Estimated Delivery:</strong> <?= date('M d, Y', strtotime($order->estimated_date)) ?></p>
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-shopping-cart"></i> Order Items</h3>
                <?php foreach ($order_items as $item): ?>
                    <?php $item_total = $item->quantity * $item->unit_price; ?>
                    <div class="order-item">
                        <div class="item-image">
                            <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>">
                        </div>
                        <div class="item-info">
                            <h4><?= htmlspecialchars($item->product_name) ?></h4>
                            <p>Product ID: <?= $item->product_id ?></p>
                            <p>Quantity: <?= $item->quantity ?></p>
                        </div>
                        <div class="item-price">
                            <p>RM <?= number_format($item->unit_price, 2) ?> × <?= $item->quantity ?></p>
                            <p class="item-total">RM <?= number_format($item_total, 2) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                <div class="payment-details">
                    <div class="summary-item">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value">RM <?= number_format($order->order_subtotal, 2) ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Shipping Fee:</span>
                        <span class="summary-value">
                            <?php $shipping_fee = $order->order_total - $order->order_subtotal - $order->tax; ?>
                            <?php if ($shipping_fee > 0): ?>
                                RM <?= number_format($shipping_fee, 2) ?>
                            <?php else: ?>
                                <span class="free-shipping">Free</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Tax (6% GST):</span>
                        <span class="summary-value">RM <?= number_format($order->tax, 2) ?></span>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="summary-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value">RM <?= number_format($order->order_total, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="orders.php" class="action-button view-orders">
                <i class="fas fa-list"></i> View My Orders
            </a>
            <a href="products.php" class="action-button continue-shopping">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>