<?php
$_title = 'Add Discount';
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

// Initialize variables
$product = null;
$errors = [];
$success = false;

try {
    // Fetch product details
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

    // Check if product is discontinued
    if ($product->product_status === 'Discontinued') {
        temp('error', 'Cannot add discount to discontinued products');
        redirect('Detail_Product.php?id=' . $product_id);
    }

    // Check for existing active discounts
    $existing_query = "SELECT * FROM discount 
                      WHERE product_id = ? 
                      AND status = 'Active' 
                      AND CURDATE() <= end_date";
    $existing_stmt = $_db->prepare($existing_query);
    $existing_stmt->execute([$product_id]);
    $existing_discount = $existing_stmt->fetch();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data
        $discount_rate = trim($_POST['discount_rate'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        
        // Validate discount rate
        if (empty($discount_rate)) {
            $errors[] = 'Discount rate is required';
        } elseif (!is_numeric($discount_rate) || $discount_rate <= 0 || $discount_rate > 100) {
            $errors[] = 'Discount rate must be a number between 0 and 100';
        }
        
        // Validate dates
        if (empty($start_date)) {
            $errors[] = 'Start date is required';
        }
        
        if (empty($end_date)) {
            $errors[] = 'End date is required';
        }
        
        // Validate date range
        if (!empty($start_date) && !empty($end_date)) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $today = new DateTime();
            
            if ($start > $end) {
                $errors[] = 'End date must be after start date';
            }
            
            if ($start < $today) {
                $errors[] = 'Start date cannot be in the past';
            }
        }
        
        // If existing discount found, handle conflicts
        if ($existing_discount && empty($errors)) {
            // Option to update existing discount or create a new one
            $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
            
            if ($update_existing) {
                // Update existing discount
                $update_query = "UPDATE discount 
                                SET discount_rate = ?, start_date = ?, end_date = ?
                                WHERE Discount_id = ?";
                $update_stmt = $_db->prepare($update_query);
                $update_stmt->execute([$discount_rate, $start_date, $end_date, $existing_discount->Discount_id]);
                
                temp('success', 'Existing discount updated successfully');
                redirect('Detail_Product.php?id=' . $product_id);
            } else {
                // Deactivate existing discount first
                $deactivate_query = "UPDATE discount 
                                    SET status = 'Inactive', end_date = CURDATE()
                                    WHERE Discount_id = ?";
                $deactivate_stmt = $_db->prepare($deactivate_query);
                $deactivate_stmt->execute([$existing_discount->Discount_id]);
                
                // Continue to create new discount
            }
        }
        
        // Process form if no errors
        if (empty($errors)) {
            // Generate unique discount ID
            $discount_id = 'DISC' . date('YmdHis') . rand(100, 999);
            
            // Insert new discount record
            $insert_query = "INSERT INTO discount 
                           (Discount_id, product_id, discount_rate, start_date, end_date, status)
                           VALUES (?, ?, ?, ?, ?, 'Active')";
            $insert_stmt = $_db->prepare($insert_query);
            $insert_stmt->execute([$discount_id, $product_id, $discount_rate, $start_date, $end_date]);
            
            temp('success', 'Discount added successfully');
            redirect('Detail_Product.php?id=' . $product_id);
        }
    }
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Calculate current price and discounted preview
$current_price = $product->product_price;
$preview_discount = isset($_POST['discount_rate']) ? floatval($_POST['discount_rate']) : 0;
$preview_price = $current_price * (1 - ($preview_discount / 100));
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

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #6B7280;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background-color: white;
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
            color: #4F46E5;
            transition: color 0.2s;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item a:hover {
            color: #4338CA;
            text-decoration: underline;
        }

        .breadcrumb-item:last-child {
            font-weight: 600;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Discount rate input handling for live preview
            $('#discountRate').on('input', function() {
                calculatePreview();
            });

            function calculatePreview() {
                const originalPrice = <?= $product->product_price ?>;
                const discountRate = $('#discountRate').val() || 0;
                const discountedPrice = originalPrice * (1 - (discountRate / 100));
                const savings = originalPrice - discountedPrice;
                
                $('#previewPrice').text('RM' + discountedPrice.toFixed(2));
                $('#previewSavings').text('RM' + savings.toFixed(2));
                
                // Update the discount percent badge
                $('#discountBadge').text(discountRate + '% OFF');
            }

            // Initialize date inputs with sensible defaults
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const nextMonth = new Date(today);
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            
            // Format dates for input fields
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set default dates if not already set
            if (!$('#startDate').val()) {
                $('#startDate').val(formatDate(tomorrow));
            }
            
            if (!$('#endDate').val()) {
                $('#endDate').val(formatDate(nextMonth));
            }
            
            // Initialize preview
            calculatePreview();
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
                <a href="Detail_Product.php?id=<?= htmlspecialchars($product_id) ?>"><?= htmlspecialchars($product->product_name) ?></a>
            </div>
            <div class="breadcrumb-item">
                <span class="text-gray-600">Add Discount</span>
            </div>
        </div>

        <!-- Header with Actions -->
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-800">Add Discount</h1>
            <div>
                <a href="Detail_Product.php?id=<?= htmlspecialchars($product_id) ?>" class="btn-secondary py-2 px-4 rounded flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Product
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Errors:</p>
                <ul class="mt-2 ml-4 list-disc">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($existing_discount): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Warning: This product already has an active discount!</p>
                <p class="mt-2">Current discount: <?= htmlspecialchars($existing_discount->discount_rate) ?>% valid from <?= date('d M Y', strtotime($existing_discount->start_date)) ?> to <?= date('d M Y', strtotime($existing_discount->end_date)) ?></p>
                <p class="mt-2">Choose to update the existing discount or deactivate it and create a new one.</p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Product Information Card -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">Product Information</h2>
                    <div class="flex items-center mb-4">
                        <div class="w-20 h-20 bg-gray-100 rounded flex items-center justify-center overflow-hidden mr-4">
                            <?php if ($product->product_pic1): ?>
                                <img src="../uploads/product_images/<?= htmlspecialchars($product->product_pic1) ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="object-cover h-full w-full">
                            <?php else: ?>
                                <i class="fas fa-box text-gray-400 text-4xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg"><?= htmlspecialchars($product->product_name) ?></h3>
                            <p class="text-gray-600">Product ID: <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($product->product_id) ?></span></p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-700">Category: <span class="font-medium"><?= htmlspecialchars($product->category_name ?? 'Uncategorized') ?></span></p>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-700">Current Price: <span class="font-bold text-indigo-700">RM<?= number_format($product->product_price, 2) ?></span></p>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-700">Status: 
                            <span class="px-2 py-1 rounded-full text-sm font-medium 
                                <?= $product->product_status === 'Available' ? 'bg-green-100 text-green-800' : 
                                    ($product->product_status === 'Out of Stock' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                                <?= htmlspecialchars($product->product_status) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Discount Form -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden md:col-span-2">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">Discount Details</h2>
                    <form method="post" class="space-y-6">
                        <?php if ($existing_discount): ?>
                            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                                <div class="mb-4">
                                    <label class="block font-medium text-gray-700 mb-2">
                                        <input type="radio" name="update_existing" value="1" checked> 
                                        Update existing discount
                                    </label>
                                    <label class="block font-medium text-gray-700">
                                        <input type="radio" name="update_existing" value="0"> 
                                        Deactivate existing and create new discount
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="discountRate" class="block text-sm font-medium text-gray-700 mb-1">Discount Rate (%)</label>
                            <input type="number" id="discountRate" name="discount_rate" min="1" max="100" step="0.01" 
                                value="<?= isset($_POST['discount_rate']) ? htmlspecialchars($_POST['discount_rate']) : ($existing_discount ? htmlspecialchars($existing_discount->discount_rate) : '') ?>" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-2 px-4 border" 
                                required>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" id="startDate" name="start_date" 
                                    value="<?= isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ($existing_discount ? htmlspecialchars($existing_discount->start_date) : '') ?>" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-2 px-4 border" 
                                    required>
                            </div>
                            <div>
                                <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" id="endDate" name="end_date" 
                                    value="<?= isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ($existing_discount ? htmlspecialchars($existing_discount->end_date) : '') ?>" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-2 px-4 border" 
                                    required>
                            </div>
                        </div>

                        <!-- Discount Preview -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mt-6">
                            <h3 class="font-bold text-gray-700 mb-3">Discount Preview</h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600">Original Price:</p>
                                    <p class="line-through text-gray-500">RM<?= number_format($product->product_price, 2) ?></p>
                                </div>
                                <div class="bg-red-500 text-white text-sm px-3 py-1 rounded-full" id="discountBadge">
                                    0% OFF
                                </div>
                                <div>
                                    <p class="text-gray-600">Discounted Price:</p>
                                    <p class="font-bold text-indigo-700" id="previewPrice">RM<?= number_format($preview_price, 2) ?></p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-gray-600">Customer Savings: <span class="font-medium text-green-600" id="previewSavings">RM<?= number_format($current_price - $preview_price, 2) ?></span></p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <a href="Detail_Product.php?id=<?= htmlspecialchars($product_id) ?>" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" class="btn-primary py-2 px-4 rounded flex items-center">
                                <i class="fas fa-save mr-2"></i>Save Discount
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 py-4 mt-12">
        <div class="container mx-auto px-4">
            <p class="text-center text-gray-500 text-sm">© <?= date('Y') ?> K&P Fashion Admin Portal. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>