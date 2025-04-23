<?php
$_title = 'Admin Dashboard';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Initialize totals
$total_products = 0;
$total_orders = 0;
$total_users = 0;
$total_revenue = 0;
$recent_orders = [];
$top_products = [];
$stock_alerts = [];
$monthly_revenue = [];

try {
    // Get total product count
    $product_query = "SELECT COUNT(*) as count FROM product";
    $stmt = $_db->prepare($product_query);
    $stmt->execute();
    $total_products = $stmt->fetchColumn();

    // Get total order count
    $order_query = "SELECT COUNT(*) as count FROM orders";
    $stmt = $_db->prepare($order_query);
    $stmt->execute();
    $total_orders = $stmt->fetchColumn();

    // Get total user count (only regular customers, not admin)
    $customer_query = "SELECT COUNT(*) as count FROM user WHERE role != 'admin'";
    $stmt = $_db->prepare($customer_query);
    $stmt->execute();
    $total_users = $stmt->fetchColumn();

    // Get total revenue
    $revenue_query = "SELECT SUM(total_amount) as total FROM payment WHERE payment_status = 'Completed'";
    $stmt = $_db->prepare($revenue_query);
    $stmt->execute();
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // Get recent orders (last 5)
    $recent_orders_query = "SELECT o.order_id, o.order_date, o.orders_status, o.order_total, u.user_name 
                           FROM orders o
                           JOIN user u ON o.user_id = u.user_id
                           ORDER BY o.order_date DESC
                           LIMIT 5";
    $stmt = $_db->prepare($recent_orders_query);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();

    // Get top selling products
    $top_products_query = "SELECT p.product_id, p.product_name, p.product_pic1, 
                          SUM(od.quantity) as total_sold, 
                          SUM(od.unit_price * od.quantity) as revenue
                          FROM product p
                          JOIN order_details od ON p.product_id = od.product_id
                          JOIN orders o ON od.order_id = o.order_id
                          WHERE o.orders_status != 'Cancelled'
                          GROUP BY p.product_id
                          ORDER BY total_sold DESC
                          LIMIT 5";
    $stmt = $_db->prepare($top_products_query);
    $stmt->execute();
    $top_products = $stmt->fetchAll();

    // Get low stock alerts
    $low_stock_query = "SELECT p.product_id, p.product_name, p.product_pic1, q.size, q.product_stock
                       FROM product p
                       JOIN quantity q ON p.product_id = q.product_id
                       WHERE q.product_stock <= 5 AND p.product_status = 'Available'
                       ORDER BY q.product_stock ASC
                       LIMIT 5";
    $stmt = $_db->prepare($low_stock_query);
    $stmt->execute();
    $stock_alerts = $stmt->fetchAll();

    // Get monthly revenue for the current year
    $current_year = date('Y');
    $monthly_revenue_query = "SELECT MONTH(payment_date) as month, SUM(total_amount) as revenue
                             FROM payment
                             WHERE YEAR(payment_date) = ? AND payment_status = 'Completed'
                             GROUP BY MONTH(payment_date)
                             ORDER BY month";
    $stmt = $_db->prepare($monthly_revenue_query);
    $stmt->execute([$current_year]);

    // Initialize array with all months
    $monthly_data = array_fill(1, 12, 0);

    // Fill in actual data
    while ($row = $stmt->fetch()) {
        $monthly_data[$row->month] = floatval($row->revenue);
    }

    $monthly_revenue = $monthly_data;
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
}

// Calculate order statistics
$orders_pending = 0;
$orders_processing = 0;
$orders_completed = 0;

