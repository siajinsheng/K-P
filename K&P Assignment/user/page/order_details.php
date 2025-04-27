<?php
require_once '../../_base.php';

safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view order details');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$page_title = "Order Details";

// Get order ID from URL
$order_id = req('id');

if (empty($order_id)) {
    temp('error', 'Order ID is required');
    redirect('orders.php');
    exit;
}

// Handle order cancellation
if (is_post() && isset($_POST['cancel_order'])) {
    try {
        // Check if the order exists and belongs to the user
        $stmt = $_db->prepare("SELECT orders_status FROM orders WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch();

        if (!$order) {
            temp('error', "Order not found or you do not have permission to cancel it.");
            redirect("order_details.php?id=$order_id");
        } elseif (!in_array($order->orders_status, ['Pending', 'Processing'])) {
            temp('error', "Only orders in 'Pending' or 'Processing' status can be canceled.");
            redirect("order_details.php?id=$order_id");
        } else {
            // Update the order status to "Cancelled"
            $_db->beginTransaction();

            $updateOrderQuery = "UPDATE orders SET orders_status = 'Cancelled' WHERE order_id = ?";
            $updateDeliveryQuery = "UPDATE delivery d 
                                    JOIN orders o ON d.delivery_id = o.delivery_id 
                                    SET d.delivery_status = 'Failed' 
                                    WHERE o.order_id = ?";

            $stmt = $_db->prepare($updateOrderQuery);
            $stmt->execute([$order_id]);

            $stmt = $_db->prepare($updateDeliveryQuery);
            $stmt->execute([$order_id]);

            $_db->commit();

            temp('success', "Order #$order_id has been successfully canceled.");
            redirect("order_details.php?id=$order_id");
        }
    } catch (PDOException $e) {
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        error_log("Error canceling order: " . $e->getMessage());
        temp('error', "An error occurred while canceling the order. Please try again.");
        redirect("order_details.php?id=$order_id");
    }
}

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');

try {
    // Get order information
    $stmt = $_db->prepare("
        SELECT o.*, p.payment_id, p.tax, p.total_amount, p.payment_method, p.payment_status, p.payment_date, 
               d.delivery_id, d.address_id, d.delivery_fee, d.delivery_status, d.estimated_date, d.delivered_date
        FROM orders o
        LEFT JOIN payment p ON o.order_id = p.order_id
        LEFT JOIN delivery d ON o.delivery_id = d.delivery_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        temp('error', 'Order not found or you do not have permission to view it');
        redirect('orders.php');
        exit;
    }

    // Get order items
    $stmt = $_db->prepare("
        SELECT od.*, p.product_name, p.product_pic1, q.size
        FROM order_details od
        JOIN product p ON od.product_id = p.product_id
        LEFT JOIN quantity q ON od.quantity_id = q.quantity_id
        WHERE od.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

    // Get delivery address
    $stmt = $_db->prepare("
        SELECT * FROM address WHERE address_id = ?
    ");
    $stmt->execute([$order->address_id]);
    $address = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving the order details');
    redirect('orders.php');
    exit;
}

// Current date and time for display
$current_date = date('Y-m-d H:i:s');

// Format dates for display
$order_date = date('F j, Y, g:i a', strtotime($order->order_date));
$payment_date = !empty($order->payment_date) ? date('F j, Y, g:i a', strtotime($order->payment_date)) : '-';
$estimated_date = !empty($order->estimated_date) ? date('F j, Y', strtotime($order->estimated_date)) : '-';
$delivered_date = !empty($order->delivered_date) ? date('F j, Y', strtotime($order->delivered_date)) : '-';

// Calculate order summary
$subtotal = $order->order_subtotal;
$tax = $order->tax;
$delivery_fee = $order->delivery_fee;
$total = $order->total_amount;
$discount = $total - $subtotal - $tax - $delivery_fee;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Additional styles specific to order details page */
        .order-details-container {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .order-details-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-details-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .order-details-title .status-badge {
            margin-left: 15px;
        }

        .order-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .order-date-info {
            font-size: 0.85rem;
            color: #666;
        }

        .order-id-info {
            font-weight: 600;
        }

        .order-details-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }

        .order-details-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.2rem;
            margin: 0 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: #666;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-items-table th {
            text-align: left;
            padding: 12px 15px;
            background-color: #f5f5f5;
            font-weight: 600;
            color: #333;
        }

        .order-items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .order-items-table tr:last-child td {
            border-bottom: none;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }

        .product-details {
            line-height: 1.4;
        }

        .product-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .product-size,
        .product-id {
            font-size: 0.85rem;
            color: #666;
        }

        .item-quantity,
        .item-price,
        .item-total {
            font-weight: 500;
        }

        .order-summary {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }

        .summary-row.total {
            font-weight: 600;
            font-size: 1.1rem;
            border-top: 1px solid #ddd;
            padding-top: 12px;
            margin-top: 5px;
        }

        .shipping-details,
        .payment-details {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .info-card {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }

        .info-card-title {
            font-weight: 600;
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
        }

        .info-card-title i {
            margin-right: 8px;
        }

        .info-row {
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .info-label {
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            padding: 0 30px 30px;
        }

        .action-button {
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
        }

        .action-button i {
            margin-right: 8px;
        }

        .primary-action {
            background-color: #000;
            color: white;
            border: none;
        }

        .primary-action:hover {
            background-color: #333;
        }

        .secondary-action {
            background-color: white;
            color: #000;
            border: 1px solid #000;
        }

        .secondary-action:hover {
            background-color: #f5f5f5;
        }

        .tracking-info {
            background-color: #f0f7ff;
            border-radius: 5px;
            padding: 15px 20px;
            margin-top: 20px;
            border-left: 3px solid #0066cc;
        }

        .tracking-info p {
            margin: 5px 0;
            font-size: 0.95rem;
        }

        .delivery-timeline {
            margin: 25px 0 15px;
        }

        .timeline-track {
            position: relative;
            height: 4px;
            background-color: #ddd;
            margin: 40px 0 60px;
            border-radius: 4px;
        }

        .timeline-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background-color: #000;
            border-radius: 4px;
        }

        .timeline-steps {
            position: relative;
            display: flex;
            justify-content: space-between;
        }

        .timeline-step {
            position: absolute;
            top: -34px;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 120px;
        }

        .step-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #ddd;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7rem;
            border: 2px solid white;
            z-index: 1;
        }

        .step-icon.completed {
            background-color: #000;
        }

        .step-icon.current {
            background-color: #000;
            animation: pulse 1.5s infinite;
        }

        .step-text {
            font-size: 0.85rem;
            text-align: center;
            color: #666;
            font-weight: 500;
        }

        .step-date {
            font-size: 0.75rem;
            color: #999;
            margin-top: 3px;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.4);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(0, 0, 0, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
            }
        }

        @media (max-width: 768px) {

            .shipping-details,
            .payment-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .order-details-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-meta {
                align-items: flex-start;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .action-button {
                width: 100%;
            }

            .order-items-table {
                display: block;
                overflow-x: auto;
            }

            .timeline-step {
                width: 90px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .order-details-section {
                padding: 20px;
            }

            .timeline-track {
                margin: 30px 0 80px;
            }

            .timeline-step {
                width: 70px;
            }

            .step-text {
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">Order Details</h1>

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

        <div class="back-link">
            <a href="orders.php">
                <i class="fas fa-arrow-left"></i> Back to My Orders
            </a>
        </div>

        <div class="order-details-container">
            <div class="order-details-header">
                <h2 class="order-details-title">
                    Order #<?= htmlspecialchars($order->order_id) ?>
                    <span class="status-badge status-<?= strtolower($order->orders_status) ?>">
                        <?= htmlspecialchars($order->orders_status) ?>
                    </span>
                </h2>

                <div class="order-meta">
                    <div class="order-date-info">Placed on: <?= $order_date ?></div>
                    <div class="order-id-info">Order ID: <?= htmlspecialchars($order->order_id) ?></div>
                </div>
            </div>

            <div class="order-details-section">
                <h3 class="section-title">
                    <i class="fas fa-box-open"></i> Order Items
                </h3>

                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th width="70">Item</th>
                            <th>Product</th>
                            <th width="100">Price</th>
                            <th width="100">Quantity</th>
                            <th width="100">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td>
                                    <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="product-image">
                                </td>
                                <td>
                                    <div class="product-details">
                                        <div class="product-name"><?= htmlspecialchars($item->product_name) ?></div>
                                        <?php if (!empty($item->size)): ?>
                                            <div class="product-size">Size: <?= htmlspecialchars($item->size) ?></div>
                                        <?php endif; ?>
                                        <div class="product-id">Product ID: <?= htmlspecialchars($item->product_id) ?></div>
                                    </div>
                                </td>
                                <td class="item-price">RM <?= number_format($item->unit_price, 2) ?></td>
                                <td class="item-quantity"><?= $item->quantity ?></td>
                                <td class="item-total">RM <?= number_format($item->unit_price * $item->quantity, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="order-summary">
                    <div class="summary-row">
                        <div>Subtotal</div>
                        <div>RM <?= number_format($subtotal, 2) ?></div>
                    </div>
                    <div class="summary-row">
                        <div>Shipping Fee</div>
                        <div>RM <?= number_format($delivery_fee, 2) ?></div>
                    </div>
                    <div class="summary-row">
                        <div>Tax</div>
                        <div>RM <?= number_format($tax, 2) ?></div>
                    </div>
                    <?php if ($discount != 0): ?>
                        <div class="summary-row">
                            <div>Discount</div>
                            <div>-RM <?= number_format(abs($discount), 2) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <div>Total</div>
                        <div>RM <?= number_format($total, 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="order-details-section">
                <h3 class="section-title">
                    <i class="fas fa-truck"></i> Shipment Details
                </h3>

                <?php if (in_array($order->delivery_status, ['Processing', 'Out for Delivery', 'Delivered'])): ?>
                    <div class="delivery-timeline">
                        <?php
                        // Define timeline steps and their states
                        $steps = [
                            'Processing' => [
                                'label' => 'Processing',
                                'position' => 0,
                                'completed' => in_array($order->delivery_status, ['Processing', 'Out for Delivery', 'Delivered']),
                                'current' => $order->delivery_status === 'Processing',
                                'date' => $order_date
                            ],
                            'Shipped' => [
                                'label' => 'Shipped',
                                'position' => 50,
                                'completed' => in_array($order->delivery_status, ['Out for Delivery', 'Delivered']),
                                'current' => false,
                                'date' => $order->delivery_status !== 'Processing' ? date('M d, Y', strtotime($order_date . ' +1 day')) : ''
                            ],
                            'Delivered' => [
                                'label' => 'Delivered',
                                'position' => 100,
                                'completed' => $order->delivery_status === 'Delivered',
                                'current' => $order->delivery_status === 'Delivered',
                                'date' => $order->delivery_status === 'Delivered' ? $delivered_date : $estimated_date
                            ]
                        ];

                        // Determine progress width based on current status
                        $progress_width = 0;
                        foreach ($steps as $status => $step) {
                            if ($order->delivery_status === $status) {
                                $progress_width = $step['position'];
                                break;
                            } elseif ($order->delivery_status === 'Delivered') {
                                $progress_width = 100;
                                break;
                            }
                        }
                        ?>

                        <div class="timeline-track">
                            <div class="timeline-progress" style="width: <?= $progress_width ?>%;"></div>
                            <div class="timeline-steps">
                                <?php foreach ($steps as $status => $step): ?>
                                    <div class="timeline-step" style="left: <?= $step['position'] ?>%;">
                                        <div class="step-icon <?= $step['completed'] ? 'completed' : '' ?> <?= $step['current'] ? 'current' : '' ?>">
                                            <i class="fas fa-<?= $step['completed'] ? 'check' : 'circle' ?>"></i>
                                        </div>
                                        <div class="step-text"><?= $step['label'] ?></div>
                                        <?php if (!empty($step['date'])): ?>
                                            <div class="step-date"><?= $step['date'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>

                    <div class="tracking-info">
                        <p>
                            <strong>Estimated Delivery:</strong>
                            <?= $estimated_date ?>
                        </p>
                        <?php if ($order->delivery_status === 'Delivered'): ?>
                            <p>
                                <strong>Delivered On:</strong>
                                <?= $delivered_date ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($order->delivery_status === 'Failed'): ?>
                    <div class="tracking-info" style="background-color: #fff4f4; border-left-color: #d32f2f;">
                        <p>
                            <i class="fas fa-exclamation-circle" style="color: #d32f2f;"></i>
                            <strong>Delivery Failed</strong>
                        </p>
                        <p>There was an issue with your delivery. Please contact our customer support for assistance.</p>
                    </div>
                <?php else: ?>
                    <p>Delivery information is not available at this time.</p>
                <?php endif; ?>

                <div class="shipping-details">
                    <div class="info-card">
                        <h4 class="info-card-title">
                            <i class="fas fa-map-marker-alt"></i> Shipping Address
                        </h4>
                        <div class="info-content">
                            <p><?= htmlspecialchars($address->recipient_name) ?></p>
                            <p><?= htmlspecialchars($address->phone) ?></p>
                            <p><?= htmlspecialchars($address->address_line1) ?></p>
                            <?php if (!empty($address->address_line2)): ?>
                                <p><?= htmlspecialchars($address->address_line2) ?></p>
                            <?php endif; ?>
                            <p>
                                <?= htmlspecialchars($address->city) ?>,
                                <?= htmlspecialchars($address->state) ?>,
                                <?= htmlspecialchars($address->post_code) ?>
                            </p>
                            <p><?= htmlspecialchars($address->country) ?></p>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 class="info-card-title">
                            <i class="fas fa-shipping-fast"></i> Delivery Method
                        </h4>
                        <div class="info-content">
                            <div class="info-row">
                                <div class="info-label">Delivery Type</div>
                                <div class="info-value">Standard Delivery</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Shipping Fee</div>
                                <div class="info-value">RM <?= number_format($delivery_fee, 2) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-<?= strtolower($order->delivery_status) ?>">
                                        <?= $order->delivery_status ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-details-section">
                <h3 class="section-title">
                    <i class="fas fa-credit-card"></i> Payment Information
                </h3>

                <div class="payment-details">
                    <div class="info-card">
                        <h4 class="info-card-title">
                            <i class="fas fa-money-bill-wave"></i> Payment Method
                        </h4>
                        <div class="info-content">
                            <div class="info-row">
                                <div class="info-label">Method</div>
                                <div class="info-value">
                                    <?php if ($order->payment_method === 'Credit Card'): ?>
                                        <i class="fas fa-credit-card"></i> Credit Card
                                    <?php elseif ($order->payment_method === 'PayPal'): ?>
                                        <i class="fab fa-paypal"></i> PayPal
                                    <?php elseif ($order->payment_method === 'Bank Transfer'): ?>
                                        <i class="fas fa-university"></i> Bank Transfer
                                    <?php else: ?>
                                        <i class="fas fa-money-bill-wave"></i> <?= $order->payment_method ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Payment Date</div>
                                <div class="info-value"><?= $payment_date ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <?php
                                    $status_class = '';
                                    switch ($order->payment_status) {
                                        case 'Completed':
                                            $status_class = 'status-delivered';
                                            break;
                                        case 'Pending':
                                            $status_class = 'status-pending';
                                            break;
                                        case 'Failed':
                                            $status_class = 'status-cancelled';
                                            break;
                                        case 'Refunded':
                                            $status_class = 'status-processing';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $order->payment_status ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 class="info-card-title">
                            <i class="fas fa-file-invoice-dollar"></i> Billing Summary
                        </h4>
                        <div class="info-content">
                            <div class="info-row">
                                <div class="info-label">Subtotal</div>
                                <div class="info-value">RM <?= number_format($subtotal, 2) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Shipping Fee</div>
                                <div class="info-value">RM <?= number_format($delivery_fee, 2) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Tax (6%)</div>
                                <div class="info-value">RM <?= number_format($tax, 2) ?></div>
                            </div>
                            <?php if ($discount != 0): ?>
                                <div class="info-row">
                                    <div class="info-label">Discount</div>
                                    <div class="info-value">-RM <?= number_format(abs($discount), 2) ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="info-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                <div class="info-label" style="font-weight: 600;">Total Amount</div>
                                <div class="info-value" style="font-weight: 600;">RM <?= number_format($total, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <?php if (in_array($order->orders_status, ['Pending', 'Processing'])): ?>
                    <form method="post" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                        <input type="hidden" name="cancel_order" value="1">
                        <button type="submit" class="action-button primary-action">
                            <i class="fas fa-times-circle"></i> Cancel Order
                        </button>
                    </form>
                <?php endif; ?>
                <a href="orders.php" class="action-button secondary-action">
                    <i class="fas fa-list"></i> Back to Orders
                </a>
            </div>
        </div>
    </div>

    <?php include('../footer.php'); ?>
</body>

</html>