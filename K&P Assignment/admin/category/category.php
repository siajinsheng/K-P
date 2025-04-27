<?php
// Start output buffering at the very beginning
ob_start();

$_title = 'Category Management';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Handle duplicate fix action
if (isset($_GET['fix_duplicates']) && $_GET['fix_duplicates'] == 'true') {
    try {
        // Find all duplicate category IDs
        $find_duplicates = "SELECT category_id, COUNT(*) as count FROM category GROUP BY category_id HAVING COUNT(*) > 1";
        $stmt = $_db->query($find_duplicates);
        $duplicates = $stmt->fetchAll();
        $fixed_count = 0;

        foreach ($duplicates as $dupe) {
            // Get records for this category ID
            $get_records = "SELECT * FROM category WHERE category_id = ? ORDER BY category_name";
            $stmt = $_db->prepare($get_records);
            $stmt->execute([$dupe->category_id]);
            $records = $stmt->fetchAll();

            // Keep the first one, delete the rest
            $first = true;
            foreach ($records as $record) {
                if ($first) {
                    $first = false;
                    continue;
                }

                // Check if category has products before deleting
                $check_products = "SELECT COUNT(*) FROM product WHERE category_id = ?";
                $stmt = $_db->prepare($check_products);
                $stmt->execute([$record->category_id]);
                $has_products = $stmt->fetchColumn() > 0;

                if ($has_products) {
                    // Generate a new unique ID for this duplicate since it has products
                    $get_max_id = "SELECT MAX(CAST(SUBSTRING(category_id, 4) AS UNSIGNED)) as max_id FROM category WHERE category_id LIKE 'CAT%'";
                    $stmt = $_db->query($get_max_id);
                    $result = $stmt->fetch();
                    $next_num = ($result && $result->max_id) ? (int)$result->max_id + 1 : 1001;
                    $new_id = 'CAT' . $next_num;

                    // Update the category ID in both tables
                    $update_category = "UPDATE category SET category_id = ? WHERE category_id = ? AND category_name = ?";
                    $stmt = $_db->prepare($update_category);
                    $stmt->execute([$new_id, $record->category_id, $record->category_name]);

                    $update_products = "UPDATE product SET category_id = ? WHERE category_id = ?";
                    $stmt = $_db->prepare($update_products);
                    $stmt->execute([$new_id, $record->category_id]);
                } else {
                    // Delete the duplicate if it has no products
                    $delete = "DELETE FROM category WHERE category_id = ? AND category_name = ?";
                    $stmt = $_db->prepare($delete);
                    $stmt->execute([$record->category_id, $record->category_name]);
                }
                $fixed_count++;
            }
        }

        if ($fixed_count > 0) {
            temp('success', "Fixed $fixed_count duplicate categories successfully.");
        } else {
            temp('info', "No duplicate categories found to fix.");
        }

        // Redirect to remove the query parameter
        redirect('category.php');
    } catch (PDOException $e) {
        temp('error', "Error fixing duplicates: " . $e->getMessage());
    }
}

// Check for duplicates in the database
$check_query = "SELECT COUNT(*) as total_rows, COUNT(DISTINCT category_id) as unique_ids FROM category";
$stmt = $_db->query($check_query);
$counts = $stmt->fetch();
$has_duplicates = $counts->total_rows > $counts->unique_ids;
$duplicate_count = $counts->total_rows - $counts->unique_ids;

// Handle messages
$success_message = temp('success');
$error_message = temp('error');
$info_message = temp('info');

// Search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting parameters
$sort = get('sort', 'category_id');
$dir = get('dir', 'asc');

// Build the query with search and sorting
$query_params = [];
$search_condition = '';

if (!empty($search)) {
    $search_condition = " WHERE category_id LIKE ? OR category_name LIKE ? ";
    $query_params[] = "%$search%";
    $query_params[] = "%$search%";
}

// Fetch categories with proper distinct handling - FIXED QUERY
try {
    // Simplified query to get unique categories
    $query = "SELECT * FROM category";

    if (!empty($search)) {
        $query .= " WHERE category_id LIKE ? OR category_name LIKE ?";
    }

    $query .= " ORDER BY $sort $dir";

    $stmt = $_db->prepare($query);
    $stmt->execute($query_params);
    $all_categories = $stmt->fetchAll();

    // Ensure uniqueness by category_id
    $categories = [];
    $seen_category_ids = [];

    foreach ($all_categories as $category) {
        if (!in_array($category->category_id, $seen_category_ids)) {
            $seen_category_ids[] = $category->category_id;
            $categories[] = $category;

            // Count products for this category
            $prod_query = "SELECT COUNT(*) FROM product WHERE category_id = ?";
            $prod_stmt = $_db->prepare($prod_query);
            $prod_stmt->execute([$category->category_id]);
            $category->product_count = $prod_stmt->fetchColumn();
        }
    }
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    $categories = [];
}

