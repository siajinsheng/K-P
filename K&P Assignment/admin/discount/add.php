<?php
$_title = 'Add New Discount';
require_once '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

// Get all products for the dropdown
$stm = $_db->prepare("SELECT product_id, product_name, product_pic1, product_price, product_type FROM product WHERE product_status = 'Available' ORDER BY product_name");
$stm->execute();
$products = $stm->fetchAll();

// Get all product categories
$stm = $_db->prepare("SELECT category_id, category_name FROM category ORDER BY category_name");
$stm->execute();
$categories = $stm->fetchAll();

// Set default dates
$default_start_date = date('Y-m-d');
$default_end_date = date('Y-m-d', strtotime('+30 days'));

// Handle form submission
if (is_post()) {
    $_err = [];
    
    // Get form data
    $product_ids = post('product_ids', []);
    $discount_rate = post('discount_rate');
    $start_date = post('start_date');
    $end_date = post('end_date');
    
    // Validate products
    if (empty($product_ids)) {
        $_err['product_ids'] = 'Please select at least one product';
    }
    
    // Validate discount rate
    if (!is_numeric($discount_rate) || $discount_rate <= 0 || $discount_rate > 100) {
        $_err['discount_rate'] = 'Discount rate must be between 1 and 100';
    }
    
    // Validate dates
    if (!$start_date) {
        $_err['start_date'] = 'Start date is required';
    }
    
    if (!$end_date) {
        $_err['end_date'] = 'End date is required';
    } elseif ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
        $_err['end_date'] = 'End date must be after start date';
    }
    
    // Check for existing discount on these products
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $params = $product_ids;
        $params[] = $start_date;
        $params[] = $end_date;
        $params[] = $start_date;
        $params[] = $end_date;
        $params[] = $start_date;
        $params[] = $end_date;
        
        $stm = $_db->prepare("SELECT product_id, product_name FROM product 
                            WHERE product_id IN ($placeholders) 
                            AND product_id IN (
                                SELECT product_id FROM discount 
                                WHERE ((start_date BETWEEN ? AND ?) OR 
                                      (end_date BETWEEN ? AND ?) OR 
                                      (start_date <= ? AND end_date >= ?))
                            )");
        $stm->execute($params);
        $conflictingProducts = $stm->fetchAll();
        
        if (!empty($conflictingProducts)) {
            $names = array_map(function($p) { return "{$p->product_id} ({$p->product_name})"; }, $conflictingProducts);
            $_err['product_ids'] = 'The following products already have discounts for the selected date range: ' . implode(', ', $names);
        }
    }
    
    // If no errors, create the discounts
    if (empty($_err)) {
        // Determine status based on dates
        $today = date('Y-m-d');
        if ($today >= $start_date && $today <= $end_date) {
            $status = 'Active';
        } elseif ($today < $start_date) {
            $status = 'Upcoming';
        } else {
            $status = 'Expired';
        }
        
        // Insert discount for each selected product
        $stm = $_db->prepare("INSERT INTO discount (Discount_id, product_id, discount_rate, start_date, end_date, status) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        
        $discountsCreated = 0;
        
        foreach ($product_ids as $product_id) {
            // Generate discount ID (format: DISC_YYYYMMDD_randomstring)
            $discount_id = 'DISC_' . date('Ymd') . '_' . substr(md5(uniqid() . $product_id), 0, 8);
            
            try {
                $stm->execute([$discount_id, $product_id, $discount_rate, $start_date, $end_date, $status]);
                $discountsCreated++;
            } catch (PDOException $e) {
                // Log error but continue with other products
                error_log("Error creating discount for product $product_id: " . $e->getMessage());
            }
        }
        
        if ($discountsCreated > 0) {
            $message = $discountsCreated == 1 
                ? 'Discount created successfully' 
                : $discountsCreated . ' discounts created successfully';
            
            temp('success', $message);
            redirect('discount.php');
        } else {
            temp('error', 'Failed to create discounts');
            redirect('add.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="add.css" rel="stylesheet">
    <script src="add.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800"><?= $_title ?></h1>
            <a href="discount.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Discounts
            </a>
        </div>
        
        <?php if (!empty($_err) && !isset($_err['product_ids']) && !isset($_err['discount_rate']) && !isset($_err['start_date']) && !isset($_err['end_date'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-bold">Please fix the following errors:</p>
                        <ul class="list-disc list-inside">
                            <?php foreach ($_err as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-semibold text-gray-700">Create New Discount</h2>
                <p class="text-gray-500">Apply discount to one or multiple products</p>
            </div>
            
            <form method="post" class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <!-- Discount Details Panel - Left Side -->
                    <div class="lg:col-span-2 order-2 lg:order-1">
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Discount Details</h3>
                            
                            <div class="mb-4">
                                <label for="discount_rate" class="block text-sm font-medium text-gray-700 mb-2">Discount Rate (%)</label>
                                <input type="number" id="discount_rate" name="discount_rate" min="1" max="100" step="0.1" value="<?= post('discount_rate', 10) ?>" 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <?php if (isset($_err['discount_rate'])): ?>
                                    <div class="text-sm text-red-600 mt-1"><?= $_err['discount_rate'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" value="<?= post('start_date', $default_start_date) ?>" 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <?php if (isset($_err['start_date'])): ?>
                                        <div class="text-sm text-red-600 mt-1"><?= $_err['start_date'] ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                    <input type="date" id="end_date" name="end_date" value="<?= post('end_date', $default_end_date) ?>" 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <?php if (isset($_err['end_date'])): ?>
                                        <div class="text-sm text-red-600 mt-1"><?= $_err['end_date'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Selected Products Summary -->
                            <div class="mt-6">
                                <h4 class="text-md font-medium text-gray-700 mb-2">Selected Products</h4>
                                
                                <div class="relative bg-white border border-gray-200 rounded-lg p-2 mb-4">
                                    <div id="selectedProductCount" class="text-center text-gray-500 py-4">
                                        No products selected
                                    </div>
                                    
                                    <div id="selectedProductsList" class="selected-products-container hidden">
                                        <!-- Selected products will be displayed here via JavaScript -->
                                    </div>
                                </div>
                                
                                <?php if (isset($_err['product_ids'])): ?>
                                    <div class="text-sm text-red-600 mt-1"><?= $_err['product_ids'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Discount Preview -->
                            <div class="mt-6">
                                <h4 class="text-md font-medium text-gray-700 mb-2">Discount Preview</h4>
                                
                                <div id="discountPreview" class="bg-white border border-gray-200 rounded-lg p-4">
                                    <div id="previewContent" class="text-center text-gray-500 py-4">
                                        Select products and set discount details to see preview
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-between mt-8 pt-6 border-t border-gray-200">
                                <a href="discount.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Cancel
                                </a>
                                <button type="submit" id="createDiscountBtn" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50" disabled>
                                    Create Discount
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Selection Panel - Right Side -->
                    <div class="lg:col-span-3 order-1 lg:order-2">
                        <div class="bg-white rounded-lg border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Select Products</h3>
                            
                            <!-- Search and Filters -->
                            <div class="mb-6">
                                <div class="flex flex-col md:flex-row gap-4">
                                    <div class="flex-1">
                                        <div class="relative">
                                            <input type="text" id="productSearch" placeholder="Search products..." 
                                                   class="w-full p-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <button type="button" id="clearSelectionBtn" class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Clear Selection
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Product Type Filters -->
                                <div class="flex flex-wrap gap-2 mt-4">
                                    <button type="button" class="type-filter px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500 active" data-type="all">
                                        All
                                    </button>
                                    <button type="button" class="type-filter px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500" data-type="Man">
                                        Men
                                    </button>
                                    <button type="button" class="type-filter px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500" data-type="Women">
                                        Women
                                    </button>
                                    <button type="button" class="type-filter px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500" data-type="Unisex">
                                        Unisex
                                    </button>
                                </div>
                                
                                <!-- Categories Pills -->
                                <div class="overflow-x-auto whitespace-nowrap py-3 mt-2 category-filter-container">
                                    <div class="flex gap-2">
                                        <button type="button" class="category-pill px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500 active" data-category="all">
                                            All Categories
                                        </button>
                                        
                                        <?php foreach ($categories as $category): ?>
                                            <button type="button" class="category-pill px-3 py-1 text-sm font-medium rounded-full border border-gray-300 hover:border-indigo-500" 
                                                    data-category="<?= $category->category_id ?>">
                                                <?= $category->category_name ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Products Grid -->
                            <div class="border-t border-gray-200 pt-4">
                                <div id="productCount" class="text-sm text-gray-500 mb-4">
                                    Showing <?= count($products) ?> products
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4" id="productGrid">
                                    <?php foreach ($products as $product): ?>
                                        <div class="product-card border rounded-lg overflow-hidden" 
                                             data-product-id="<?= $product->product_id ?>"
                                             data-product-name="<?= htmlspecialchars($product->product_name) ?>"
                                             data-product-price="<?= $product->product_price ?>"
                                             data-product-image="<?= htmlspecialchars($product->product_pic1) ?>"
                                             data-product-type="<?= htmlspecialchars($product->product_type) ?>">
                                            <div class="relative">
                                                <img src="../../img/<?= encode($product->product_pic1) ?>" alt="<?= encode($product->product_name) ?>" class="product-image w-full">
                                                <div class="absolute top-2 right-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        <?= $product->product_type ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="p-4">
                                                <h3 class="font-semibold text-gray-800 mb-1 truncate" title="<?= encode($product->product_name) ?>">
                                                    <?= encode($product->product_name) ?>
                                                </h3>
                                                <div class="text-sm text-gray-500 mb-2"><?= encode($product->product_id) ?></div>
                                                <div class="font-bold text-indigo-600">RM <?= number_format($product->product_price, 2) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Empty State -->
                                <div id="emptyState" class="hidden text-center py-12">
                                    <div class="text-4xl text-gray-300 mb-4">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-700">No products found</h4>
                                    <p class="text-gray-500">Try changing your search or filter criteria</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden input field to store selected product IDs -->
                <div id="selectedProductsInput">
                    <!-- Will be populated by JavaScript -->
                </div>
            </form>
        </div>
    </div>

    <?php require '../headFooter/footer.php'; ?>
</body>
</html>