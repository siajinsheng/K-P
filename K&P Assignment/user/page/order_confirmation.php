<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view order confirmation');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$page_title = "Order Confirmation";

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');

// Get order ID from URL parameter
$order_id = req('order_id');

if (empty($order_id)) {
    temp('error', 'Order ID is required');
    redirect('profile.php#order-history');
    exit;
}

// Get order details
try {
    // Get order data
    $stm = $_db->prepare("
        SELECT o.*, p.payment_id, p.payment_method, p.payment_status, p.payment_date,
               d.delivery_id, d.delivery_status, d.estimated_date
        FROM orders o
        JOIN payment p ON o.order_id = p.order_id
        JOIN delivery d ON o.delivery_id = d.delivery_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or you do not have permission to view it');
        redirect('profile.php#order-history');
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
    
} catch (PDOException $e) {
    error_log("Error fetching order confirmation: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving the order confirmation');
    redirect('profile.php#order-history');
    exit;
}

// Format dates for better display
$order_date = date('F j, Y, g:i a', strtotime($order->order_date));
$payment_date = date('F j, Y, g:i a', strtotime($order->payment_date));
$estimated_date = date('F j, Y', strtotime($order->estimated_date));

// Calculate the item count
$item_count = 0;
foreach ($order_items as $item) {
    $item_count += $item->quantity;
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
    <style>
        .confirmation-container {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 40px;
            margin: 30px 0;
            text-align: center;
        }
        
        .confirmation-icon {
            color: #28a745;
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .confirmation-heading {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #000;
        }
        
        .confirmation-message {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: #555;
        }
        
        .order-details {
            margin: 30px auto;
            max-width: 600px;
            text-align: left;
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 5px;
        }
        
        .order-details h3 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .detail-label {
            font-weight: 600;
        }
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .order-summary {
            margin-top: 40px;
            border-top: 1px solid #eee;
            padding-top: 30px;
        }
        
        .summary-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            padding: 12px 15px;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-cell {
            display: flex;
            align-items: center;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .product-name {
            font-weight: 500;
        }
        
        .product-size {
            font-size: 0.85rem;
            color: #666;
        }
        
        .summary-footer {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            width: 300px;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 1.1rem;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 5px;
        }
        
        .estimated-delivery {
            background-color: #f9f9f9;
            padding: 15px 20px;
            margin: 30px 0;
            border-radius: 5px;
            text-align: center;
            border-left: 3px solid #000;
        }
        
        .estimated-delivery p {
            margin: 0;
            font-size: 1rem;
        }
        
        .estimated-delivery strong {
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .confirmation-container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
            
            .items-table {
                display: block;
                overflow-x: auto;
            }
            
            .summary-footer {
                align-items: stretch;
            }
            
            .summary-row {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
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
        
        <div class="confirmation-container">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1 class="confirmation-heading">Thank You for Your Order!</h1>
            
            <p class="confirmation-message">
                Your order has been placed successfully. We're processing it right away.
            </p>
            
            <div class="order-details">
                <h3>Order Information</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span><?= htmlspecialchars($order->order_id) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span><?= $order_date ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span><?= htmlspecialchars($order->payment_method) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Payment Status:</span>
                    <span><?= htmlspecialchars($order->payment_status) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span>RM <?= number_format($order->order_total, 2) ?></span>
                </div>
            </div>
            
            <div class="estimated-delivery">
                <p>
                    <i class="fas fa-truck"></i>
                    <strong>Estimated Delivery:</strong> <?= $estimated_date ?>
                </p>
            </div>
            
            <div class="action-buttons">
                <a href="order_details.php?id=<?= $order_id ?>" class="btn primary-btn">
                    <i class="fas fa-receipt"></i> View Order Details
                </a>
                
                <a href="index.php" class="btn secondary-btn">
                    <i class="fas fa-home"></i> Continue Shopping
                </a>
            </div>
            
            <div class="order-summary">
                <h3 class="summary-title">Order Summary</h3>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="product-image">
                                        <div>
                                            <div class="product-name"><?= htmlspecialchars($item->product_name) ?></div>
                                            <div class="product-size">Size: <?= $item->size ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>RM <?= number_format($item->unit_price, 2) ?></td>
                                <td><?= $item->quantity ?></td>
                                <td>RM <?= number_format($item->unit_price * $item->quantity, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="summary-footer">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>RM <?= number_format($order->order_subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>RM <?= number_format($order->order_total - $order->order_subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>RM <?= number_format($order->order_total, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>