// Calculate total counts for statistics cards
$total_products = 0;
foreach ($categories as $category) {
    $total_products += $category->product_count;
}
$avg_products = count($categories) > 0 ? round($total_products / count($categories), 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="category.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Category data for chart - this defines the global variable used in the external JS file
        const categoryData = <?= json_encode(array_map(function ($cat) {
                                    return [
                                        'name' => $cat->category_name,
                                        'count' => $cat->product_count
                                    ];
                                }, $categories)) ?>;
    </script>
    <script src="category.js"></script>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Category Management</h1>
            <a href="add_category.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Category
            </a>
        </div>

        <!-- Alerts Section -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $success_message ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $error_message ?></span>
            </div>
        <?php endif; ?>

        <?php if ($info_message): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $info_message ?></span>
            </div>
        <?php endif; ?>

        <?php if ($has_duplicates): ?>
            <!-- Duplicate Categories Warning -->
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded relative mb-6" role="alert">
                <div class="flex">
                    <div class="py-1"><i class="fas fa-exclamation-triangle fa-lg mr-4"></i></div>
                    <div>
                        <p class="font-bold">Warning: Duplicate Categories Detected</p>
                        <p class="text-sm">
                            Found <?= $duplicate_count ?> duplicate category IDs in the database.
                            This can cause inconsistencies in your product catalog.
                        </p>
                        <div class="mt-3">
                            <a href="category.php?fix_duplicates=true"
                                class="fix-duplicates-btn py-2 px-4 rounded-lg inline-flex items-center">
                                <i class="fas fa-wrench mr-2"></i> Fix Duplicates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Categories -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Total Categories</h3>
                    <span class="p-2 rounded-full bg-indigo-100 text-indigo-700">
                        <i class="fas fa-tags"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-indigo-600"><?= count($categories) ?></p>
                <p class="text-sm text-gray-500 mt-2">Product categories</p>
            </div>

            <!-- Product Count -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Total Products</h3>
                    <span class="p-2 rounded-full bg-green-100 text-green-700">
                        <i class="fas fa-tshirt"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-green-600"><?= $total_products ?></p>
                <p class="text-sm text-gray-500 mt-2">Across all categories</p>
            </div>

            <!-- Average Products per Category -->
            <div class="dashboard-card bg-white rounded-lg shadow p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Avg Products/Category</h3>
                    <span class="p-2 rounded-full bg-blue-100 text-blue-700">
                        <i class="fas fa-chart-bar"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-blue-600"><?= $avg_products ?></p>
                <p class="text-sm text-gray-500 mt-2">Average distribution</p>
            </div>
        </div>

        <!-- Category Table Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">All Categories</h2>

                <!-- Search Box -->
                <div class="mb-6">
                    <form action="" method="GET" class="w-full md:w-1/3 ml-auto">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search categories..."
                                value="<?= htmlspecialchars($search) ?>">
                            <button type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Categories Table -->
                <div class="overflow-x-auto">
                    <table class="category-table min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                                    <?php
                                    $id_dir = $sort == 'category_id' ? ($dir == 'asc' ? 'desc' : 'asc') : 'asc';
                                    $id_class = $sort == 'category_id' ? ($dir == 'asc' ? '↑' : '↓') : '';
                                    $search_param = $search ? "&search=$search" : "";
                                    ?>
                                    <a href="?sort=category_id&dir=<?= $id_dir . $search_param ?>">
                                        Category ID <?= $id_class ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">
                                    <?php
                                    $name_dir = $sort == 'category_name' ? ($dir == 'asc' ? 'desc' : 'asc') : 'asc';
                                    $name_class = $sort == 'category_name' ? ($dir == 'asc' ? '↑' : '↓') : '';
                                    ?>
                                    <a href="?sort=category_name&dir=<?= $name_dir . $search_param ?>">
                                        Category Name <?= $name_class ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider products-column">
                                    Products
                                </th>
                                <th class="px-6 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider actions-column">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        <?php if (!empty($search)): ?>
                                            No categories found matching "<?= htmlspecialchars($search) ?>".
                                            <a href="category.php" class="text-indigo-600 hover:text-indigo-900">Clear search</a>
                                        <?php else: ?>
                                            No categories found. <a href="add_category.php" class="text-indigo-600 hover:text-indigo-900">Add your first category</a>.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($category->category_id) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($category->category_name) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="category-product-badge <?= $category->product_count > 0 ? '' : 'empty' ?>">
                                                <?= $category->product_count ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center space-x-3">
                                                <a href="update_category.php?id=<?= urlencode($category->category_id) ?>" title="Edit Category">
                                                    <i class="fas fa-edit edit-icon"></i>
                                                </a>
                                                <?php if ($category->product_count == 0): ?>
                                                    <a href="#" onclick="confirmDelete('<?= htmlspecialchars($category->category_id) ?>', '<?= htmlspecialchars($category->category_name) ?>')" title="Delete Category">
                                                        <i class="fas fa-trash delete-icon"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span title="Cannot delete: Category has products">
                                                        <i class="fas fa-trash disabled-icon"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category Distribution Chart -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Product Distribution by Category</h3>
            <div class="h-64">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full">
            <h2 class="text-xl font-bold mb-4">Confirm Deletion</h2>
            <p class="mb-6">Are you sure you want to delete the category "<span id="deleteCategoryName"></span>"? This action cannot be undone.</p>
            <div class="flex justify-end space-x-2">
                <button id="cancelDelete" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-4 rounded">
                    Cancel
                </button>
                <form id="deleteForm" action="delete_category.php" method="post">
                    <input type="hidden" id="deleteCategoryId" name="category_id">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php require '../headFooter/footer.php'; ?>
</body>

</html>
<?php
// Flush the output buffer and send output to browser
ob_end_flush();
?>