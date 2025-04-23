<?php
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
        $num = (int)substr($lastId, 2) + 1;
        return 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
    } else {
        return 'P001';
    }
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

            // Insert quantity for each size
            foreach ($sizes as $size) {
                if ($size_quantities[$size] > 0) {
                    $quantity_stmt = $_db->prepare("INSERT INTO quantity (
                        product_id, size, product_stock, product_sold
                    ) VALUES (?, ?, ?, ?)");

                    $quantity_stmt->execute([
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
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 1rem;
            transition: transform 0.2s ease-in-out;
            background-color: white;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .form-input {
            background-color: #f5f5f5;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            background-color: #fff;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .btn-primary {
            background-color: #4f46e5;
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
            color: white;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: #e2e8f0;
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
            color: #334155;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary:hover {
            background-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-danger {
            background-color: #ef4444;
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
            color: white;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f8fafc;
            cursor: pointer;
        }

        .drop-zone.drag-over {
            border-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.1);
        }

        .drop-zone-prompt {
            margin-bottom: 1rem;
            color: #64748b;
        }

        .drop-zone-icon {
            font-size: 2.5rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .drop-zone-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .drop-zone-preview {
            position: relative;
            height: 150px;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .drop-zone-preview:hover {
            transform: scale(1.05);
        }

        .drop-zone-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .drop-zone-preview-remove {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .drop-zone-preview:hover .drop-zone-preview-remove {
            opacity: 1;
        }

        .size-quantity {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
            gap: 1rem;
        }

        .size-badge {
            display: inline-block;
            background-color: #f3f4f6;
            color: #4b5563;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            margin-right: 0.5rem;
            font-weight: 600;
        }
        
        .product-type-selector {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .type-option {
            border: 2px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            flex: 1;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .type-option:hover {
            border-color: #94a3b8;
            background-color: #f8fafc;
        }
        
        .type-option.selected {
            border-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e40af;
        }
        
        .panel-section {
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            background: white;
        }
        
        /* Animation for success feedback */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out forwards;
        }
    </style>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const dropZonePreviews = document.getElementById('dropZonePreviews');
            const uploadedImagesInput = document.getElementById('uploadedImagesInput');
            const productTypeInput = document.getElementById('product_type_input');
            const typeOptions = document.querySelectorAll('.type-option');
            let uploadedFiles = [];

            // Handle product type selection
            typeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    typeOptions.forEach(el => el.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Update hidden input
                    productTypeInput.value = this.getAttribute('data-value');
                });
            });

            // Set initial selection if value exists
            if (productTypeInput.value) {
                document.querySelector(`.type-option[data-value="${productTypeInput.value}"]`)?.classList.add('selected');
            }

            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });

            // Handle dropped files
            dropZone.addEventListener('drop', handleDrop, false);

            // Handle click to select files
            dropZone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            }, false);

            // Reset button handling
            document.querySelector('button[type="reset"]').addEventListener('click', function() {
                // Clear all previews
                dropZonePreviews.innerHTML = '';

                // Delete all uploaded files from server
                uploadedFiles.forEach(filename => {
                    deleteUploadedFile(filename);
                });

                // Reset product type selection
                typeOptions.forEach(el => el.classList.remove('selected'));
                productTypeInput.value = '';

                uploadedFiles = [];
                uploadedImagesInput.value = JSON.stringify(uploadedFiles);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                dropZone.classList.add('drag-over');
            }

            function unhighlight() {
                dropZone.classList.remove('drag-over');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }

            function handleFiles(files) {
                // Limit to 3 files based on database schema
                if (uploadedFiles.length + files.length > 3) {
                    alert('Maximum 3 images allowed');
                    return;
                }

                // Process each file
                Array.from(files).forEach(file => {
                    if (!validateImage(file)) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const uniqueFileName = Date.now() + '_' + file.name.replace(/\s+/g, '_');

                        // Create preview
                        const previewDiv = document.createElement('div');
                        previewDiv.classList.add('drop-zone-preview');
                        previewDiv.dataset.filename = uniqueFileName;

                        const img = document.createElement('img');
                        img.src = e.target.result;

                        const removeBtn = document.createElement('div');
                        removeBtn.classList.add('drop-zone-preview-remove');
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        removeBtn.addEventListener('click', () => {
                            // Remove from previews
                            dropZonePreviews.removeChild(previewDiv);

                            // Remove from uploaded files
                            uploadedFiles = uploadedFiles.filter(f => f !== uniqueFileName);

                            // Update hidden input
                            uploadedImagesInput.value = JSON.stringify(uploadedFiles);

                            deleteUploadedFile(uniqueFileName);
                        });

                        previewDiv.appendChild(img);
                        previewDiv.appendChild(removeBtn);
                        dropZonePreviews.appendChild(previewDiv);

                        // Upload file via AJAX
                        uploadFile(file, uniqueFileName);
                    };
                    reader.readAsDataURL(file);
                });

                // Clear file input
                if (fileInput.value) fileInput.value = '';
            }

            function uploadFile(file, uniqueFileName) {
                const formData = new FormData();
                formData.append('image', file, uniqueFileName);

                fetch('upload_image.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            uploadedFiles.push(result.filename);
                            uploadedImagesInput.value = JSON.stringify(uploadedFiles);

                            // Add a nice animation to the preview
                            const preview = document.querySelector(`.drop-zone-preview[data-filename="${result.filename}"]`);
                            if (preview) {
                                preview.classList.add('animate-pulse');
                                setTimeout(() => {
                                    preview.classList.remove('animate-pulse');
                                }, 1000);
                            }
                        } else {
                            alert('Upload failed: ' + result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Upload failed. Please try again.');
                    });
            }

            function deleteUploadedFile(filename) {
                fetch('delete_image.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            filename: filename
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (!result.success) {
                            console.error('Error deleting file:', result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            // Validate form before submission
            document.getElementById('productForm').addEventListener('submit', function(e) {
                // Check product type
                if (!productTypeInput.value) {
                    e.preventDefault();
                    alert('Please select a product type (Men, Women, or Unisex)');
                    return false;
                }
                
                let totalStock = 0;
                const sizes = ['S', 'M', 'L', 'XL', 'XXL'];

                sizes.forEach(size => {
                    const qtyInput = document.getElementById('quantity_' + size);
                    if (qtyInput) {
                        totalStock += parseInt(qtyInput.value || 0);
                    }
                });

                if (totalStock <= 0) {
                    e.preventDefault();
                    alert('Please add stock for at least one size');
                    document.querySelector('.panel-section:nth-child(2)').scrollIntoView({
                        behavior: 'smooth'
                    });
                    return false;
                }

                if (uploadedFiles.length === 0) {
                    e.preventDefault();
                    alert('Please upload at least one product image');
                    document.querySelector('.panel-section:nth-child(3)').scrollIntoView({
                        behavior: 'smooth'
                    });
                    return false;
                }

                // Show loading state
                const submitBtn = document.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                submitBtn.disabled = true;

                return true;
            });

            // Cancel button event
            document.querySelector('a[href="product.php"]').addEventListener('click', function(e) {
                // Prevent default navigation
                e.preventDefault();

                // Delete all uploaded files from server
                if (uploadedFiles.length > 0) {
                    uploadedFiles.forEach(filename => {
                        deleteUploadedFile(filename);
                    });

                    // Clear uploaded files array
                    uploadedFiles = [];
                }

                // Navigate to product.php after cleanup
                window.location.href = "product.php";
            });

            function validateImage(file) {
                // Check file type
                if (!file.type.startsWith('image/')) {
                    alert('Only image files are allowed');
                    return false;
                }

                // Check file size (limit to 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size should be less than 5MB');
                    return false;
                }

                return true;
            }
        });
    </script>
</body>

</html>