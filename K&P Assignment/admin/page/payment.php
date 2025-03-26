<?php
$_title = 'Payment';
require '../../_base.php';
//auth('Admin', 'Manager');
require 'header.php';

// (1) Sorting fields
$fields = [
    'payment_id'      => 'Payment ID',
    'orders_id'       => 'Order ID',
    'total_amount'    => 'Total Amount',
    'payment_status'  => 'Payment Status',
    'payment_method'  => 'Payment Method'
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'payment_id'; // Default sort by Payment ID

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc'; // Default direction is ascending

// (2) Paging settings
$page = req('page', 1); // Get the current page or default to 1
$items_per_page = 10;   // Items per page
$offset = ($page - 1) * $items_per_page; // Calculate the offset

// (3) Search inputs
$searchPaymentId = req('searchPaymentId'); // Search for Payment ID
$searchOrderId = req('searchOrderId');     // Search for Order ID
$searchPaymentStatus = req('searchPaymentStatus'); // Search for Payment Status

try {
    // Create database connection
    $conn = new PDO('mysql:host=localhost;dbname=k&p;charset=utf8mb4', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Count total records for pagination with search filters
    $sql_count = "SELECT COUNT(*) FROM payment p
                  JOIN orders o ON p.orders_id = o.orders_id
                  JOIN customer c ON o.cus_id = c.cus_id
                  WHERE 1=1";

    // Add search conditions
    $params = [];
    if ($searchPaymentId) {
        $sql_count .= " AND p.payment_id LIKE :payment_id";
        $params[':payment_id'] = "%$searchPaymentId%";
    }
    if ($searchOrderId) {
        $sql_count .= " AND p.orders_id LIKE :order_id";
        $params[':order_id'] = "%$searchOrderId%";
    }
    if ($searchPaymentStatus) {
        $sql_count .= " AND p.payment_status LIKE :payment_status";
        $params[':payment_status'] = "%$searchPaymentStatus%";
    }

    $stmt = $conn->prepare($sql_count);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_records / $items_per_page);

    // Prepare main query
    $sql = "SELECT p.*, o.order_date, c.cus_name, c.cus_Email 
            FROM payment p
            JOIN orders o ON p.orders_id = o.orders_id
            JOIN customer c ON o.cus_id = c.cus_id
            WHERE 1=1";

    // Add search conditions to main query
    if ($searchPaymentId) {
        $sql .= " AND p.payment_id LIKE :payment_id";
    }
    if ($searchOrderId) {
        $sql .= " AND p.orders_id LIKE :order_id";
    }
    if ($searchPaymentStatus) {
        $sql .= " AND p.payment_status LIKE :payment_status";
    }

    // Apply sorting
    $sql .= " ORDER BY p.$sort $dir";

    // Apply pagination
    $sql .= " LIMIT $items_per_page OFFSET $offset";

    // Prepare and execute the statement
    $stmt = $conn->prepare($sql);
    
    // Bind search parameters
    if ($searchPaymentId) {
        $stmt->bindValue(':payment_id', "%$searchPaymentId%");
    }
    if ($searchOrderId) {
        $stmt->bindValue(':order_id', "%$searchOrderId%");
    }
    if ($searchPaymentStatus) {
        $stmt->bindValue(':payment_status', "%$searchPaymentStatus%");
    }

    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions Management</title>
    <style>
        /* Include the styling from the previous example */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        form {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 10px;
        }

        form input, form select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 200px;
        }

        form button {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a, .pagination span {
            margin: 0 5px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
        }

        .pagination .active {
            background-color: #333;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Payment Transactions Management</h1>

    <!-- Search Form -->
    <form method="get">
        <input type="text" name="searchPaymentId" placeholder="Search Payment ID" 
               value="<?= htmlspecialchars($searchPaymentId ?? '') ?>">
        
        <input type="text" name="searchOrderId" placeholder="Search Order ID" 
               value="<?= htmlspecialchars($searchOrderId ?? '') ?>">
        
        <select name="searchPaymentStatus">
            <option value="">All Payment Statuses</option>
            <option value="completed" <?= $searchPaymentStatus == 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="pending" <?= $searchPaymentStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="failed" <?= $searchPaymentStatus == 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>

        <button type="submit">Search</button>
    </form>

    <p>
        <?= $total_records ?> record(s) | 
        Page <?= $page ?> of <?= $total_pages ?>
    </p>

    <?php if (!empty($payments)): ?>
    <table>
        <thead>
            <tr>
                <th><a href="?sort=payment_id&dir=<?= $sort == 'payment_id' && $dir == 'asc' ? 'desc' : 'asc' ?>">Payment ID</a></th>
                <th><a href="?sort=orders_id&dir=<?= $sort == 'orders_id' && $dir == 'asc' ? 'desc' : 'asc' ?>">Order ID</a></th>
                <th>Customer Name</th>
                <th>Customer Email</th>
                <th><a href="?sort=total_amount&dir=<?= $sort == 'total_amount' && $dir == 'asc' ? 'desc' : 'asc' ?>">Total Amount</a></th>
                <th><a href="?sort=tax&dir=<?= $sort == 'tax' && $dir == 'asc' ? 'desc' : 'asc' ?>">Tax</a></th>
                <th><a href="?sort=payment_status&dir=<?= $sort == 'payment_status' && $dir == 'asc' ? 'desc' : 'asc' ?>">Payment Status</a></th>
                <th><a href="?sort=payment_method&dir=<?= $sort == 'payment_method' && $dir == 'asc' ? 'desc' : 'asc' ?>">Payment Method</a></th>
                <th>Payment Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                <td><?= htmlspecialchars($payment['orders_id']) ?></td>
                <td><?= htmlspecialchars($payment['cus_name']) ?></td>
                <td><?= htmlspecialchars($payment['cus_Email']) ?></td>
                <td><?= number_format($payment['total_amount'], 2) ?></td>
                <td><?= number_format($payment['tax'], 2) ?></td>
                <td><?= htmlspecialchars($payment['payment_status']) ?></td>
                <td><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($payment['payment_date']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No payment records found.</p>
    <?php endif; ?>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&sort=<?= $sort ?>&dir=<?= $dir ?>">First</a>
            <a href="?page=<?= $page - 1 ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Previous</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
               class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Next</a>
            <a href="?page=<?= $total_pages ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Last</a>
        <?php endif; ?>
    </div>
</body>
</html>