try {
    $orders_status_query = "SELECT orders_status, COUNT(*) as count 
                           FROM orders 
                           GROUP BY orders_status";
    $stmt = $_db->prepare($orders_status_query);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        switch ($row->orders_status) {
            case 'Pending':
                $orders_pending = $row->count;
                break;
            case 'Processing':
                $orders_processing = $row->count;
                break;
            case 'Delivered':
                $orders_completed = $row->count;
                break;
        }
    }
} catch (PDOException $e) {
    // Silently handle error
    error_log("Error fetching order statistics: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="/admin/home/home.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <!-- Hidden inputs for chart data -->
    <input type="hidden" id="monthlyRevenueData" value='<?= json_encode(array_values($monthly_revenue)) ?>'>
    <input type="hidden" id="ordersPending" value="<?= $orders_pending ?>">
    <input type="hidden" id="ordersProcessing" value="<?= $orders_processing ?>">
    <input type="hidden" id="ordersCompleted" value="<?= $orders_completed ?>">
    <input type="hidden" id="totalOrders" value="<?= $total_orders ?>">

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Welcome to the Admin Dashboard</h1>

        <!-- Date and Quick Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div class="text-gray-600 mb-4 md:mb-0">
                <p class="text-lg"><i class="far fa-calendar-alt mr-2"></i> <?= date('l, F j, Y') ?></p>
            </div>
            <div class="flex space-x-3">
                <a href="../product/product.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-box mr-2"></i> Manage Products
                </a>
                <a href="../order/orders.php" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i> View Orders
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Products Card -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-indigo-500">
                <div class="p-5 flex items-center">
                    <div class="stat-icon bg-indigo-100 text-indigo-600">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Products</p>
                        <p class="text-2xl font-bold"><?= number_format($total_products) ?></p>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-2">
                    <a href="../product/product.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                        View all products <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-green-500">
                <div class="p-5 flex items-center">
                    <div class="stat-icon bg-green-100 text-green-600">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Orders</p>
                        <p class="text-2xl font-bold"><?= number_format($total_orders) ?></p>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-2">
                    <a href="../order/orders.php" class="text-green-600 hover:text-green-800 text-sm font-medium">
                        View all orders <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Users Card (previously Customers) -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-blue-500">
                <div class="p-5 flex items-center">
                    <div class="stat-icon bg-blue-100 text-blue-600">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Users</p>
                        <p class="text-2xl font-bold"><?= number_format($total_users) ?></p>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-2">
                    <a href="../customer/users.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View all users <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Revenue Card -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-purple-500">
                <div class="p-5 flex items-center">
                    <div class="stat-icon bg-purple-100 text-purple-600">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold">RM<?= number_format($total_revenue, 2) ?></p>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-2">
                    <a href="reports.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                        View reports <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Order Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Pending Orders -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Pending Orders</h3>
                    <span class="p-2 rounded-full bg-yellow-100 text-yellow-700">
                        <i class="fas fa-clock"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($orders_pending) ?></p>
                <p class="text-sm text-gray-500 mt-2">Awaiting processing</p>
            </div>

            <!-- Processing Orders -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Processing Orders</h3>
                    <span class="p-2 rounded-full bg-blue-100 text-blue-700">
                        <i class="fas fa-cog"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($orders_processing) ?></p>
                <p class="text-sm text-gray-500 mt-2">Currently being processed</p>
            </div>

            <!-- Completed Orders -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Completed Orders</h3>
                    <span class="p-2 rounded-full bg-green-100 text-green-700">
                        <i class="fas fa-check"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-green-600"><?= number_format($orders_completed) ?></p>
                <p class="text-sm text-gray-500 mt-2">Successfully delivered</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Monthly Revenue Chart -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Monthly Revenue (<?= date('Y') ?>)</h3>
                <div class="h-64">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Orders Status Chart -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Order Status Distribution</h3>
                <div class="h-64">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Orders Section -->
        <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="flex justify-between items-center p-5 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">Recent Orders</h3>
                <a href="orders.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No recent orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($order->order_id) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($order->user_name) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($order->order_date)) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        RM<?= number_format($order->order_total, 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = match ($order->orders_status) {
                                            'Pending' => 'bg-yellow-100 text-yellow-800',
                                            'Processing' => 'bg-blue-100 text-blue-800',
                                            'Shipped' => 'bg-purple-100 text-purple-800',
                                            'Delivered' => 'bg-green-100 text-green-800',
                                            'Cancelled' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                            <?= htmlspecialchars($order->orders_status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="order_details.php?id=<?= $order->order_id ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Products and Stock Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top Products -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">Best Selling Products</h3>
                    <a href="reports.php?view=top_products" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php if (empty($top_products)): ?>
                        <div class="px-5 py-4 text-center text-gray-500">No product sales data available</div>
                    <?php else: ?>
                        <?php foreach ($top_products as $product): ?>
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-lg overflow-hidden mr-4">
                                    <?php if ($product->product_pic1): ?>
                                        <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                            <i class="fas fa-tshirt text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product->product_name) ?></h4>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-sm text-gray-500"><?= number_format($product->total_sold) ?> units sold</span>
                                        <span class="text-sm font-medium text-gray-900">RM<?= number_format($product->revenue, 2) ?></span>
                                    </div>
                                </div>
                                <a href="Detail_Product.php?id=<?= $product->product_id ?>" class="ml-4 text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stock Alerts -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">Low Stock Alerts</h3>
                    <a href="reports.php?view=low_stock" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php if (empty($stock_alerts)): ?>
                        <div class="px-5 py-4 text-center text-gray-500">No low stock items</div>
                    <?php else: ?>
                        <?php foreach ($stock_alerts as $item): ?>
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-lg overflow-hidden mr-4">
                                    <?php if ($item->product_pic1): ?>
                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                            <i class="fas fa-tshirt text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item->product_name) ?></h4>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-sm">Size: <strong><?= htmlspecialchars($item->size) ?></strong></span>
                                        <?php if ($item->product_stock <= 0): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <?= $item->product_stock ?> left
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="Update_Product.php?id=<?= $item->product_id ?>" class="ml-4 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 py-1 px-3 rounded-lg text-sm">
                                    Restock
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require '../headFooter/footer.php'; ?>

    <script src="/admin/home/home.js"></script>
</body>

</html>