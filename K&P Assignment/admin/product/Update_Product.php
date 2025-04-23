<?php
$_title = 'Update Product';
require '../../_base.php';
auth('admin', 'staff');
require 'header.php';

// Get product ID from URL parameter
$product_id = req('id');

// Redirect if no product ID provided
if (!$product_id) {
    temp('error', 'No product ID specified');
    redirect('product.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $_db->beginTransaction();

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

        $update_query = "UPDATE product SET 
                         product_name = ?,
                         category_id = ?, 
                         product_price = ?, 
                         product_description = ?, 
                         product_status = ? 
                         WHERE product_id = ?";

        $stmt = $_db->prepare($update_query);
        $stmt->execute([
            $product_name,
            $category_id,
            $product_price,
            $product_description,
            $product_status,
            $product_id
        ]);

        // Handle image uploads
        $image_fields = ['product_pic1', 'product_pic2', 'product_pic3', 'product_pic4', 'product_pic5', 'product_pic6'];

        foreach ($image_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
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

        // Update stock quantities
        $sizes = req('size', []);
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
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-hover: #4338CA;
            --secondary-color: #3B82F6;
            --accent-color: #F59E0B;
            --danger-color: #EF4444;
            --success-color: #10B981;
            --text-color: #1F2937;
            --light-gray: #F3F4F6;
            --white: #FFFFFF;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            background-color: #F9FAFB;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--white);
        }

        .btn-secondary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-accent {
            background-color: var(--accent-color);
            color: var(--white);
        }

        .btn-accent:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }

        .btn-danger:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-success:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text-color);
            border: 1px solid #D1D5DB;
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        .card {
            background-color: var(--white);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1.25rem 1.5rem;
            background-color: #F9FAFB;
            border-top: 1px solid #E5E7EB;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            transition: all 0.2s;
            font-size: 0.9375rem;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        label {
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 0.375rem;
            display: block;
            color: #4B5563;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .img-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 2px solid #E5E7EB;
            background-color: #F9FAFB;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.2s;
        }

        .img-preview:hover {
            border-color: var(--primary-color);
        }

        .img-preview img {
            max-width: 100%;
            max-height: 100%;
        }

        .drop-zone {
            border: 2px dashed #D1D5DB;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .drop-zone:hover {
            border-color: var(--primary-color);
            background-color: rgba(79, 70, 229, 0.05);
        }

        .drop-zone.drag-over {
            background-color: rgba(79, 70, 229, 0.1);
            border-color: var(--primary-color);
        }

        .required-field::after {
            content: '*';
            color: var(--danger-color);
            margin-left: 0.25rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #6B7280;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item:not(:last-child)::after {
            content: '/';
            margin: 0 0.5rem;
            color: #9CA3AF;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            transition: color 0.2s;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .breadcrumb-item:last-child {
            font-weight: 600;
        }

        .size-row {
            transition: all 0.2s;
            position: relative;
        }

        .size-row:hover {
            background-color: #F9FAFB;
        }

        .size-row .action-buttons {
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .size-row:hover .action-buttons {
            opacity: 1;
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: max-content;
            max-width: 250px;
            background-color: #374151;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Tab system for product sections */
        .tabs {
            display: flex;
            border-bottom: 1px solid #E5E7EB;
            margin-bottom: 1.5rem;
            overflow-x: auto;
            scrollbar-width: none;
            /* Firefox */
        }

        .tabs::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, Edge */
        }

        .tab {
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Custom switch toggle */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-color);
        }

        input:focus+.slider {
            box-shadow: 0 0 1px var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Loading spinner */
        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 3px solid var(--primary-color);
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Status badge */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }

        .status-available {
            background-color: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-outofstock {
            background-color: rgba(239, 68, 68, 0.1);
            color: #DC2626;
        }

        .status-discontinued {
            background-color: rgba(107, 114, 128, 0.1);
            color: #4B5563;
        }

        /* Image cropper modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .cropper-container {
            height: 400px;
            margin-bottom: 1rem;
        }

        /* Sticky form actions */
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-top: 1px solid #E5E7EB;
            padding: 1rem;
            margin: 0 -1.5rem -1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.05);
            z-index: 10;
        }

        /* Help sidebar */
        .help-sidebar {
            position: fixed;
            right: -350px;
            top: 0;
            width: 350px;
            height: 100vh;
            background-color: white;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 100;
            overflow-y: auto;
        }

        .help-sidebar.open {
            right: 0;
        }

        .help-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            z-index: 101;
        }

        .help-toggle:hover {
            background-color: var(--primary-hover);
        }

        .help-header {
            padding: 1rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .help-content {
            padding: 1rem;
        }

        .help-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .help-item:last-child {
            border-bottom: none;
        }

        .help-item h3 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* Mobile responsiveness improvements */
        @media (max-width: 640px) {
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }

            .card-header,
            .card-body,
            .card-footer {
                padding: 1rem;
            }

            .breadcrumb {
                padding: 0.5rem;
                font-size: 0.75rem;
                margin-bottom: 1rem;
                overflow-x: auto;
                white-space: nowrap;
            }

            h1 {
                font-size: 1.5rem !important;
            }

            h2 {
                font-size: 1.25rem !important;
            }

            .img-preview {
                width: 120px;
                height: 120px;
                margin: 0 auto;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .cropper-container {
                height: 300px;
            }

            .sticky-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .sticky-actions .btn {
                width: 100%;
            }

            .help-sidebar {
                width: 100%;
                right: -100%;
            }
        }

        .drop-zone.highlight {
            border-color: var(--primary-color);
            background-color: rgba(79, 70, 229, 0.05);
        }

        .drop-zone.error {
            border-color: var(--danger-color);
            background-color: rgba(239, 68, 68, 0.05);
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-6">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="home.php"><i class="fas fa-home mr-1"></i>Home</a>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                            <?php
                            $image_fields = ['product_pic1', 'product_pic2', 'product_pic3', 'product_pic4', 'product_pic5', 'product_pic6'];
                            $image_labels = ['Main Image', 'Image 2', 'Image 3', 'Image 4', 'Image 5', 'Image 6'];

                            foreach ($image_fields as $index => $field):
                                $image_url = !empty($product->$field) ? '../../img/' . $product->$field : '';
                            ?>
                                <div class="form-group">
                                    <label for="<?= $field ?>" class="<?= $index === 0 ? 'required-field' : '' ?>"><?= $image_labels[$index] ?></label>
                                    <div class="drop-zone" id="dropZone_<?= $field ?>"
                                        ondragover="handleDragOver(event, '<?= $field ?>')"
                                        ondragleave="handleDragLeave(event, '<?= $field ?>')"
                                        ondrop="handleDrop(event, '<?= $field ?>')">
                                        <div class="img-preview mb-3 mx-auto">
                                            <?php if (!empty($image_url)): ?>
                                                <img src="<?= $image_url ?>" alt="<?= $image_labels[$index] ?>" id="preview_<?= $field ?>">
                                            <?php else: ?>
                                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                                    <i class="fas fa-image text-4xl mb-2"></i>
                                                    <span class="text-sm">Drag and drop or click to upload</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="<?= $field ?>" id="<?= $field ?>" class="hidden" accept="image/jpeg,image/png,image/gif">
                                        <div class="text-center">
                                            <button type="button" class="btn btn-outline mt-2" onclick="document.getElementById('<?= $field ?>').click()">
                                                <i class="fas fa-upload mr-2"></i>Choose File
                                            </button>
                                            <?php if (!empty($image_url)): ?>
                                                <button type="button" class="btn btn-danger mt-2 ml-2" onclick="clearImage('<?= $field ?>')">
                                                    <i class="fas fa-trash-alt mr-2"></i>Remove
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <p>Max file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</p>
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
                    <p>Enter the product's name, category, price, and status. Make sure the product name is clear and descriptive.</p>
                </div>
                <div class="help-item">
                    <h3>Product Images</h3>
                    <p>Upload at least one main image. Additional images should show the product from different angles or highlight specific features.</p>
                    <ul class="list-disc ml-5 mt-2 text-sm">
                        <li>Image format: JPG, JPEG, PNG, or GIF</li>
                        <li>Maximum file size: 5MB</li>
                        <li>Recommended dimensions: 1000x1000 pixels</li>
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

    <!-- Image Cropper Modal -->
    <div id="cropperModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Crop Image</h2>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeCropperModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cropper-container">
                <img id="cropperImage" src="" alt="Image to crop">
            </div>
            <div class="flex justify-end gap-4 mt-4">
                <button type="button" class="btn btn-outline" onclick="closeCropperModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="cropImageBtn">
                    <i class="fas fa-crop-alt mr-2"></i>Crop & Save
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
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

        // Image preview functionality
        const imageInputs = ['product_pic1', 'product_pic2', 'product_pic3', 'product_pic4', 'product_pic5', 'product_pic6'];

        imageInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById('preview_' + inputId);

            input.addEventListener('change', function() {
                if (input.files && input.files[0]) {
                    if (!validateImage(input.files[0], inputId)) {
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();

                    reader.onload = function(e) {
                        if (preview) {
                            preview.src = e.target.result;
                        } else {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.id = 'preview_' + inputId;
                            const previewContainer = document.querySelector('#dropZone_' + inputId + ' .img-preview');
                            previewContainer.innerHTML = '';
                            previewContainer.appendChild(img);
                        }
                    }

                    reader.readAsDataURL(input.files[0]);
                }

                const input = document.getElementById(inputId);
                const dropZone = document.getElementById(`dropZone_${inputId}`);

                // Prevent default drag behaviors
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });

        // Clear image
        function clearImage(fieldId) {
            const input = document.getElementById(fieldId);
            const preview = document.getElementById('preview_' + fieldId);
            const previewContainer = document.querySelector('#dropZone_' + fieldId + ' .img-preview');

            input.value = '';
            if (preview) {
                previewContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <i class="fas fa-image text-4xl mb-2"></i>
                        <span class="text-sm">No image</span>
                    </div>
                `;
            }
        }

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Product ID copied to clipboard');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Form submission
        document.getElementById('updateProductForm').addEventListener('submit', function() {
            document.getElementById('submitSpinner').style.display = 'inline-block';
            document.getElementById('submitBtn').disabled = true;
        });

        // Image cropper functionality
        let cropper;
        let currentImageInput;

        function openCropperModal(input) {
            const modal = document.getElementById('cropperModal');
            const cropperImage = document.getElementById('cropperImage');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    cropperImage.src = e.target.result;
                    currentImageInput = input.id;

                    modal.style.display = 'block';

                    if (cropper) {
                        cropper.destroy();
                    }

                    cropper = new Cropper(cropperImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false
                    });
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        function closeCropperModal() {
            document.getElementById('cropperModal').style.display = 'none';
            if (cropper) {
                cropper.destroy();
            }
        }

        document.getElementById('cropImageBtn').addEventListener('click', function() {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({
                    width: 1000,
                    height: 1000
                });

                const preview = document.getElementById('preview_' + currentImageInput);
                const previewContainer = document.querySelector('#dropZone_' + currentImageInput + ' .img-preview');

                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);

                    if (preview) {
                        preview.src = url;
                    } else {
                        const img = document.createElement('img');
                        img.src = url;
                        img.id = 'preview_' + currentImageInput;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(img);
                    }

                    // Create a File object from the blob
                    const file = new File([blob], 'cropped.jpg', {
                        type: 'image/jpeg'
                    });

                    // Create a FileList-like object
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);

                    // Set the file to the input
                    document.getElementById(currentImageInput).files = dataTransfer.files;

                    closeCropperModal();
                });
            }
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
                    input.files = files;
                    updateImagePreview(file, fieldId);
                }
            }
        }

        function updateImagePreview(file, fieldId) {
            const reader = new FileReader();
            const preview = document.getElementById(`preview_${fieldId}`);
            const previewContainer = document.querySelector(`#dropZone_${fieldId} .img-preview`);

            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                } else {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.id = `preview_${fieldId}`;
                    previewContainer.innerHTML = '';
                    previewContainer.appendChild(img);
                }
            };

            reader.readAsDataURL(file);
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
    </script>
</body>

</html>