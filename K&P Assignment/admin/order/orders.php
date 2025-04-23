<?php
$_title = 'Order Management';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Search and Sort
$searchTerm = req('searchTerm', '');
$sortField = req('sort', 'order_date');
$sortDirection = req('dir', 'desc');

// Pagination
$limit = 10;
$page = req('page', 1);
$offset = ($page - 1) * $limit;

// Valid sort fields
$validSortFields = [
    'order_id',
    'order_date',
    'user_name',
    'order_total',
    'orders_status',
    'payment_status'
];

$sortField = in_array($sortField, $validSortFields) ? $sortField : 'order_date';

// Count total orders
$countQuery = "SELECT COUNT(DISTINCT o.order_id) 
               FROM orders o
               JOIN user u ON o.user_id = u.user_id
               JOIN payment p ON o.order_id = p.order_id
               WHERE o.order_id LIKE ?
                  OR u.user_name LIKE ?
                  OR o.order_date LIKE ?
                  OR o.order_total LIKE ?
                  OR o.orders_status LIKE ?
                  OR p.payment_status LIKE ?";

$countStmt = $_db->prepare($countQuery);
$searchParam = "%$searchTerm%";
$countStmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Main query
$query = "SELECT o.order_id, o.order_date, 
                 SUM(od.quantity) as total_quantity,
                 o.orders_status, u.user_name, u.user_Email,
                 p.total_amount, p.payment_status, p.payment_date
          FROM orders o
          JOIN user u ON o.user_id = u.user_id
          JOIN payment p ON o.order_id = p.order_id
          JOIN order_details od ON o.order_id = od.order_id
          WHERE o.order_id LIKE :search
             OR u.user_name LIKE :search
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

// Get status counts for summary cards
$statusQuery = "SELECT 
    SUM(CASE WHEN orders_status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN orders_status = 'Processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN orders_status = 'Shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN orders_status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN orders_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
FROM orders";
$statusStmt = $_db->query($statusQuery);
$statusCounts = $statusStmt->fetch(PDO::FETCH_ASSOC);
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
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-center mb-2 text-indigo-900">Order Management System</h1>
            <p class="text-center text-gray-600">View and manage customer orders</p>
        </div>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <!-- Total Orders -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-indigo-100 p-3 mr-4">
                    <i class="fas fa-shopping-cart text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $totalOrders ?></h3>
                    <p class="text-sm text-gray-600">Total Orders</p>
                </div>
            </div>

            <!-- Pending -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['pending'] ?></h3>
                    <p class="text-sm text-gray-600">Pending</p>
                </div>
            </div>

            <!-- Processing -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <i class="fas fa-cog text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['processing'] ?></h3>
                    <p class="text-sm text-gray-600">Processing</p>
                </div>
            </div>

            <!-- Delivered -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['delivered'] ?></h3>
                    <p class="text-sm text-gray-600">Delivered</p>
                </div>
            </div>

            <!-- Cancelled -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-red-100 p-3 mr-4">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['cancelled'] ?></h3>
                    <p class="text-sm text-gray-600">Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Search and Controls -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Search Form -->
            <div class="flex">
                <form method="get" class="flex w-full">
                    <div class="relative flex-grow">
                        <input type="text" 
                               name="searchTerm" 
                               placeholder="Search orders by ID, customer, status..."
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               class="search-input w-full pr-8">
                        <?php if (!empty($searchTerm)): ?>
                            <button type="button" 
                                    onclick="window.location.href='?'"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-primary rounded-l-none">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3"><?= sortable_header('Order ID', 'order_id') ?></th>
                            <th class="px-4 py-3"><?= sortable_header('Date', 'order_date') ?></th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Quantity</th>
                            <th class="px-4 py-3"><?= sortable_header('Total', 'order_total') ?></th>
                            <th class="px-4 py-3"><?= sortable_header('Status', 'orders_status') ?></th>
                            <th class="px-4 py-3"><?= sortable_header('Payment', 'payment_status') ?></th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-search text-4xl text-gray-300 mb-3"></i>
                                        <p class="text-lg font-medium mb-2">No orders found</p>
                                        <p class="text-sm">Try adjusting your search criteria</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($order['order_id']) ?></td>
                                    <td class="px-4 py-3"><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span><?= htmlspecialchars($order['user_name']) ?></span>
                                            <span class="text-sm text-gray-500"><?= htmlspecialchars($order['user_Email']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($order['total_quantity']) ?></td>
                                    <td class="px-4 py-3 font-medium">
                                        RM <?= number_format($order['total_amount'], 2) ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge <?= strtolower($order['orders_status']) ?>">
                                            <?= htmlspecialchars($order['orders_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="payment-badge <?= strtolower($order['payment_status']) ?>">
                                            <?= htmlspecialchars($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <a href="view_order_details.php?id=<?= $order['order_id'] ?>" 
                                               class="btn-primary py-1 px-2 text-xs">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($order['orders_status'] === 'Pending'): ?>
                                                <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Processing')"
                                                        class="btn-secondary py-1 px-2 text-xs">
                                                    Process
                                                </button>
                                            <?php elseif ($order['orders_status'] === 'Processing'): ?>
                                                <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Shipped')"
                                                        class="btn-secondary py-1 px-2 text-xs">
                                                    Ship
                                                </button>
                                            <?php elseif ($order['orders_status'] === 'Shipped'): ?>
                                                <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Delivered')"
                                                        class="btn-secondary py-1 px-2 text-xs">
                                                    Deliver
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!in_array($order['orders_status'], ['Cancelled', 'Delivered'])): ?>
                                                <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Cancelled')"
                                                        class="btn-danger py-1 px-2 text-xs">
                                                    Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <?php if ($totalPages > 1): ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&searchTerm=<?= urlencode($searchTerm) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>"
                       class="pagination-btn <?= $i == $page ? 'pagination-btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
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

<?php
function sortable_header($title, $field) {
    global $sortField, $sortDirection, $searchTerm;
    $dir = ($sortField === $field && $sortDirection === 'asc') ? 'desc' : 'asc';
    $url = "?sort=$field&dir=$dir&searchTerm=" . urlencode($searchTerm);
    $arrow = $sortField === $field 
        ? ($sortDirection === 'asc' ? '↑' : '↓') 
        : '';
    
    return "<a href='$url' class='sortable-header'>$title $arrow</a>";
}

require '../headFooter/footer.php';
?>