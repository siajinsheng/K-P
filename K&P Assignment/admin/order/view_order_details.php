<?php
$_title = 'Order Details';
require '../../_base.php';
auth('admin', 'staff'); // Only admins and staff can access this page
include "header.php";

// Get order ID from URL
$order_id = req('id');

if (empty($order_id)) {
    temp('error', 'Order ID is required');
    redirect('orders.php');
}

// Fetch order details
try {
    // Get main order information
    $orderQuery = "SELECT 
        o.order_id, 
        o.order_date, 
        o.orders_status, 
        o.order_subtotal, 
        o.order_total,
        u.user_id,
        u.user_name,
        u.user_Email,
        u.user_phone,
        d.delivery_id,
        d.delivery_fee,
        d.delivery_status,
        d.estimated_date,
        d.delivered_date,
        p.payment_id,
        p.payment_method,
        p.payment_status,
        p.payment_date,
        p.tax,
        p.discount
    FROM orders o
    JOIN user u ON o.user_id = u.user_id
    JOIN payment p ON o.order_id = p.order_id
    JOIN delivery d ON o.delivery_id = d.delivery_id
    WHERE o.order_id = ?";

    $orderStmt = $_db->prepare($orderQuery);
    $orderStmt->execute([$order_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        temp('error', 'Order not found');
        redirect('orders.php');
    }

    // Get delivery address information
    $addressQuery = "SELECT 
        a.* 
    FROM address a
    JOIN delivery d ON a.address_id = d.address_id
    JOIN orders o ON d.delivery_id = o.delivery_id
    WHERE o.order_id = ?";

    $addressStmt = $_db->prepare($addressQuery);
    $addressStmt->execute([$order_id]);
    $address = $addressStmt->fetch(PDO::FETCH_ASSOC);

    // Get order items
    $itemsQuery = "SELECT 
        od.order_id,
        od.product_id,
        od.quantity,
        od.unit_price,
        p.product_name,
        p.product_pic1
    FROM order_details od
    JOIN product p ON od.product_id = p.product_id
    WHERE od.order_id = ?";

    $itemsStmt = $_db->prepare($itemsQuery);
    $itemsStmt->execute([$order_id]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving order details');
    redirect('orders.php');
}

// Format date and time
function formatDateTime($dateTime) {
    if (!$dateTime) return 'N/A';
    return date('M d, Y h:i A', strtotime($dateTime));
}

// Format date only
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// Get status badge class
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'pending': return 'pending';
        case 'processing': return 'processing';
        case 'shipped': return 'shipped';
        case 'delivered': return 'delivered';
        case 'cancelled': return 'cancelled';
        case 'completed': return 'delivered';
        case 'failed': return 'cancelled';
        case 'refunded': return 'cancelled';
        default: return '';
    }
}

