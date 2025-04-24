<?php
// Start output buffering at the very beginning
ob_start();

$_title = 'Add New Category';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Function to generate new sequential category ID
function generate_category_id($_db) {
    // Get the highest existing category ID
    $stmt = $_db->prepare("SELECT MAX(CAST(SUBSTRING(category_id, 4) AS UNSIGNED)) as max_id FROM category WHERE category_id LIKE 'CAT%'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    $next_id = 1001; // Default starting ID
    
    if ($result && $result->max_id) {
        $next_id = (int)$result->max_id + 1;
    }
    
    // Format the new category ID
    return "CAT" . $next_id;
}

// Generate preview ID for display
$preview_category_id = generate_category_id($_db);

// Process form submission
if (is_post()) {
    // Auto-generate category ID
    $category_id = generate_category_id($_db);
    $category_name = post('category_name');
    
    // Validation
    $_err = [];
    
    if (empty($category_name)) {
        $_err['category_name'] = 'Category Name is required';
    } elseif (strlen($category_name) > 255) {
        $_err['category_name'] = 'Category Name must be 255 characters or less';
    }
    
    // If no errors, insert new category
    if (empty($_err)) {
        try {
            $stm = $_db->prepare('INSERT INTO category (category_id, category_name) VALUES (?, ?)');
            $stm->execute([$category_id, $category_name]);
            
            temp('success', 'Category added successfully with ID: ' . $category_id);
            redirect('category.php');
        } catch (PDOException $e) {
            temp('error', 'Database error: ' . $e->getMessage());
        }
    }
}

// Fetch recent categories for display
$stmt = $_db->query("SELECT * FROM category ORDER BY category_id DESC LIMIT 6");
$recent_categories = $stmt->fetchAll();
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
    <style>
        /* Additional custom styles */
        .card-hover-effect {
            transition: all 0.3s ease;
        }
        .card-hover-effect:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1.25rem;
            margin: 0.25rem;
            transition: all 0.2s ease;
        }
        .category-badge:hover {
            transform: scale(1.05);
        }
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        .preview-container {
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            border: 1px dashed #cbd5e0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="flex items-center mb-8">
            <a href="category.php" class="group mr-4 flex items-center text-indigo-600 hover:text-indigo-800 transition-colors">
                <i class="fas fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i>
                <span>Back to Categories</span>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Add New Category</h1>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover-effect">
                    <!-- Form Header -->
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6">
                        <div class="flex items-center">
                            <div class="rounded-full bg-white p-3 mr-4">
                                <i class="fas fa-tag text-indigo-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-white text-xl font-bold">Create New Category</h2>
                                <p class="text-indigo-100 text-sm">Add a new product category to organize your inventory</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Body -->
                    <div class="p-8">
                        <form method="post" class="space-y-8">
                            <!-- Info Notice -->
                            <div class="flex items-start p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                <div class="flex-shrink-0 pt-0.5">
                                    <i class="fas fa-info-circle text-blue-500 text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Automatic ID Generation</h3>
                                    <p class="text-sm text-blue-700 mt-1">
                                        A unique Category ID will be assigned automatically in the format: <strong><?= $preview_category_id ?></strong>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Category Preview -->
                            <div class="preview-container p-6 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-700 mb-3">Preview:</h3>
                                <div class="flex items-center p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                                    <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-tags text-indigo-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm text-gray-500">ID: <span class="font-mono text-gray-800"><?= $preview_category_id ?></span></div>
                                        <div class="font-medium text-gray-800" id="previewName">
                                            <?= $GLOBALS['category_name'] ?? 'New Category Name' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Category Name Input -->
                            <div class="space-y-2">
                                <label for="category_name" class="block text-sm font-medium text-gray-700">
                                    Category Name <span class="text-red-600">*</span>
                                </label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-pencil-alt text-gray-400"></i>
                                    </div>
                                    <input type="text" id="category_name" name="category_name" 
                                           class="form-input block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none"
                                           value="<?= $GLOBALS['category_name'] ?? '' ?>"
                                           placeholder="e.g., Summer Collection"
                                           oninput="document.getElementById('previewName').textContent = this.value || 'New Category Name'">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <span class="text-gray-400 text-xs" id="charCount">0/255</span>
                                    </div>
                                </div>
                                <?php if (isset($_err['category_name'])): ?>
                                <p class="mt-1 text-sm text-red-600">
                                    <i class="fas fa-exclamation-circle mr-1"></i> <?= $_err['category_name'] ?>
                                </p>
                                <?php else: ?>
                                <p class="mt-1 text-xs text-gray-500">
                                    Enter a descriptive name for the category (max 255 characters)
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button type="submit" class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                    <i class="fas fa-save mr-2"></i> Save Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Section -->
            <div class="lg:col-span-1">
                <!-- Recent Categories Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6 card-hover-effect">
                    <div class="bg-gradient-to-r from-green-500 to-teal-500 p-4">
                        <h3 class="text-white font-bold">Recent Categories</h3>
                        <p class="text-green-100 text-xs">Recently added categories in the system</p>
                    </div>
                    <div class="p-4">
                        <?php if (count($recent_categories) > 0): ?>
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($recent_categories as $category): ?>
                                <li class="py-3 hover:bg-gray-50 px-2 rounded-lg transition-colors">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($category->category_name) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($category->category_id) ?></p>
                                        </div>
                                        <a href="update_category.php?id=<?= $category->category_id ?>" class="text-indigo-600 hover:text-indigo-800">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="py-4 text-center text-gray-500">
                                <i class="fas fa-folder-open text-gray-400 text-3xl mb-2"></i>
                                <p>No categories found</p>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4 text-center">
                            <a href="category.php" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                View all categories <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover-effect">
                    <div class="bg-gradient-to-r from-yellow-400 to-orange-500 p-4">
                        <h3 class="text-white font-bold">Tips & Guidelines</h3>
                        <p class="text-yellow-100 text-xs">Best practices for category management</p>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <p class="ml-2 text-sm text-gray-600">
                                    Use clear and descriptive category names
                                </p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <p class="ml-2 text-sm text-gray-600">
                                    Avoid creating duplicate categories
                                </p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <p class="ml-2 text-sm text-gray-600">
                                    Category IDs are automatically generated
                                </p>
                            </li>
                            <li class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <p class="ml-2 text-sm text-gray-600">
                                    Categories with products cannot be deleted
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require '../headFooter/footer.php'; ?>
    
    <script>
        // Character counter for category name
        document.getElementById('category_name').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('charCount').textContent = count + '/255';
            
            // Change color based on length
            if (count > 200) {
                document.getElementById('charCount').className = 'text-orange-500 text-xs';
            } else {
                document.getElementById('charCount').className = 'text-gray-400 text-xs';
            }
        });
        
        // Trigger initial character count
        document.addEventListener('DOMContentLoaded', function() {
            const inputElement = document.getElementById('category_name');
            if (inputElement.value) {
                const event = new Event('input');
                inputElement.dispatchEvent(event);
            }
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer and send output to browser
ob_end_flush();
?>