<?php
$_title = 'Product';
require '../../_base.php';
auth(0, 1);
require 'header.php';

// (1) Sorting
$fields = [
    'product_id'        => 'Product ID',
    'product_name'      => 'Name',
    'product_price'     => 'Price',
    'total_stock'       => 'Stock',
    'category_name'     => 'Category',
    'product_status'    => 'Status',
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'product_id';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// (2) Paging
$page = req('page', 1);
$searchTerm = req('searchTerm');

if (!function_exists('req')) {
    function req($key, $default = '')
    {
        return $_REQUEST[$key] ?? $default;
    }
}

if (!function_exists('key_exists')) {
    function key_exists($key, $array)
    {
        return array_key_exists($key, $array);
    }
}

// (3) Auto-update products with zero stock to "Out of Stock"
try {
    // Query to find products with no stock
    $zero_stock_query = "SELECT p.product_id
                        FROM product p
                        LEFT JOIN (
                            SELECT product_id, SUM(product_stock) as total_stock
                            FROM quantity
                            GROUP BY product_id
                        ) q ON p.product_id = q.product_id
                        WHERE q.total_stock = 0 OR q.total_stock IS NULL";

    $zero_stock_stmt = $_db->prepare($zero_stock_query);
    $zero_stock_stmt->execute();
    $zero_stock_products = $zero_stock_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Update status for zero stock products
    if (!empty($zero_stock_products)) {
        // Safer way to create placeholders
        $placeholders = implode(',', array_fill(0, count($zero_stock_products), '?'));
        $update_query = "UPDATE product SET product_status = 'Out of Stock' WHERE product_id IN ($placeholders)";
        $update_stmt = $_db->prepare($update_query);
        $update_stmt->execute($zero_stock_products);

        // Log the update
        error_log("Updated " . count($zero_stock_products) . " products to 'Out of Stock'");
    }
} catch (PDOException $e) {
    error_log("Error updating product status: " . $e->getMessage());
}

// (4) Search and Pagination
require_once '../lib/SimplePager.php';

// Query to get products with total stock calculated
$query = "SELECT p.product_id, p.product_name, p.product_price, 
                 p.product_status, c.category_name,
                 COALESCE(SUM(q.product_stock), 0) as total_stock
          FROM product p
          LEFT JOIN category c ON p.category_id = c.category_id
          LEFT JOIN quantity q ON p.product_id = q.product_id
          WHERE (
                p.product_id LIKE ? OR 
                p.product_name LIKE ? OR 
                p.product_price LIKE ? OR 
                c.category_name LIKE ? OR 
                p.product_status LIKE ?
          )
          GROUP BY p.product_id, p.product_name, p.product_price, p.product_status, c.category_name
          ORDER BY 
            CASE WHEN COALESCE(SUM(q.product_stock), 0) < 10 THEN 0 ELSE 1 END, 
            $sort $dir";

// Prepare search parameters
$searchParam = "%$searchTerm%";
$params = [
    $searchParam,   // product_id
    $searchParam,   // product_name
    $searchParam,   // product_price
    $searchParam,   // category_name
    $searchParam    // product_status
];

$p = new SimplePager($query, $params, 10, $page);
$products = $p->result;

// Chart data for inventory visualization
$chart_query = "SELECT p.product_id, c.category_name, 
                       COALESCE(SUM(q.product_stock), 0) as total_stock
                FROM product p
                LEFT JOIN category c ON p.category_id = c.category_id
                LEFT JOIN quantity q ON p.product_id = q.product_id
                GROUP BY p.product_id, c.category_name";

$chart_stm = $_db->prepare($chart_query);
$chart_stm->execute();
$chart_data = $chart_stm->fetchAll(PDO::FETCH_ASSOC);

// Calculate max stock for chart scaling
$maxStockQty = !empty($chart_data) ? max(array_column($chart_data, 'total_stock')) : 0;

$labels = [];
$data = [];
$backgroundColors = [];
$categories = [];

foreach ($chart_data as $row) {
    $labels[] = $row['product_id'];
    $data[] = $row['total_stock'];
    $categories[] = $row['category_name'] ?: 'Uncategorized';

    // Color coding based on stock levels
    $percentage = $maxStockQty > 0 ? ($row['total_stock'] / $maxStockQty) * 100 : 0;
    $backgroundColors[] = match (true) {
        $percentage >= 50 => 'rgba(34, 197, 94, 0.8)', // Green
        $percentage >= 20 => 'rgba(234, 179, 8, 0.8)',  // Yellow
        default => 'rgba(239, 68, 68, 0.8)'            // Red
    };
}

// Low stock check (total_stock < 10)
$lowStockProducts = array_filter(
    $products,
    fn($product) =>
    $product->total_stock < 10
);
$showModal = !empty($lowStockProducts);

// Get category list for the dropdown
$category_query = "SELECT category_id, category_name FROM category ORDER BY category_name";
$category_stmt = $_db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom styles */
        :root {
            --primary: #4338ca;
            --primary-hover: #3730a3;
            --secondary: #6366f1;
            --accent: #4f46e5;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .btn-primary {
            background-color: #000000;
            color: white;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #333333;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #000000;
            color: white;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }

        .btn-secondary:hover {
            background-color: #333333;
            transform: translateY(-1px);
        }

        .card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .search-input {
            transition: all 0.3s ease;
            border-color: #e2e8f0;
            border-width: 1px;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            text-align: left;
        }

        table th a {
            color: #475569;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        table th a:hover {
            color: var(--primary);
        }

        table th a.asc:after {
            content: "↑";
            margin-left: 0.5rem;
        }

        table th a.desc:after {
            content: "↓";
            margin-left: 0.5rem;
        }

        table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background-color: #f8fafc;
        }

        /* Status colors */
        .status-available {
            color: #22c55e;
            font-weight: 600;
        }

        .status-out-of-stock {
            color: #ef4444;
            font-weight: 600;
        }

        .status-discontinued {
            color: #94a3b8;
            font-weight: 600;
        }

        /* Badge styling */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .badge-green {
            background-color: #dcfce7;
            color: #22c55e;
        }

        .badge-yellow {
            background-color: #fef3c7;
            color: #f59e0b;
        }

        /* Pagination styling */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 1.5rem;
            gap: 0.25rem;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 0.75rem;
            background-color: white;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 2.5rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f1f5f9;
            color: var(--primary);
            transform: translateY(-1px);
        }

        .pagination-btn-active {
            background-color: #000000;
            color: white;
            border-color: #000000;
        }

        .pagination-btn-active:hover {
            background-color: #333333;
            color: white;
        }

        .pagination-btn:disabled {
            background-color: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            position: relative;
            background-color: white;
            margin: 10% auto;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 90%;
            max-width: 600px;
            animation: slideIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close {
            position: absolute;
            top: 0.75rem;
            right: 1rem;
            color: #94a3b8;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #475569;
        }

        /* Status tooltip */
        .status-tooltip {
            position: relative;
            cursor: pointer;
        }

        .status-tooltip .tooltip-text {
            visibility: hidden;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #334155;
            color: white;
            text-align: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .status-tooltip .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #334155 transparent transparent transparent;
        }

        .status-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Refresh with animation */
        .refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            transform: scale(1.05);
        }

        .refresh-btn i {
            transition: transform 0.5s ease;
        }

        .refresh-btn:hover i {
            transform: rotate(180deg);
        }

        /* Animation for new items added */
        @keyframes highlight {
            0% {
                background-color: rgba(99, 102, 241, 0.3);
            }

            100% {
                background-color: transparent;
            }
        }

        .highlight {
            animation: highlight 2s ease-out;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-center mb-2 text-indigo-900">Product Inventory Management</h1>
            <p class="text-center text-gray-600">Manage your product catalog, stock levels, and categories</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons and Search -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Buttons -->
            <div class="flex flex-wrap gap-3">
                <a href="Insert_Product.php" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Product
                </a>
                <button id="addCategoryButton" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-tags"></i> Add Category
                </button>
                <button id="productChartButton" class="btn-secondary flex items-center gap-2">
                    <i class="fas fa-chart-bar"></i> Stock Chart
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['refresh' => 1])) ?>" class="refresh-btn btn-secondary flex items-center">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
            </div>

            <!-- Search -->
            <div class="flex">
                <form method="get" class="flex w-full">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                    <div class="relative flex-grow">
                        <input
                            type="text"
                            id="searchInput"
                            name="searchTerm"
                            placeholder="Search products by ID, name, price, category..."
                            value="<?= htmlspecialchars($searchTerm) ?>"
                            class="search-input w-full pr-8">
                        <?php if (!empty($searchTerm)): ?>
                            <button
                                type="button"
                                id="clearSearch"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <button
                        type="submit"
                        class="btn-primary rounded-l-none">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <?php
            // Total Products
            $total_query = "SELECT COUNT(*) FROM product";
            $total_stmt = $_db->prepare($total_query);
            $total_stmt->execute();
            $total_products = $total_stmt->fetchColumn();

            // Available Products
            $available_query = "SELECT COUNT(*) FROM product WHERE product_status = 'Available'";
            $available_stmt = $_db->prepare($available_query);
            $available_stmt->execute();
            $available_products = $available_stmt->fetchColumn();

            // Out of Stock Products
            $out_of_stock_query = "SELECT COUNT(*) FROM product WHERE product_status = 'Out of Stock'";
            $out_of_stock_stmt = $_db->prepare($out_of_stock_query);
            $out_of_stock_stmt->execute();
            $out_of_stock_products = $out_of_stock_stmt->fetchColumn();

            // Total Categories
            $category_count_query = "SELECT COUNT(*) FROM category";
            $category_count_stmt = $_db->prepare($category_count_query);
            $category_count_stmt->execute();
            $total_categories = $category_count_stmt->fetchColumn();
            ?>

            <!-- Total Products Card -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-indigo-100 p-3 mr-4">
                    <i class="fas fa-boxes text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $total_products ?></h3>
                    <p class="text-sm text-gray-600">Total Products</p>
                </div>
            </div>

            <!-- Available Products Card -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $available_products ?></h3>
                    <p class="text-sm text-gray-600">Available Products</p>
                </div>
            </div>

            <!-- Out of Stock Products Card -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-red-100 p-3 mr-4">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $out_of_stock_products ?></h3>
                    <p class="text-sm text-gray-600">Out of Stock</p>
                </div>
            </div>

            <!-- Total Categories Card -->
            <div class="card p-4 flex items-center">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <i class="fas fa-tags text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900"><?= $total_categories ?></h3>
                    <p class="text-sm text-gray-600">Categories</p>
                </div>
            </div>
        </div>

        <!-- Pagination Info -->
        <div class="flex items-center justify-between mb-4">
            <p class="text-gray-600">
                <span class="font-medium"><?= $p->count ?></span> of <span class="font-medium"><?= $p->item_count ?></span> product(s) |
                Page <span class="font-medium"><?= $p->page ?></span> of <span class="font-medium"><?= $p->page_count ?></span>
            </p>

            <!-- Items per page selector (optional) -->
            <div class="flex items-center">
                <label for="itemsPerPage" class="text-sm text-gray-600 mr-2">Show:</label>
                <select id="itemsPerPage" class="search-input text-sm py-1" onchange="changeItemsPerPage(this.value)">
                    <option value="10" <?= $p->limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $p->limit == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $p->limit == 50 ? 'selected' : '' ?>>50</option>
                </select>
            </div>
        </div>

        <!-- Product Table -->
        <div class="card overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <?= table_headers($fields, $sort, $dir, "page=$page&searchTerm=" . urlencode($searchTerm)) ?>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?= count($fields) + 1 ?>" class="px-4 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-search text-4xl text-gray-300 mb-3"></i>
                                        <p class="text-lg font-medium mb-2">No products found</p>
                                        <p class="text-sm mb-4">Try adjusting your search or add a new product</p>
                                        <a href="Insert_Product.php" class="btn-primary flex items-center gap-2">
                                            <i class="fas fa-plus"></i> Add Product
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="border-b hover:bg-gray-50 transition <?= isset($_GET['highlight']) && $_GET['highlight'] == $product->product_id ? 'highlight' : '' ?>">
                                    <td class="px-4 py-3"><?= htmlspecialchars($product->product_id) ?></td>
                                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($product->product_name) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars(number_format($product->product_price, 2)) ?></td>

                                    <!-- Stock column with visual indicator -->
                                    <td class="px-4 py-3">
                                        <?php if ($product->total_stock < 10 && $product->total_stock > 0): ?>
                                            <span class="badge badge-yellow">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($product->total_stock) ?>
                                            </span>
                                        <?php elseif ($product->total_stock <= 0): ?>
                                            <span class="badge badge-red">
                                                <i class="fas fa-times-circle mr-1"></i> <?= htmlspecialchars($product->total_stock) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-green">
                                                <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($product->total_stock) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-4 py-3"><?= htmlspecialchars($product->category_name ?? 'Uncategorized') ?></td>

                                    <!-- Status column with clickable status -->
                                    <td class="px-4 py-3">
                                        <div
                                            class="status-cell status-tooltip"
                                            data-id="<?= $product->product_id ?>"
                                            data-current-status="<?= htmlspecialchars($product->product_status) ?>"
                                            data-current-stock="<?= $product->total_stock ?>">
                                            <span class="tooltip-text">Click to change status</span>
                                            <?php
                                            $statusClass = match ($product->product_status) {
                                                'Available' => 'status-available',
                                                'Out of Stock' => 'status-out-of-stock',
                                                'Discontinued' => 'status-discontinued',
                                                default => ''
                                            };
                                            $statusIcon = match ($product->product_status) {
                                                'Available' => '<i class="fas fa-check-circle mr-1"></i>',
                                                'Out of Stock' => '<i class="fas fa-times-circle mr-1"></i>',
                                                'Discontinued' => '<i class="fas fa-ban mr-1"></i>',
                                                default => ''
                                            };
                                            ?>
                                            <span class="<?= $statusClass ?> flex items-center">
                                                <?= $statusIcon ?> <?= htmlspecialchars($product->product_status) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions column -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <a href="Detail_Product.php?id=<?= $product->product_id ?>" class="btn-primary py-1 px-2 text-xs">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                            <a href="Update_Product.php?id=<?= $product->product_id ?>" class="btn-secondary py-1 px-2 text-xs">
                                                <i class="fas fa-edit"></i> Update
                                            </a>
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
            <?php
            // Base URL for pagination links
            $baseUrl = "?sort=$sort&dir=$dir&searchTerm=" . urlencode($searchTerm);

            // Calculate pagination ranges
            $firstPage = 1;
            $lastPage = $p->page_count;
            $prevPage = max(1, $p->page - 1);
            $nextPage = min($lastPage, $p->page + 1);

            // First button
            echo "<a href='{$baseUrl}&page={$firstPage}' class='pagination-btn" . ($p->page == 1 ? " opacity-50 cursor-not-allowed" : "") . "' " .
                ($p->page == 1 ? "disabled" : "") . ">" .
                "<i class='fas fa-angle-double-left'></i></a>";

            // Previous button
            echo "<a href='{$baseUrl}&page={$prevPage}' class='pagination-btn" . ($p->page == 1 ? " opacity-50 cursor-not-allowed" : "") . "' " .
                ($p->page == 1 ? "disabled" : "") . ">" .
                "<i class='fas fa-angle-left'></i></a>";

            // Page numbers
            $startPage = max(1, min($p->page - 2, $lastPage - 4));
            $endPage = min($lastPage, max(5, $p->page + 2));

            // Adjust to show at least 5 pages if possible
            if ($endPage - $startPage + 1 < 5 && $lastPage >= 5) {
                if ($startPage == 1) {
                    $endPage = min($lastPage, 5);
                } elseif ($endPage == $lastPage) {
                    $startPage = max(1, $lastPage - 4);
                }
            }

            // Page number buttons
            for ($i = $startPage; $i <= $endPage; $i++) {
                $activeClass = $i == $p->page ? " pagination-btn-active" : "";
                echo "<a href='{$baseUrl}&page={$i}' class='pagination-btn{$activeClass}'>{$i}</a>";
            }

            // Next button
            echo "<a href='{$baseUrl}&page={$nextPage}' class='pagination-btn" . ($p->page == $lastPage ? " opacity-50 cursor-not-allowed" : "") . "' " .
                ($p->page == $lastPage ? "disabled" : "") . ">" .
                "<i class='fas fa-angle-right'></i></a>";

            // Last button
            echo "<a href='{$baseUrl}&page={$lastPage}' class='pagination-btn" . ($p->page == $lastPage ? " opacity-50 cursor-not-allowed" : "") . "' " .
                ($p->page == $lastPage ? "disabled" : "") . ">" .
                "<i class='fas fa-angle-double-right'></i></a>";
            ?>
        </div>
    </div>

    <!-- Low Stock Alert Modal -->
    <div id="lowStockModal" class="modal" style="<?= $showModal ? 'display: block;' : '' ?>">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="mb-4">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-2"></i>
                <h2 class="text-xl font-bold text-gray-900">Low Stock Alert</h2>
                <p class="text-gray-600">The following products have low stock (less than 10 units) and require attention:</p>
            </div>
            <div class="max-h-60 overflow-y-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Product ID</th>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Stock</th>
                            <th class="px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= htmlspecialchars($product->product_id) ?></td>
                                <td class="px-4 py-2 font-medium"><?= htmlspecialchars($product->product_name) ?></td>
                                <td class="px-4 py-2">
                                    <span class="badge <?= $product->total_stock > 0 ? 'badge-yellow' : 'badge-red' ?>">
                                        <?= htmlspecialchars($product->total_stock) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="Update_Product.php?id=<?= $product->product_id ?>" class="btn-primary py-1 px-2 text-xs">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button id="closeModalBtn" class="btn-primary">
                    Acknowledge
                </button>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-900">Add New Category</h2>
                <p class="text-gray-600">Create a new category for your products</p>
            </div>
            <form id="categoryForm" method="post" action="process_category.php">
                <div class="mb-4">
                    <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" id="category_name" name="category_name" required
                        class="search-input w-full" placeholder="Enter category name">
                </div>
                <div class="mb-4">
                    <label for="category_description" class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <textarea id="category_description" name="category_description"
                        class="search-input w-full h-24" placeholder="Enter category description"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-1"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Chart Modal -->
    <div id="productChartModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close">&times;</span>
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-900">Product Stock Overview</h2>
                <p class="text-gray-600">Visual representation of your current inventory</p>
            </div>
            <div>
                <canvas id="productChart" height="300"></canvas>
            </div>
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Stock Level Legend</h3>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-500 opacity-80 mr-2 rounded-sm"></div>
                        <span class="text-sm text-gray-700">Healthy (50%+ of max)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-500 opacity-80 mr-2 rounded-sm"></div>
                        <span class="text-sm text-gray-700">Warning (20-49% of max)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-500 opacity-80 mr-2 rounded-sm"></div>
                        <span class="text-sm text-gray-700">Critical (< 20% of max)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusChangeModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-900">Change Product Status</h2>
                <p class="text-gray-600">Update the status for <span id="productNameInModal" class="font-medium"></span></p>
            </div>
            <form id="statusChangeForm">
                <input type="hidden" id="productIdInput" name="product_id">
                <div class="mb-4">
                    <label for="product_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="product_status" name="product_status" class="search-input w-full" required>
                        <option value="Available">Available</option>
                        <option value="Out of Stock">Out of Stock</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>
                <div id="warningMessage" class="mb-4 p-3 bg-yellow-50 text-yellow-800 rounded-md border border-yellow-200 hidden">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="warningText"></span>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="btn-secondary mr-2" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Clear search button
            $('#clearSearch').click(function() {
                window.location.href = '?sort=<?= $sort ?>&dir=<?= $dir ?>';
            });

            // Change items per page
            window.changeItemsPerPage = function(limit) {
                window.location.href = '?sort=<?= $sort ?>&dir=<?= $dir ?>&searchTerm=<?= urlencode($searchTerm) ?>&limit=' + limit;
            };

            // Modal handling
            const modals = {
                lowStock: document.getElementById('lowStockModal'),
                addCategory: document.getElementById('addCategoryModal'),
                productChart: document.getElementById('productChartModal'),
                statusChange: document.getElementById('statusChangeModal')
            };

            // Close buttons
            document.querySelectorAll('.close, [data-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    for (const modal in modals) {
                        modals[modal].style.display = 'none';
                    }
                });
            });

            // Close modal with escape key
            window.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    for (const modal in modals) {
                        modals[modal].style.display = 'none';
                    }
                }
            });

            // Close when clicking outside of modal content
            window.addEventListener('click', function(event) {
                for (const modal in modals) {
                    if (event.target === modals[modal]) {
                        modals[modal].style.display = 'none';
                    }
                }
            });

            // Modal trigger buttons
            $('#closeModalBtn').click(function() {
                modals.lowStock.style.display = 'none';
            });

            $('#addCategoryButton').click(function() {
                modals.addCategory.style.display = 'block';
            });

            $('#productChartButton').click(function() {
                modals.productChart.style.display = 'block';
                renderProductChart();
            });

            // Status change handling
            $('.status-cell').click(function() {
                const productId = $(this).data('id');
                const currentStatus = $(this).data('current-status');
                const currentStock = $(this).data('current-stock');

                // Set form values
                $('#productIdInput').val(productId);
                $('#product_status').val(currentStatus);
                $('#productNameInModal').text('Product ID: ' + productId);

                // Show warnings based on status and stock
                const warningMessage = $('#warningMessage');
                const warningText = $('#warningText');

                if (currentStock <= 0 && currentStatus !== 'Out of Stock') {
                    warningMessage.removeClass('hidden');
                    warningText.text('This product has zero stock. Consider setting status to "Out of Stock".');
                } else if (currentStock > 0 && currentStatus === 'Out of Stock') {
                    warningMessage.removeClass('hidden');
                    warningText.text('This product has stock available. Consider setting status to "Available".');
                } else {
                    warningMessage.addClass('hidden');
                }

                // Show modal
                modals.statusChange.style.display = 'block';
            });

            // Handle status change form submission
            $('#statusChangeForm').submit(function(e) {
                e.preventDefault();
                const productId = $('#productIdInput').val();
                const newStatus = $('#product_status').val();

                // AJAX to update status
                $.ajax({
                    url: 'update_product_status.php',
                    type: 'POST',
                    data: {
                        product_id: productId,
                        product_status: newStatus
                    },
                    success: function(response) {
                        // Close modal
                        modals.statusChange.style.display = 'none';

                        // Refresh page with highlight
                        window.location.href = '?sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $page ?>&searchTerm=<?= urlencode($searchTerm) ?>&highlight=' + productId;
                    },
                    error: function() {
                        alert('An error occurred while updating the product status.');
                    }
                });
            });

            // Chart rendering function
            function renderProductChart() {
                const ctx = document.getElementById('productChart').getContext('2d');

                // Prepare chart data
                const labels = <?= json_encode($labels) ?>;
                const data = <?= json_encode($data) ?>;
                const categories = <?= json_encode($categories) ?>;
                const backgroundColors = <?= json_encode($backgroundColors) ?>;

                // Create chart
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Stock Level',
                            data: data,
                            backgroundColor: backgroundColors,
                            borderColor: 'rgba(0, 0, 0, 0.1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Stock Quantity'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Product ID'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    afterLabel: function(context) {
                                        const index = context.dataIndex;
                                        return 'Category: ' + categories[index];
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>