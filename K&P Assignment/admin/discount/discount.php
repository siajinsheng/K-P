<?php
$_title = 'Discount Management';
require_once '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

// Initialize variables - CAST TO INTEGERS to fix the SQL error
$page = (int)get('page', 1);
$limit = (int)get('limit', 10);
$search = get('search', '');
$filter_status = get('status', '');
$filter_product = get('product', '');
$sort = get('sort', 'start_date');
$dir = get('dir', 'desc');
$offset = ($page - 1) * $limit;

// Get discount statistics
try {
    // Total discounts
    $stmt = $_db->prepare("SELECT COUNT(*) FROM discount");
    $stmt->execute();
    $total_discounts = $stmt->fetchColumn();
    
    // Active discounts
    $stmt = $_db->prepare("SELECT COUNT(*) FROM discount WHERE status = 'Active'");
    $stmt->execute();
    $active_discounts = $stmt->fetchColumn();
    
    // Upcoming discounts
    $stmt = $_db->prepare("SELECT COUNT(*) FROM discount WHERE status = 'Upcoming'");
    $stmt->execute();
    $upcoming_discounts = $stmt->fetchColumn();
    
    // Expired discounts
    $stmt = $_db->prepare("SELECT COUNT(*) FROM discount WHERE status = 'Expired'");
    $stmt->execute();
    $expired_discounts = $stmt->fetchColumn();
    
    // Average discount rate
    $stmt = $_db->prepare("SELECT AVG(discount_rate) FROM discount WHERE status = 'Active'");
    $stmt->execute();
    $avg_discount_rate = $stmt->fetchColumn() ?: 0;
    
    // Get all products for dropdown filter
    $stmt = $_db->prepare("SELECT product_id, product_name FROM product ORDER BY product_name");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Build the discount query with filters
    $query = "
        SELECT d.*, p.product_name, p.product_pic1, p.product_price 
        FROM discount d
        JOIN product p ON d.product_id = p.product_id
        WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (d.Discount_id LIKE ? OR p.product_name LIKE ? OR d.product_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND d.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_product)) {
        $query .= " AND d.product_id = ?";
        $params[] = $filter_product;
    }
    
    // Count total filtered records
    $count_stmt = $_db->prepare("SELECT COUNT(*) FROM ($query) AS filtered");
    $count_stmt->execute($params);
    $total_filtered = $count_stmt->fetchColumn();
    
    // Add sorting and pagination
    $valid_sorts = ['Discount_id', 'product_id', 'discount_rate', 'start_date', 'end_date', 'status'];
    if (!in_array($sort, $valid_sorts)) {
        $sort = 'start_date';
    }
    
    // FIX: Directly include integer values in the query instead of using parameters
    $query .= " ORDER BY d.$sort $dir LIMIT $limit OFFSET $offset";
    
    $stmt = $_db->prepare($query);
    $stmt->execute($params);
    $discounts = $stmt->fetchAll();
    
    // Calculate total pages
    $total_pages = ceil($total_filtered / $limit);
    
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    $discounts = [];
    $total_pages = 0;
}

