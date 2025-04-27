<?php
$_title = 'Product Management';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Initialize variables
$page = get('page', 1);
$limit = get('limit', 10);
$search = get('search', '');
$filter_category = get('category', '');
$filter_status = get('status', '');
$min_price = get('min_price', '');
$max_price = get('max_price', '');
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

    // Check for low stock products (less than 10)
    $stmt = $_db->prepare("
        SELECT p.product_id, p.product_name, q.size, q.product_stock 
        FROM product p 
        JOIN quantity q ON p.product_id = q.product_id 
        WHERE q.product_stock < 10 AND p.product_status = 'Available'
        ORDER BY q.product_stock ASC
    ");
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll();
    $low_stock_count = count($low_stock_products);

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

    // Add price range filter
    if ($min_price !== '' && is_numeric($min_price)) {
        $query .= " AND p.product_price >= ?";
        $params[] = $min_price;
    }

    if ($max_price !== '' && is_numeric($max_price)) {
        $query .= " AND p.product_price <= ?";
        $params[] = $max_price;
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
    $low_stock_products = [];
    $low_stock_count = 0;
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

// Handle batch update via AJAX
if (is_post() && isset($_POST['batch_update'])) {
    $product_ids = $_POST['product_ids'] ?? [];
    $update_type = $_POST['update_type'] ?? '';
    $update_value = $_POST['update_value'] ?? '';
    $status_value = $_POST['status_value'] ?? '';
    $category_value = $_POST['category_value'] ?? '';

    if (empty($product_ids)) {
        echo json_encode(['success' => false, 'message' => 'No products selected']);
        exit;
    }

    try {
        $success_count = 0;

        // Begin transaction
        $_db->beginTransaction();

        foreach ($product_ids as $product_id) {
            switch ($update_type) {
                case 'increase_price':
                    // Increase price by percentage
                    $stmt = $_db->prepare("UPDATE product SET product_price = product_price * (1 + ?/100) WHERE product_id = ?");
                    $stmt->execute([(float)$update_value, $product_id]);
                    break;

                case 'decrease_price':
                    // Decrease price by percentage
                    $stmt = $_db->prepare("UPDATE product SET product_price = product_price * (1 - ?/100) WHERE product_id = ?");
                    $stmt->execute([(float)$update_value, $product_id]);
                    break;

                case 'set_price':
                    // Set exact price
                    $stmt = $_db->prepare("UPDATE product SET product_price = ? WHERE product_id = ?");
                    $stmt->execute([(float)$update_value, $product_id]);
                    break;

                case 'change_status':
                    // Update status
                    $stmt = $_db->prepare("UPDATE product SET product_status = ? WHERE product_id = ?");
                    $stmt->execute([$status_value, $product_id]);
                    break;

                case 'change_category':
                    // Update category
                    $stmt = $_db->prepare("UPDATE product SET category_id = ? WHERE product_id = ?");
                    $stmt->execute([$category_value, $product_id]);
                    break;

                default:
                    throw new Exception('Invalid update type');
            }

            $success_count++;
        }

        // Commit transaction
        $_db->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully updated $success_count products"
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $_db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle CSV file upload
if (is_post() && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];

        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        // Check file type
        $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
        if (!in_array($file['type'], $allowed_types) && !preg_match('/\.csv$/i', $file['name'])) {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }

        // Read and process CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open file for reading.');
        }

        // Begin transaction
        $_db->beginTransaction();

        $header = fgetcsv($handle);
        $expected_columns = ['product_id', 'category_id', 'product_name', 'product_description', 'product_type', 'product_price', 'product_status'];
        $header = array_map('strtolower', $header);

        // Validate header columns
        foreach ($expected_columns as $col) {
            if (!in_array($col, $header)) {
                throw new Exception("Required column '$col' not found in CSV file.");
            }
        }

        $success_count = 0;
        $row_number = 1; // Header is row 1

        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;

            if (count($data) !== count($header)) {
                throw new Exception("Row $row_number has incorrect number of columns.");
            }

            // Create associative array from CSV data
            $product_data = array_combine($header, $data);

            // Check if product_id exists
            $check_stmt = $_db->prepare("SELECT COUNT(*) FROM product WHERE product_id = ?");
            $check_stmt->execute([$product_data['product_id']]);
            $product_exists = $check_stmt->fetchColumn() > 0;

            if ($product_exists) {
                // Update existing product
                $stmt = $_db->prepare("
                    UPDATE product SET 
                        category_id = ?, 
                        product_name = ?,
                        product_description = ?, 
                        product_type = ?,
                        product_price = ?,
                        product_status = ?
                    WHERE product_id = ?
                ");

                $stmt->execute([
                    $product_data['category_id'],
                    $product_data['product_name'],
                    $product_data['product_description'],
                    $product_data['product_type'],
                    $product_data['product_price'],
                    $product_data['product_status'],
                    $product_data['product_id']
                ]);
            } else {
                // Insert new product
                $stmt = $_db->prepare("
                    INSERT INTO product (
                        product_id, category_id, product_name, product_description, 
                        product_type, product_price, product_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $product_data['product_id'],
                    $product_data['category_id'],
                    $product_data['product_name'],
                    $product_data['product_description'],
                    $product_data['product_type'],
                    $product_data['product_price'],
                    $product_data['product_status']
                ]);
            }

            $success_count++;
        }

        fclose($handle);

        // Commit transaction
        $_db->commit();

        temp('success', "Successfully imported $success_count products from CSV");
        redirect('product.php');
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($_db) && $_db->inTransaction()) {
            $_db->rollBack();
        }
        temp('error', $e->getMessage());
        redirect('product.php');
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
    <link href="/admin/product/product.css" rel="stylesheet">
    <script src="product.js"></script>

</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Product Management</h1>
            <div class="flex flex-wrap gap-2">
                <a href="Insert_Product.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add Product
                </a>
                <a href="../category/Add_Category.php" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-folder-plus mr-2"></i> Add Category
                </a>
                <a href="product_csv_upload.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-file-upload mr-2"></i> Batch Upload
                </a>
                <button id="batchUpdateBtn" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-edit mr-2"></i> Batch Update
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

            <!-- Low Stock Alert -->
            <div id="lowStockCard" class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 <?= $low_stock_count > 0 ? 'border-red-500 cursor-pointer' : 'border-gray-500' ?>" data-count="<?= $low_stock_count ?>">
            <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon <?= $low_stock_count > 0 ? 'bg-red-100 text-red-600 animate-pulse' : 'bg-gray-100 text-gray-600' ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Low Stock Items</p>
                            <p class="text-2xl font-bold"><?= number_format($low_stock_count) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4" id="filterForm">
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

                <!-- Price Range Filter -->
                <div>
                    <label for="min_price" class="block text-sm font-medium text-gray-700 mb-1">Min Price (RM)</label>
                    <input type="number" id="min_price" name="min_price" min="0" step="0.01" value="<?= htmlspecialchars($min_price) ?>" placeholder="Min price" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="max_price" class="block text-sm font-medium text-gray-700 mb-1">Max Price (RM)</label>
                    <input type="number" id="max_price" name="max_price" min="0" step="0.01" value="<?= htmlspecialchars($max_price) ?>" placeholder="Max price" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                            <th class="px-2 py-3 text-center">
                                <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-indigo-600 cursor-pointer">
                            </th>
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
                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-2 py-4 whitespace-nowrap text-center">
                                        <input type="checkbox" class="product-select form-checkbox h-4 w-4 text-indigo-600 cursor-pointer" value="<?= $product->product_id ?>">
                                    </td>
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
                                        <a href="?page=1&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
                                            class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1 rounded-l-md">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
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
                                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
                                            class="page-link <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-100' ?> border border-gray-300 px-3 py-1">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
                                            class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $total_pages ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>&status=<?= urlencode($filter_status) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
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

    <!-- Low Stock Modal -->
    <div id="lowStockModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Low Stock Alert
                </h3>
                <button id="closeLowStockModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6">
                <?php if (count($low_stock_products) > 0): ?>
                    <p class="mb-4 text-gray-700">The following products are running low on stock (less than 10 units):</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($low_stock_products as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item->product_id) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item->product_name) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item->size) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $item->product_stock <= 5 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                <?= $item->product_stock ?> units
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="Update_Product.php?id=<?= $item->product_id ?>" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit mr-1"></i> Update
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-700">No products are currently low in stock.</p>
                <?php endif; ?>
            </div>

            <div class="flex justify-end">
                <button type="button" id="closeLowStockBtn" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Batch Upload Modal -->
    <div id="batchUploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-file-upload mr-2"></i>
                    Batch Upload Products
                </h3>
                <button id="closeBatchUploadModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="product.php" method="post" enctype="multipart/form-data">
                <div class="mb-6">
                    <p class="mb-4 text-gray-700">Upload a CSV file containing product information.</p>

                    <div class="mb-4">
                        <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required
                            class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> CSV file must have the following columns:
                                </p>
                                <ul class="mt-1 text-xs text-yellow-700 list-disc list-inside">
                                    <li>product_id (e.g., P001)</li>
                                    <li>category_id (e.g., CAT1001)</li>
                                    <li>product_name</li>
                                    <li>product_description</li>
                                    <li>product_type (Unisex, Man, or Women)</li>
                                    <li>product_price</li>
                                    <li>product_status (Available, Out of Stock, or Discontinued)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <a href="product_template.csv" download class="font-medium underline">
                                        Download CSV Template
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelBatchUpload" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-upload mr-1"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Update Modal -->
    <div id="batchUpdateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-edit mr-2"></i>
                    Batch Update Products
                </h3>
                <button id="closeBatchUpdateModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6">
                <p id="selectedProductsCount" class="mb-4 text-gray-700">No products selected.</p>

                <div class="mb-4">
                    <label for="updateType" class="block text-sm font-medium text-gray-700 mb-1">Update Type</label>
                    <select id="updateType" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Select Action --</option>
                        <option value="increase_price">Increase Price (%)</option>
                        <option value="decrease_price">Decrease Price (%)</option>
                        <option value="set_price">Set Exact Price (RM)</option>
                        <option value="change_status">Change Status</option>
                        <option value="change_category">Change Category</option>
                    </select>
                </div>

                <!-- Percentage fields (increase/decrease price) -->
                <div id="percentageFields" class="mb-4 hidden">
                    <label for="percentValue" class="block text-sm font-medium text-gray-700 mb-1">Percentage Value</label>
                    <div class="flex items-center">
                        <input type="number" id="percentValue" min="0.01" step="0.01" max="100"
                            class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span class="ml-2">%</span>
                    </div>
                </div>

                <!-- Exact price field (set price) -->
                <div id="exactPriceField" class="mb-4 hidden">
                    <label for="exactPrice" class="block text-sm font-medium text-gray-700 mb-1">New Price</label>
                    <div class="flex items-center">
                        <span class="mr-2">RM</span>
                        <input type="number" id="exactPrice" min="0.01" step="0.01"
                            class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Status field -->
                <div id="statusField" class="mb-4 hidden">
                    <label for="newStatus" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                    <select id="newStatus" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Available">Available</option>
                        <option value="Out of Stock">Out of Stock</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>

                <!-- Category field -->
                <div id="categoryField" class="mb-4 hidden">
                    <label for="newCategory" class="block text-sm font-medium text-gray-700 mb-1">New Category</label>
                    <select id="newCategory" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat->category_id ?>">
                                <?= htmlspecialchars($cat->category_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelBatchUpdate" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg">
                    Cancel
                </button>
                <button type="button" id="applyBatchUpdate" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg" disabled>
                    <i class="fas fa-check mr-1"></i> Apply Update
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php $error = temp('error');
    if ($error): ?>
        <div id="errorAlert" class="fixed right-5 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md z-1001 transform transition-transform duration-500 translate-x-0" style="top: <?= isset($navbar_height) ? $navbar_height : '85' ?>px;">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <span><?= $error ?></span>
            </div>
            <button class="absolute top-1 right-1 text-red-500 hover:text-red-700" onclick="document.getElementById('errorAlert').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>

    <?php endif; ?>

    <?php $success = temp('success');
    if ($success): ?>
        <div id="successAlert" class="fixed right-5 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md z-1001 transform transition-transform duration-500 translate-x-0" style="top: <?= isset($navbar_height) ? $navbar_height : '85' ?>px;">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <span><?= $success ?></span>
            </div>
            <button class="absolute top-1 right-1 text-green-500 hover:text-green-700" onclick="document.getElementById('successAlert').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>

    <?php endif; ?>

    <?php require '../headFooter/footer.php'; ?>
</body>

</html>