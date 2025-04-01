<?php
$_title = 'Order Management';
require '../../_base.php';
auth(0,1);
include "header.php";

// Search and Sort
$searchTerm = req('searchTerm', '');
$sortField = req('sort', 'order_date');
$sortDirection = req('dir', 'desc');

// Pagination
$limit = 5;
$page = req('page', 1);
$offset = ($page - 1) * $limit;

// Valid sort fields for injection prevention
$validSortFields = [
    'order_id',
    'order_date',
    'cus_name',
    'order_total',
    'orders_status',
    'payment_status'
];

$sortField = in_array($sortField, $validSortFields) ? $sortField : 'order_date';

// Count total orders
$countQuery = "SELECT COUNT(*) FROM orders o
               JOIN customer c ON o.cus_id = c.cus_id
               JOIN payment p ON o.order_id = p.order_id
               WHERE o.order_id LIKE ?
               OR c.cus_name LIKE ?
               OR o.order_date LIKE ?
               OR o.order_total LIKE ?
               OR o.orders_status LIKE ?
               OR p.payment_status LIKE ?";

$countStmt = $_db->prepare($countQuery);
$searchParam = "%$searchTerm%";
$countStmt->execute([
    $searchParam, $searchParam, $searchParam,
    $searchParam, $searchParam, $searchParam
]);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Main query
$query = "SELECT o.order_id, o.order_date, od.quantity, o.orders_status,
                 c.cus_name, c.cus_Email,
                 p.total_amount, p.payment_status, p.payment_date
          FROM orders o
          JOIN customer c ON o.cus_id = c.cus_id
          JOIN payment p ON o.order_id = p.order_id
          JOIN order_details od ON o.order_id = od.order_id
          WHERE o.order_id LIKE :search
             OR c.cus_name LIKE :search
             OR o.order_date LIKE :search
             OR o.order_total LIKE :search
             OR o.orders_status LIKE :search
             OR p.payment_status LIKE :search
          GROUP BY o.order_id
          ORDER BY $sortField $sortDirection
          LIMIT :limit OFFSET :offset";

$stmt = $_db->prepare($query);
$stmt->bindValue(':search', "%$searchTerm%", PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="../css/orders.css">
</head>
<body>
    <h1 style="text-align: center;">Order Management</h1>
    <form method="get" class="search-form">
        <input type="text" name="searchTerm" value="<?= htmlspecialchars($searchTerm) ?>" 
               placeholder="Search orders...">
        <button type="submit">Search</button>
    </form>

    <div class="pagination-info">
        Showing <?= count($orders) ?> of <?= $totalOrders ?> orders
    </div>

    <table>
        <thead>
            <tr>
                <th><a href="?sort=order_id&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>&searchTerm=<?= urlencode($searchTerm) ?>">Order ID</a></th>
                <th><a href="?sort=order_date&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>&searchTerm=<?= urlencode($searchTerm) ?>">Date</a></th>
                <th>Customer</th>
                <th>Quantity</th>
                <th><a href="?sort=order_total&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>&searchTerm=<?= urlencode($searchTerm) ?>">Total</a></th>
                <th><a href="?sort=orders_status&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>&searchTerm=<?= urlencode($searchTerm) ?>">Order Status</a></th>
                <th><a href="?sort=payment_status&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>&searchTerm=<?= urlencode($searchTerm) ?>">Payment Status</a></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No orders found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['order_date']) ?></td>
                        <td>
                            <?= htmlspecialchars($order['cus_name']) ?><br>
                            <small><?= htmlspecialchars($order['cus_Email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($order['quantity']) ?></td>
                        <td>RM <?= number_format($order['total_amount'], 2) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($order['orders_status']) ?>">
                                <?= htmlspecialchars($order['orders_status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="payment-badge <?= strtolower($order['payment_status']) ?>">
                                <?= htmlspecialchars($order['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_order_details.php?id=<?= $order['order_id'] ?>" 
                               class="btn view-btn">
                                View
                            </a>
                            <?php if ($order['orders_status'] === 'Pending'): ?>
                                <button class="btn process-btn" 
                                        onclick="updateOrderStatus('<?= $order['order_id'] ?>', 'Processing')">
                                    Process
                                </button>
                            <?php elseif ($order['orders_status'] === 'Processing'): ?>
                                <button class="btn ship-btn" 
                                        onclick="updateOrderStatus('<?= $order['order_id'] ?>', 'Shipped')">
                                    Ship
                                </button>
                            <?php elseif ($order['orders_status'] === 'Shipped'): ?>
                                <button class="btn deliver-btn" 
                                        onclick="updateOrderStatus('<?= $order['order_id'] ?>', 'Delivered')">
                                    Deliver
                                </button>
                            <?php endif; ?>
                            <?php if ($order['orders_status'] !== 'Cancelled' && $order['orders_status'] !== 'Delivered'): ?>
                                <button class="btn cancel-btn" 
                                        onclick="updateOrderStatus('<?= $order['order_id'] ?>', 'Cancelled')">
                                    Cancel
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>">&laquo; First</a>
            <a href="?page=<?= $page - 1 ?>&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>">Previous</a>
        <?php endif; ?>

        <?php 
        // Show pagination links with limit
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        for ($i = $startPage; $i <= $endPage; $i++): 
        ?>
            <a href="?page=<?= $i ?>&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>" 
               <?= $i == $page ? 'class="active"' : '' ?>>
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>">Next</a>
            <a href="?page=<?= $totalPages ?>&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>">Last &raquo;</a>
        <?php endif; ?>
    </div>

    <script>
    function updateOrderStatus(orderId, newStatus) {
        if (confirm('Update this order to ' + newStatus + '?')) {
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
                    alert('Error updating order: ' + data.message);
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