// Get timeline progress percentage based on order status
function getOrderProgressPercentage($status) {
    switch ($status) {
        case 'Pending': return 20;
        case 'Processing': return 40;
        case 'Shipped': return 60;
        case 'Delivered': return 100;
        case 'Cancelled': return 100; // Full width but will be red
        default: return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/orders.css">
    <style>
        .timeline-track {
            height: 4px;
            background-color: #e5e7eb;
            position: relative;
            margin: 0 10px;
        }
        
        .timeline-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background-color: #4338ca;
        }
        
        .timeline-progress.cancelled {
            background-color: #ef4444;
        }
        
        .timeline-step {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid #e5e7eb;
            z-index: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .timeline-step.active {
            border-color: #4338ca;
            background-color: #4338ca;
            color: white;
        }
        
        .timeline-step.cancelled {
            border-color: #ef4444;
            background-color: #ef4444;
            color: white;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header with back button -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <a href="orders.php" class="text-indigo-600 hover:text-indigo-800 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                </a>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Order #<?= htmlspecialchars($order_id) ?></h1>
            <div>
                <button onclick="window.print()" class="btn-secondary py-2 px-4 text-sm">
                    <i class="fas fa-print mr-2"></i> Print Order
                </button>
            </div>
        </div>

        <!-- Order status timeline -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Order Status</h2>
            
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-gray-600">
                    <?= formatDateTime($order['order_date']) ?>
                </div>
                <div class="text-sm font-medium text-right text-gray-600">
                    <?= $order['delivered_date'] ? formatDateTime($order['delivered_date']) : 'Estimated: ' . formatDate($order['estimated_date']) ?>
                </div>
            </div>
            
            <div class="flex items-center justify-between mb-6">
                <?php 
                $progress = getOrderProgressPercentage($order['orders_status']);
                $isCancelled = $order['orders_status'] === 'Cancelled';
                $progressClass = $isCancelled ? 'cancelled' : '';
                ?>
                
                <!-- Timeline steps -->
                <div class="timeline-step <?= $progress >= 20 ? 'active' : '' ?> <?= $isCancelled ? 'cancelled' : '' ?>">
                    <i class="fas fa-shopping-bag text-xs"></i>
                </div>
                
                <div class="timeline-track flex-grow">
                    <div class="timeline-progress <?= $progressClass ?>" style="width: <?= $progress ?>%"></div>
                </div>
                
                <div class="timeline-step <?= $progress >= 40 ? 'active' : '' ?> <?= $isCancelled ? 'cancelled' : '' ?>">
                    <i class="fas fa-cog text-xs"></i>
                </div>
                
                <div class="timeline-track flex-grow">
                    <div class="timeline-progress <?= $progressClass ?>" style="width: <?= $progress >= 40 ? $progress - 20 : 0 ?>%"></div>
                </div>
                
                <div class="timeline-step <?= $progress >= 60 ? 'active' : '' ?> <?= $isCancelled ? 'cancelled' : '' ?>">
                    <i class="fas fa-truck text-xs"></i>
                </div>
                
                <div class="timeline-track flex-grow">
                    <div class="timeline-progress <?= $progressClass ?>" style="width: <?= $progress >= 60 ? $progress - 40 : 0 ?>%"></div>
                </div>
                
                <div class="timeline-step <?= $progress >= 100 ? 'active' : '' ?> <?= $isCancelled ? 'cancelled' : '' ?>">
                    <i class="fas <?= $isCancelled ? 'fa-times' : 'fa-check' ?> text-xs"></i>
                </div>
            </div>
            
            <!-- Status labels -->
            <div class="flex justify-between text-sm">
                <div class="text-center">
                    <div class="font-semibold">Ordered</div>
                    <div class="text-xs text-gray-500"><?= date('M d', strtotime($order['order_date'])) ?></div>
                </div>
                <div class="text-center">
                    <div class="font-semibold">Processing</div>
                </div>
                <div class="text-center">
                    <div class="font-semibold">Shipped</div>
                </div>
                <div class="text-center">
                    <div class="font-semibold"><?= $isCancelled ? 'Cancelled' : 'Delivered' ?></div>
                    <?php if ($order['delivered_date'] && !$isCancelled): ?>
                        <div class="text-xs text-gray-500"><?= date('M d', strtotime($order['delivered_date'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$isCancelled && $order['orders_status'] !== 'Delivered'): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php if ($order['orders_status'] === 'Pending'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Processing')" class="btn-secondary py-1 px-4 text-sm">
                        Process Order
                    </button>
                <?php elseif ($order['orders_status'] === 'Processing'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Shipped')" class="btn-secondary py-1 px-4 text-sm">
                        Mark as Shipped
                    </button>
                <?php elseif ($order['orders_status'] === 'Shipped'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Delivered')" class="btn-secondary py-1 px-4 text-sm">
                        Mark as Delivered
                    </button>
                <?php endif; ?>
                
                <button onclick="updateStatus('<?= $order_id ?>', 'Cancelled')" class="btn-danger py-1 px-4 text-sm">
                    Cancel Order
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Information and Customer Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Order Info -->
            <div class="card p-6">
                <h2 class="text-lg font-semibold mb-4">Order Information</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order ID:</span>
                        <span class="font-medium"><?= htmlspecialchars($order_id) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date Placed:</span>
                        <span><?= formatDateTime($order['order_date']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="status-badge <?= getStatusBadgeClass($order['orders_status']) ?>">
                            <?= htmlspecialchars($order['orders_status']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Status:</span>
                        <span class="payment-badge <?= getStatusBadgeClass($order['payment_status']) ?>">
                            <?= htmlspecialchars($order['payment_status']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method:</span>
                        <span><?= htmlspecialchars($order['payment_method']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Date:</span>
                        <span><?= formatDateTime($order['payment_date']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Customer Details -->
            <div class="card p-6">
                <h2 class="text-lg font-semibold mb-4">Customer Details</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-medium"><?= htmlspecialchars($order['user_name']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Email:</span>
                        <span><?= htmlspecialchars($order['user_Email']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phone:</span>
                        <span><?= htmlspecialchars($order['user_phone']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Customer ID:</span>
                        <span><?= htmlspecialchars($order['user_id']) ?></span>
                    </div>
                    
                    <?php if ($address): ?>
                    <div class="pt-2 mt-2 border-t border-gray-200">
                        <h3 class="font-medium mb-2">Shipping Address</h3>
                        <p>
                            <?= htmlspecialchars($address['street']) ?><br>
                            <?= htmlspecialchars($address['city']) ?>, 
                            <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['post_code']) ?><br>
                            <?= htmlspecialchars($address['country']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Order Items</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-center">Quantity</th>
                            <th class="px-4 py-2 text-right">Unit Price</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <tr class="border-b">
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <?php if ($item['product_pic1']): ?>
                                    <img src="../../img/<?= htmlspecialchars($item['product_pic1']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="product-image mr-3">
                                    <?php else: ?>
                                    <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center mr-3">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($item['product_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center"><?= htmlspecialchars($item['quantity']) ?></td>
                            <td class="px-4 py-4 text-right">RM <?= number_format($item['unit_price'], 2) ?></td>
                            <td class="px-4 py-4 text-right font-medium">
                                RM <?= number_format($item['quantity'] * $item['unit_price'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Order Summary -->
            <div class="mt-6 border-t border-gray-200 pt-4">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Subtotal:</span>
                    <span>RM <?= number_format($order['order_subtotal'], 2) ?></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Shipping Fee:</span>
                    <span>RM <?= number_format($order['delivery_fee'], 2) ?></span>
                </div>
                <?php if ($order['tax'] > 0): ?>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Tax:</span>
                    <span>RM <?= number_format($order['tax'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['discount'] > 0): ?>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Discount:</span>
                    <span class="text-green-600">-RM <?= number_format($order['discount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between mt-3 pt-3 border-t border-gray-200">
                    <span class="text-lg font-bold">Total:</span>
                    <span class="text-lg font-bold">RM <?= number_format($order['order_total'], 2) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Delivery Information -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Delivery Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-gray-600 mb-2">Delivery Status</p>
                    <p class="font-medium">
                        <span class="status-badge <?= getStatusBadgeClass($order['delivery_status']) ?>">
                            <?= htmlspecialchars($order['delivery_status']) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-gray-600 mb-2">Estimated Delivery Date</p>
                    <p class="font-medium"><?= formatDate($order['estimated_date']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600 mb-2">Delivered Date</p>
                    <p class="font-medium"><?= $order['delivered_date'] ? formatDate($order['delivered_date']) : 'N/A' ?></p>
                </div>
            </div>
        </div>
        
        <!-- Action buttons -->
        <div class="flex justify-between mt-8">
            <a href="orders.php" class="btn-secondary py-2 px-4">
                Back to Orders
            </a>
            
            <?php if ($order['orders_status'] !== 'Cancelled' && $order['orders_status'] !== 'Delivered'): ?>
            <div class="flex gap-3">
                <button onclick="updateStatus('<?= $order_id ?>', 'Cancelled')" class="btn-danger py-2 px-4">
                    Cancel Order
                </button>
                
                <?php if ($order['orders_status'] === 'Pending'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Processing')" class="btn-primary py-2 px-4">
                        Process Order
                    </button>
                <?php elseif ($order['orders_status'] === 'Processing'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Shipped')" class="btn-primary py-2 px-4">
                        Mark as Shipped
                    </button>
                <?php elseif ($order['orders_status'] === 'Shipped'): ?>
                    <button onclick="updateStatus('<?= $order_id ?>', 'Delivered')" class="btn-primary py-2 px-4">
                        Mark as Delivered
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function updateStatus(orderId, newStatus) {
        if (confirm(`Update order #${orderId} to ${newStatus}?`)) {
            fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    new_status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the order.');
            });
        }
    }
    </script>
</body>
</html>