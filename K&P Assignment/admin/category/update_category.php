<?php
// Start output buffering at the very beginning
ob_start();

$_title = 'Update Category';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

$category_id = get('id');

if (empty($category_id)) {
    temp('error', 'Category ID is required');
    redirect('category.php');
}

// Fetch category details
try {
    $stm = $_db->prepare('SELECT * FROM category WHERE category_id = ?');
    $stm->execute([$category_id]);
    $category = $stm->fetch();
    
    if (!$category) {
        temp('error', 'Category not found');
        redirect('category.php');
    }
    
    // Set variables for form
    $GLOBALS['category_name'] = $category->category_name;
    
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    redirect('category.php');
}

// Process form submission
if (is_post()) {
    $category_name = post('category_name');
    
    // Validation
    $_err = [];
    
    if (empty($category_name)) {
        $_err['category_name'] = 'Category Name is required';
    } elseif (strlen($category_name) > 255) {
        $_err['category_name'] = 'Category Name must be 255 characters or less';
    }
    
    // If no errors, update category
    if (empty($_err)) {
        try {
            $stm = $_db->prepare('UPDATE category SET category_name = ? WHERE category_id = ?');
            $stm->execute([$category_name, $category_id]);
            
            temp('success', 'Category updated successfully');
            redirect('category.php');
        } catch (PDOException $e) {
            temp('error', 'Database error: ' . $e->getMessage());
        }
    }
}

// Get product count for this category
try {
    $stm = $_db->prepare('SELECT COUNT(*) FROM product WHERE category_id = ?');
    $stm->execute([$category_id]);
    $product_count = $stm->fetchColumn();
} catch (PDOException $e) {
    $product_count = 0;
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
    <link href="category.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center mb-6">
            <a href="category.php" class="mr-4 text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left"></i> Back to Categories
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Update Category</h1>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Category Form -->
            <div class="lg:col-span-2">
                <div class="dashboard-card bg-white rounded-lg shadow p-6">
                    <form method="post" class="space-y-6">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category ID</label>
                            <input type="text" id="category_id" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                                   value="<?= htmlspecialchars($category_id) ?>"
                                   disabled>
                            <p class="text-xs text-gray-500 mt-1">Category ID cannot be changed</p>
                        </div>
                        
                        <div>
                            <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                            <input type="text" id="category_name" name="category_name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?= $GLOBALS['category_name'] ?? '' ?>">
                            <?php err('category_name'); ?>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-md">
                                <i class="fas fa-save mr-2"></i> Update Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Category Info -->
            <div class="lg:col-span-1">
                <div class="dashboard-card bg-white rounded-lg shadow p-6">
                    <h3 class="font-bold text-lg text-gray-800 mb-4">Category Details</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <span class="text-gray-500 text-sm">ID</span>
                            <p class="font-semibold"><?= htmlspecialchars($category_id) ?></p>
                        </div>
                        
                        <div>
                            <span class="text-gray-500 text-sm">Products in this category</span>
                            <p class="font-semibold">
                                <span class="px-2 py-1 rounded-full <?= $product_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $product_count ?> products
                                </span>
                            </p>
                        </div>
                        
                        <?php if ($product_count > 0): ?>
                        <div class="pt-2">
                            <a href="../product/product.php?category=<?= urlencode($category_id) ?>" class="text-indigo-600 hover:text-indigo-800 text-sm flex items-center">
                                <i class="fas fa-eye mr-2"></i> View products in this category
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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