<?php
$_title = 'Admin Dashboard';
require '../../_base.php';
auth('admin', 'Manager'); // Only admins and managers can access
require 'header.php';

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
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg mb-8 p-6 text-white animate-fadeIn">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2">Welcome to K&P Fashion Admin</h1>
                <p class="text-indigo-100">Manage your store, track sales, and grow your business.</p>
                <p class="text-sm text-indigo-200 mt-2">Today is <?= date('l, F j, Y') ?> | Last login: Today at 09:35 AM</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex space-x-3">
                    <a href="product.php" class="bg-white text-indigo-600 hover:bg-indigo-50 py-2 px-4 rounded-lg flex items-center transition-colors shadow-sm">
                        <i class="fas fa-plus mr-2"></i> New Product
                    </a>
                    <a href="reports.php" class="bg-indigo-700 hover:bg-indigo-800 text-white py-2 px-4 rounded-lg flex items-center transition-colors shadow-sm">
                        <i class="fas fa-chart-line mr-2"></i> Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Products Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-indigo-500 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="p-5 flex items-center">
                <div class="bg-indigo-100 text-indigo-600 rounded-full p-3 mr-4 flex items-center justify-center w-12 h-12">
                    <i class="fas fa-tshirt text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Total Products</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($total_products) ?></p>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="product.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center justify-between">
                    <span>View all products</span>
                    <i class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                </a>
            </div>
        </div>

        <!-- Orders Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-green-500 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="p-5 flex items-center">
                <div class="bg-green-100 text-green-600 rounded-full p-3 mr-4 flex items-center justify-center w-12 h-12">
                    <i class="fas fa-shopping-bag text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($total_orders) ?></p>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="orders.php" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center justify-between">
                    <span>View all orders</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Users Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-blue-500 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="p-5 flex items-center">
                <div class="bg-blue-100 text-blue-600 rounded-full p-3 mr-4 flex items-center justify-center w-12 h-12">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Total Users</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($total_users) ?></p>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="users.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center justify-between">
                    <span>View all users</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-purple-500 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="p-5 flex items-center">
                <div class="bg-purple-100 text-purple-600 rounded-full p-3 mr-4 flex items-center justify-center w-12 h-12">
                    <i class="fas fa-dollar-sign text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-800">RM<?= number_format($total_revenue, 2) ?></p>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="reports.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center justify-between">
                    <span>View reports</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Order Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Pending Orders -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-700">Pending Orders</h3>
                <span class="p-2 rounded-full bg-amber-100 text-amber-700">
                    <i class="fas fa-clock"></i>
                </span>
            </div>
            <p class="text-3xl font-bold text-amber-600"><?= number_format($orders_pending) ?></p>
            <div class="flex items-center justify-between mt-4">
                <p class="text-sm text-gray-500">Awaiting processing</p>
                <a href="orders.php?status=Pending" class="text-amber-600 hover:text-amber-800 text-sm">View <i class="fas fa-chevron-right ml-1 text-xs"></i></a>
            </div>
        </div>

        <!-- Processing Orders -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-700">Processing Orders</h3>
                <span class="p-2 rounded-full bg-blue-100 text-blue-700">
                    <i class="fas fa-cog"></i>
                </span>
            </div>
            <p class="text-3xl font-bold text-blue-600"><?= number_format($orders_processing) ?></p>
            <div class="flex items-center justify-between mt-4">
                <p class="text-sm text-gray-500">Currently being processed</p>
                <a href="orders.php?status=Processing" class="text-blue-600 hover:text-blue-800 text-sm">View <i class="fas fa-chevron-right ml-1 text-xs"></i></a>
            </div>
        </div>

        <!-- Completed Orders -->
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-all transform hover:-translate-y-1">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-700">Completed Orders</h3>
                <span class="p-2 rounded-full bg-green-100 text-green-700">
                    <i class="fas fa-check"></i>
                </span>
            </div>
            <p class="text-3xl font-bold text-green-600"><?= number_format($orders_completed) ?></p>
            <div class="flex items-center justify-between mt-4">
                <p class="text-sm text-gray-500">Successfully delivered</p>
                <a href="orders.php?status=Delivered" class="text-green-600 hover:text-green-800 text-sm">View <i class="fas fa-chevron-right ml-1 text-xs"></i></a>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Monthly Revenue Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-all">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-line text-indigo-500 mr-2"></i>
                Monthly Revenue (<?= date('Y') ?>)
            </h3>
            <div class="h-72">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Orders Status Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-all">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-pie text-indigo-500 mr-2"></i>
                Order Status Distribution
            </h3>
            <div class="h-72">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="flex justify-between items-center p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <i class="fas fa-clipboard-list text-indigo-500 mr-2"></i>
                Recent Orders
            </h3>
            <a href="orders.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
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
                            <tr class="hover:bg-gray-50 transition-colors">
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
                                        'Pending' => 'bg-amber-100 text-amber-800',
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
                                    <a href="order_details.php?id=<?= $order->order_id ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-eye"></i> View
                                    </a>
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
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-fire text-indigo-500 mr-2"></i>
                    Best Selling Products
                </h3>
                <a href="reports.php?view=top_products" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if (empty($top_products)): ?>
                    <div class="px-5 py-4 text-center text-gray-500">No product sales data available</div>
                <?php else: ?>
                    <?php foreach ($top_products as $product): ?>
                        <div class="p-5 flex items-center hover:bg-gray-50 transition-colors">
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-lg overflow-hidden mr-4">
                                <?php if ($product->product_pic1): ?>
                                    <img src="../uploads/product_images/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="w-full h-full object-cover">
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
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>
                    Low Stock Alerts
                </h3>
                <a href="reports.php?view=low_stock" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if (empty($stock_alerts)): ?>
                    <div class="px-5 py-4 text-center text-gray-500">No low stock items</div>
                <?php else: ?>
                    <?php foreach ($stock_alerts as $item): ?>
                        <div class="p-5 flex items-center hover:bg-gray-50 transition-colors">
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-lg overflow-hidden mr-4">
                                <?php if ($item->product_pic1): ?>
                                    <img src="../uploads/product_images/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="w-full h-full object-cover">
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
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                            <?= $item->product_stock ?> left
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="Update_Product.php?id=<?= $item->product_id ?>" class="ml-4 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 py-1 px-3 rounded-lg text-sm">
                                <i class="fas fa-plus-circle mr-1"></i> Restock
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Monthly Revenue Chart
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthlyData = <?= json_encode(array_values($monthly_revenue)) ?>;

    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'Revenue (RM)',
                data: monthlyData,
                backgroundColor: 'rgba(79, 70, 229, 0.2)', // Indigo color to match theme
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'RM' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: RM' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Orders Status Chart
    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    const ordersChart = new Chart(ordersCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Processing', 'Delivered', 'Other'],
            datasets: [{
                data: [
                    <?= $orders_pending ?>,
                    <?= $orders_processing ?>,
                    <?= $orders_completed ?>,
                    <?= $total_orders - ($orders_pending + $orders_processing + $orders_completed) ?>
                ],
                backgroundColor: [
                    'rgba(251, 191, 36, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(156, 163, 175, 0.8)'
                ],
                borderColor: [
                    'rgba(251, 191, 36, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(156, 163, 175, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });

    // Add animation for cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.hover\\:shadow-md');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('opacity-100');
                card.classList.remove('opacity-0');
            }, 100 * index);
        });
    });
</script>

<?php require 'footer.php'; ?>