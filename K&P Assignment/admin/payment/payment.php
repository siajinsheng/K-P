<?php
$_title = 'Payment';
require '../../_base.php';
auth('admin','staff');
require 'header.php';

// (1) Sorting fields
$fields = [
    'payment_id'     => 'Payment ID',
    'order_id'       => 'Order ID',
    'total_amount'   => 'Total Amount',
    'payment_status' => 'Payment Status',
    'payment_method' => 'Payment Method'
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'payment_id';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// (2) Paging and Search Settings
$page = req('page', 1);
$searchPaymentId   = req('searchPaymentId');
$searchOrderId     = req('searchOrderId');
$searchPaymentStatus = req('searchPaymentStatus');

// Build search conditions dynamically
$params = [];
$where = " WHERE 1=1 ";
if ($searchPaymentId) {
    $where .= " AND p.payment_id LIKE ? ";
    $params[] = "%$searchPaymentId%";
}
if ($searchOrderId) {
    $where .= " AND p.order_id LIKE ? ";
    $params[] = "%$searchOrderId%";
}
if ($searchPaymentStatus) {
    $where .= " AND p.payment_status LIKE ? ";
    $params[] = "%$searchPaymentStatus%";
}

// (3) Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM payment p
              JOIN orders o ON p.order_id = o.order_id
              JOIN user u ON o.user_id = u.user_id " . $where;
$stmt = $_db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();

$items_per_page = 10;
$total_pages = ceil($total_records / $items_per_page);
$offset = ($page - 1) * $items_per_page;

// (4) Main Query: Retrieve payment transactions
$sql = "SELECT p.*, o.order_date, u.user_name, u.user_Email 
        FROM payment p
        JOIN orders o ON p.order_id = o.order_id
        JOIN user u ON o.user_id = u.user_id "
        . $where .
        " ORDER BY p.$sort $dir
          LIMIT $items_per_page OFFSET $offset";
$stmt = $_db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Transactions Management</title>
  <link rel="stylesheet" href="/admin/css/payment.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
  <div class="container1">
    <h1>Payment Transactions Management</h1>

    <!-- Search Form -->
    <form method="get" class="search-form">
      <div class="form-group">
        <input type="text" name="searchPaymentId" 
               placeholder="Payment ID" 
               class="form-input"
               value="<?= htmlspecialchars($searchPaymentId ?? '') ?>">
      </div>
      <div class="form-group">
        <input type="text" name="searchOrderId" 
               placeholder="Order ID" 
               class="form-input"
               value="<?= htmlspecialchars($searchOrderId ?? '') ?>">
      </div>
      <div class="form-group3">
        <select name="searchPaymentStatus" class="form-select">
          <option value="">All Statuses</option>
          <option value="completed" <?= $searchPaymentStatus == 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="pending" <?= $searchPaymentStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="failed" <?= $searchPaymentStatus == 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>
      </div>
      <button type="submit" class="form-button">
        Search <i class="fas fa-search"></i>
      </button>
    </form>

    <p class="record">
      <?= $total_records ?> record(s) | Page <?= $page ?> of <?= $total_pages ?>
    </p>

    <?php if (!empty($payments)): ?>
      <table class="table">
        <thead>
          <tr>
            <th><a href="?sort=payment_id&dir=<?= $sort == 'payment_id' && $dir == 'asc' ? 'desc' : 'asc' ?>">Payment ID</a></th>
            <th><a href="?sort=order_id&dir=<?= $sort == 'order_id' && $dir == 'asc' ? 'desc' : 'asc' ?>">Order ID</a></th>
            <th>Customer Name</th>
            <th>Customer Email</th>
            <th><a href="?sort=total_amount&dir=<?= $sort == 'total_amount' && $dir == 'asc' ? 'desc' : 'asc' ?>">Total Amount</a></th>
            <th><a href="?sort=payment_status&dir=<?= $sort == 'payment_status' && $dir == 'asc' ? 'desc' : 'asc' ?>">Status</a></th>
            <th><a href="?sort=payment_method&dir=<?= $sort == 'payment_method' && $dir == 'asc' ? 'desc' : 'asc' ?>">Method</a></th>
            <th>Payment Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?= htmlspecialchars($payment['payment_id']) ?></td>
              <td><?= htmlspecialchars($payment['order_id']) ?></td>
              <td><?= htmlspecialchars($payment['user_name']) ?></td>
              <td><?= htmlspecialchars($payment['user_Email']) ?></td>
              <td>$<?= number_format($payment['total_amount'], 2) ?></td>
              <td>
                <span class="status-badge status-<?= strtolower($payment['payment_status']) ?>">
                  <?= htmlspecialchars($payment['payment_status']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($payment['payment_date']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="record">No payment records found.</p>
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
        for ($i = $start; $i <= $end; $i++):
      ?>
        <a href="?page=<?= $i ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
           class="<?= $i == $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Next</a>
        <a href="?page=<?= $total_pages ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Last</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
