<?php
// Start output buffering at the very beginning
ob_start();

$_title = 'Insert Product';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['form_token'];

// Function to generate a new product ID
function generateProductId($db)
{
    $lastIdQuery = $db->query("SELECT product_id FROM product ORDER BY product_id DESC LIMIT 1");
    $lastId = $lastIdQuery->fetch(PDO::FETCH_COLUMN);

    if ($lastId) {
        $num = (int)substr($lastId, 1) + 1;
        return 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
    } else {
        return 'P001';
    }
}

// Function to get the next available quantity_id
function getNextQuantityId($db)
{
    $query = $db->query("SELECT MAX(quantity_id) FROM quantity");
    $maxId = $query->fetch(PDO::FETCH_COLUMN);
    return ($maxId) ? $maxId + 1 : 1;
}

// Function to check if product name already exists
function isProductNameExists($db, $product_name)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM product WHERE LOWER(product_name) = LOWER(?)");
    $stmt->execute([$product_name]);
    return $stmt->fetchColumn() > 0;
}

// Fetch categories from the database
$categoryQuery = $_db->query('SELECT category_id, category_name FROM category');
$categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate a new unique product ID
    $product_id = generateProductId($_db);

    // Collect form inputs
    $product_name = trim($_POST['product_name'] ?? '');
    $product_description = trim($_POST['product_description'] ?? '');
    $product_price = $_POST['product_price'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $product_type = $_POST['product_type'] ?? ''; // Added product_type field

    // Initialize error array
    $errors = [];

    // Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['form_token']) {
        $errors['security'] = 'Security validation failed. Please try again.';
    }
    if (empty($product_name)) {
        $errors['product_name'] = 'Product name is required';
    } elseif (strlen($product_name) > 255) {
        $errors['product_name'] = 'Product name must be less than 255 characters';
    } elseif (isProductNameExists($_db, $product_name)) {
        $errors['product_name'] = 'Product name already exists. Please choose a unique name.';
    }

    if (empty($product_description)) {
        $errors['product_description'] = 'Product description is required';
    }

    if (!is_numeric($product_price) || $product_price <= 0) {
        $errors['product_price'] = 'Valid product price is required';
    }

    if (empty($category_id)) {
        $errors['category_id'] = 'Category is required';
    }

    // Validate product_type
    if (empty($product_type)) {
        $errors['product_type'] = 'Product type is required';
    } elseif (!in_array($product_type, ['Unisex', 'Man', 'Women'])) {
        $errors['product_type'] = 'Invalid product type selected';
    }

    // Image upload handling
    $image_filenames = [];
    $upload_dir = '../../img/';

    // Ensure upload directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle uploaded images from drag and drop
    if (isset($_POST['uploaded_images']) && !empty($_POST['uploaded_images'])) {
        $uploaded_images = json_decode($_POST['uploaded_images'], true);

        if (is_array($uploaded_images) && count($uploaded_images) > 0) {
            $image_filenames = $uploaded_images;
        } else {
            $errors['product_images'] = 'At least one image is required';
        }
    } else {
        $errors['product_images'] = 'At least one image is required';
    }

    // Collect size and quantity information
    $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
    $size_quantities = [];
    $total_stock = 0;

    foreach ($sizes as $size) {
        $quantity = isset($_POST['quantity_' . $size]) ? (int)$_POST['quantity_' . $size] : 0;
        if ($quantity < 0) {
            $errors['quantity_' . $size] = 'Quantity for size ' . $size . ' cannot be negative';
        }
        $size_quantities[$size] = $quantity;
        $total_stock += $quantity;
    }

    if ($total_stock <= 0) {
        $errors['product_stock'] = 'Total product stock must be greater than zero';
    }

    // If no errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Start transaction
            $_db->beginTransaction();

            // Prepare image filename storage for up to 3 images (based on your schema)
            $image_slots = array_pad($image_filenames, 3, null);

            // Insert product
            $stmt = $_db->prepare("INSERT INTO product (
                product_id, 
                category_id,
                product_name, 
                product_pic1, product_pic2, product_pic3,
                product_description, product_price, 
                product_type, 
                product_status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?
            )");

            $stmt->execute([
                $product_id,
                $category_id,
                $product_name,
                $image_slots[0],
                $image_slots[1],
                $image_slots[2],
                $product_description,
                $product_price,
                $product_type, // Include product_type in the SQL insert
                'Available' // Default status
            ]);

            // Get the next available quantity_id
            $next_quantity_id = getNextQuantityId($_db);

            // Insert quantity for each size
            foreach ($sizes as $size) {
                if ($size_quantities[$size] > 0) {
                    $quantity_stmt = $_db->prepare("INSERT INTO quantity (
                        quantity_id, product_id, size, product_stock, product_sold
                    ) VALUES (?, ?, ?, ?, ?)");

                    $quantity_stmt->execute([
                        $next_quantity_id++,  // Auto-increment the quantity_id for each insertion
                        $product_id,
                        $size,
                        $size_quantities[$size],
                        0 // Default sold value
                    ]);
                }
            }

            // Commit transaction
            $_db->commit();

            // Set success message in session
            $_SESSION['success_message'] = "Product '$product_name' added successfully!";
            
            // Redirect to product list page
            header("Location: product.php");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction
            $_db->rollBack();

            // Remove uploaded images
            foreach ($image_filenames as $filename) {
                @unlink($upload_dir . $filename);
            }

            // Log error
            error_log("Product insertion error: " . $e->getMessage());
            $errors['database'] = 'Failed to insert product. Please try again. Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Product - K&P</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="Insert_Product.js"></script>
    <link href="Insert_Product.css" rel="stylesheet">
