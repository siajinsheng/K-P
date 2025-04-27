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

// Initialize variables
$error_message = temp('error');
$success_message = temp('success');
$info_message = temp('info');

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

try {
    // Build the query for orders - order by most recent by default
    $query = "
        SELECT o.order_id, o.order_date, o.orders_status, o.order_total,
               p.payment_method, p.payment_status,
               COUNT(od.product_id) as item_count
        FROM orders o
        LEFT JOIN payment p ON o.order_id = p.order_id
        LEFT JOIN order_details od ON o.order_id = od.order_id
        WHERE o.user_id = :user_id
        GROUP BY o.order_id, o.order_date, o.orders_status, o.order_total, p.payment_method, p.payment_status
        ORDER BY o.order_date DESC
    ";
    
    // Count total records for pagination
    $count_query = "
        SELECT COUNT(*) FROM (
            SELECT o.order_id
            FROM orders o
            WHERE o.user_id = :user_id
            GROUP BY o.order_id
        ) as temp
    ";
    
    $count_stmt = $_db->prepare($count_query);
    $count_stmt->execute([':user_id' => $user_id]);
    $total_records = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $items_per_page);
    
    // Adjust current page if out of bounds
    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    
    // Add pagination
    $query .= " LIMIT :offset, :per_page";
    $params = [
        ':user_id' => $user_id,
        ':offset' => $offset,
        ':per_page' => $items_per_page
    ];
    
    // Execute the query
    $stmt = $_db->prepare($query);
    
    // PDO doesn't support binding LIMIT parameters directly, so we need to do this
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Get total orders count
    $stmt = $_db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_orders = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $total_pages = 0;
    $current_page = 1;
    $total_orders = 0;
    $error_message = "An error occurred while retrieving your orders.";
}

// Helper function to build URLs with parameters
function build_url($params = []) {
    $current_params = $_GET;
    $new_params = array_merge($current_params, $params);
    return '?' . http_build_query($new_params);
}
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
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">My Orders</h1>
        
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
            <a href="profile.php#order-history">
                <i class="fas fa-arrow-left"></i> Back to My Profile
            </a>
        </div>
        
        <div class="orders-container">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>You haven't placed any orders yet.</p>
                    <a href="products.php" class="btn secondary-btn">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="order-summary">
                    <p>Showing all orders (<?= $total_records ?>)</p>
                </div>
                
                <!-- Orders List -->
                <div class="orders-list">
                    <div class="order-header">
                        <div class="order-col">Order ID</div>
                        <div class="order-col">Date</div>
                        <div class="order-col">Items</div>
                        <div class="order-col">Total</div>
                        <div class="order-col">Status</div>
                        <div class="order-col">Action</div>
                    </div>
                    
                    <?php foreach ($orders as $order): ?>
                        <div class="order-row">
                            <div class="order-col order-id">
                                <span class="mobile-label">Order ID:</span>
                                <?= htmlspecialchars($order->order_id) ?>
                            </div>
                            <div class="order-col order-date">
                                <span class="mobile-label">Date:</span>
                                <?= date('M d, Y', strtotime($order->order_date)) ?>
                                <span class="order-time"><?= date('h:i A', strtotime($order->order_date)) ?></span>
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
                                <?php if (!empty($order->payment_status) && $order->payment_status !== 'Completed'): ?>
                                    <span class="payment-status">
                                        <?= htmlspecialchars($order->payment_status) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="order-col order-action">
                                <a href="order_details.php?id=<?= $order->order_id ?>" class="btn outline-btn sm">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="orders.php<?= build_url(['page' => $current_page - 1]) ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php
                            $max_visible_pages = 5;
                            $start_page = max(1, min($current_page - floor($max_visible_pages / 2), $total_pages - $max_visible_pages + 1));
                            $end_page = min($start_page + $max_visible_pages - 1, $total_pages);
                            
                            // Display first page if not in visible range
                            if ($start_page > 1) {
                                echo '<a href="orders.php' . build_url(['page' => 1]) . '" class="page-number">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="page-ellipsis">...</span>';
                                }
                            }
                            
                            // Display visible page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $current_page) {
                                    echo '<span class="page-number active">' . $i . '</span>';
                                } else {
                                    echo '<a href="orders.php' . build_url(['page' => $i]) . '" class="page-number">' . $i . '</a>';
                                }
                            }
                            
                            // Display last page if not in visible range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="page-ellipsis">...</span>';
                                }
                                echo '<a href="orders.php' . build_url(['page' => $total_pages]) . '" class="page-number">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="orders.php<?= build_url(['page' => $current_page + 1]) ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>