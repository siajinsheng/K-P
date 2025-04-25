<?php
$_title = 'Admin Reports';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Get filter parameters
$view = get('view', 'sales'); // Default view is sales
$period = get('period', 'monthly'); // Default period is monthly
$start_date = get('start_date', date('Y-m-d', strtotime('-365 days')));
$end_date = get('end_date', date('Y-m-d'));
$category = get('category', '');

// Initialize data arrays
$sales_data = [];
$chart_labels = [];
$chart_values = [];
$top_products = [];
$low_stock_items = [];
$category_sales = [];
$customer_data = [];

try {
    // SALES REPORTS
    if ($view == 'sales' || $view == 'dashboard') {
        $date_format = '';
        $group_by = '';
        $date_label = '';
        
        switch ($period) {
            case 'daily':
                $date_format = '%Y-%m-%d';
                $group_by = "DATE(p.payment_date)";
                $date_label = "DATE_FORMAT(p.payment_date, '%d %b %Y')";
                break;
                
            case 'weekly':
                $date_format = '%Y-%u';
                $group_by = "YEAR(p.payment_date), WEEK(p.payment_date)";
                $date_label = "CONCAT('Week ', WEEK(p.payment_date), ', ', YEAR(p.payment_date))";
                break;
                
            case 'monthly':
                $date_format = '%Y-%m';
                $group_by = "YEAR(p.payment_date), MONTH(p.payment_date)";
                $date_label = "DATE_FORMAT(p.payment_date, '%b %Y')";
                break;
                
            case 'yearly':
                $date_format = '%Y';
                $group_by = "YEAR(p.payment_date)";
                $date_label = "YEAR(p.payment_date)";
                break;
        }
        
        // Get sales data
        $sales_query = "
            SELECT 
                $date_label as period,
                SUM(p.total_amount) as revenue,
                COUNT(DISTINCT o.order_id) as orders,
                SUM(od.quantity) as items_sold
            FROM 
                payment p
                JOIN orders o ON p.order_id = o.order_id
                JOIN order_details od ON o.order_id = od.order_id
            WHERE 
                p.payment_status = 'Completed'
                AND p.payment_date BETWEEN ? AND ?
            GROUP BY 
                $group_by
            ORDER BY 
                MIN(p.payment_date)
        ";
        
        $stmt = $_db->prepare($sales_query);
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $sales_data = $stmt->fetchAll();
        
        // Format chart data
        foreach ($sales_data as $row) {
            $chart_labels[] = $row->period;
            $chart_values[] = floatval($row->revenue);
        }
        
        // Get total statistics
        $totals_query = "
            SELECT 
                SUM(p.total_amount) as total_revenue,
                COUNT(DISTINCT o.order_id) as total_orders,
                SUM(od.quantity) as total_items_sold,
                AVG(p.total_amount) as avg_order_value
            FROM 
                payment p
                JOIN orders o ON p.order_id = o.order_id
                JOIN order_details od ON o.order_id = od.order_id
            WHERE 
                p.payment_status = 'Completed'
                AND p.payment_date BETWEEN ? AND ?
        ";
        
        $stmt = $_db->prepare($totals_query);
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $totals = $stmt->fetch();
    }
    
    // TOP PRODUCTS REPORT
    if ($view == 'top_products' || $view == 'dashboard') {
        $top_products_query = "
            SELECT 
                p.product_id,
                p.product_name,
                p.product_price,
                p.product_pic1,
                p.product_type,
                c.category_name,
                SUM(od.quantity) as total_sold,
                SUM(od.unit_price * od.quantity) as total_revenue
            FROM 
                product p
                JOIN order_details od ON p.product_id = od.product_id
                JOIN orders o ON od.order_id = o.order_id
                JOIN payment pay ON o.order_id = pay.order_id
                JOIN category c ON p.category_id = c.category_id
            WHERE 
                pay.payment_status = 'Completed'
                AND pay.payment_date BETWEEN ? AND ?
                " . ($category ? "AND p.category_id = ?" : "") . "
            GROUP BY 
                p.product_id
            ORDER BY 
                total_sold DESC
            LIMIT 20
        ";
        
        $params = [$start_date, $end_date . ' 23:59:59'];
        if ($category) {
            $params[] = $category;
        }
        
        $stmt = $_db->prepare($top_products_query);
        $stmt->execute($params);
        $top_products = $stmt->fetchAll();
    }
    
    // LOW STOCK REPORT
    if ($view == 'low_stock' || $view == 'inventory') {
        $threshold = 10; // Items with stock below this are considered low
        
        $low_stock_query = "
            SELECT 
                p.product_id,
                p.product_name,
                p.product_pic1,
                p.product_type,
                p.product_status,
                c.category_name,
                q.size,
                q.product_stock,
                q.product_sold
            FROM 
                product p
                JOIN quantity q ON p.product_id = q.product_id
                JOIN category c ON p.category_id = c.category_id
            WHERE 
                q.product_stock <= ?
                " . ($category ? "AND p.category_id = ?" : "") . "
            ORDER BY 
                q.product_stock ASC,
                p.product_name
        ";
        
        $params = [$threshold];
        if ($category) {
            $params[] = $category;
        }
        
        $stmt = $_db->prepare($low_stock_query);
        $stmt->execute($params);
        $low_stock_items = $stmt->fetchAll();
    }
    
    // CATEGORY SALES REPORT
    if ($view == 'category_sales' || $view == 'dashboard') {
        $category_query = "
            SELECT 
                c.category_name,
                COUNT(DISTINCT od.order_id) as num_orders,
                SUM(od.quantity) as items_sold,
                SUM(od.unit_price * od.quantity) as revenue
            FROM 
                category c
                JOIN product p ON c.category_id = p.category_id
                JOIN order_details od ON p.product_id = od.product_id
                JOIN orders o ON od.order_id = o.order_id
                JOIN payment pay ON o.order_id = pay.order_id
            WHERE 
                pay.payment_status = 'Completed'
                AND pay.payment_date BETWEEN ? AND ?
            GROUP BY 
                c.category_id
            ORDER BY 
                revenue DESC
        ";
        
        $stmt = $_db->prepare($category_query);
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $category_sales = $stmt->fetchAll();
    }
    
    // CUSTOMER DATA REPORT
    if ($view == 'customers') {
        $customer_query = "
            SELECT 
                u.user_id,
                u.user_name,
                u.user_Email,
                COUNT(DISTINCT o.order_id) as total_orders,
                SUM(p.total_amount) as total_spent,
                MAX(p.payment_date) as last_purchase_date
            FROM 
                user u
                LEFT JOIN orders o ON u.user_id = o.user_id
                LEFT JOIN payment p ON o.order_id = p.order_id
            WHERE 
                u.role = 'member'
                AND (p.payment_status = 'Completed' OR p.payment_status IS NULL)
            GROUP BY 
                u.user_id
            ORDER BY 
                total_spent DESC
        ";
        
        $stmt = $_db->prepare($customer_query);
        $stmt->execute();
        $customer_data = $stmt->fetchAll();
    }
    
    // Get all categories for filter
    $category_filter_query = "SELECT category_id, category_name FROM category ORDER BY category_name";
    $stmt = $_db->prepare($category_filter_query);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
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
    <style>
        .tab-active {
            border-bottom: 3px solid #4F46E5;
            color: #4F46E5;
            font-weight: bold;
        }
        .report-card {
            transition: all 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hidden inputs for chart data -->
    <input type="hidden" id="chartLabels" value='<?= json_encode($chart_labels) ?>'>
    <input type="hidden" id="chartValues" value='<?= json_encode($chart_values) ?>'>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Analytics & Reports</h1>
            <a href="home.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Report Navigation Tabs -->
        <div class="flex flex-wrap border-b border-gray-200 mb-6">
            <a href="?view=sales" class="px-6 py-3 text-lg <?= $view === 'sales' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-chart-line mr-2"></i> Sales Reports
            </a>
            <a href="?view=top_products" class="px-6 py-3 text-lg <?= $view === 'top_products' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-trophy mr-2"></i> Top Products
            </a>
            <a href="?view=category_sales" class="px-6 py-3 text-lg <?= $view === 'category_sales' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-folder mr-2"></i> Category Analysis
            </a>
            <a href="?view=low_stock" class="px-6 py-3 text-lg <?= $view === 'low_stock' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-exclamation-triangle mr-2"></i> Low Stock
            </a>
            <a href="?view=customers" class="px-6 py-3 text-lg <?= $view === 'customers' ? 'tab-active' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-users mr-2"></i> Customer Insights
            </a>
        </div>
        
        <!-- Filters Section -->
        <form method="GET" class="bg-white p-6 rounded-lg shadow mb-8">
            <input type="hidden" name="view" value="<?= $view ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php if ($view !== 'low_stock' && $view !== 'customers'): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select name="period" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" onchange="toggleCustomDates(this.value)">
                        <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div id="custom-date-range" class="<?= $period === 'custom' ? 'grid grid-cols-2 gap-4' : 'hidden' ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" value="<?= $start_date ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" value="<?= $end_date ?>">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat->category_id ?>" <?= $category === $cat->category_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat->category_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Sales Reports View -->
        <?php if ($view == 'sales'): ?>
        <div>
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="report-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-green-500">
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-lg font-medium text-gray-600">Total Revenue</h3>
                            <span class="p-2 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-dollar-sign"></i>
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-800">RM<?= number_format($totals->total_revenue ?? 0, 2) ?></p>
                        <p class="mt-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></p>
                    </div>
                </div>
                
                <!-- Total Orders -->
                <div class="report-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-blue-500">
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-lg font-medium text-gray-600">Total Orders</h3>
                            <span class="p-2 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-shopping-cart"></i>
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totals->total_orders ?? 0) ?></p>
                        <p class="mt-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></p>
                    </div>
                </div>
                
                <!-- Items Sold -->
                <div class="report-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-purple-500">
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-lg font-medium text-gray-600">Items Sold</h3>
                            <span class="p-2 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-box"></i>
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($totals->total_items_sold ?? 0) ?></p>
                        <p class="mt-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></p>
                    </div>
                </div>
                
                <!-- Average Order Value -->
                <div class="report-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-yellow-500">
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-lg font-medium text-gray-600">Avg. Order Value</h3>
                            <span class="p-2 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-chart-bar"></i>
                            </span>
                        </div>
                        <p class="text-3xl font-bold text-gray-800">RM<?= number_format($totals->avg_order_value ?? 0, 2) ?></p>
                        <p class="mt-2 text-sm text-gray-600">Per order average</p>
                    </div>
                </div>
            </div>
            
            <!-- Sales Trend Chart -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Sales Trend</h2>
                <div class="h-80">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <!-- Sales Data Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Sales Data</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue (RM)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Order (RM)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($sales_data)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No sales data available for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_data as $row): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($row->period) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            RM<?= number_format($row->revenue, 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row->orders) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row->items_sold) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            RM<?= number_format($row->orders > 0 ? $row->revenue / $row->orders : 0, 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Top Products View -->
        <?php if ($view == 'top_products'): ?>
        <div>
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Top Selling Products</h2>
                
                <?php if (empty($top_products)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-box-open text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500">No product sales data available for the selected period</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="report-card bg-white rounded-lg border border-gray-200 overflow-hidden flex flex-col">
                                <div class="relative bg-gray-100 h-48">
                                    <?php if ($product->product_pic1): ?>
                                        <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-tshirt text-gray-400 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Ranking Badge -->
                                    <?php if ($index < 3): ?>
                                        <div class="absolute top-2 left-2 w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm
                                            <?= $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-400' : 'bg-amber-700') ?>">
                                            #<?= $index + 1 ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-4 flex-grow">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800 mb-2">
                                        <?= htmlspecialchars($product->category_name) ?>
                                    </span>
                                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($product->product_name) ?></h3>
                                    <p class="text-sm text-gray-500 mb-3"><?= htmlspecialchars($product->product_type) ?></p>
                                    
                                    <div class="flex justify-between items-center mt-2 mb-1">
                                        <span class="text-sm font-medium text-gray-600">Price:</span>
                                        <span class="text-sm font-medium">RM<?= number_format($product->product_price, 2) ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-600">Units Sold:</span>
                                        <span class="text-sm font-bold text-green-600"><?= number_format($product->total_sold) ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-600">Revenue:</span>
                                        <span class="text-sm font-bold text-green-600">RM<?= number_format($product->total_revenue, 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="border-t border-gray-200 px-4 py-3">
                                    <a href="../product/Detail_Product.php?id=<?= $product->product_id ?>" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center justify-center">
                                        <i class="fas fa-eye mr-2"></i> View Product Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Category Sales View -->
        <?php if ($view == 'category_sales'): ?>
        <div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Category Performance Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Category Performance</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($category_sales)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No category data available for the selected period</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($category_sales as $cat): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($cat->category_name) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= number_format($cat->num_orders) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= number_format($cat->items_sold) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                RM<?= number_format($cat->revenue, 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Category Sales Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Category Revenue Breakdown</h2>
                    <div class="h-80">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Low Stock View -->
        <?php if ($view == 'low_stock'): ?>
        <div>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Low Stock Items</h2>
                    <p class="text-sm text-gray-600 mt-1">Showing products with less than 10 items in stock</p>
                </div>
                
                <?php if (empty($low_stock_items)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
                        <p class="text-gray-500">Great! No products are currently low on stock.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($low_stock_items as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 rounded overflow-hidden bg-gray-100">
                                                    <?php if ($item->product_pic1): ?>
                                                        <img src="../../img/<?= $item->product_pic1 ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="h-full w-full object-cover">
                                                    <?php else: ?>
                                                        <div class="h-full w-full flex items-center justify-center">
                                                            <i class="fas fa-tshirt text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item->product_name) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($item->product_id) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item->category_name) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item->size) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($item->product_stock <= 0): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Out of Stock
                                                </span>
                                            <?php elseif ($item->product_stock <= 5): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Critical: <?= $item->product_stock ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Low: <?= $item->product_stock ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($item->product_sold) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = match ($item->product_status) {
                                                'Available' => 'bg-green-100 text-green-800',
                                                'Out of Stock' => 'bg-red-100 text-red-800',
                                                'Discontinued' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                                <?= htmlspecialchars($item->product_status) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="../product/Update_Product.php?id=<?= $item->product_id ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">Update</a>
                                            <a href="../product/Detail_Product.php?id=<?= $item->product_id ?>" class="text-gray-600 hover:text-gray-900">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Customer Insights View -->
        <?php if ($view == 'customers'): ?>
        <div>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Customer Purchase Insights</h2>
                </div>
                
                <?php if (empty($customer_data)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-users text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500">No customer data available</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Purchase</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($customer_data as $customer): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($customer->user_name ?: 'N/A') ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($customer->user_id) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($customer->user_Email) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= number_format($customer->total_orders) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            RM<?= number_format($customer->total_spent, 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $customer->last_purchase_date ? date('M d, Y', strtotime($customer->last_purchase_date)) : 'Never' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="../customer/view_customer.php?id=<?= $customer->user_id ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php require '../headFooter/footer.php'; ?>

    <script>
        // Toggle custom date range inputs
        function toggleCustomDates(value) {
            const customDateDiv = document.getElementById('custom-date-range');
            if (value === 'custom') {
                customDateDiv.classList.remove('hidden');
                customDateDiv.classList.add('grid');
            } else {
                customDateDiv.classList.add('hidden');
                customDateDiv.classList.remove('grid');
            }
        }
        
        // Sales Chart
        <?php if ($view == 'sales' && !empty($chart_labels)): ?>
        const salesChartCtx = document.getElementById('salesChart').getContext('2d');
        const chartLabels = JSON.parse(document.getElementById('chartLabels').value);
        const chartValues = JSON.parse(document.getElementById('chartValues').value);
        
        new Chart(salesChartCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue (RM)',
                    data: chartValues,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 1
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
        <?php endif; ?>
        
        // Category Chart
        <?php if ($view == 'category_sales' && !empty($category_sales)): ?>
        const categoryChartCtx = document.getElementById('categoryChart').getContext('2d');
        
        new Chart(categoryChartCtx, {
            type: 'pie',
            data: {
                labels: [<?= implode(', ', array_map(fn($cat) => "'" . addslashes($cat->category_name) . "'", $category_sales)) ?>],
                datasets: [{
                    data: [<?= implode(', ', array_map(fn($cat) => $cat->revenue, $category_sales)) ?>],
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.6)',
                        'rgba(16, 185, 129, 0.6)',
                        'rgba(245, 158, 11, 0.6)',
                        'rgba(239, 68, 68, 0.6)',
                        'rgba(59, 130, 246, 0.6)',
                        'rgba(139, 92, 246, 0.6)',
                        'rgba(236, 72, 153, 0.6)',
                    ],
                    borderColor: [
                        'rgba(79, 70, 229, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(236, 72, 153, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return context.label + ': RM' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>