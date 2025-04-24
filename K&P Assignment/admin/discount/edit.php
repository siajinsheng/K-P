<?php
require_once '../../_base.php';
auth('admin', 'staff');

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

// Get discount ID from URL
$discount_id = get('id');

if (!$discount_id) {
    $_SESSION['temp_error'] = 'Invalid discount ID';
    redirect('index.php');
}

// Get discount data
$stm = $_db->prepare("
    SELECT d.*, p.product_name, p.product_pic1, p.product_price 
    FROM discount d
    JOIN product p ON d.product_id = p.product_id
    WHERE d.Discount_id = ?
");
$stm->execute([$discount_id]);
$discount = $stm->fetch();

if (!$discount) {
    $_SESSION['temp_error'] = 'Discount not found';
    redirect('index.php');
}

// Handle form submission
if (is_post()) {
    $_err = [];
    
    // Get form data
    $discount_rate = post('discount_rate');
    $start_date = post('start_date');
    $end_date = post('end_date');
    
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
    
    // Check for conflicting discounts on this product
    $stm = $_db->prepare("SELECT COUNT(*) FROM discount 
                         WHERE product_id = ? 
                         AND Discount_id != ?
                         AND ((start_date BETWEEN ? AND ?) OR 
                              (end_date BETWEEN ? AND ?) OR 
                              (start_date <= ? AND end_date >= ?))");
    $stm->execute([
        $discount->product_id, 
        $discount_id, 
        $start_date, 
        $end_date, 
        $start_date, 
        $end_date, 
        $start_date, 
        $end_date
    ]);
    
    if ($stm->fetchColumn() > 0) {
        $_err['general'] = 'This product already has another discount for the selected date range';
    }
    
    // If no errors, update the discount
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
        
        // Update discount
        $stm = $_db->prepare("UPDATE discount 
                             SET discount_rate = ?, start_date = ?, end_date = ?, status = ? 
                             WHERE Discount_id = ?");
        $stm->execute([$discount_rate, $start_date, $end_date, $status, $discount_id]);
        
        $_SESSION['temp_success'] = 'Discount updated successfully';
        redirect('index.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Discount - K&P Admin</title>
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
        
        .product-display {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-id {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .product-name {
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .product-price {
            font-size: 16px;
            color: #dc3545;
            font-weight: bold;
        }
        
        .discount-preview {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .discount-preview-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: #4a6fa5;
        }
        
        .discount-preview-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .discount-preview-item {
            margin-bottom: 10px;
            flex: 0 0 48%;
        }
        
        .discount-preview-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .discount-preview-value {
            font-size: 16px;
        }
        
        .original-price {
            text-decoration: line-through;
            color: #6c757d;
        }
        
        .discounted-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .err {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        
        .general-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .discount-preview-item {
                flex: 0 0 100%;
            }
            
            .product-display {
                flex-direction: column;
                text-align: center;
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
            <h2 class="form-title">Edit Discount</h2>
            
            <?php if (isset($_err['general'])): ?>
                <div class="general-error"><?= $_err['general'] ?></div>
            <?php endif; ?>
            
            <div class="product-display">
                <img src="../../user/product_pic/<?= encode($discount->product_pic1) ?>" class="product-image" alt="Product Image">
                <div class="product-details">
                    <div class="product-id"><?= encode($discount->product_id) ?></div>
                    <div class="product-name"><?= encode($discount->product_name) ?></div>
                    <div class="product-price">RM <?= number_format($discount->product_price, 2) ?></div>
                </div>
            </div>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="discount_rate">Discount Rate (%):</label>
                    <input type="number" id="discount_rate" name="discount_rate" min="1" max="100" step="0.01" 
                           value="<?= post('discount_rate', $discount->discount_rate) ?>" required>
                    <?php err('discount_rate'); ?>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?= post('start_date', $discount->start_date) ?>" required>
                    <?php err('start_date'); ?>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?= post('end_date', $discount->end_date) ?>" required>
                    <?php err('end_date'); ?>
                </div>
                
                <div class="discount-preview">
                    <h3 class="discount-preview-title">Discount Preview</h3>
                    <div class="discount-preview-content">
                        <div class="discount-preview-item">
                            <div class="discount-preview-label">Original Price:</div>
                            <div class="discount-preview-value original-price">RM <span id="original-price"><?= number_format($discount->product_price, 2) ?></span></div>
                        </div>
                        
                        <div class="discount-preview-item">
                            <div class="discount-preview-label">Discounted Price:</div>
                            <div class="discount-preview-value discounted-price">RM <span id="discounted-price">0.00</span></div>
                        </div>
                        
                        <div class="discount-preview-item">
                            <div class="discount-preview-label">Savings:</div>
                            <div class="discount-preview-value">RM <span id="savings">0.00</span> (<span id="discount-rate-display">0</span>%)</div>
                        </div>
                        
                        <div class="discount-preview-item">
                            <div class="discount-preview-label">Status:</div>
                            <div class="discount-preview-value"><span id="discount-status">-</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="back-button">Cancel</a>
                    <button type="submit" class="submit-button">Update Discount</button>
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
            const discountRateInput = document.getElementById('discount_rate');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const originalPrice = parseFloat("<?= $discount->product_price ?>");
            
            // Function to update the discount preview
            function updateDiscountPreview() {
                const discountRate = parseFloat(discountRateInput.value) || 0;
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                const today = new Date();
                
                // Calculate discounted price and savings
                const discount = originalPrice * (discountRate / 100);
                const discountedPrice = originalPrice - discount;
                
                document.getElementById('discount-rate-display').textContent = discountRate.toFixed(2);
                document.getElementById('discounted-price').textContent = discountedPrice.toFixed(2);
                document.getElementById('savings').textContent = discount.toFixed(2);
                
                // Determine status
                let status = "-";
                
                if (isNaN(startDate) || isNaN(endDate)) {
                    status = "Invalid dates";
                } else if (today >= startDate && today <= endDate) {
                    status = "<span style='color: #28a745;'>Active</span>";
                } else if (today < startDate) {
                    status = "<span style='color: #007bff;'>Upcoming</span>";
                } else {
                    status = "<span style='color: #dc3545;'>Expired</span>";
                }
                
                document.getElementById('discount-status').innerHTML = status;
            }
            
            // Update preview when inputs change
            discountRateInput.addEventListener('input', updateDiscountPreview);
            startDateInput.addEventListener('change', function() {
                // End date must be after start date
                if (startDateInput.value) {
                    endDateInput.min = startDateInput.value;
                    
                    if (endDateInput.value && endDateInput.value < startDateInput.value) {
                        endDateInput.value = startDateInput.value;
                    }
                }
                
                updateDiscountPreview();
            });
            endDateInput.addEventListener('change', updateDiscountPreview);
            
            // Initialize preview on page load
            updateDiscountPreview();
        });
    </script>
</body>
</html>