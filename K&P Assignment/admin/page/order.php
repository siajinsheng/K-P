<?php
$_title = 'Order';
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
    'orders_id',
    'order_date',
    'cus_name',
    'total_amount',
    'orders_status',
    'payment_status'
];

$sortField = in_array($sortField, $validSortFields) ? $sortField : 'order_date';

// Count total orders
$countQuery = "SELECT COUNT(*) FROM orders o
               JOIN customer c ON o.cus_id = c.cus_id
               JOIN payment p ON o.orders_id = p.orders_id
               WHERE o.orders_id LIKE ?
               OR c.cus_name LIKE ?
               OR o.order_date LIKE ?
               OR p.total_amount LIKE ?
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
$query = "SELECT o.orders_id, o.order_date, o.quantity, o.orders_status,
                 c.cus_name, c.cus_email,
                 p.total_amount, p.payment_status, p.payment_date
          FROM orders o
          JOIN customer c ON o.cus_id = c.cus_id
          JOIN payment p ON o.orders_id = p.orders_id
          WHERE o.orders_id LIKE :search
             OR c.cus_name LIKE :search
             OR o.order_date LIKE :search
             OR p.total_amount LIKE :search
             OR o.orders_status LIKE :search
             OR p.payment_status LIKE :search
          ORDER BY $sortField $sortDirection
          LIMIT :limit OFFSET :offset";

$stmt = $_db->prepare($query);
$stmt->bindValue(':search', "%$searchTerm%", PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
    <link rel="stylesheet" href="../css/order.css">
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
                <th><a href="?sort=orders_id&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>">Order ID</a></th>
                <th><a href="?sort=order_date&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>">Date</a></th>
                <th>Customer</th>
                <th>Quantity</th>
                <th><a href="?sort=total_amount&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>">Total</a></th>
                <th><a href="?sort=orders_status&dir=<?= $sortDirection === 'asc' ? 'desc' : 'asc' ?>">Order Status</a></th>
                <th>Payment Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['orders_id']) ?></td>
                    <td><?= htmlspecialchars($order['order_date']) ?></td>
                    <td>
                        <?= htmlspecialchars($order['cus_name']) ?><br>
                        <small><?= htmlspecialchars($order['cus_email']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($order['quantity']) ?></td>
                    <td>RM <?= number_format($order['total_amount'], 2) ?></td>
                    <td>
                        <span class="status-badge <?= strtolower($order['orders_status']) ?>">
                            <?= htmlspecialchars($order['orders_status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($order['payment_status']) ?></td>
                    <td>
                        <a href="view_order_details.php?id=<?= $order['orders_id'] ?>" 
                           class="btn view-btn">
                            View
                        </a>
                        <?php if ($order['orders_status'] === 'Pending'): ?>
                            <button class="btn process-btn" 
                                    onclick="processOrder('<?= $order['orders_id'] ?>')">
                                Process
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1">&laquo; First</a>
            <a href="?page=<?= $page - 1 ?>">Previous</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>>
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Next</a>
            <a href="?page=<?= $totalPages ?>">Last &raquo;</a>
        <?php endif; ?>
    </div>

    <script>
    function processOrder(orderId) {
        if (confirm('Mark this order as processed?')) {
            fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    new_status: 'Processed'
                })
            })
            .then(() => window.location.reload());
        }
    }
    </script>
</body>
</html>