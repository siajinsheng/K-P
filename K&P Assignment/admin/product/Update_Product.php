<?php
$_title = 'Update Product';
require '../../_base.php';
auth('admin', 'staff');

// Get product ID from URL parameter
$product_id = req('id');

// Redirect if no product ID provided
if (!$product_id) {
    temp('error', 'No product ID specified');
    redirect('product.php');
}

// Process form submission BEFORE including any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $_db->beginTransaction();

        // Get sizes from POST data
        $sizes = req('size', []);

        if (!empty($sizes)) {
            $placeholders = rtrim(str_repeat('?,', count($sizes)), ',');
            $delete_query = "DELETE FROM quantity 
                            WHERE product_id = ? 
                            AND size NOT IN ($placeholders)";
            $delete_params = array_merge([$product_id], $sizes);
            $stmt = $_db->prepare($delete_query);
            $stmt->execute($delete_params);
        }

        // Update product basic info
        $product_name = req('product_name');
        $category_id = req('category_id');
        $product_price = req('product_price');
        $product_description = req('product_description');
        $product_status = req('product_status');
        $product_type = req('product_type');

        $update_query = "UPDATE product SET 
                         product_name = ?,
                         category_id = ?, 
                         product_price = ?, 
                         product_description = ?, 
                         product_status = ?,
                         product_type = ? 
                         WHERE product_id = ?";

        $stmt = $_db->prepare($update_query);
        $stmt->execute([
            $product_name,
            $category_id,
            $product_price,
            $product_description,
            $product_status,
            $product_type,
            $product_id
        ]);

        // Handle image uploads - limited to 3 images as per database schema
        $image_fields = ['product_pic1', 'product_pic2', 'product_pic3'];

        // First check if we're trying to remove product_pic1 without providing a replacement
        if (isset($_POST['remove_product_pic1']) && $_POST['remove_product_pic1'] === '1' && empty($_FILES['product_pic1']['name'])) {
            // Check if there's currently an image for product_pic1
            $check_pic1_query = "SELECT product_pic1 FROM product WHERE product_id = ? AND product_pic1 IS NOT NULL";
            $check_pic1_stmt = $_db->prepare($check_pic1_query);
            $check_pic1_stmt->execute([$product_id]);

            // If trying to remove the main image without a replacement, throw an exception
            if ($check_pic1_stmt->fetch()) {
                throw new Exception("Main product image (Image 1) cannot be removed without a replacement");
            }
        }

        foreach ($image_fields as $field) {
            // Check if the image should be removed
            if (isset($_POST['remove_' . $field]) && $_POST['remove_' . $field] === '1') {
                // Can't remove product_pic1 without a replacement - this is checked above
                if ($field === 'product_pic1' && empty($_FILES['product_pic1']['name'])) {
                    continue; // Skip this iteration
                }

                // Get the old filename before removing it
                $old_filename_query = "SELECT $field FROM product WHERE product_id = ?";
                $old_filename_stmt = $_db->prepare($old_filename_query);
                $old_filename_stmt->execute([$product_id]);
                $old_filename = $old_filename_stmt->fetchColumn();

                // Update the database to remove the image reference
                $update_image = "UPDATE product SET $field = NULL WHERE product_id = ?";
                $stmt = $_db->prepare($update_image);
                $stmt->execute([$product_id]);

                // Delete the old image file if it exists
                if (!empty($old_filename)) {
                    $old_file_path = '../../img/' . $old_filename;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            }
            // Check if a new image is being uploaded
            elseif (!empty($_FILES[$field]['name'])) {
                // Generate a unique filename
                $file_extension = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                $new_filename = $product_id . '_' . $field . '_' . time() . '.' . $file_extension;
                $upload_path = '../../img/' . $new_filename;

                // Check if file is an actual image
                $check = getimagesize($_FILES[$field]['tmp_name']);
                if (!$check) {
                    throw new Exception("File $field is not a valid image");
                }

                // Check file size (max 5MB)
                if ($_FILES[$field]['size'] > 5000000) {
                    throw new Exception("File $field is too large (max 5MB)");
                }

                // Allow only certain file formats
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed for $field");
                }

                // Get the old filename before uploading the new one
                $old_filename_query = "SELECT $field FROM product WHERE product_id = ?";
                $old_filename_stmt = $_db->prepare($old_filename_query);
                $old_filename_stmt->execute([$product_id]);
                $old_filename = $old_filename_stmt->fetchColumn();

                // Upload the file
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_path)) {
                    // Update the database with the new filename
                    $update_image = "UPDATE product SET $field = ? WHERE product_id = ?";
                    $stmt = $_db->prepare($update_image);
                    $stmt->execute([$new_filename, $product_id]);

                    // Delete the old image if it exists
                    if (!empty($old_filename)) {
                        $old_file_path = '../../img/' . $old_filename;
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                    }
                } else {
                    throw new Exception("Failed to upload image $field");
                }
            }
        }

        // One final check to make sure product_pic1 exists
        $check_main_image_query = "SELECT product_pic1 FROM product WHERE product_id = ?";
        $check_main_image_stmt = $_db->prepare($check_main_image_query);
        $check_main_image_stmt->execute([$product_id]);
        $main_image = $check_main_image_stmt->fetchColumn();

        if (empty($main_image)) {
            throw new Exception("Main product image (Image 1) is required");
        }

        // Update stock quantities
        $stocks = req('stock', []);

        if (!empty($sizes) && count($sizes) === count($stocks)) {
            foreach ($sizes as $index => $size) {
                $stock = intval($stocks[$index]);

                // Check if this size already exists for this product
                $check_query = "SELECT quantity_id FROM quantity WHERE product_id = ? AND size = ?";
                $check_stmt = $_db->prepare($check_query);
                $check_stmt->execute([$product_id, $size]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                    // Update existing size
                    $update_stock = "UPDATE quantity SET product_stock = ? WHERE quantity_id = ?";
                    $stmt = $_db->prepare($update_stock);
                    $stmt->execute([$stock, $existing->quantity_id]);
                } else {
                    // Insert new size
                    $insert_stock = "INSERT INTO quantity (product_id, size, product_stock, product_sold) VALUES (?, ?, ?, 0)";
                    $stmt = $_db->prepare($insert_stock);
                    $stmt->execute([$product_id, $size, $stock]);
                }
            }
        }

        // Commit the transaction
        $_db->commit();

        temp('success', 'Product updated successfully');
        redirect('Detail_Product.php?id=' . $product_id);
    } catch (Exception $e) {
        $_db->rollBack();
        // Delete any uploaded files if transaction failed
        foreach ($image_fields as $field) {
            if (!empty($new_filename) && file_exists($upload_path)) {
                unlink($upload_path);
            }
        }
        temp('error', 'Error updating product: ' . $e->getMessage());
        redirect('Update_Product.php?id=' . $product_id); // Redirect back to the form with error message
    }
}