// Update all discount statuses automatically
$today = date('Y-m-d');
try {
    $_db->exec("
        UPDATE discount 
        SET status = 
            CASE 
                WHEN '$today' BETWEEN start_date AND end_date THEN 'Active'
                WHEN '$today' < start_date THEN 'Upcoming'
                ELSE 'Expired'
            END
    ");
} catch (PDOException $e) {
    error_log("Error updating discount statuses: " . $e->getMessage());
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
    <style>
        .dashboard-card {
            transition: all 0.3s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.upcoming {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge.expired {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .date-range-badge {
            background-color: #f3f4f6;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            color: #4b5563;
            display: inline-flex;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Discount Management</h1>
            <a href="add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Discount
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Discounts -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-indigo-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-indigo-100 text-indigo-600">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Discounts</p>
                            <p class="text-2xl font-bold"><?= number_format($total_discounts) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Discounts -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-green-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-green-100 text-green-600">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Active Discounts</p>
                            <p class="text-2xl font-bold"><?= number_format($active_discounts) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Discounts -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-blue-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-blue-100 text-blue-600">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Upcoming</p>
                            <p class="text-2xl font-bold"><?= number_format($upcoming_discounts) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expired Discounts -->
            <div class="dashboard-card bg-white rounded-lg shadow overflow-hidden border-l-4 border-red-500">
                <div class="p-4">
                    <div class="flex items-center">
                        <div class="stat-icon bg-red-100 text-red-600">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Expired</p>
                            <p class="text-2xl font-bold"><?= number_format($expired_discounts) ?></p>
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
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search discounts..." class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                
                <div>
                    <label for="product" class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <select id="product" name="product" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product->product_id ?>" <?= $product->product_id === $filter_product ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product->product_id . ' - ' . $product->product_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All Status</option>
                        <option value="Active" <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Upcoming" <?= $filter_status === 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="Expired" <?= $filter_status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg mr-2">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="discount.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success = temp('success')): ?>
            <div id="successAlert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-check-circle mr-3"></i></div>
                    <div><?= $success ?></div>
                </div>
                <button class="float-right" onclick="document.getElementById('successAlert').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($error = temp('error')): ?>
            <div id="errorAlert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow">
                <div class="flex items-center">
                    <div class="py-1"><i class="fas fa-exclamation-circle mr-3"></i></div>
                    <div><?= $error ?></div>
                </div>
                <button class="float-right" onclick="document.getElementById('errorAlert').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Discounts Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="font-bold text-xl">Discounts List</h2>
                <div class="flex items-center">
                    <span class="text-sm text-gray-600 mr-2">Show:</span>
                    <select id="limitSelect" class="border border-gray-300 rounded-md p-1 text-sm">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="Discount_id">
                                ID <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="discount_rate">
                                Discount <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="start_date">
                                Start Date <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="end_date">
                                End Date <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" data-sort="status">
                                Status <i class="fas fa-sort ml-1"></i>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Final Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($discounts)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No discounts found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($discounts as $discount): ?>
                                <?php 
                                    // Calculate discounted price
                                    $final_price = $discount->product_price * (1 - ($discount->discount_rate / 100));
                                    
                                    // Determine status class
                                    $status_class = '';
                                    if ($discount->status === 'Active') {
                                        $status_class = 'active';
                                    } elseif ($discount->status === 'Upcoming') {
                                        $status_class = 'upcoming';
                                    } elseif ($discount->status === 'Expired') {
                                        $status_class = 'expired';
                                    }
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($discount->Discount_id) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php if ($discount->product_pic1): ?>
                                                <img src="../../img/<?= $discount->product_pic1 ?>" alt="Product Image" class="product-image mr-3">
                                            <?php else: ?>
                                                <div class="h-12 w-12 bg-gray-200 flex items-center justify-center rounded-md mr-3">
                                                    <i class="fas fa-tshirt text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($discount->product_id) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($discount->product_name) ?></div>
                                                <div class="text-xs text-gray-700">RM <?= number_format($discount->product_price, 2) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-bold text-red-600"><?= number_format($discount->discount_rate, 1) ?>%</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d M Y', strtotime($discount->start_date)) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d M Y', strtotime($discount->end_date)) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= htmlspecialchars($discount->status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-xs text-gray-500 line-through">RM <?= number_format($discount->product_price, 2) ?></div>
                                        <div class="font-bold text-green-600">RM <?= number_format($final_price, 2) ?></div>
                                        <div class="text-xs text-gray-500">Save: RM <?= number_format($discount->product_price - $final_price, 2) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="edit.php?id=<?= $discount->Discount_id ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Delete button removed as requested -->
                                        </div>
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
                            Showing <?= min(($page - 1) * $limit + 1, $total_filtered) ?> to <?= min($page * $limit, $total_filtered) ?> of <?= $total_filtered ?> discounts
                        </div>
                        <div>
                            <ul class="pagination flex">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a href="?page=1&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&product=<?= urlencode($filter_product) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1 rounded-l-md">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&product=<?= urlencode($filter_product) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
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
                                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&product=<?= urlencode($filter_product) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-100' ?> border border-gray-300 px-3 py-1">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&product=<?= urlencode($filter_product) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
                                           class="page-link bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-3 py-1">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a href="?page=<?= $total_pages ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&product=<?= urlencode($filter_product) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>" 
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

    <?php require '../headFooter/footer.php'; ?>

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
            
            // Automatically hide alerts after 5 seconds
            const alerts = document.querySelectorAll('#successAlert, #errorAlert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>