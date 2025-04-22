<?php
$_title = 'Product Management';
require '../../_base.php';
auth('admin', 'staff');
require 'header.php';

// Initialize variables
$page = get('page', 1);
$limit = get('limit', 10);
$search = get('search', '');
$filter_category = get('category', '');
$filter_status = get('status', '');
$sort = get('sort', 'product_id');
$dir = get('dir', 'asc');
$offset = ($page - 1) * $limit;

// Get total products count and statistics
try {
    // Total products
    $stmt = $_db->prepare("SELECT COUNT(*) FROM product");
    $stmt->execute();
    $total_products = $stmt->fetchColumn();
    
    // Available products
    $stmt = $_db->prepare("SELECT COUNT(*) FROM product WHERE product_status = 'Available'");
    $stmt->execute();
    $available_products = $stmt->fetchColumn();
    
    // Out of stock products
    $stmt = $_db->prepare("SELECT COUNT(*) FROM product WHERE product_status = 'Out of Stock'");
    $stmt->execute();
    $outofstock_products = $stmt->fetchColumn();
    
    // Discontinued products
    $stmt = $_db->prepare("SELECT COUNT(*) FROM product WHERE product_status = 'Discontinued'");
    $stmt->execute();
    $discontinued_products = $stmt->fetchColumn();
    
    // Total categories
    $stmt = $_db->prepare("SELECT COUNT(*) FROM category");
    $stmt->execute();
    $total_categories = $stmt->fetchColumn();
    
    // Get all categories for dropdown
    $stmt = $_db->prepare("SELECT category_id, category_name FROM category ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Build the product query with filters
    $query = "SELECT p.*, c.category_name 
              FROM product p 
              LEFT JOIN category c ON p.category_id = c.category_id 
              WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (p.product_id LIKE ? OR p.product_name LIKE ? OR c.category_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($filter_category)) {
        $query .= " AND p.category_id = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND p.product_status = ?";
        $params[] = $filter_status;
    }
    
    // Count total filtered records
    $count_stmt = $_db->prepare("SELECT COUNT(*) FROM ($query) AS filtered");
    $count_stmt->execute($params);
    $total_filtered = $count_stmt->fetchColumn();
    
    // Add sorting and pagination
    $query .= " ORDER BY $sort $dir LIMIT $limit OFFSET $offset";
    
    $stmt = $_db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Calculate total pages
    $total_pages = ceil($total_filtered / $limit);
    
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    $products = [];
    $total_pages = 0;
}

// Handle category addition
if (is_post() && isset($_POST['add_category'])) {
    $category_id = post('category_id');
    $category_name = post('category_name');
    
    // Simple validation
    if (empty($category_id) || empty($category_name)) {
        temp('error', 'Category ID and Name are required');
    } else {
        try {
            // Check if category ID already exists
            $stmt = $_db->prepare("SELECT COUNT(*) FROM category WHERE category_id = ?");
            $stmt->execute([$category_id]);
            if ($stmt->fetchColumn() > 0) {
                temp('error', 'Category ID already exists');
            } else {
                $stmt = $_db->prepare("INSERT INTO category (category_id, category_name) VALUES (?, ?)");
                $stmt->execute([$category_id, $category_name]);
                temp('success', 'Category added successfully');
            }
        } catch (PDOException $e) {
            temp('error', 'Error adding category: ' . $e->getMessage());
        }
    }
    redirect('product.php');
}

// Handle product status update via AJAX
if (is_post() && isset($_POST['update_status'])) {
    $product_id = post('product_id');
    $status = post('status');
    
    try {
        $stmt = $_db->prepare("UPDATE product SET product_status = ? WHERE product_id = ?");
        $stmt->execute([$status, $product_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
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
    <link href="../css/product.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Product Management</h1>
            <div class="flex space-x-3">
                <a href="Insert_Product.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Product
                </a>
                <button id="addCategoryBtn" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-folder-plus mr-2"></i> Add New Category
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <!-- Total Products -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-gray-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-gray-100 text-gray-600">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Products</p>
                            <p class="text-2xl font-bold"><?= number_format($total_products) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Products -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-green-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-green-100 text-green-600">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Available</p>
                            <p class="text-2xl font-bold"><?= number_format($available_products) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Out of Stock Products -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-yellow-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-yellow-100 text-yellow-600">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Out of Stock</p>
                            <p class="text-2xl font-bold"><?= number_format($outofstock_products) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discontinued Products -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-red-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-red-100 text-red-600">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Discontinued</p>
                            <p class="text-2xl font-bold"><?= number_format($discontinued_products) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-blue-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-blue-100 text-blue-600">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Categories</p>
                            <p class="text-2xl font-bold"><?= number_format($total_categories) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filterForm">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category" name="category" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat->category_id ?>" <?= $cat->category_id === $filter_category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat->category_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Status</option>
                        <option value="Available" <?= $filter_status === 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Out of Stock" <?= $filter_status === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                        <option value="Discontinued" <?= $filter_status === 'Discontinued' ? 'selected' : '' ?>>Discontinued</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg mr-2">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="product.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="font-bold text-xl">Products List</h2>
                <div class="flex items-center">
                    <span class="text-sm text-gray-600 mr-2">Show:</span>
                    <select id="limitSelect" class="border border-gray-300 rounded-md p-1 text-sm">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="product_id">
                                ID <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="product_name">
                                Name <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="category_name">
                                Category <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="product_price">
                                Price <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="product_type">
                                Type <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($product->product_id) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($product->product_pic1): ?>
                                            <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>" class="h-12 w-12 object-cover rounded-md">
                                        <?php else: ?>
                                            <div class="h-12 w-12 bg-gray-200 flex items-center justify-center rounded-md">
                                                <i class="fas fa-tshirt text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($product->product_name) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($product->category_name) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        RM <?= number_format($product->product_price, 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($product->product_type) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <select class="status-select rounded-full text-xs py-1 px-2 border" 
                                                data-product-id="<?= $product->product_id ?>">
                                            <option value="Available" class="bg-green-100 text-green-800" 
                                                    <?= $product->product_status === 'Available' ? 'selected' : '' ?>>
                                                Available
                                            </option>
                                            <option value="Out of Stock" class="bg-yellow-100 text-yellow-800" 
                                                    <?= $product->product_status === 'Out of Stock' ? 'selected' : '' ?>>
                                                Out of Stock
                                            </option>
                                            <option value="Discontinued" class="bg-red-100 text-red-800" 
                                                    <?= $product->product_status === 'Discontinued' ? 'selected' : '' ?>>
                                                Discontinued
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="Detail_Product.php?id=<?= $product->product_id ?>" class="text-indigo-600 hover:text-indigo-900 mx-1" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="Update_Product.php?id=<?= $product->product_id ?>" class="text-blue-600 hover:text-blue-900 mx-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="view-stock-btn text-green-600 hover:text-green-900 mx-1" 
                                                data-product-id="<?= $product->product_id ?>" 
                                                data-product-name="<?= htmlspecialchars($product->product_name) ?>" 
                                                title="View Stock">
                                            <i class="fas fa-boxes"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?= min(($page - 1) * $limit + 1, $total_filtered) ?> to <?= min($page * $limit, $total_filtered) ?> of <?= $total_filtered ?> products
                        </div>
                        <div>
                            <ul class="pagination flex">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a href="?page=1&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1 rounded-l-md">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($start_page + 4, $total_pages);
                                if ($end_page - $start_page < 4 && $start_page > 1) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-100' ?> border border-gray-300 px-3 py-1">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $total_pages ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1 rounded-r-md">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Add New Category</h3>
                <button id="closeCategoryModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post">
                <div class="mb-4">
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category ID</label>
                    <input type="text" id="category_id" name="category_id" required 
                           class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="Example: CAT1004">
                    <p class="text-xs text-gray-500 mt-1">Enter a unique category ID (e.g., CAT1004)</p>
                </div>
                
                <div class="mb-4">
                    <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" id="category_name" name="category_name" required
                           class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="Enter category name">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelCategoryBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-lg mr-2">
                        Cancel
                    </button>
                    <button type="submit" name="add_category" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Stock View Modal -->
    <div id="stockModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800" id="stockModalTitle">Product Stock</h3>
                <button id="closeStockModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="stockContent" class="mb-6">
                <div class="text-center p-8">
                    <i class="fas fa-spinner fa-spin text-indigo-600 text-3xl"></i>
                    <p class="mt-2 text-gray-500">Loading stock information...</p>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="button" id="closeStockBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php $error = temp('error'); if ($error): ?>
        <div id="errorAlert" class="fixed top-5 right-5 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md z-50 transform transition-transform duration-500 translate-x-0">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <span><?= $error ?></span>
            </div>
            <button class="absolute top-1 right-1 text-red-500 hover:text-red-700" onclick="document.getElementById('errorAlert').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <script>
            setTimeout(function() {
                const alert = document.getElementById('errorAlert');
                if (alert) {
                    alert.classList.add('translate-x-full');
                    setTimeout(() => alert.remove(), 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>
    
    <?php $success = temp('success'); if ($success): ?>
        <div id="successAlert" class="fixed top-5 right-5 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md z-50 transform transition-transform duration-500 translate-x-0">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <span><?= $success ?></span>
            </div>
            <button class="absolute top-1 right-1 text-green-500 hover:text-green-700" onclick="document.getElementById('successAlert').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <script>
            setTimeout(function() {
                const alert = document.getElementById('successAlert');
                if (alert) {
                    alert.classList.add('translate-x-full');
                    setTimeout(() => alert.remove(), 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <?php require 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Limit select change handler
        document.getElementById('limitSelect').addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        });
        
        // Sort headers click handler
        document.querySelectorAll('th[data-sort]').forEach(header => {
            header.addEventListener('click', function() {
                const sort = this.getAttribute('data-sort');
                const currentSort = '<?= $sort ?>';
                const currentDir = '<?= $dir ?>';
                
                let dir = 'asc';
                if (sort === currentSort) {
                    dir = currentDir === 'asc' ? 'desc' : 'asc';
                }
                
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sort);
                url.searchParams.set('dir', dir);
                window.location.href = url.toString();
            });
        });
        
        // Status select change handler
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const productId = this.getAttribute('data-product-id');
                const newStatus = this.value;
                
                // Apply visual styling based on status
                if (newStatus === 'Available') {
                    this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-green-100 text-green-800';
                } else if (newStatus === 'Out of Stock') {
                    this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-yellow-100 text-yellow-800';
                } else if (newStatus === 'Discontinued') {
                    this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-red-100 text-red-800';
                }
                
                // Send AJAX request to update status
                const formData = new FormData();
                formData.append('update_status', '1');
                formData.append('product_id', productId);
                formData.append('status', newStatus);
                
                fetch('product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success notification
                        const alert = document.createElement('div');
                        alert.id = 'statusSuccessAlert';
                        alert.className = 'fixed top-5 right-5 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md z-50 transform transition-opacity duration-500 opacity-100';
                        alert.innerHTML = `
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-3"></i>
                                <span>Status updated successfully</span>
                            </div>
                            <button class="absolute top-1 right-1 text-green-500 hover:text-green-700" onclick="this.parentElement.remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        document.body.appendChild(alert);
                        
                        setTimeout(() => {
                            alert.classList.replace('opacity-100', 'opacity-0');
                            setTimeout(() => alert.remove(), 500);
                        }, 3000);
                    } else {
                        // Show error notification
                        const alert = document.createElement('div');
                        alert.id = 'statusErrorAlert';
                        alert.className = 'fixed top-5 right-5 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md z-50';
                        alert.innerHTML = `
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-3"></i>
                                <span>Error updating status: ${data.message}</span>
                            </div>
                            <button class="absolute top-1 right-1 text-red-500 hover:text-red-700" onclick="this.parentElement.remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        document.body.appendChild(alert);
                        
                        setTimeout(() => {
                            alert.classList.add('opacity-0');
                            setTimeout(() => alert.remove(), 500);
                        }, 5000);
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
        
        // Add Category Modal
        const categoryModal = document.getElementById('categoryModal');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const closeCategoryModal = document.getElementById('closeCategoryModal');
        const cancelCategoryBtn = document.getElementById('cancelCategoryBtn');
        
        addCategoryBtn.addEventListener('click', () => {
            categoryModal.classList.remove('hidden');
        });
        
        closeCategoryModal.addEventListener('click', () => {
            categoryModal.classList.add('hidden');
        });
        
        cancelCategoryBtn.addEventListener('click', () => {
            categoryModal.classList.add('hidden');
        });
        
        // Stock modal
        const stockModal = document.getElementById('stockModal');
        const closeStockModal = document.getElementById('closeStockModal');
        const closeStockBtn = document.getElementById('closeStockBtn');
        const stockModalTitle = document.getElementById('stockModalTitle');
        const stockContent = document.getElementById('stockContent');
        
        document.querySelectorAll('.view-stock-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                
                stockModalTitle.textContent = 'Stock Levels: ' + productName;
                stockModal.classList.remove('hidden');
                
                // Fetch stock information
                fetch(`get_stock.php?product_id=${productId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.stock.length > 0) {
                            let stockHtml = `
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Stock</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                            `;
                            
                            data.stock.forEach(item => {
                                // Set color class based on stock level
                                let stockColorClass = 'text-green-600';
                                if (item.product_stock < 10) {
                                    stockColorClass = 'text-yellow-600';
                                }
                                if (item.product_stock <= 5) {
                                    stockColorClass = 'text-red-600';
                                }
                                
                                stockHtml += `
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${item.size}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium ${stockColorClass}">${item.product_stock}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.product_sold}</td>
                                    </tr>
                                `;
                            });
                            
                            stockHtml += `
                                    </tbody>
                                </table>
                                <div class="mt-4 text-sm text-gray-500">
                                    <div class="flex items-center mb-1">
                                        <span class="h-3 w-3 rounded-full bg-green-600 mr-2"></span>
                                        <span>Good stock level (10+)</span>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <span class="h-3 w-3 rounded-full bg-yellow-600 mr-2"></span>
                                        <span>Low stock level (6-9)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-red-600 mr-2"></span>
                                        <span>Critical stock level (0-5)</span>
                                    </div>
                                </div>
                            `;
                            
                            stockContent.innerHTML = stockHtml;
                        } else {
                            stockContent.innerHTML = `
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-exclamation-circle text-3xl mb-3"></i>
                                    <p>No stock information available for this product.</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching stock data:', error);
                        stockContent.innerHTML = `
                            <div class="p-6 text-center text-red-500">
                                <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                                <p>Error loading stock information. Please try again.</p>
                            </div>
                        `;
                    });
            });
        });
        
        closeStockModal.addEventListener('click', () => {
            stockModal.classList.add('hidden');
        });
        
        closeStockBtn.addEventListener('click', () => {
            stockModal.classList.add('hidden');
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === categoryModal) {
                categoryModal.classList.add('hidden');
            }
            if (event.target === stockModal) {
                stockModal.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>