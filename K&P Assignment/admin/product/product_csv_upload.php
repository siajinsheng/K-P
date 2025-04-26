<?php
// Start output buffering at the very beginning
ob_start();

$_title = 'Upload Products via CSV';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

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

// Success and error messages
$success = [];
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        if ($file_ext != 'csv') {
            $errors[] = "Only CSV files are allowed.";
        } else {
            // Read CSV file
            $handle = fopen($file_tmp, "r");
            if ($handle !== FALSE) {
                // Read header row
                $header = fgetcsv($handle);
                
                // Validate header structure
                $expected_columns = [
                    'product_id', 'category_id', 'product_name', 'product_description', 
                    'product_type', 'product_price', 'product_status', 
                    'quantity_S', 'quantity_M', 'quantity_L', 'quantity_XL', 'quantity_XXL'
                ];
                
                $missing_columns = array_diff($expected_columns, $header);
                if (!empty($missing_columns)) {
                    $errors[] = "Missing columns in CSV: " . implode(", ", $missing_columns);
                    fclose($handle);
                } else {
                    // Start database transaction
                    $_db->beginTransaction();
                    
                    // Get next available quantity_id
                    $next_quantity_id = getNextQuantityId($_db);
                    
                    // Process rows
                    $row_number = 1; // Header is row 0
                    $products_added = 0;
                    $products_skipped = 0;
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $row_number++;
                        
                        // Map CSV data to columns
                        $row_data = array_combine($header, $data);
                        
                        // Check for required fields
                        $required_fields = ['category_id', 'product_name', 'product_description', 'product_type', 'product_price'];
                        $missing_fields = [];
                        
                        foreach ($required_fields as $field) {
                            if (empty($row_data[$field])) {
                                $missing_fields[] = $field;
                            }
                        }
                        
                        if (!empty($missing_fields)) {
                            $errors[] = "Row {$row_number}: Missing required fields: " . implode(", ", $missing_fields);
                            $products_skipped++;
                            continue;
                        }
                        
                        // Validate data types
                        if (!is_numeric($row_data['product_price']) || $row_data['product_price'] <= 0) {
                            $errors[] = "Row {$row_number}: Invalid product price";
                            $products_skipped++;
                            continue;
                        }
                        
                        // Check if product type is valid
                        if (!in_array($row_data['product_type'], ['Unisex', 'Man', 'Women'])) {
                            $errors[] = "Row {$row_number}: Invalid product type. Must be Unisex, Man, or Women";
                            $products_skipped++;
                            continue;
                        }
                        
                        // Validate inventory quantities
                        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
                        $total_stock = 0;
                        $size_quantities = [];
                        
                        foreach ($sizes as $size) {
                            $qty_key = 'quantity_' . $size;
                            $quantity = isset($row_data[$qty_key]) ? (int)$row_data[$qty_key] : 0;
                            
                            if ($quantity < 0) {
                                $errors[] = "Row {$row_number}: Quantity for size {$size} cannot be negative";
                                continue 2; // Skip this row
                            }
                            
                            $size_quantities[$size] = $quantity;
                            $total_stock += $quantity;
                        }
                        
                        if ($total_stock <= 0) {
                            $errors[] = "Row {$row_number}: Total stock must be greater than zero";
                            $products_skipped++;
                            continue;
                        }
                        
                        // Check if product name already exists
                        if (isProductNameExists($_db, $row_data['product_name'])) {
                            $errors[] = "Row {$row_number}: Product name '{$row_data['product_name']}' already exists";
                            $products_skipped++;
                            continue;
                        }
                        
                        try {
                            // Generate product ID if not provided or auto-generate
                            $product_id = !empty($row_data['product_id']) ? $row_data['product_id'] : generateProductId($_db);
                            
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
                                ?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?
                            )");
                            
                            $product_status = !empty($row_data['product_status']) ? $row_data['product_status'] : 'Available';
                            
                            $stmt->execute([
                                $product_id,
                                $row_data['category_id'],
                                $row_data['product_name'],
                                $row_data['product_description'],
                                $row_data['product_price'],
                                $row_data['product_type'],
                                $product_status
                            ]);
                            
                            // Insert quantities for each size
                            foreach ($sizes as $size) {
                                $quantity = $size_quantities[$size];
                                if ($quantity > 0) {
                                    $quantity_stmt = $_db->prepare("INSERT INTO quantity (
                                        quantity_id, product_id, size, product_stock, product_sold
                                    ) VALUES (?, ?, ?, ?, ?)");
                                    
                                    $quantity_stmt->execute([
                                        $next_quantity_id++,
                                        $product_id,
                                        $size,
                                        $quantity,
                                        0 // Default sold value
                                    ]);
                                }
                            }
                            
                            $products_added++;
                            $success[] = "Row {$row_number}: Product '{$row_data['product_name']}' added successfully with ID {$product_id}";
                            
                        } catch (PDOException $e) {
                            $errors[] = "Row {$row_number}: Database error - " . $e->getMessage();
                            $products_skipped++;
                        }
                    }
                    
                    fclose($handle);
                    
                    // Commit or rollback based on results
                    if (!empty($errors) && count($errors) > count($success)) {
                        $_db->rollBack();
                        $errors[] = "Transaction rolled back due to errors.";
                    } else {
                        $_db->commit();
                        $success[] = "Transaction committed successfully. {$products_added} products added, {$products_skipped} products skipped.";
                    }
                }
            } else {
                $errors[] = "Could not open CSV file.";
            }
        }
    } else {
        $errors[] = "No file uploaded or upload error occurred.";
    }
}