try {
    // Fetch product details
    $query = "SELECT p.*, c.category_name 
              FROM product p
              LEFT JOIN category c ON p.category_id = c.category_id
              WHERE p.product_id = ?";
    $stmt = $_db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    // Check if product exists
    if (!$product) {
        temp('error', 'Product not found');
        redirect('product.php');
    }

    // Fetch stock information by size
    $stock_query = "SELECT * FROM quantity 
                   WHERE product_id = ? 
                   ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL')";
    $stock_stmt = $_db->prepare($stock_query);
    $stock_stmt->execute([$product_id]);
    $stock_info = $stock_stmt->fetchAll();

    // Fetch all categories
    $categories_query = "SELECT * FROM category ORDER BY category_name";
    $categories_stmt = $_db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    redirect('product.php');
}

// NOW it's safe to include the header and output HTML
require '../headFooter/header.php';

// Check for success message
$success_message = temp('success');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link href="Update_Product.css" rel="stylesheet">
    <script src="Update_Product.js"></script>
</head>

<body>
    <div class="container mx-auto px-4 py-6">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="../home/home.php"><i class="fas fa-home mr-1"></i>Home</a>
            </div>
            <div class="breadcrumb-item">
                <a href="product.php">Products</a>
            </div>
            <div class="breadcrumb-item">
                <a href="Detail_Product.php?id=<?= $product_id ?>"><?= htmlspecialchars($product->product_name) ?></a>
            </div>
            <div class="breadcrumb-item">
                <span>Update</span>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success_message): ?>
            <div class="alert alert-success mb-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong><?= $success_message ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="alert alert-error mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <strong><?= $error_message ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Header with Actions -->
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($product->product_name) ?></h1>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '', $product->product_status)) ?>">
                        <i class="fas fa-circle mr-1 text-xs"></i> <?= $product->product_status ?>
                    </span>
                    <span>|</span>
                    <span><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($product->category_name) ?></span>
                    <span>|</span>
                    <span><i class="fas fa-key mr-1"></i> <?= htmlspecialchars($product->product_id) ?></span>
                </div>
            </div>
            <a href="Detail_Product.php?id=<?= $product_id ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left mr-2"></i>Back to Details
            </a>
        </div>

        <!-- Update Form -->
        <form method="POST" enctype="multipart/form-data" class="mb-8" id="updateProductForm">
            <div class="card">
                <!-- Tabs navigation -->
                <div class="tabs">
                    <div class="tab active" data-tab="basicInfo">
                        <i class="fas fa-info-circle mr-2"></i>Basic Info
                    </div>
                    <div class="tab" data-tab="images">
                        <i class="fas fa-images mr-2"></i>Images
                    </div>
                    <div class="tab" data-tab="inventory">
                        <i class="fas fa-box mr-2"></i>Inventory
                    </div>
                </div>

                <!-- Tab content -->
                <div class="card-body">
                    <!-- Basic Information Tab -->
                    <div class="tab-content active" id="basicInfo">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="product_id" class="text-sm">Product ID</label>
                                <div class="flex">
                                    <input type="text" id="product_id" name="product_id" value="<?= htmlspecialchars($product->product_id) ?>" class="bg-gray-100" readonly>
                                    <button type="button" class="ml-2 px-2 py-1 bg-gray-200 rounded-md tooltip" onclick="copyToClipboard('<?= htmlspecialchars($product->product_id) ?>')">
                                        <i class="fas fa-copy"></i>
                                        <span class="tooltip-text">Copy to clipboard</span>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="product_name" class="required-field">Product Name</label>
                                <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($product->product_name) ?>" required autofocus>
                            </div>

                            <div class="form-group">
                                <label for="category_id" class="required-field">Category</label>
                                <select id="category_id" name="category_id" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category->category_id ?>" <?= $category->category_id === $product->category_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category->category_name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="product_type" class="required-field">Product Type</label>
                                <select id="product_type" name="product_type" required>
                                    <option value="Unisex" <?= $product->product_type === 'Unisex' ? 'selected' : '' ?>>Unisex</option>
                                    <option value="Man" <?= $product->product_type === 'Man' ? 'selected' : '' ?>>Man</option>
                                    <option value="Women" <?= $product->product_type === 'Women' ? 'selected' : '' ?>>Women</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="product_price" class="required-field">Price (RM)</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">RM</span>
                                    </div>
                                    <input type="number" id="product_price" name="product_price" value="<?= htmlspecialchars($product->product_price) ?>" min="0" step="0.01" class="pl-12" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="product_status" class="required-field">Status</label>
                                <div class="flex gap-4">
                                    <div class="flex items-center">
                                        <input type="radio" id="status_available" name="product_status" value="Available" <?= $product->product_status === 'Available' ? 'checked' : '' ?> class="mr-2">
                                        <label for="status_available">Available</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="status_outofstock" name="product_status" value="Out of Stock" <?= $product->product_status === 'Out of Stock' ? 'checked' : '' ?> class="mr-2">
                                        <label for="status_outofstock">Out of Stock</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="status_discontinued" name="product_status" value="Discontinued" <?= $product->product_status === 'Discontinued' ? 'checked' : '' ?> class="mr-2">
                                        <label for="status_discontinued">Discontinued</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group md:col-span-2">
                                <label for="product_description">Product Description</label>
                                <textarea id="product_description" name="product_description" rows="5"><?= htmlspecialchars($product->product_description) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Images Tab -->
                    <div class="tab-content" id="images">
                        <h2 class="text-xl font-semibold mb-4">Product Images</h2>

                        <!-- Image Processing Tips Box -->
                        <div class="bg-blue-50 p-3 rounded-lg mb-6 border border-blue-200">
                            <h3 class="text-sm font-medium text-blue-800 flex items-center mb-2">
                                <i class="fas fa-lightbulb text-blue-600 mr-2"></i>
                                Image Editing Features
                            </h3>
                            <p class="text-xs text-blue-700 mb-2">
                                Hover over any product image to reveal editing tools. You can rotate and flip images to get the perfect product shot.
                            </p>
                            <div class="flex items-center gap-4 text-xs text-blue-700">
                                <span><i class="fas fa-undo"></i> Rotate Left</span>
                                <span><i class="fas fa-redo"></i> Rotate Right</span>
                                <span><i class="fas fa-arrows-alt-h"></i> Flip Horizontally</span>
                                <span><i class="fas fa-arrows-alt-v"></i> Flip Vertically</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                            <?php
                            $image_fields = ['product_pic1', 'product_pic2', 'product_pic3'];
                            $image_labels = ['Main Image (Required)', 'Image 2', 'Image 3'];

                            foreach ($image_fields as $index => $field):
                                $image_url = !empty($product->$field) ? '../../img/' . $product->$field : '';
                                $is_required = $field === 'product_pic1';
                            ?>
                                <div class="form-group">
                                    <label for="<?= $field ?>" class="<?= $is_required ? 'required-field' : '' ?>"><?= $image_labels[$index] ?></label>
                                    <div class="drop-zone" id="dropZone_<?= $field ?>"
                                        ondragover="handleDragOver(event, '<?= $field ?>')"
                                        ondragleave="handleDragLeave(event, '<?= $field ?>')"
                                        ondrop="handleDrop(event, '<?= $field ?>')">
                                        <div class="img-preview mb-3 mx-auto">
                                            <?php if (!empty($image_url)): ?>
                                                <img src="<?= $image_url ?>" alt="<?= $image_labels[$index] ?>" id="preview_<?= $field ?>">

                                                <!-- Image toolbar -->
                                                <div class="img-toolbar">
                                                    <div class="img-tool" title="Rotate Left" onclick="processImage('<?= basename($image_url) ?>', 'rotate_left', '<?= $field ?>', this)">
                                                        <i class="fas fa-undo"></i>
                                                    </div>
                                                    <div class="img-tool" title="Rotate Right" onclick="processImage('<?= basename($image_url) ?>', 'rotate_right', '<?= $field ?>', this)">
                                                        <i class="fas fa-redo"></i>
                                                    </div>
                                                    <div class="img-tool" title="Flip Horizontal" onclick="processImage('<?= basename($image_url) ?>', 'flip_horizontal', '<?= $field ?>', this)">
                                                        <i class="fas fa-arrows-alt-h"></i>
                                                    </div>
                                                    <div class="img-tool" title="Flip Vertical" onclick="processImage('<?= basename($image_url) ?>', 'flip_vertical', '<?= $field ?>', this)">
                                                        <i class="fas fa-arrows-alt-v"></i>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex flex-col items-center justify-center h-full text-gray-400" id="placeholder_<?= $field ?>">
                                                    <i class="fas fa-image text-4xl mb-2"></i>
                                                    <span class="text-sm"><?= $is_required ? 'Main image required' : 'Drag and drop or click to upload' ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="<?= $field ?>" id="<?= $field ?>" class="hidden" accept="image/jpeg,image/png,image/gif">
                                        <!-- Hidden field to track if image should be removed -->
                                        <input type="hidden" name="remove_<?= $field ?>" id="remove_<?= $field ?>" value="0">
                                        <div class="text-center">
                                            <button type="button" class="btn btn-outline mt-2" onclick="document.getElementById('<?= $field ?>').click()">
                                                <i class="fas fa-upload mr-2"></i>Choose File
                                            </button>
                                            <?php if (!empty($image_url)): ?>
                                                <button type="button" class="btn btn-danger mt-2 ml-2 <?= $is_required ? 'main-img-remove-btn' : '' ?>" id="removeBtn_<?= $field ?>"
                                                    <?= $is_required ? 'data-field="' . $field . '"' : '' ?>
                                                    onclick="removeImage('<?= $field ?>')">
                                                    <i class="fas fa-trash-alt mr-2"></i>Remove
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-danger mt-2 ml-2 hidden <?= $is_required ? 'main-img-remove-btn' : '' ?>" id="removeBtn_<?= $field ?>"
                                                    <?= $is_required ? 'data-field="' . $field . '"' : '' ?>
                                                    onclick="removeImage('<?= $field ?>')">
                                                    <i class="fas fa-trash-alt mr-2"></i>Remove
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <p>Max file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</p>
                                            <?php if ($is_required): ?>
                                                <p class="text-red-500 font-semibold mt-1">Main image is required</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Inventory Tab -->
                    <div class="tab-content" id="inventory">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">Sizes & Stock</h2>
                            <button type="button" id="addSizeBtn" class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>Add Size
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="py-2 px-4 border-b text-left">Size</th>
                                        <th class="py-2 px-4 border-b text-left">Stock</th>
                                        <th class="py-2 px-4 border-b text-left">Sold</th>
                                        <th class="py-2 px-4 border-b text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="sizeTableBody">
                                    <?php if (empty($stock_info)): ?>
                                        <tr class="size-row">
                                            <td class="py-2 px-4 border-b">
                                                <select name="size[]" class="w-full">
                                                    <option value="S">S</option>
                                                    <option value="M">M</option>
                                                    <option value="L">L</option>
                                                    <option value="XL">XL</option>
                                                    <option value="XXL">XXL</option>
                                                </select>
                                            </td>
                                            <td class="py-2 px-4 border-b">
                                                <input type="number" name="stock[]" min="0" class="w-full" value="0">
                                            </td>
                                            <td class="py-2 px-4 border-b text-gray-500">0</td>
                                            <td class="py-2 px-4 border-b">
                                                <div class="action-buttons">
                                                    <button type="button" class="text-red-500 hover:text-red-700" onclick="removeSize(this)">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($stock_info as $stock): ?>
                                            <tr class="size-row">
                                                <td class="py-2 px-4 border-b">
                                                    <select name="size[]" class="w-full">
                                                        <option value="S" <?= $stock->size === 'S' ? 'selected' : '' ?>>S</option>
                                                        <option value="M" <?= $stock->size === 'M' ? 'selected' : '' ?>>M</option>
                                                        <option value="L" <?= $stock->size === 'L' ? 'selected' : '' ?>>L</option>
                                                        <option value="XL" <?= $stock->size === 'XL' ? 'selected' : '' ?>>XL</option>
                                                        <option value="XXL" <?= $stock->size === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <input type="number" name="stock[]" min="0" class="w-full" value="<?= $stock->product_stock ?>">
                                                </td>
                                                <td class="py-2 px-4 border-b text-gray-500"><?= $stock->product_sold ?></td>
                                                <td class="py-2 px-4 border-b">
                                                    <div class="action-buttons">
                                                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removeSize(this)">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sticky Form Actions -->
                    <div class="sticky-actions">
                        <button type="button" class="btn btn-outline" onclick="location.href='Detail_Product.php?id=<?= $product_id ?>'">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save mr-2"></i>Save Changes
                            <span class="spinner ml-2" id="submitSpinner"></span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Help Sidebar -->
        <div class="help-sidebar" id="helpSidebar">
            <div class="help-header">
                <h3 class="font-bold text-lg">Help & Guidelines</h3>
                <button type="button" onclick="toggleHelpSidebar()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="help-content">
                <div class="help-item">
                    <h3>Basic Information</h3>
                    <p>Enter the product's name, category, type, price, and status. Make sure the product name is clear and descriptive.</p>
                </div>
                <div class="help-item">
                    <h3>Product Images</h3>
                    <p>Upload at least one main image. Additional images should show the product from different angles or highlight specific features.</p>
                    <ul class="list-disc ml-5 mt-2 text-sm">
                        <li>Image format: JPG, JPEG, PNG, or GIF</li>
                        <li>Maximum file size: 5MB</li>
                        <li>Recommended dimensions: 1000x1000 pixels</li>
                        <li><strong>NEW:</strong> Use the image editing tools to rotate and flip images</li>
                    </ul>
                </div>
                <div class="help-item">
                    <h3>Image Editing Tools</h3>
                    <p>Hover over any uploaded image to see the editing toolbar:</p>
                    <ul class="list-disc ml-5 mt-2 text-sm">
                        <li><i class="fas fa-undo"></i> <strong>Rotate Left:</strong> Rotates the image 90° counterclockwise</li>
                        <li><i class="fas fa-redo"></i> <strong>Rotate Right:</strong> Rotates the image 90° clockwise</li>
                        <li><i class="fas fa-arrows-alt-h"></i> <strong>Flip Horizontally:</strong> Mirrors the image left to right</li>
                        <li><i class="fas fa-arrows-alt-v"></i> <strong>Flip Vertically:</strong> Mirrors the image top to bottom</li>
                    </ul>
                </div>
                <div class="help-item">
                    <h3>Inventory Management</h3>
                    <p>Add all available sizes and corresponding stock quantities. You can add multiple sizes by clicking the "Add Size" button.</p>
                </div>
                <div class="help-item">
                    <h3>Tips for Better Product Pages</h3>
                    <ul class="list-disc ml-5 text-sm">
                        <li>Write detailed product descriptions</li>
                        <li>Include high-quality images from multiple angles</li>
                        <li>Keep inventory levels updated</li>
                        <li>Use consistent sizing across similar products</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="help-toggle" onclick="toggleHelpSidebar()">
            <i class="fas fa-question"></i>
        </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 1s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.style.display = 'none', 1000);
            }, 5000);
        }

        // Auto-hide error message after 8 seconds
        const errorAlert = document.querySelector('.alert-error');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.transition = 'opacity 1s';
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.style.display = 'none', 1000);
            }, 8000);
        }

        // Tabs functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(this.getAttribute('data-tab')).classList.add('active');
            });
        });

        // Help sidebar toggle
        function toggleHelpSidebar() {
            document.getElementById('helpSidebar').classList.toggle('open');
        }

        // Add Size Row
        document.getElementById('addSizeBtn').addEventListener('click', function() {
            const tableBody = document.getElementById('sizeTableBody');
            const newRow = document.createElement('tr');
            newRow.className = 'size-row';

            newRow.innerHTML = `
                <td class="py-2 px-4 border-b">
                    <select name="size[]" class="w-full">
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="XXL">XXL</option>
                    </select>
                </td>
                <td class="py-2 px-4 border-b">
                    <input type="number" name="stock[]" min="0" class="w-full" value="0">
                </td>
                <td class="py-2 px-4 border-b text-gray-500">0</td>
                <td class="py-2 px-4 border-b">
                    <div class="action-buttons">
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removeSize(this)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            `;

            tableBody.appendChild(newRow);
        });

        // Remove Size Row
        function removeSize(button) {
            if (document.querySelectorAll('.size-row').length > 1) {
                button.closest('tr').remove();
            } else {
                alert('At least one size is required.');
            }
        }

        // NEW: Process image function for rotate/flip operations
        function processImage(filename, operation, fieldId, buttonElement) {
            // Show loading state
            buttonElement.classList.add('loading');

            // Prepare request data
            const requestData = {
                filename: filename,
                operation: operation
            };

            // Send request to process image
            fetch('process_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(result => {
                    // Remove loading state
                    buttonElement.classList.remove('loading');

                    if (result.success) {
                        // Update image with new version (add timestamp to prevent caching)
                        const imgElement = document.getElementById(`preview_${fieldId}`);
                        imgElement.src = `../../img/${result.updated_url}`;

                        // Add a temporary highlight effect
                        const preview = buttonElement.closest('.img-preview');
                        preview.style.boxShadow = '0 0 0 3px rgba(79, 70, 229, 0.6)';
                        setTimeout(() => {
                            preview.style.boxShadow = '';
                        }, 1000);
                    } else {
                        console.error('Error processing image:', result.message);
                        alert('Error processing image: ' + result.message);
                    }
                })
                .catch(error => {
                    // Remove loading state
                    buttonElement.classList.remove('loading');
                    console.error('Error:', error);
                    alert('An error occurred while processing the image');
                });
        }

        // Image preview functionality
        const imageInputs = ['product_pic1', 'product_pic2', 'product_pic3'];

        imageInputs.forEach(inputId => {
            const input = document.getElementById(inputId);

            input.addEventListener('change', function() {
                if (input.files && input.files[0]) {
                    if (!validateImage(input.files[0], inputId)) {
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    const previewContainer = document.querySelector(`#dropZone_${inputId} .img-preview`);
                    const removeField = document.getElementById(`remove_${inputId}`);
                    const removeBtn = document.getElementById(`removeBtn_${inputId}`);

                    reader.onload = function(e) {
                        // Clear any previous image or placeholder
                        previewContainer.innerHTML = '';

                        // Create and append the new image
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.id = `preview_${inputId}`;

                        // Create image toolbar for the new image
                        const toolbar = document.createElement('div');
                        toolbar.className = 'img-toolbar';
                        toolbar.innerHTML = `
                            <div class="img-tool" title="Rotate Left" onclick="processImage('${input.files[0].name}', 'rotate_left', '${inputId}', this)">
                                <i class="fas fa-undo"></i>
                            </div>
                            <div class="img-tool" title="Rotate Right" onclick="processImage('${input.files[0].name}', 'rotate_right', '${inputId}', this)">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="img-tool" title="Flip Horizontal" onclick="processImage('${input.files[0].name}', 'flip_horizontal', '${inputId}', this)">
                                <i class="fas fa-arrows-alt-h"></i>
                            </div>
                            <div class="img-tool" title="Flip Vertical" onclick="processImage('${input.files[0].name}', 'flip_vertical', '${inputId}', this)">
                                <i class="fas fa-arrows-alt-v"></i>
                            </div>
                        `;

                        previewContainer.appendChild(img);
                        previewContainer.appendChild(toolbar);

                        // Reset the remove flag and show remove button
                        removeField.value = "0";
                        removeBtn.classList.remove('hidden');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            });
        });

        // Remove image (sets hidden flag and clears preview)
        function removeImage(fieldId) {
            // Get references to elements
            const input = document.getElementById(fieldId);
            const previewContainer = document.querySelector(`#dropZone_${fieldId} .img-preview`);
            const removeField = document.getElementById(`remove_${fieldId}`);
            const removeBtn = document.getElementById(`removeBtn_${fieldId}`);

            // Special handling for the main image (product_pic1)
            if (fieldId === 'product_pic1') {
                // Check if there is a new image selected to replace it
                if (!input.files || !input.files[0]) {
                    alert('Main product image (Image 1) cannot be removed without a replacement. Please select a new image first.');
                    return; // Don't proceed with removal
                }
            }

            // Clear file input value
            input.value = '';

            // Set the remove flag to 1 (true) to tell the backend to remove the image
            removeField.value = "1";

            // Clear the preview and add the placeholder
            const isMainImage = fieldId === 'product_pic1';
            previewContainer.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-gray-400" id="placeholder_${fieldId}">
                    <i class="fas fa-image text-4xl mb-2"></i>
                    <span class="text-sm">${isMainImage ? 'Main image required' : 'Drag and drop or click to upload'}</span>
                </div>
            `;

            // Hide the remove button since there's no longer an image to remove
            removeBtn.classList.add('hidden');
        }

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Product ID copied to clipboard');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Form submission - validate main image presence
        document.getElementById('updateProductForm').addEventListener('submit', function(event) {
            const mainImageField = document.getElementById('product_pic1');
            const mainImageRemove = document.getElementById('remove_product_pic1');

            // Check if main image would be removed without replacement
            if (mainImageRemove.value === '1' && (!mainImageField.files || !mainImageField.files[0])) {
                event.preventDefault();
                alert('Main product image (Image 1) is required. Please upload an image before saving.');

                // Switch to the Images tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.querySelector('[data-tab="images"]').classList.add('active');
                document.getElementById('images').classList.add('active');

                return false;
            }

            document.getElementById('submitSpinner').style.display = 'inline-block';
            document.getElementById('submitBtn').disabled = true;
        });

        // Drag and drop handlers
        function handleDragOver(e, fieldId) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById(`dropZone_${fieldId}`).classList.add('highlight');
        }

        function handleDragLeave(e, fieldId) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById(`dropZone_${fieldId}`).classList.remove('highlight');
        }

        function handleDrop(e, fieldId) {
            e.preventDefault();
            e.stopPropagation();
            const dropZone = document.getElementById(`dropZone_${fieldId}`);
            dropZone.classList.remove('highlight');

            const dt = e.dataTransfer;
            const files = dt.files;
            const input = document.getElementById(fieldId);

            if (files.length > 0) {
                const file = files[0];
                if (validateImage(file, fieldId)) {
                    // Create a FileList-like object
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);

                    // Set the file input's files property
                    input.files = dataTransfer.files;

                    // Trigger the change event manually
                    const event = new Event('change', {
                        bubbles: true
                    });
                    input.dispatchEvent(event);
                }
            }
        }

        function validateImage(file, fieldId) {
            const dropZone = document.getElementById(`dropZone_${fieldId}`);

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                dropZone.classList.add('error');
                setTimeout(() => dropZone.classList.remove('error'), 2000);
                alert('Only JPG, PNG, and GIF files are allowed!');
                return false;
            }

            // Check file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                dropZone.classList.add('error');
                setTimeout(() => dropZone.classList.remove('error'), 2000);
                alert('File size exceeds 5MB limit!');
                return false;
            }

            return true;
        }

        // Special handling for main image remove buttons
        document.querySelectorAll('.main-img-remove-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const fieldId = this.getAttribute('data-field');
                const input = document.getElementById(fieldId);

                // If it's the main image and no replacement is selected, show warning
                if (fieldId === 'product_pic1' && (!input.files || !input.files[0])) {
                    e.preventDefault();
                    alert('Main product image cannot be removed without a replacement image. Please select a new image first.');
                    return false;
                }
            });
        });

        // Display current date and time in user's timezone
        function getCurrentDateTime() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };
            return now.toLocaleString('en-US', options);
        }

        // Show last updated info when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const currentDateTime = getCurrentDateTime();
            const userInfo = document.createElement('div');
            userInfo.className = 'text-xs text-gray-500 text-center mt-4';
            userInfo.innerHTML = `Last updated: ${currentDateTime}`;
            document.querySelector('.sticky-actions').before(userInfo);
        });
    </script>
</body>

</html>