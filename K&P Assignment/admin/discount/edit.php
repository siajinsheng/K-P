<?php
$_title = 'Edit Discount';
require_once '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

// Get discount ID from URL
$discount_id = get('id');

if (!$discount_id) {
    temp('error', 'Invalid discount ID');
    redirect('discount.php');
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
    temp('error', 'Discount not found');
    redirect('discount.php');
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
    } elseif ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
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
        
        temp('success', 'Discount updated successfully');
        redirect('discount.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="edit.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800"><?= $_title ?></h1>
            <a href="discount.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Discounts
            </a>
        </div>
        
        <?php if (!empty($_err['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-bold">Error</p>
                        <p><?= $_err['general'] ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Product Info Card -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-700">Product Information</h2>
                </div>
                
                <div class="p-6">
                    <div class="mb-6 flex justify-center">
                        <img src="../../img/<?= encode($discount->product_pic1) ?>" alt="<?= encode($discount->product_name) ?>" 
                             class="product-image w-full rounded-lg shadow-sm">
                    </div>
                    
                    <h3 class="text-lg font-bold text-gray-800 mb-2"><?= encode($discount->product_name) ?></h3>
                    <div class="text-sm text-gray-500 mb-4"><?= encode($discount->product_id) ?></div>
                    
                    <div class="bg-gray-50 rounded-md p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-500">Regular Price:</span>
                            <span class="font-bold text-gray-800">RM <?= number_format($discount->product_price, 2) ?></span>
                        </div>
                        
                        <div class="text-sm text-gray-500 mt-4">Discount ID:</div>
                        <div class="font-mono text-sm bg-gray-100 p-2 rounded border border-gray-200 mt-1 break-all">
                            <?= encode($discount->Discount_id) ?>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <div class="text-sm font-medium text-gray-700 mb-2">Discount Timeline</div>
                        <div class="timeline">
                            <?php
                            $today = date('Y-m-d');
                            $creationDate = (new DateTime($discount->start_date))->modify('-1 day')->format('Y-m-d');
                            $timelineClass = '';
                            
                            if ($discount->status === 'Active') {
                                $timelineClass = 'active';
                            } elseif ($discount->status === 'Expired') {
                                $timelineClass = 'expired';
                            }
                            ?>
                            
                            <div class="timeline-item">
                                <div class="text-sm font-medium">Created</div>
                                <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($creationDate)) ?></div>
                            </div>
                            
                            <div class="timeline-item <?= $today >= $discount->start_date ? $timelineClass : '' ?>">
                                <div class="text-sm font-medium">Starts</div>
                                <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($discount->start_date)) ?></div>
                            </div>
                            
                            <div class="timeline-item <?= $today > $discount->end_date ? 'expired' : '' ?>">
                                <div class="text-sm font-medium">Ends</div>
                                <div class="text-xs text-gray-500"><?= date('d M Y', strtotime($discount->end_date)) ?></div>
                            </div>
                            
                            <div class="flex items-center mt-4">
                                <span class="text-sm mr-2">Current Status:</span>
                                <?php
                                $statusClass = '';
                                if ($discount->status === 'Active') {
                                    $statusClass = 'active';
                                } elseif ($discount->status === 'Upcoming') {
                                    $statusClass = 'upcoming';
                                } elseif ($discount->status === 'Expired') {
                                    $statusClass = 'expired';
                                }
                                ?>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($discount->status) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form Card -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden lg:col-span-2">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-700">Edit Discount</h2>
                    <p class="text-gray-500">Modify the discount details</p>
                </div>
                
                <form method="post" class="p-6">
                    <!-- Discount Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="discount_rate" class="block text-sm font-medium text-gray-700 mb-2">Discount Rate (%)</label>
                            <input type="number" id="discount_rate" name="discount_rate" min="1" max="100" step="0.1" 
                                   value="<?= post('discount_rate', $discount->discount_rate) ?>" 
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <?php if (isset($_err['discount_rate'])): ?>
                                <div class="text-sm text-red-600 mt-1"><?= $_err['discount_rate'] ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                <input type="date" id="start_date" name="start_date" 
                                       value="<?= post('start_date', $discount->start_date) ?>" 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <?php if (isset($_err['start_date'])): ?>
                                    <div class="text-sm text-red-600 mt-1"><?= $_err['start_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                <input type="date" id="end_date" name="end_date" 
                                       value="<?= post('end_date', $discount->end_date) ?>" 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <?php if (isset($_err['end_date'])): ?>
                                    <div class="text-sm text-red-600 mt-1"><?= $_err['end_date'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Discount Preview -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-lg border border-gray-200 discount-preview" id="discountPreview">
                        <h3 class="font-semibold text-lg text-gray-700 mb-4">Discount Preview</h3>
                        <div id="previewContent">
                            <!-- Preview content will be injected here by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                        <a href="discount.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Update Discount
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require '../headFooter/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const discountRateInput = document.getElementById('discount_rate');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const previewContent = document.getElementById('previewContent');
            
            // Original product price from PHP
            const productPrice = <?= json_encode($discount->product_price) ?>;
            const productImage = "../../img/<?= encode($discount->product_pic1) ?>";
            const productName = "<?= addslashes($discount->product_name) ?>";
            const productId = "<?= addslashes($discount->product_id) ?>";
            
            // Start date change handler
            startDateInput.addEventListener('change', function() {
                // End date must be after start date
                if (this.value && endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
                
                updateDiscountPreview();
            });
            
            // End date change handler
            endDateInput.addEventListener('change', updateDiscountPreview);
            
            // Discount rate change handler
            discountRateInput.addEventListener('input', updateDiscountPreview);
            
            // Function to update discount preview
            function updateDiscountPreview() {
                const discountRate = parseFloat(discountRateInput.value) || 0;
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                
                // Calculate discounted price
                const price = parseFloat(productPrice) || 0;
                const discountAmount = price * (discountRate / 100);
                const finalPrice = price - discountAmount;
                
                // Determine status based on dates
                let statusText = '';
                let statusClass = '';
                const today = new Date().toISOString().split('T')[0];
                
                if (!startDate || !endDate) {
                    statusText = 'Dates not set';
                    statusClass = 'bg-gray-100 text-gray-600';
                } else if (today >= startDate && today <= endDate) {
                    statusText = 'Will be Active';
                    statusClass = 'bg-green-100 text-green-800';
                } else if (today < startDate) {
                    statusText = 'Will be Upcoming';
                    statusClass = 'bg-blue-100 text-blue-800';
                } else {
                    statusText = 'Will be Expired';
                    statusClass = 'bg-red-100 text-red-800';
                }
                
                // Format dates nicely
                let formattedStartDate = startDate ? new Date(startDate).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not set';
                let formattedEndDate = endDate ? new Date(endDate).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not set';
                
                // Create preview HTML
                previewContent.innerHTML = `
                    <div class="flex flex-col md:flex-row items-start gap-6">
                        <div class="flex-1">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500">Original Price</div>
                                    <div class="font-bold text-gray-700 line-through">RM ${price.toFixed(2)}</div>
                                </div>
                                
                                <div>
                                    <div class="text-sm text-gray-500">Discount Rate</div>
                                    <div class="font-bold text-red-600">${discountRate.toFixed(1)}% OFF</div>
                                </div>
                                
                                <div>
                                    <div class="text-sm text-gray-500">Discounted Price</div>
                                    <div class="font-bold text-green-600">RM ${finalPrice.toFixed(2)}</div>
                                </div>
                                
                                <div>
                                    <div class="text-sm text-gray-500">You Save</div>
                                    <div class="font-bold text-indigo-600">RM ${discountAmount.toFixed(2)}</div>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex flex-col md:flex-row md:justify-between gap-2">
                                    <div>
                                        <span class="text-sm text-gray-500">New Validity:</span>
                                        <span class="ml-2 text-sm font-medium">${formattedStartDate} to ${formattedEndDate}</span>
                                    </div>
                                    <div>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Initial update of discount preview
            updateDiscountPreview();
        });
    </script>
</body>
</html>