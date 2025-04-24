<?php
require_once '../../_base.php';
auth('admin', 'staff');

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

// Get all products for the dropdown
$stm = $_db->prepare("SELECT product_id, product_name, product_pic1, product_price FROM product ORDER BY product_name");
$stm->execute();
$products = $stm->fetchAll();

// Handle form submission
if (is_post()) {
    $_err = [];
    
    // Get form data
    $product_id = post('product_id');
    $discount_rate = post('discount_rate');
    $start_date = post('start_date');
    $end_date = post('end_date');
    
    // Validate product
    if (!$product_id) {
        $_err['product_id'] = 'Please select a product';
    }
    
    // Validate discount rate
    if (!is_numeric($discount_rate) || $discount_rate <= 0 || $discount_rate > 100) {
        $_err['discount_rate'] = 'Discount rate must be between 1 and 100';
    }
    
    // Validate dates
    if (!$start_date) {
        $_err['start_date'] = 'Start date is required';
    }
    
    if (!$end_date) {
        $_err['end_date'] = 'End date is required';
    } elseif ($start_date && $end_date && $end_date < $start_date) {
        $_err['end_date'] = 'End date must be after start date';
    }
    
    // Check for existing discount on this product
    $stm = $_db->prepare("SELECT COUNT(*) FROM discount WHERE product_id = ? AND 
        ((start_date BETWEEN ? AND ?) OR 
         (end_date BETWEEN ? AND ?) OR 
         (start_date <= ? AND end_date >= ?))");
    $stm->execute([$product_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    
    if ($stm->fetchColumn() > 0) {
        $_err['product_id'] = 'This product already has a discount for the selected date range';
    }
    
    // If no errors, create the discount
    if (empty($_err)) {
        // Determine status based on dates
        $today = date('Y-m-d');
        if ($today >= $start_date && $today <= $end_date) {
            $status = 'Active';
        } elseif ($today < $start_date) {
            $status = 'Upcoming';
        } else {
            $status = 'Expired';
        }
        
        // Generate discount ID (format: DISC_YYYYMMDD_randomstring)
        $discount_id = 'DISC_' . date('Ymd') . '_' . substr(md5(uniqid()), 0, 8);
        
        // Insert discount
        $stm = $_db->prepare("INSERT INTO discount (Discount_id, product_id, discount_rate, start_date, end_date, status) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stm->execute([$discount_id, $product_id, $discount_rate, $start_date, $end_date, $status]);
        
        $_SESSION['temp_success'] = 'Discount created successfully';
        redirect('index.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Discount - K&P Admin</title>
    <!-- Include head content directly instead of using include -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../user/style/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }
        
        .form-title {
            margin-bottom: 20px;
            color: #4a6fa5;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group select {
            height: 42px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .back-button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .submit-button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .back-button:hover {
            background-color: #5a6268;
        }
        
        .submit-button:hover {
            background-color: #218838;
        }
        
        .product-selection {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .product-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .product-card {
            width: 150px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .product-card.selected {
            border: 2px solid #4a6fa5;
            background-color: #e9f0f9;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            margin-bottom: 10px;
        }
        
        .product-info {
            text-align: center;
        }
        
        .product-name {
            font-size: 14px;
            margin-bottom: 5px;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            font-weight: bold;
            color: #dc3545;
        }
        
        .err {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        
        .product-search {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 100%;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .product-card {
                width: calc(50% - 10px);
            }
        }
    </style>
</head>
<body>
    <!-- Simple header instead of included one -->
    <header>
        <div class="logo">
            <h1>K&P Admin Panel</h1>
        </div>
        <nav>
            <ul>
                <li><a href="../index.php">Dashboard</a></li>
                <li><a href="../product/index.php">Products</a></li>
                <li><a href="../discount/index.php" class="active">Discounts</a></li>
                <li><a href="../payment/index.php">Orders</a></li>
                <li><a href="../../user/page/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="form-container">
        <a href="index.php" class="back-button">← Back to Discounts</a>
        
        <div class="form-card">
            <h2 class="form-title">Add New Discount</h2>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="product_search">Search Products:</label>
                    <input type="text" id="product_search" class="product-search" placeholder="Type to search products...">
                </div>
                
                <div class="form-group product-selection">
                    <label>Select Product:</label>
                    <input type="hidden" name="product_id" id="product_id" value="<?= post('product_id') ?>">
                    <?php err('product_id'); ?>
                    
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card <?= post('product_id') == $product->product_id ? 'selected' : '' ?>" 
                                 data-product-id="<?= $product->product_id ?>"
                                 data-product-name="<?= strtolower($product->product_name) ?>">
                                <img src="../../user/product_pic/<?= encode($product->product_pic1) ?>" class="product-image" alt="Product Image">
                                <div class="product-info">
                                    <div class="product-name"><?= encode($product->product_name) ?></div>
                                    <div class="product-price">RM <?= number_format($product->product_price, 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="discount_rate">Discount Rate (%):</label>
                    <input type="number" id="discount_rate" name="discount_rate" min="1" max="100" step="0.01" value="<?= post('discount_rate') ?>" required>
                    <?php err('discount_rate'); ?>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= post('start_date') ?>" required>
                    <?php err('start_date'); ?>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= post('end_date') ?>" required>
                    <?php err('end_date'); ?>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="back-button">Cancel</a>
                    <button type="submit" class="submit-button">Create Discount</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Simple footer -->
    <footer>
        <p>&copy; <?= date('Y') ?> K&P Fashion Admin Panel. All rights reserved.</p>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Product selection
            const productCards = document.querySelectorAll('.product-card');
            const productIdInput = document.getElementById('product_id');
            
            productCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    productCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Update hidden input value
                    productIdInput.value = this.getAttribute('data-product-id');
                });
            });
            
            // Product search functionality
            const searchInput = document.getElementById('product_search');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                productCards.forEach(card => {
                    const productName = card.getAttribute('data-product-name').toLowerCase();
                    const productId = card.getAttribute('data-product-id').toLowerCase();
                    
                    if (productName.includes(searchTerm) || productId.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Date validation
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            startDateInput.min = today;
            
            startDateInput.addEventListener('change', function() {
                // End date must be after start date
                endDateInput.min = this.value;
                
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
            });
        });
    </script>
</body>
</html>