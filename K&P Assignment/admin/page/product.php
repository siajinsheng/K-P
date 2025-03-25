<?php
$_title = 'Product';
require '../../_base.php';
//auth('Admin', 'Manager',1);
require 'header.php';

// (1) Sorting - Adjusted to match k_p.sql schema
$fields = [
    'product_id'        => 'Product ID',
    'product_name'      => 'Name',
    'product_price'     => 'Price',
    'product_status'    => 'Status',
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'product_id';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// (2) Paging
$page = req('page', 1);
$searchName = req('searchName');

// (3) Search and Pagination
require_once '../lib/SimplePager.php';

// Modified query to match k_p.sql columns
$query = "SELECT product_id, product_name, product_price, product_status, product_stock 
          FROM product 
          WHERE product_name LIKE ? 
          ORDER BY 
            CASE WHEN product_stock < 10 THEN 0 ELSE 1 END, 
            $sort $dir";

$params = ["%$searchName%"];

$p = new SimplePager($query, $params, 10, $page);
$products = $p->result;

// Chart data - adjusted column names
$chart_query = "SELECT product_id, product_stock FROM product";
$chart_stm = $_db->prepare($chart_query);
$chart_stm->execute();
$chart_data = $chart_stm->fetchAll(PDO::FETCH_ASSOC);

// Calculate max stock
$maxStockQty = max(array_column($chart_data, 'product_stock'));

$labels = [];
$data = [];
$backgroundColors = [];

foreach ($chart_data as $row) {
    $labels[] = $row['product_id'];
    $data[] = $row['product_stock'];

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
    <link rel="stylesheet" href="../css/product.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    $(document).ready(function() {
        // Modify Status button click handler
        $('.modify-status-btn').on('click', function(e) {
            e.preventDefault();
            const productId = $(this).data('id');
            
            // Create a modal for status selection
            const modal = `
                <div id="statusModal" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h2>Modify Product Status</h2>
                        <form id="statusChangeForm">
                            <input type="hidden" name="product_id" value="${productId}">
                            <label for="product_status">Select New Status:</label>
                            <select name="product_status" id="product_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Discontinued">Discontinued</option>
                            </select>
                            <button type="submit">Update Status</button>
                        </form>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
            
            // Close modal functionality
            $('.close').on('click', function() {
                $('#statusModal').remove();
            });
            
            // Form submission handler
            $('#statusChangeForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'Update_Product_Status.php', // You'll need to create this file
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        alert('Product status updated successfully');
                        location.reload(); // Reload to see updated status
                    },
                    error: function() {
                        alert('Failed to update product status');
                    }
                });
            });
            
            // Show the modal
            $('#statusModal').show();
        });
    });
    </script>
</head>

<body>
    <h1 style="text-align: center;">Product List</h1><br>
    <div class="container">
        <!-- Buttons, search form, and table structure remain the same -->
        <div class="button-container">
            <?php html_button('Insert_Product.php', 'Insert Product'); ?>
            <button id="addCategoryButton" class="button">Add Category</button>
            <button id="productChartButton" class="button">Product Chart</button>
        </div>

        <form method="get">
            <?= html_search('searchName', $searchName) ?>
            <button>Search</button>
        </form>

        <p>
            <?= $p->count ?> of <?= $p->item_count ?> record(s) |
            Page <?= $p->page ?> of <?= $p->page_count ?>
        </p>

        <table class="table">
            <thead>
                <tr>
                    <?= table_headers($fields, $sort, $dir, "page=$page&searchName=" . urlencode($searchName)) ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product->product_id) ?></td>
                        <td><?= htmlspecialchars($product->product_name) ?></td>
                        <td><?= htmlspecialchars(number_format($product->product_price, 2)) ?></td>
                        <td><?= htmlspecialchars($product->product_status) ?></td>
                        <td>
                            <a href="Detail_Product.php?id=<?= $product->product_id ?>" class="button">Details</a>
                            <a href="Update_Product.php?id=<?= $product->product_id ?>" class="button">Update</a>
                            <button class="button modify-status-btn" 
                                    data-id="<?= $product->product_id ?>">Modify Status</button>
                            <?php if ($product->product_stock < 10): ?>
                                <span class="stock-warning" style="color: red; font-size: 1.5em;">⚠️</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $p->html("sort=$sort&dir=$dir&searchName=" . urlencode($searchName)) ?>
    </div>

    <div id="lowStockModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Stock Warning</h2>
            <p>Some products are running low on stock (less than 10). Please check the inventory.</p>
        </div>
    </div>

    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New Category</h2>
            <form id="addCategoryForm" method="post">
                <label for="catName">Category Name:</label>
                <input type="text" id="catName" name="catName" required>
                <button type="submit">Add Category</button>
            </form>
        </div>
    </div>

    <div id="productChartModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Product Stock Chart</h2>
            <canvas id="productChart" width="400" height="200"></canvas>
        </div>
    </div>

</body>

</html>