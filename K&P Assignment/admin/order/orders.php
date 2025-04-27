<?php
$_title = 'Order Management';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Get current user's role
$currentUserRole = $_SESSION['user']->role;

// Auto-update order statuses based on dates
autoUpdateOrderStatuses();

// Search and Sort
$searchTerm = req('searchTerm', '');
$statusFilter = req('status', 'all');
$paymentFilter = req('payment', 'all');
$dateFrom = req('dateFrom', '');
$dateTo = req('dateTo', '');
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

// Build where clause with all filters
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(o.order_id LIKE :search OR u.user_name LIKE :search OR u.user_Email LIKE :search)";
    $params[':search'] = "%$searchTerm%";
}

if ($statusFilter != 'all') {
    $whereConditions[] = "o.orders_status = :status";
    $params[':status'] = $statusFilter;
}

if ($paymentFilter != 'all') {
    $whereConditions[] = "p.payment_status = :payment";
    $params[':payment'] = $paymentFilter;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(o.order_date) >= :dateFrom";
    $params[':dateFrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(o.order_date) <= :dateTo";
    $params[':dateTo'] = $dateTo;
}

// Combine where conditions
$whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

// Count total orders
$countQuery = "SELECT COUNT(DISTINCT o.order_id) 
               FROM orders o
               JOIN user u ON o.user_id = u.user_id
               JOIN payment p ON o.order_id = p.order_id
               $whereClause";

$countStmt = $_db->prepare($countQuery);
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Main query - include delivery information for date checks
$query = "SELECT o.order_id, o.order_date, 
                 SUM(od.quantity) as total_quantity,
                 o.orders_status, u.user_name, u.user_Email,
                 p.total_amount, p.payment_status, p.payment_date,
                 d.estimated_date, d.delivered_date
          FROM orders o
          JOIN user u ON o.user_id = u.user_id
          JOIN payment p ON o.order_id = p.order_id
          JOIN order_details od ON o.order_id = od.order_id
          JOIN delivery d ON o.delivery_id = d.delivery_id
          $whereClause
          GROUP BY o.order_id
          ORDER BY $sortField $sortDirection
          LIMIT :limit OFFSET :offset";

// Add limit and offset to parameters
$params[':limit'] = $limit;
$params[':offset'] = $offset;

// Prepare and execute with all named parameters
$stmt = $_db->prepare($query);

// Bind all parameters using named parameters
foreach ($params as $key => $value) {
    // Bind integer parameters as integers
    if ($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}

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

/**
 * Auto-update order statuses based on dates
 * 
 * Rules:
 * 1. Orders are auto-updated to "Delivered" when the estimated_date equals current date
 * 2. Orders are auto-cancelled when the delivered_date is past the current date and order isn't completed
 * 3. When an order is cancelled due to late delivery, payment is auto-refunded
 */
function autoUpdateOrderStatuses() {
    global $_db;
    
    $currentDate = date('Y-m-d');
    
    try {
        // Begin transaction to ensure all updates are atomic
        $_db->beginTransaction();
        
        // 1. Orders that should be delivered today - update to "Delivered"
        // Only shipped orders will be auto-updated to delivered
        $deliveredQuery = "UPDATE orders o
                          JOIN delivery d ON o.delivery_id = d.delivery_id
                          SET o.orders_status = 'Delivered'
                          WHERE DATE(d.estimated_date) = :currentDate
                          AND o.orders_status = 'Shipped'";
        
        $deliveredStmt = $_db->prepare($deliveredQuery);
        $deliveredStmt->bindValue(':currentDate', $currentDate);
        $deliveredStmt->execute();
        
        // Also update the delivery status to "Delivered" and set the delivered_date
        if ($deliveredStmt->rowCount() > 0) {
            $updateDeliveryQuery = "UPDATE delivery d
                                   JOIN orders o ON d.delivery_id = o.delivery_id
                                   SET d.delivery_status = 'Delivered',
                                       d.delivered_date = :currentDate
                                   WHERE DATE(d.estimated_date) = :currentDate
                                   AND o.orders_status = 'Delivered'
                                   AND d.delivery_status = 'Out for Delivery'";
            
            $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
            $updateDeliveryStmt->bindValue(':currentDate', $currentDate);
            $updateDeliveryStmt->execute();
        }
        
        // 2. Orders that have missed their delivery date - update to "Cancelled"
        // Only affects orders that aren't already Delivered or Cancelled
        $cancelledQuery = "UPDATE orders o
                          JOIN delivery d ON o.delivery_id = d.delivery_id
                          SET o.orders_status = 'Cancelled'
                          WHERE DATE(d.delivered_date) < :currentDate
                          AND o.orders_status NOT IN ('Delivered', 'Cancelled')";
        
        $cancelledStmt = $_db->prepare($cancelledQuery);
        $cancelledStmt->bindValue(':currentDate', $currentDate);
        $cancelledStmt->execute();
        
        // 3. For cancelled orders due to missed delivery, update payment status to "Refunded"
        // and delivery status to "Failed"
        if ($cancelledStmt->rowCount() > 0) {
            // Update payment status to "Refunded"
            $updatePaymentQuery = "UPDATE payment p
                                  JOIN orders o ON p.order_id = o.order_id
                                  JOIN delivery d ON o.delivery_id = d.delivery_id
                                  SET p.payment_status = 'Refunded'
                                  WHERE DATE(d.delivered_date) < :currentDate
                                  AND o.orders_status = 'Cancelled'
                                  AND p.payment_status != 'Refunded'";
            
            $updatePaymentStmt = $_db->prepare($updatePaymentQuery);
            $updatePaymentStmt->bindValue(':currentDate', $currentDate);
            $updatePaymentStmt->execute();
            
            // Update delivery status to "Failed"
            $updateDeliveryQuery = "UPDATE delivery d
                                   JOIN orders o ON d.delivery_id = o.delivery_id
                                   SET d.delivery_status = 'Failed'
                                   WHERE DATE(d.delivered_date) < :currentDate
                                   AND o.orders_status = 'Cancelled'
                                   AND d.delivery_status != 'Failed'";
            
            $updateDeliveryStmt = $_db->prepare($updateDeliveryQuery);
            $updateDeliveryStmt->bindValue(':currentDate', $currentDate);
            $updateDeliveryStmt->execute();
        }
        
        // Commit all changes
        $_db->commit();
        
    } catch (Exception $e) {
        // Rollback on error
        $_db->rollBack();
        error_log("Auto-update orders error: " . $e->getMessage());
    }
}

/**
 * Check if staff can update to the given status
 * - Staff can only update from Pending to Processing
 * - Staff can only update from Processing to Shipped
 */
function canStaffUpdateToStatus($currentStatus, $newStatus) {
    if ($currentStatus === 'Pending' && $newStatus === 'Processing') {
        return true;
    }
    
    if ($currentStatus === 'Processing' && $newStatus === 'Shipped') {
        return true;
    }
    
    return false;
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
    <link rel="stylesheet" href="orders.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="orders.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-center mb-2 text-indigo-900">Order Management System</h1>
            <p class="text-center text-gray-600">View and manage customer orders</p>
        </div>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <!-- Total Orders -->
            <div class="card p-4 flex items-center rounded-lg shadow-sm transition-all hover:shadow-md cursor-pointer bg-white border-l-4 border-indigo-500">
                <div class="rounded-full bg-indigo-100 p-3 mr-4">
                    <i class="fas fa-shopping-cart text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $totalOrders ?></h3>
                    <p class="text-sm text-gray-600">Total Orders</p>
                </div>
            </div>

            <!-- Pending -->
            <div class="card p-4 flex items-center rounded-lg shadow-sm transition-all hover:shadow-md cursor-pointer bg-white border-l-4 border-yellow-500" onclick="filterByStatus('Pending')">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['pending'] ?></h3>
                    <p class="text-sm text-gray-600">Pending</p>
                </div>
            </div>

            <!-- Processing -->
            <div class="card p-4 flex items-center rounded-lg shadow-sm transition-all hover:shadow-md cursor-pointer bg-white border-l-4 border-blue-500" onclick="filterByStatus('Processing')">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <i class="fas fa-cog text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['processing'] ?></h3>
                    <p class="text-sm text-gray-600">Processing</p>
                </div>
            </div>

            <!-- Delivered -->
            <div class="card p-4 flex items-center rounded-lg shadow-sm transition-all hover:shadow-md cursor-pointer bg-white border-l-4 border-green-500" onclick="filterByStatus('Delivered')">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $statusCounts['delivered'] ?></h3>
                    <p class="text-sm text-gray-600">Delivered</p>
                </div>
            </div>

            <!-- Cancelled -->
            <div class="card p-4 flex items-center rounded-lg shadow-sm transition-all hover:shadow-md cursor-pointer bg-white border-l-4 border-red-500" onclick="filterByStatus('Cancelled')">
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
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <form method="get" id="filterForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Hidden sort fields to maintain state -->
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortField) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDirection) ?>">
                
                <!-- Search Term -->
                <div class="relative">
                    <label for="searchTerm" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="flex items-center">
                        <div class="relative flex-grow">
                            <input type="text" 
                                id="searchTerm"
                                name="searchTerm" 
                                placeholder="Order ID, customer name, email..."
                                value="<?= htmlspecialchars($searchTerm) ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 pl-10 pr-4 py-2">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <?php if (!empty($searchTerm)): ?>
                                <button type="button" 
                                        onclick="document.getElementById('searchTerm').value='';document.getElementById('filterForm').submit();"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-times text-gray-400 hover:text-gray-600"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Pending" <?= $statusFilter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $statusFilter == 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped" <?= $statusFilter == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $statusFilter == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $statusFilter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div>
                    <label for="payment" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select id="payment" name="payment" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all" <?= $paymentFilter == 'all' ? 'selected' : '' ?>>All Payments</option>
                        <option value="Pending" <?= $paymentFilter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Completed" <?= $paymentFilter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Failed" <?= $paymentFilter == 'Failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="Refunded" <?= $paymentFilter == 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <!-- Date Range Selection -->
                <div class="md:col-span-2 lg:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="text" id="dateFrom" name="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>" 
                               class="datepicker w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="Select start date">
                    </div>
                    <div>
                        <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="text" id="dateTo" name="dateTo" value="<?= htmlspecialchars($dateTo) ?>"
                               class="datepicker w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="Select end date">
                    </div>
                    <div class="flex items-end">
                        <div class="flex gap-2 w-full">
                            <button type="submit" class="flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex-1">
                                <i class="fas fa-filter mr-1"></i> Apply Filters
                            </button>
                            <a href="orders.php" class="flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="fas fa-redo-alt mr-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
            <div class="text-gray-600 mb-2 md:mb-0">
                Showing <span class="font-semibold"><?= min($totalOrders, $offset + 1) ?>-<?= min($totalOrders, $offset + $limit) ?></span> 
                of <span class="font-semibold"><?= $totalOrders ?></span> orders
                <?php if (!empty($searchTerm) || $statusFilter != 'all' || $paymentFilter != 'all' || !empty($dateFrom) || !empty($dateTo)): ?>
                    <span class="text-sm italic">(with applied filters)</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600">Display as:</span>
                <button id="tableViewBtn" class="p-1 rounded border border-gray-300 hover:bg-gray-100 active-view">
                    <i class="fas fa-table"></i>
                </button>
                <button id="cardViewBtn" class="p-1 rounded border border-gray-300 hover:bg-gray-100">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>

        <!-- Table View (Default) -->
        <div id="tableView" class="card rounded-lg shadow overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-4 py-3 text-gray-700"><?= sortable_header('Order ID', 'order_id') ?></th>
                            <th class="px-4 py-3 text-gray-700"><?= sortable_header('Date', 'order_date') ?></th>
                            <th class="px-4 py-3 text-gray-700">Customer</th>
                            <th class="px-4 py-3 text-gray-700">Quantity</th>
                            <th class="px-4 py-3 text-gray-700"><?= sortable_header('Total', 'order_total') ?></th>
                            <th class="px-4 py-3 text-gray-700"><?= sortable_header('Status', 'orders_status') ?></th>
                            <th class="px-4 py-3 text-gray-700"><?= sortable_header('Payment', 'payment_status') ?></th>
                            <th class="px-4 py-3 text-gray-700">Actions</th>
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
                                        <a href="orders.php" class="mt-4 text-indigo-600 hover:text-indigo-800 font-medium">
                                            <i class="fas fa-redo-alt mr-1"></i> Clear all filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($order['order_id']) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span><?= date('M d, Y', strtotime($order['order_date'])) ?></span>
                                            <span class="text-xs text-gray-500"><?= date('H:i', strtotime($order['order_date'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span><?= htmlspecialchars($order['user_name']) ?></span>
                                            <span class="text-xs text-gray-500"><?= htmlspecialchars($order['user_Email']) ?></span>
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
                                               class="bg-indigo-600 hover:bg-indigo-700 text-white py-1 px-2 rounded text-xs">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'staff'): ?>
                                                <?php if ($order['orders_status'] === 'Pending' && ($currentUserRole === 'admin' || canStaffUpdateToStatus($order['orders_status'], 'Processing'))): ?>
                                                    <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Processing')"
                                                            class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-2 rounded text-xs">
                                                        Process
                                                    </button>
                                                <?php elseif ($order['orders_status'] === 'Processing' && ($currentUserRole === 'admin' || canStaffUpdateToStatus($order['orders_status'], 'Shipped'))): ?>
                                                    <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Shipped')"
                                                            class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-2 rounded text-xs">
                                                        Ship
                                                    </button>
                                                <?php elseif ($order['orders_status'] === 'Shipped' && $currentUserRole === 'admin'): ?>
                                                    <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Delivered')"
                                                            class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-2 rounded text-xs">
                                                        Deliver
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!in_array($order['orders_status'], ['Cancelled', 'Delivered']) && $currentUserRole === 'admin'): ?>
                                                    <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Cancelled')"
                                                            class="bg-red-600 hover:bg-red-700 text-white py-1 px-2 rounded text-xs">
                                                        Cancel
                                                    </button>
                                                <?php endif; ?>
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

        <!-- Card View (Alternative) -->
        <div id="cardView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" style="display: none;">
            <?php if (empty($orders)): ?>
                <div class="col-span-full bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    <div class="flex flex-col items-center">
                        <i class="fas fa-search text-4xl text-gray-300 mb-3"></i>
                        <p class="text-lg font-medium mb-2">No orders found</p>
                        <p class="text-sm">Try adjusting your search criteria</p>
                        <a href="orders.php" class="mt-4 text-indigo-600 hover:text-indigo-800 font-medium">
                            <i class="fas fa-redo-alt mr-1"></i> Clear all filters
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="p-4 border-b">
                            <div class="flex justify-between items-center">
                                <h3 class="font-bold text-lg">#<?= htmlspecialchars($order['order_id']) ?></h3>
                                <span class="status-badge <?= strtolower($order['orders_status']) ?>">
                                    <?= htmlspecialchars($order['orders_status']) ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                <?= date('F d, Y H:i', strtotime($order['order_date'])) ?>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <div>
                                    <div class="text-xs font-medium text-gray-500 mb-1">Customer</div>
                                    <div class="text-sm"><?= htmlspecialchars($order['user_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($order['user_Email']) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 mb-1">Order Details</div>
                                    <div class="text-sm"><?= htmlspecialchars($order['total_quantity']) ?> items</div>
                                    <div class="font-medium">RM <?= number_format($order['total_amount'], 2) ?></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="payment-badge <?= strtolower($order['payment_status']) ?> text-xs">
                                    Payment: <?= htmlspecialchars($order['payment_status']) ?>
                                </span>
                                <div class="flex gap-2">
                                    <a href="view_order_details.php?id=<?= $order['order_id'] ?>" 
                                       class="bg-indigo-600 hover:bg-indigo-700 text-white py-1 px-3 rounded text-xs">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($currentUserRole === 'admin' || $currentUserRole === 'staff'): ?>
                                        <?php if ($order['orders_status'] === 'Pending' && ($currentUserRole === 'admin' || canStaffUpdateToStatus($order['orders_status'], 'Processing'))): ?>
                                            <button onclick="updateStatus('<?= $order['order_id'] ?>', 'Processing')"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded text-xs">
                                                Process
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="flex items-center space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&searchTerm=<?= urlencode($searchTerm) ?>&status=<?= $statusFilter ?>&payment=<?= $paymentFilter ?>&dateFrom=<?= urlencode($dateFrom) ?>&dateTo=<?= urlencode($dateTo) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>" 
                           class="px-3 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Logic for showing page numbers with ellipsis
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="?page=1&searchTerm=' . urlencode($searchTerm) . '&status=' . $statusFilter . '&payment=' . $paymentFilter . '&dateFrom=' . urlencode($dateFrom) . '&dateTo=' . urlencode($dateTo) . '&sort=' . $sortField . '&dir=' . $sortDirection . '" class="px-3 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">1</a>';
                        if ($startPage > 2) {
                            echo '<span class="px-3 py-2 text-gray-500">...</span>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50';
                        echo '<a href="?page=' . $i . '&searchTerm=' . urlencode($searchTerm) . '&status=' . $statusFilter . '&payment=' . $paymentFilter . '&dateFrom=' . urlencode($dateFrom) . '&dateTo=' . urlencode($dateTo) . '&sort=' . $sortField . '&dir=' . $sortDirection . '" class="px-3 py-2 rounded-md text-sm font-medium border border-gray-300 ' . $activeClass . '">' . $i . '</a>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span class="px-3 py-2 text-gray-500">...</span>';
                        }
                        echo '<a href="?page=' . $totalPages . '&searchTerm=' . urlencode($searchTerm) . '&status=' . $statusFilter . '&payment=' . $paymentFilter . '&dateFrom=' . urlencode($dateFrom) . '&dateTo=' . urlencode($dateTo) . '&sort=' . $sortField . '&dir=' . $sortDirection . '" class="px-3 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">' . $totalPages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&searchTerm=<?= urlencode($searchTerm) ?>&status=<?= $statusFilter ?>&payment=<?= $paymentFilter ?>&dateFrom=<?= urlencode($dateFrom) ?>&dateTo=<?= urlencode($dateTo) ?>&sort=<?= $sortField ?>&dir=<?= $sortDirection ?>"
                           class="px-3 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function sortable_header($title, $field) {
    global $sortField, $sortDirection, $searchTerm, $statusFilter, $paymentFilter, $dateFrom, $dateTo;
    
    $dir = ($sortField === $field && $sortDirection === 'asc') ? 'desc' : 'asc';
    
    $params = [
        'sort' => $field,
        'dir' => $dir,
        'searchTerm' => $searchTerm,
        'status' => $statusFilter,
        'payment' => $paymentFilter,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo
    ];
    
    $queryString = http_build_query($params);
    
    $arrow = '';
    if ($sortField === $field) {
        $arrow = $sortDirection === 'asc' ? 
            '<i class="fas fa-sort-up ml-1"></i>' : 
            '<i class="fas fa-sort-down ml-1"></i>';
    }
    
    return "<a href='?$queryString' class='sortable-header'>$title $arrow</a>";
}

require '../headFooter/footer.php';
?>