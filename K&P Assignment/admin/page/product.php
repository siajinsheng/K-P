<?php
$_title = 'Product';
require '../../_base.php';
auth(0, 1);
require 'header.php';

// (1) Sorting - Updated to include stock and category
$fields = [
    'product_id'        => 'Product ID',
    'product_name'      => 'Name',
    'product_price'     => 'Price',
    'product_stock'     => 'Stock',
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

// (3) Auto-update products with zero stock to Inactive
try {
    $update_zero_stock_query = "UPDATE product SET product_status = 'Inactive' WHERE product_stock = 0";
    $update_stmt = $_db->prepare($update_zero_stock_query);
    $update_stmt->execute();
} catch (PDOException $e) {
    error_log("Error updating product status: " . $e->getMessage());
}

// (4) Search and Pagination
require_once '../lib/SimplePager.php';

// Modified query to include comprehensive search across multiple fields
$query = "SELECT p.product_id, p.product_name, p.product_price, 
                 p.product_status, p.product_stock, 
                 COALESCE(c.category_name, 'Uncategorized') AS category_name
          FROM product p
          LEFT JOIN category c ON p.product_id = c.product_id
          WHERE (
                p.product_id LIKE ? OR 
                p.product_name LIKE ? OR 
                p.product_price LIKE ? OR 
                p.product_stock LIKE ? OR 
                COALESCE(c.category_name, 'Uncategorized') LIKE ? OR 
                p.product_status LIKE ?
          )
          ORDER BY 
            CASE WHEN p.product_stock < 10 THEN 0 ELSE 1 END, 
            $sort $dir";

// Prepare search parameters - use the same search term for all fields
$searchParam = "%$searchTerm%";
$params = [
    $searchParam,   // product_id
    $searchParam,   // product_name
    $searchParam,   // product_price
    $searchParam,   // product_stock
    $searchParam,   // category_name
    $searchParam    // product_status
];

$p = new SimplePager($query, $params, 10, $page);
$products = $p->result;

// Chart data - adjusted to show stock
$chart_query = "SELECT p.product_id, p.product_stock, 
                       COALESCE(c.category_name, 'Uncategorized') AS category_name 
                FROM product p
                LEFT JOIN category c ON p.product_id = c.product_id";
$chart_stm = $_db->prepare($chart_query);
$chart_stm->execute();
$chart_data = $chart_stm->fetchAll(PDO::FETCH_ASSOC);

// Calculate max stock
$maxStockQty = max(array_column($chart_data, 'product_stock'));

$labels = [];
$data = [];
$backgroundColors = [];
$categories = [];

foreach ($chart_data as $row) {
    $labels[] = $row['product_id'];
    $data[] = $row['product_stock'];
    $categories[] = $row['category_name'];

    $percentage = ($row['product_stock'] / $maxStockQty) * 100;
    $backgroundColors[] = match (true) {
        $percentage >= 50 => 'green',
        $percentage >= 20 => 'yellow',
        default => 'red'
    };
}

// Low stock check (product_stock < 10)
$lowStockProducts = array_filter(
    $products,
    fn($product) =>
    $product->product_stock < 10
);
$showModal = !empty($lowStockProducts);

// ----------------------------------------------------------------------------
$_title = 'Product List';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom styles */
        .btn-black {
            background-color: black;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-black:hover {
            background-color: #333;
        }

        button {
            border-color: #000;
        }

        .search-input {
            transition: all 0.3s ease;
            border-color: #000;
        }

        .search-input:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.3);
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            padding: 8px 16px;
            border: 1px solid #000;
            background-color: black;
            color: white;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #333;
            transform: scale(1.05);
        }

        .pagination-btn:disabled {
            background-color: #888;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pagination-btn i {
            margin: 0 5px;
        }

        /* Rest of the previous styles... */
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
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .status-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .status-active {
            color: green;
        }

        .status-inactive {
            color: red;
        }

        .status-discontinued {
            color: gray;
        }

        /* Modal styles remain the same */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Status Interaction
            $('.status-cell').on('click', function() {
                const productId = $(this).data('id');
                const currentStatus = $(this).data('current-status');
                const currentStock = parseInt($(this).data('current-stock'));

                // Check if stock is 0
                if (currentStock === 0) {
                    alert('Cannot change status. Stock is 0. Please update stock first.');
                    window.location.href = 'Update_Product.php?id=' + productId;
                    return;
                }

                // Determine new status
                const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';

                // Confirm status change
                if (confirm(`Change product status from ${currentStatus} to ${newStatus}?`)) {
                    $.ajax({
                        url: 'Update_Product_Status.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            product_id: productId,
                            product_status: newStatus
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Product status updated successfully');
                                location.reload();
                            } else {
                                alert('Failed to update product status: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Failed to update product status');
                        }
                    });
                }
            });

            // Search Input Interaction
            $('#searchInput').on('input', function() {
                const searchTerm = $(this).val();
                if (searchTerm.length > 0) {
                    $('#clearSearch').removeClass('hidden');
                } else {
                    $('#clearSearch').addClass('hidden');
                }
            });

            $('#clearSearch').on('click', function() {
                $('#searchInput').val('');
                $(this).addClass('hidden');
            });

            // Product Stock Chart Modal
            $('#productChartButton').on('click', function() {
                $('#productChartModal').show();

                var ctx = document.getElementById('productChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels) ?>,
                        datasets: [{
                            label: 'Product Stock',
                            data: <?= json_encode($data) ?>,
                            backgroundColor: <?= json_encode($backgroundColors) ?>
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });

            // Modal close functionality
            $('.close').on('click', function() {
                $(this).closest('.modal').hide();
            });

            // Show low stock modal if applicable
            <?php if ($showModal): ?>
                $('#lowStockModal').show();
            <?php endif; ?>
        });
    </script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-6">Product List</h1>

        <div class="flex justify-between items-center mb-4">
            <div class="space-x-3">
                <?php html_button('Insert_Product.php', 'Insert Product', 'inline-block btn-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded'); ?>
                <button id="addCategoryButton" class="btn-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">Add Category</button>
                <button id="productChartButton" class="btn-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">Product Chart</button>
            </div>

            <form method="get" class="flex items-center">
                <div class="relative">
                    <input
                        type="text"
                        id="searchInput"
                        name="searchTerm"
                        placeholder="Search products..."
                        value="<?= htmlspecialchars($searchTerm) ?>"
                        class="search-input border-2 rounded-l-lg px-3 py-2 w-96">
                    <button
                        type="button"
                        id="clearSearch"
                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 <?= empty($searchTerm) ? 'hidden' : '' ?>">
                        ✕
                    </button>
                </div>
                <button
                    type="submit"
                    class="btn-black text-white px-4 py-2 rounded-r-lg hover:bg-gray-800 transition duration-300">
                    Search
                </button>
            </form>
        </div>

        <p class="text-gray-600 mb-4">
            <?= $p->count ?> of <?= $p->item_count ?> record(s) |
            Page <?= $p->page ?> of <?= $p->page_count ?>
        </p>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <?= table_headers($fields, $sort, $dir, "page=$page&searchTerm=" . urlencode($searchTerm)) ?>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="px-4 py-3"><?= htmlspecialchars($product->product_id) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($product->product_name) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars(number_format($product->product_price, 2)) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($product->product_stock) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($product->category_name ?? 'Uncategorized') ?></td>
                            <td class="px-4 py-3">
                                <div
                                    class="status-cell status-tooltip"
                                    data-id="<?= $product->product_id ?>"
                                    data-current-status="<?= htmlspecialchars($product->product_status) ?>"
                                    data-current-stock="<?= $product->product_stock ?>">
                                    <span class="tooltip-text">Click to change status</span>
                                    <span class="status-<?= strtolower(htmlspecialchars($product->product_status)) ?> font-bold">
                                        <?= htmlspecialchars($product->product_status) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 relative">
                                <div class="flex items-center space-x-2">
                                    <a href="Detail_Product.php?id=<?= $product->product_id ?>" class="btn-black hover:bg-gray-800 text-white font-bold py-1 px-2 rounded text-sm">Details</a>
                                    <a href="Update_Product.php?id=<?= $product->product_id ?>" class="btn-black hover:bg-gray-800 text-white font-bold py-1 px-2 rounded text-sm">Update</a>

                                    <?php if ($product->product_stock < 10): ?>
                                        <span class="text-red-500 font-bold">Low Stock</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Custom Pagination with Improved Design -->
        <div class="pagination-container">
            <?php
            // Modify the pagination links with custom styling
            $paginationLinks = $p->html("sort=$sort&dir=$dir&searchTerm=" . urlencode($searchTerm), true);

            // Custom button styling
            $customizedLinks = preg_replace_callback('/<(a|span)([^>]*)>([^<]*)<\/(a|span)>/', function ($matches) {
                $content = $matches[3];
                $attrs = $matches[2];

                // Map text to icons and add context
                $iconMap = [
                    '&laquo;' => '<i class="fas fa-angle-double-left"></i> First',
                    '&lsaquo;' => '<i class="fas fa-angle-left"></i> Previous',
                    '&raquo;' => 'Last <i class="fas fa-angle-double-right"></i>',
                    '&rsaquo;' => 'Next <i class="fas fa-angle-right"></i>'
                ];

                $buttonText = $iconMap[$content] ?? $content;

                // Determine button type and attributes
                if (strpos($attrs, 'disabled') !== false) {
                    return "<button $attrs class='pagination-btn' disabled>$buttonText</button>";
                } elseif (strpos($attrs, 'class="current"') !== false) {
                    return "<button $attrs class='pagination-btn bg-gray-700 text-white'>$buttonText</button>";
                } else {
                    return "<button $attrs class='pagination-btn'>$buttonText</button>";
                }
            }, $paginationLinks);

            echo $customizedLinks;
            ?>
        </div>
    </div>

    <!-- Low Stock Modal remains the same -->
    <div id="lowStockModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-2xl font-bold mb-4">Stock Warning</h2>
            <p class="mb-4">Some products are running low on stock (less than 10). Please check the inventory.</p>
            <ul class="list-disc pl-5">
                <?php foreach ($lowStockProducts as $lowProduct): ?>
                    <li>
                        <?= htmlspecialchars($lowProduct->product_name) ?>
                        (ID: <?= htmlspecialchars($lowProduct->product_id) ?>)
                        - Current Stock: <?= htmlspecialchars($lowProduct->product_stock) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Product Chart Modal remains the same -->
    <div id="productChartModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-2xl font-bold mb-4">Product Stock Chart</h2>
            <canvas id="productChart" width="400" height="200"></canvas>
        </div>
    </div>

</body>

</html>