</head>

<body>
    <div class="container mx-auto px-4 py-8">
        <div class="card bg-white p-8 max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Insert New Product</h1>
                <p class="text-gray-600">Add a new product to your inventory</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-md animate-fadeInUp" role="alert">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <p class="font-bold">Please fix the following errors:</p>
                    </div>
                    <ul class="list-disc pl-5">
                        <?php foreach ($errors as $field => $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="productForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="uploaded_images" id="uploadedImagesInput">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="product_type" id="product_type_input" value="<?= htmlspecialchars($_POST['product_type'] ?? '') ?>">

                <div class="panel-section">
                    <div class="section-title mb-4">
                        <i class="fas fa-info-circle text-blue-700"></i>
                        <h2 class="text-lg font-semibold">Basic Information</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="product_name" class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                            <input type="text" id="product_name" name="product_name"
                                value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                                class="form-input w-full rounded-lg px-4 py-2 focus:outline-none"
                                maxlength="255" required
                                placeholder="Enter unique product name">
                        </div>

                        <div>
                            <label for="product_price" class="block text-sm font-medium text-gray-700 mb-1">Price (RM)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">RM</span>
                                </div>
                                <input type="number" id="product_price" name="product_price"
                                    value="<?= htmlspecialchars($_POST['product_price'] ?? '') ?>"
                                    class="form-input w-full rounded-lg pl-12 pr-4 py-2 focus:outline-none"
                                    step="0.01" min="0" required
                                    placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select id="category_id" name="category_id"
                            class="form-input w-full rounded-lg px-4 py-2 focus:outline-none"
                            required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['category_id']) ?>"
                                    <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Type</label>
                        <div class="product-type-selector">
                            <div class="type-option <?= (isset($_POST['product_type']) && $_POST['product_type'] == 'Man') ? 'selected' : '' ?>" data-value="Man">
                                <i class="fas fa-male mr-1"></i> Men
                            </div>
                            <div class="type-option <?= (isset($_POST['product_type']) && $_POST['product_type'] == 'Women') ? 'selected' : '' ?>" data-value="Women">
                                <i class="fas fa-female mr-1"></i> Women
                            </div>
                            <div class="type-option <?= (isset($_POST['product_type']) && $_POST['product_type'] == 'Unisex') ? 'selected' : '' ?>" data-value="Unisex">
                                <i class="fas fa-user-friends mr-1"></i> Unisex
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="product_description" class="block text-sm font-medium text-gray-700 mb-1">Product Description</label>
                        <textarea id="product_description" name="product_description"
                            class="form-input w-full rounded-lg px-4 py-2 focus:outline-none"
                            rows="4" required
                            placeholder="Provide a detailed description of the product"><?= htmlspecialchars($_POST['product_description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="panel-section">
                    <div class="section-title mb-4">
                        <i class="fas fa-boxes text-green-700"></i>
                        <h2 class="text-lg font-semibold text-green-800">Inventory Management</h2>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Enter the stock quantity for each available size</p>

                    <div class="size-quantity">
                        <?php foreach (['S', 'M', 'L', 'XL', 'XXL'] as $size): ?>
                            <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-2">
                                    <label for="quantity_<?= $size ?>" class="block text-sm font-medium text-gray-700">Size <?= $size ?></label>
                                    <span class="size-badge"><?= $size ?></span>
                                </div>
                                <input type="number" id="quantity_<?= $size ?>" name="quantity_<?= $size ?>"
                                    value="<?= htmlspecialchars($_POST['quantity_' . $size] ?? '0') ?>"
                                    class="form-input w-full rounded-lg px-4 py-2 focus:outline-none"
                                    min="0">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 italic">
                        <i class="fas fa-info-circle mr-1"></i> At least one size must have stock quantity greater than zero
                    </p>
                </div>

                <div class="panel-section">
                    <div class="section-title mb-4">
                        <i class="fas fa-images text-blue-700"></i>
                        <h2 class="text-lg font-semibold text-blue-800">Product Images</h2>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Upload up to 3 high-quality images of your product</p>

                    <div id="dropZone" class="drop-zone">
                        <div class="drop-zone-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="drop-zone-prompt">
                            <p class="text-lg font-medium">Drag and drop images here</p>
                            <p class="text-sm text-gray-500 mt-1">or click to browse files</p>
                        </div>
                        <input type="file" id="fileInput" multiple accept="image/*" class="hidden" />
                        <p class="text-xs text-gray-500 mt-4">
                            <i class="fas fa-exclamation-circle mr-1"></i> Maximum 3 images, JPEG or PNG format recommended
                        </p>
                    </div>
                    <div id="dropZonePreviews" class="drop-zone-previews"></div>
                    
                    <!-- NEW: Image Processing Tips -->
                    <div class="bg-blue-50 p-3 rounded-lg mt-4">
                        <h3 class="text-sm font-medium text-blue-800 flex items-center mb-2">
                            <i class="fas fa-lightbulb text-blue-600 mr-2"></i>
                            Image Editing Tips
                        </h3>
                        <p class="text-xs text-blue-700">
                            After uploading, hover over any image to reveal editing options. You can rotate and flip images to get the perfect product shot.
                        </p>
                    </div>
                </div>

                <div class="flex justify-center space-x-4 pt-6">
                    <button type="submit"
                        class="btn-primary focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <i class="fas fa-plus-circle mr-2"></i> Add Product
                    </button>
                    <button type="reset"
                        class="btn-secondary focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <a href="product.php"
                        class="btn-danger focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        <i class="fas fa-times-circle mr-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>


</body>

</html>
<?php
// Flush the output buffer at the end of the script
ob_end_flush();
?>