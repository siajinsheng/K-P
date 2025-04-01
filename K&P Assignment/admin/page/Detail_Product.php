<?php
$_title = 'Product Details';
require '../../_base.php';
auth(0, 1); // Only admin and managers can access
require 'header.php';

// Get product ID from URL parameter
$product_id = req('id');

// Redirect if no product ID provided
if (!$product_id) {
    temp('error', 'No product ID specified');
    redirect('product.php');
}

try {
    // Fetch product details with category
    $query = "SELECT p.*, c.category_name 
              FROM product p
              LEFT JOIN category c ON p.category_id = c.category_id
              WHERE p.product_id = ?";
    $stmt = $_db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    // Check if product exists
    if (!$product) {
        temp('error', 'Product not found');
        redirect('product.php');
    }

    // Fetch stock information by size
    $stock_query = "SELECT size, product_stock, product_sold
                   FROM quantity
                   WHERE product_id = ?
                   ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL')";
    $stock_stmt = $_db->prepare($stock_query);
    $stock_stmt->execute([$product_id]);
    $stock_info = $stock_stmt->fetchAll();

    // Calculate total stock and total sold
    $total_stock = 0;
    $total_sold = 0;
    foreach ($stock_info as $stock) {
        $total_stock += $stock->product_stock;
        $total_sold += $stock->product_sold;
    }

    // Fetch discount information if any
    $discount_query = "SELECT * FROM discount 
                      WHERE product_id = ? 
                      AND status = 'Active' 
                      AND CURDATE() BETWEEN start_date AND end_date";
    $discount_stmt = $_db->prepare($discount_query);
    $discount_stmt->execute([$product_id]);
    $discount = $discount_stmt->fetch();

    // Check if product appears in any orders
    $orders_query = "SELECT COUNT(*) FROM order_details WHERE product_id = ?";
    $orders_stmt = $_db->prepare($orders_query);
    $orders_stmt->execute([$product_id]);
    $orders_count = $orders_stmt->fetchColumn();

    // Get total revenue generated by this product
    $revenue_query = "SELECT SUM(unit_price * quantity) as total_revenue 
                     FROM order_details 
                     WHERE product_id = ?";
    $revenue_stmt = $_db->prepare($revenue_query);
    $revenue_stmt->execute([$product_id]);
    $total_revenue = $revenue_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    redirect('product.php');
}

// Format the status for color coding
$status_class = match ($product->product_status) {
    'Available' => 'text-green-600',
    'Out of Stock' => 'text-red-600',
    'Discontinued' => 'text-gray-600',
    default => 'text-black'
};

// Badge background color based on status
$status_bg = match ($product->product_status) {
    'Available' => 'bg-green-100',
    'Out of Stock' => 'bg-red-100',
    'Discontinued' => 'bg-gray-100',
    default => 'bg-blue-100'
};

