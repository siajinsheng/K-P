<?php
$_title = 'Insert Product';
require '../../_base.php';
auth(0,1);
require 'header.php';

// Function to generate a new product ID
function generateProductId($db) {
    $lastIdQuery = $db->query("SELECT product_id FROM product ORDER BY product_id DESC LIMIT 1");
    $lastId = $lastIdQuery->fetch(PDO::FETCH_COLUMN);

    if ($lastId) {
        $num = (int)substr($lastId, 2) + 1;
        return 'PD' . str_pad($num, 4, '0', STR_PAD_LEFT);
    } else {
        return 'PD0001';
    }
}

// Function to check if product name already exists
function isProductNameExists($db, $product_name) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM product WHERE product_name = ?");
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
    $product_stock = $_POST['product_stock'] ?? '';
    $category_id = $_POST['category_id'] ?? '';

    // Initialize error array
    $errors = [];

    // Validation
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

    if (!is_numeric($product_stock) || $product_stock < 0) {
        $errors['product_stock'] = 'Valid stock quantity is required';
    }

    // Image upload handling
    $image_filenames = [];
    $upload_dir = '../uploads/product_images/';
    
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

    // If no errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Start transaction
            $_db->beginTransaction();

            // Prepare image filename storage (use first 8 image slots)
            $image_slots = array_pad($image_filenames, 8, null);

            // Insert product
            $stmt = $_db->prepare("INSERT INTO product (
                product_id, 
                product_pic1, product_pic2, product_pic3, product_pic4, 
                product_pic5, product_pic6, product_pic7, product_pic8, 
                product_name, product_description, product_price, 
                product_status, product_stock, product_sell
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, 'Active', ?, 0
            )");

            $stmt->execute([
                $product_id,
                $image_slots[0], $image_slots[1], $image_slots[2], $image_slots[3],
                $image_slots[4], $image_slots[5], $image_slots[6], $image_slots[7],
                $product_name, $product_description, $product_price, 
                $product_stock
            ]);

            // Optionally insert category if needed
            if (!empty($category_id)) {
                $category_stmt = $_db->prepare("INSERT INTO category (category_id, product_id, category_name) VALUES (?, ?, ?)");
                $category_stmt->execute([
                    uniqid(), // Generate a unique category ID
                    $product_id, 
                    $category_id
                ]);
            }

            // Commit transaction
            $_db->commit();

            // Redirect with success message
            $_SESSION['message'] = 'Product added successfully';
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
            $errors['database'] = 'Failed to insert product. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Product</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        input,textarea,select {
            background-color:#f0f0f0;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 20px;
            width: 100%;
            font-family: sans-serif;
            padding: 20px;
            text-align: center;
            transition: background-color 0.3s ease;
        }
        .drop-zone.drag-over {
            background-color: #f0f0f0;
        }
        .drop-zone-previews {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        .drop-zone-preview {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .drop-zone-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .drop-zone-preview-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.7);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white shadow-md rounded-lg p-8 max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-center mb-8 text-gray-800">Insert New Product</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <?php foreach ($errors as $field => $message): ?>
                        <p><?= htmlspecialchars($field) ?>: <?= htmlspecialchars($message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="productForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="uploaded_images" id="uploadedImagesInput">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                        <input type="text" id="product_name" name="product_name" 
                               value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               maxlength="255" required 
                               placeholder="Enter unique product name (e.g., Wireless Noise-Cancelling Headphones)">
                    </div>

                    <div>
                        <label for="product_price" class="block text-sm font-medium text-gray-700">Price</label>
                        <input type="number" id="product_price" name="product_price" 
                               value="<?= htmlspecialchars($_POST['product_price'] ?? '') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               step="0.01" min="0" required 
                               placeholder="Enter product price (e.g., 99.99)">
                    </div>
                </div>

                <div>
                    <label for="product_description" class="block text-sm font-medium text-gray-700">Product Description</label>
                    <textarea id="product_description" name="product_description" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                              rows="4" required 
                              placeholder="Provide a detailed description of the product features, benefits, and specifications"><?= 
                                htmlspecialchars($_POST['product_description'] ?? '') 
                              ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="product_stock" class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                        <input type="number" id="product_stock" name="product_stock" 
                               value="<?= htmlspecialchars($_POST['product_stock'] ?? '') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               min="0" required 
                               placeholder="Enter available stock quantity (e.g., 50)">
                    </div>

                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Category</label>
                        <select id="category_id" name="category_id" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['category_name']) ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Images</label>
                    <div id="dropZone" class="drop-zone">
                        <p class="text-gray-500">Drag and drop up to max 8 images here or click to select</p>
                        <input type="file" id="fileInput" multiple accept="image/*" class="hidden" />
                    </div>
                    <div id="dropZonePreviews" class="drop-zone-previews"></div>
                </div>

                <div class="flex justify-center space-x-4">
                    <button type="submit" 
                            class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Insert Product
                    </button>
                    <button type="reset" 
                            class="bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Reset Form
                    </button>
                    <a href="product.php" 
                       class="bg-red-500 text-white px-6 py-2 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Cancel
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
        let uploadedFiles = [];

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
        fileInput.addEventListener('change', handleFiles, false);

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
            // Convert to array if not already
            files = files.target ? files.target.files : files;
            
            // Limit to 8 files
            if (uploadedFiles.length + files.length > 8) {
                alert('Maximum 8 images allowed');
                return;
            }

            // Process each file
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    const uniqueFileName = Date.now() + '_' + file.name;
                    
                    // Create preview
                    const previewDiv = document.createElement('div');
                    previewDiv.classList.add('drop-zone-preview');
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.classList.add('drop-zone-preview-remove');
                    removeBtn.innerHTML = '&times;';
                    removeBtn.addEventListener('click', () => {
                        // Remove from previews
                        dropZonePreviews.removeChild(previewDiv);
                        
                        // Remove from uploaded files
                        uploadedFiles = uploadedFiles.filter(f => f !== uniqueFileName);
                        
                        // Update hidden input
                        uploadedImagesInput.value = JSON.stringify(uploadedFiles);
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
            fileInput.value = '';
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
                } else {
                    alert('Upload failed: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Upload failed');
            });
        }
    });
    </script>
</body>
</html>