// Get list of categories for reference
$categories = $_db->query('SELECT category_id, category_name FROM category')->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Products via CSV - K&P</title>
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
        
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-btn {
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f8fafc;
            cursor: pointer;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .file-upload-btn:hover {
            border-color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .file-name {
            margin-top: 1rem;
            background: #eef2ff;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #4f46e5;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: #475569;
        }
        
        tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .highlight {
            background-color: #eef2ff;
            border-left: 3px solid #4f46e5;
            padding-left: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .panel-section {
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            background: white;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <div class="card bg-white p-8 max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Upload Products via CSV</h1>
                <p class="text-gray-600">Bulk upload products with inventory quantities</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <ul class="list-disc pl-5">
                        <?php foreach ($success as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <p class="font-bold">Please fix the following errors:</p>
                    </div>
                    <ul class="list-disc pl-5">
                        <?php foreach ($errors as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="panel-section">
                <div class="section-title mb-4">
                    <i class="fas fa-file-csv text-blue-700"></i>
                    <h2 class="text-lg font-semibold text-blue-800">CSV Upload</h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="file-upload">
                        <input type="file" name="csv_file" class="file-upload-input" accept=".csv" id="csv_file">
                        <div class="file-upload-btn" id="file-upload-btn">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="text-lg font-medium">Upload CSV File</p>
                            <p class="text-sm text-gray-500 mt-1">Click or drag & drop your CSV file here</p>
                        </div>
                    </div>
                    
                    <div id="file-info" class="hidden">
                        <div class="file-name">
                            <i class="fas fa-file-csv"></i>
                            <span id="file-name-text"></span>
                        </div>
                    </div>
                    
                    <div class="flex justify-center mt-6 space-x-4">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-upload mr-2"></i> Upload Products
                        </button>
                        <a href="product.php" class="btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Products
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="panel-section mt-8">
                <div class="section-title mb-4">
                    <i class="fas fa-info-circle text-blue-700"></i>
                    <h2 class="text-lg font-semibold text-blue-800">CSV Format Instructions</h2>
                </div>
                
                <p class="text-sm text-gray-600 mb-4">Your CSV file should include the following columns:</p>
                
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Description</th>
                                <th>Required</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>product_id</code></td>
                                <td>Product ID (leave empty for auto-generation)</td>
                                <td>Optional</td>
                                <td>P100</td>
                            </tr>
                            <tr>
                                <td><code>category_id</code></td>
                                <td>Category ID</td>
                                <td>Required</td>
                                <td>CAT1001</td>
                            </tr>
                            <tr>
                                <td><code>product_name</code></td>
                                <td>Product name</td>
                                <td>Required</td>
                                <td>Example T-shirt</td>
                            </tr>
                            <tr>
                                <td><code>product_description</code></td>
                                <td>Product description</td>
                                <td>Required</td>
                                <td>This is an example product</td>
                            </tr>
                            <tr>
                                <td><code>product_type</code></td>
                                <td>Product type (Unisex, Man, or Women)</td>
                                <td>Required</td>
                                <td>Unisex</td>
                            </tr>
                            <tr>
                                <td><code>product_price</code></td>
                                <td>Product price</td>
                                <td>Required</td>
                                <td>49.90</td>
                            </tr>
                            <tr>
                                <td><code>product_status</code></td>
                                <td>Product status</td>
                                <td>Optional</td>
                                <td>Available</td>
                            </tr>
                            <tr class="highlight">
                                <td><code>quantity_S</code></td>
                                <td>Quantity for size S</td>
                                <td>Optional</td>
                                <td>10</td>
                            </tr>
                            <tr class="highlight">
                                <td><code>quantity_M</code></td>
                                <td>Quantity for size M</td>
                                <td>Optional</td>
                                <td>15</td>
                            </tr>
                            <tr class="highlight">
                                <td><code>quantity_L</code></td>
                                <td>Quantity for size L</td>
                                <td>Optional</td>
                                <td>15</td>
                            </tr>
                            <tr class="highlight">
                                <td><code>quantity_XL</code></td>
                                <td>Quantity for size XL</td>
                                <td>Optional</td>
                                <td>10</td>
                            </tr>
                            <tr class="highlight">
                                <td><code>quantity_XXL</code></td>
                                <td>Quantity for size XXL</td>
                                <td>Optional</td>
                                <td>5</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6">
                    <p class="text-sm text-gray-600 mb-2"><strong>Notes:</strong></p>
                    <ul class="list-disc pl-5 text-sm text-gray-600">
                        <li>The first row of your CSV must contain the column headers</li>
                        <li>At least one size must have a quantity greater than zero</li>
                        <li>Leave the product_id field blank to auto-generate IDs</li>
                        <li>Product images must be uploaded separately after importing products</li>
                    </ul>
                </div>
                
                <div class="mt-6">
                    <p class="text-sm text-gray-600 mb-2"><strong>Available Categories:</strong></p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                        <?php foreach ($categories as $category): ?>
                            <div class="bg-gray-100 rounded-lg p-2 text-xs">
                                <span class="font-medium"><?= htmlspecialchars($category['category_id']) ?>:</span> 
                                <?= htmlspecialchars($category['category_name']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="product_template.csv" download class="btn-primary inline-flex">
                        <i class="fas fa-download mr-2"></i> Download CSV Template
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('csv_file');
            const fileUploadBtn = document.getElementById('file-upload-btn');
            const fileInfo = document.getElementById('file-info');
            const fileNameText = document.getElementById('file-name-text');
            
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    const fileName = fileInput.files[0].name;
                    fileNameText.textContent = fileName;
                    fileInfo.classList.remove('hidden');
                    fileUploadBtn.style.borderColor = '#4f46e5';
                    fileUploadBtn.style.backgroundColor = 'rgba(79, 70, 229, 0.05)';
                } else {
                    fileInfo.classList.add('hidden');
                    fileUploadBtn.style.borderColor = '#cbd5e1';
                    fileUploadBtn.style.backgroundColor = '#f8fafc';
                }
            });
            
            fileUploadBtn.addEventListener('click', function() {
                fileInput.click();
            });
            
            // Handle drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUploadBtn.addEventListener(eventName, preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileUploadBtn.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileUploadBtn.addEventListener(eventName, unhighlight, false);
            });
            
            fileUploadBtn.addEventListener('drop', handleDrop, false);
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                fileUploadBtn.style.borderColor = '#4f46e5';
                fileUploadBtn.style.backgroundColor = 'rgba(79, 70, 229, 0.1)';
            }
            
            function unhighlight() {
                fileUploadBtn.style.borderColor = '#cbd5e1';
                fileUploadBtn.style.backgroundColor = '#f8fafc';
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                
                if (files.length > 0) {
                    const fileName = files[0].name;
                    fileNameText.textContent = fileName;
                    fileInfo.classList.remove('hidden');
                }
            }
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer at the end of the script
ob_end_flush();
?>