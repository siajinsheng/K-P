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
$page_title = "My Orders";

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filter by status if specified
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$status_filter_sql = '';
$status_params = [];

if (!empty($status_filter) && in_array($status_filter, ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'])) {
    $status_filter_sql = " AND o.orders_status = ?";
    $status_params[] = $status_filter;
}

try {
    // Count total orders for pagination
    $count_sql = "
        SELECT COUNT(*) 
        FROM orders o
        WHERE o.user_id = ?" . $status_filter_sql;
    
    $count_params = [$user_id];
    if (!empty($status_params)) {
        $count_params = array_merge($count_params, $status_params);
    }
    
    $stm = $_db->prepare($count_sql);
    $stm->execute($count_params);
    $total_orders = $stm->fetchColumn();
    
    // Calculate total pages
    $total_pages = ceil($total_orders / $items_per_page);
    
    // Ensure current page is within valid range
    if ($current_page < 1) {
        $current_page = 1;
    } else if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    
    // Get orders with pagination
    $sql = "
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_details od WHERE od.order_id = o.order_id) AS item_count
        FROM orders o
        WHERE o.user_id = ?" . $status_filter_sql . "
        ORDER BY o.order_date DESC
        LIMIT ? OFFSET ?";
    
    $params = [$user_id];
    if (!empty($status_params)) {
        $params = array_merge($params, $status_params);
    }
    $params[] = $items_per_page;
    $params[] = $offset;
    
    $stm = $_db->prepare($sql);
    $stm->execute($params);
    $orders = $stm->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $error = "An error occurred while retrieving your orders.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="page-header">
            <h1><?= $page_title ?></h1>
            <p>View and track all your orders</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <div class="orders-filters">
            <div class="filter-options">
                <a href="orders.php" class="filter-option <?= empty($status_filter) ? 'active' : '' ?>">All Orders</a>
                <a href="orders.php?status=Pending" class="filter-option <?= $status_filter === 'Pending' ? 'active' : '' ?>">Pending</a>
                <a href="orders.php?status=Processing" class="filter-option <?= $status_filter === 'Processing' ? 'active' : '' ?>">Processing</a>
                <a href="orders.php?status=Shipped" class="filter-option <?= $status_filter === 'Shipped' ? 'active' : '' ?>">Shipped</a>
                <a href="orders.php?status=Delivered" class="filter-option <?= $status_filter === 'Delivered' ? 'active' : '' ?>">Delivered</a>
                <a href="orders.php?status=Cancelled" class="filter-option <?= $status_filter === 'Cancelled' ? 'active' : '' ?>">Cancelled</a>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <i class="fas fa-shopping-bag empty-icon"></i>
                <h2>No orders found</h2>
                <?php if (!empty($status_filter)): ?>
                    <p>You don't have any <?= strtolower($status_filter) ?> orders.</p>
                    <a href="orders.php" class="btn secondary-btn">View All Orders</a>
                <?php else: ?>
                    <p>You haven't placed any orders yet.</p>
                    <a href="products.php" class="btn primary-btn">Start Shopping</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <div class="order-header">
                    <div class="order-col">Order #</div>
                    <div class="order-col">Date</div>
                    <div class="order-col">Items</div>
                    <div class="order-col">Total</div>
                    <div class="order-col">Status</div>
                    <div class="order-col">Action</div>
                </div>
                
                <?php foreach ($orders as $order): ?>
                    <div class="order-row">
                        <div class="order-col order-id">
                            <span class="mobile-label">Order #:</span>
                            <a href="order-details.php?id=<?= $order->order_id ?>" class="order-link">
                                <?= htmlspecialchars($order->order_id) ?>
                            </a>
                        </div>
                        <div class="order-col order-date">
                            <span class="mobile-label">Date:</span>
                            <?= date('M d, Y', strtotime($order->order_date)) ?>
                        </div>
                        <div class="order-col order-items">
                            <span class="mobile-label">Items:</span>
                            <?= $order->item_count ?> item<?= $order->item_count > 1 ? 's' : '' ?>
                        </div>
                        <div class="order-col order-total">
                            <span class="mobile-label">Total:</span>
                            RM <?= number_format($order->order_total, 2) ?>
                        </div>
                        <div class="order-col order-status">
                            <span class="mobile-label">Status:</span>
                            <span class="status-badge status-<?= strtolower($order->orders_status) ?>">
                                <?= htmlspecialchars($order->orders_status) ?>
                            </span>
                        </div>
                        <div class="order-col order-action">
                            <a href="order-details.php?id=<?= $order->order_id ?>" class="btn outline-btn sm">View Details</a>
                            
                            <?php if ($order->orders_status === 'Pending'): ?>
                                <a href="cancel-order.php?id=<?= $order->order_id ?>" 
                                   class="btn danger-btn sm"
                                   onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
                                    Cancel Order
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order->orders_status === 'Delivered'): ?>
                                <a href="write-review.php?order=<?= $order->order_id ?>" class="btn secondary-btn sm">
                                    Write Review
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="orders.php?page=1<?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="orders.php?page=<?= $current_page - 1 ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    
                    if ($end_page - $start_page < 4) {
                        $start_page = max(1, $end_page - 4);
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="orders.php?page=<?= $i ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>" 
                           class="page-link <?= $i === $current_page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="orders.php?page=<?= $current_page + 1 ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="orders.php?page=<?= $total_pages ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>