// Calculate discounted price if applicable
$discounted_price = $product->product_price;
if ($discount) {
    $discounted_price = $product->product_price * (1 - ($discount->discount_rate / 100));
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
    <style>
        .btn-primary {
            background-color: #4F46E5;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #4338CA;
        }

        .btn-secondary {
            background-color: #374151;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #1F2937;
        }

        .img-container {
            height: 400px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
        }

        .img-container img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .active-thumbnail {
            border: 2px solid #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 0.375rem;
        }

        .thumbnail:hover {
            transform: scale(1.05);
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #6B7280;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item:not(:last-child)::after {
            content: '/';
            margin: 0 0.5rem;
            color: #9CA3AF;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            transition: color 0.2s;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .breadcrumb-item:last-child {
            font-weight: 600;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Handle thumbnail click to change main image
            $('.thumbnail').on('click', function() {
                const imgSrc = $(this).attr('src');
                $('#mainImage').attr('src', imgSrc);

                // Update active thumbnail
                $('.thumbnail').removeClass('active-thumbnail');
                $(this).addClass('active-thumbnail');
            });

            // Initialize with first image as active
            $('.thumbnail:first').addClass('active-thumbnail');

            // Size selection handling
            $('.size-btn').on('click', function() {
                $('.size-btn').removeClass('bg-indigo-600 text-white');
                $('.size-btn').addClass('bg-white text-gray-800');
                $(this).removeClass('bg-white text-gray-800');
                $(this).addClass('bg-indigo-600 text-white');
            });

            // Simple placeholder stock chart
            if ($('#stockChart').length) {
                const stockCtx = document.getElementById('stockChart').getContext('2d');
                const stockChart = new Chart(stockCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_map(function ($item) {
                                    return $item->size;
                                }, $stock_info)) ?>,
                        datasets: [{
                            label: 'In Stock',
                            data: <?= json_encode(array_map(function ($item) {
                                        return $item->product_stock;
                                    }, $stock_info)) ?>,
                            backgroundColor: 'rgba(79, 70, 229, 0.8)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Sold',
                            data: <?= json_encode(array_map(function ($item) {
                                        return $item->product_sold;
                                    }, $stock_info)) ?>,
                            backgroundColor: 'rgba(99, 102, 241, 0.4)',
                            borderColor: 'rgba(99, 102, 241, 1)',
                            borderWidth: 1
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
            }

            // Print functionality
            $('#printBtn').on('click', function(e) {
                e.preventDefault();
                window.print();
            });
        });
    </script>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="home.php"><i class="fas fa-home mr-1"></i>Home</a>
            </div>
            <div class="breadcrumb-item">
                <a href="product.php">Products</a>
            </div>
            <div class="breadcrumb-item">
                <span class="text-gray-600"><?= htmlspecialchars($product->product_name) ?></span>
            </div>
        </div>

        <!-- Header with Actions -->
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-800">Product Details</h1>
            <div class="flex space-x-3">
                <a href="product.php" class="btn-secondary py-2 px-4 rounded flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="Update_Product.php?id=<?= $product->product_id ?>" class="btn-primary py-2 px-4 rounded flex items-center">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <button id="printBtn" class="bg-gray-200 text-gray-800 py-2 px-4 rounded flex items-center hover:bg-gray-300">
                    <i class="fas fa-print mr-2"></i>Print
                </button>
            </div>
        </div>

        <!-- Product Overview -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex flex-col md:flex-row">
                    <!-- Product Images Section -->
                    <div class="md:w-1/2 pr-0 md:pr-8">
                        <!-- Main Image Display -->
                        <div class="img-container mb-4 bg-gray-50 rounded-lg border border-gray-200">
                            <img id="mainImage" src="../uploads/product_images/<?= $product->product_pic1 ? $product->product_pic1 : 'placeholder.jpg' ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="rounded-lg">
                        </div>

                        <!-- Thumbnails -->
                        <div class="grid grid-cols-6 gap-2 py-2">
                            <?php
                            $image_fields = ['product_pic1', 'product_pic2', 'product_pic3', 'product_pic4', 'product_pic5', 'product_pic6'];
                            foreach ($image_fields as $field) {
                                if (!empty($product->$field)) {
                                    echo '<img src="../uploads/product_images/' . $product->$field . '" class="thumbnail rounded-md shadow-sm" alt="Product thumbnail">';
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Product Details Section -->
                    <div class="md:w-1/2 mt-6 md:mt-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="text-2xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($product->product_name) ?></h2>
                                <p class="text-gray-600 mb-4">Product ID: <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($product->product_id) ?></span></p>
                            </div>
                            <span class="px-3 py-1 rounded-full font-bold <?= $status_class ?> <?= $status_bg ?>">
                                <?= htmlspecialchars($product->product_status) ?>
                            </span>
                        </div>

                        <div class="mb-4 flex items-center">
                            <span class="text-gray-700 mr-2">Category:</span>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                                <?= htmlspecialchars($product->category_name ?? 'Uncategorized') ?>
                            </span>
                        </div>

                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <?php if ($discount): ?>
                                <div class="flex items-center">
                                    <span class="text-3xl font-bold text-indigo-700">RM<?= number_format($discounted_price, 2) ?></span>
                                    <span class="line-through text-gray-500 ml-2">RM<?= number_format($product->product_price, 2) ?></span>
                                    <span class="bg-red-500 text-white text-sm px-2 py-1 rounded-full ml-2">
                                        <?= htmlspecialchars($discount->discount_rate) ?>% OFF
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Sale: <?= date('d M Y', strtotime($discount->start_date)) ?> - <?= date('d M Y', strtotime($discount->end_date)) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-3xl font-bold text-indigo-700">RM<?= number_format($product->product_price, 2) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Sizes Section -->
                        <div class="mb-6">
                            <h3 class="font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-ruler mr-2"></i>Available Sizes & Stock:
                            </h3>
                            <div class="flex flex-wrap gap-4">
                                <?php foreach ($stock_info as $stock): ?>
                                    <div class="text-center">
                                        <button class="size-btn w-14 h-14 rounded-md border-2 border-gray-300 font-bold bg-white text-gray-800 transition-colors hover:border-indigo-500">
                                            <?= htmlspecialchars($stock->size) ?>
                                        </button>
                                        <div class="mt-1 text-sm">
                                            <span class="<?= $stock->product_stock > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= htmlspecialchars($stock->product_stock) ?> in stock
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Product Description -->
                        <div class="mb-6">
                            <h3 class="font-bold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>Description:
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <?php if (empty($product->product_description)): ?>
                                    <p class="text-gray-500 italic">No description available</p>
                                <?php else: ?>
                                    <p class="leading-relaxed"><?= nl2br(htmlspecialchars($product->product_description)) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Stock</p>
                        <p class="text-xl font-bold text-gray-800"><?= $total_stock ?> units</p>
                    </div>
                    <div class="bg-indigo-100 p-3 rounded-full">
                        <i class="fas fa-box text-indigo-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Sold</p>
                        <p class="text-xl font-bold text-gray-800"><?= $total_sold ?> units</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Order Count</p>
                        <p class="text-xl font-bold text-gray-800"><?= $orders_count ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-xl font-bold text-gray-800">RM<?= number_format($total_revenue, 2) ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Stock by Size Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Stock by Size</h3>
                <div class="h-64">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>

            <!-- Stock Details Table -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Stock Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($stock_info as $stock): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Size <?= htmlspecialchars($stock->size) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($stock->product_stock) ?> units
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($stock->product_sold) ?> units
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($stock->product_stock > 10): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Well Stocked
                                            </span>
                                        <?php elseif ($stock->product_stock > 0): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center my-6">
            <a href="product.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Products
            </a>

            <div class="flex space-x-3">
                <?php if ($product->product_status != 'Discontinued'): ?>
                    <a href="add_discount.php?id=<?= $product->product_id ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded flex items-center">
                        <i class="fas fa-percent mr-2"></i> Add Discount
                    </a>
                <?php endif; ?>
                <a href="Update_Product.php?id=<?= $product->product_id ?>" class="btn-primary py-2 px-4 rounded flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Product
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4">
        <div class="container mx-auto px-4">
            <p class="text-center text-gray-500 text-sm">© <?= date('Y') ?> K&P Fashion Admin Portal. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>