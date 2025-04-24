<?php
require_once '../../_base.php';
auth('admin', 'staff');

// Make sure current_user is defined for the header
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

$sort = get('sort', 'start_date');
$dir = get('dir', 'desc');
$search = get('search', '');
$filter_status = get('status', '');

$valid_sorts = ['Discount_id', 'product_id', 'discount_rate', 'start_date', 'end_date', 'status'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'start_date';
}

$sql = "SELECT d.*, p.product_name, p.product_pic1 
        FROM discount d
        JOIN product p ON d.product_id = p.product_id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (d.Discount_id LIKE ? OR p.product_name LIKE ? OR d.product_id LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($filter_status) {
    $sql .= " AND d.status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY d.$sort $dir";

$stm = $_db->prepare($sql);
$stm->execute($params);
$discounts = $stm->fetchAll();

// Get all products for dropdown
$stm = $_db->prepare("SELECT product_id, product_name FROM product ORDER BY product_name");
$stm->execute();
$products = $stm->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Management - K&P Admin</title>
    <!-- Include head content directly instead of using include -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../user/style/style.css">
    <style>
        .discount-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .search-input {
            flex: 1;
            min-width: 250px;
        }
        
        .filter-select {
            min-width: 150px;
        }
        
        .add-discount-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .add-discount-btn:hover {
            background-color: #218838;
        }
        
        .discount-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .discount-table th {
            background-color: #4a6fa5;
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .discount-table th a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .discount-table th a:hover {
            color: #e9ecef;
        }
        
        .discount-table th a.asc::after {
            content: "↑";
            margin-left: 5px;
        }
        
        .discount-table th a.desc::after {
            content: "↓";
            margin-left: 5px;
        }
        
        .discount-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .discount-table tr:last-child td {
            border-bottom: none;
        }
        
        .discount-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }
        
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        
        .edit-btn {
            background-color: #ffc107;
            color: #212529;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .edit-btn:hover {
            background-color: #e0a800;
        }
        
        .delete-btn:hover {
            background-color: #c82333;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-upcoming {
            background-color: #cce5ff;
            color: #004085;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .discount-rate {
            font-weight: bold;
            color: #dc3545;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .no-discounts {
            text-align: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .discount-table {
                display: block;
                overflow-x: auto;
            }
            
            .search-filter-container {
                flex-direction: column;
                align-items: stretch;
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
    
    <div class="discount-container">
        <h2>Discount Management</h2>
        
        <?php if (isset($_SESSION['temp_success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['temp_success']; ?>
                <?php unset($_SESSION['temp_success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['temp_error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['temp_error']; ?>
                <?php unset($_SESSION['temp_error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="search-filter-container">
            <form action="" method="get" class="search-input">
                <input type="text" name="search" placeholder="Search discount ID or product" value="<?= encode($search) ?>">
                <button type="submit">Search</button>
            </form>
            
            <form action="" method="get" class="filter-select">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="Active" <?= $filter_status == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Expired" <?= $filter_status == 'Expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="Upcoming" <?= $filter_status == 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                </select>
            </form>
            
            <a href="add.php" class="add-discount-btn">Add New Discount</a>
        </div>
        
        <?php if (count($discounts) > 0): ?>
            <table class="discount-table">
                <thead>
                    <tr>
                        <th><a href="?sort=Discount_id&dir=<?= $sort == 'Discount_id' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'Discount_id' ? $dir : '' ?>">Discount ID</a></th>
                        <th><a href="?sort=product_id&dir=<?= $sort == 'product_id' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'product_id' ? $dir : '' ?>">Product</a></th>
                        <th><a href="?sort=discount_rate&dir=<?= $sort == 'discount_rate' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'discount_rate' ? $dir : '' ?>">Discount Rate</a></th>
                        <th><a href="?sort=start_date&dir=<?= $sort == 'start_date' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'start_date' ? $dir : '' ?>">Start Date</a></th>
                        <th><a href="?sort=end_date&dir=<?= $sort == 'end_date' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'end_date' ? $dir : '' ?>">End Date</a></th>
                        <th><a href="?sort=status&dir=<?= $sort == 'status' && $dir == 'asc' ? 'desc' : 'asc' ?>" class="<?= $sort == 'status' ? $dir : '' ?>">Status</a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $discount): ?>
                        <tr>
                            <td><?= encode($discount->Discount_id) ?></td>
                            <td>
                                <div class="product-info">
                                    <img src="../../user/product_pic/<?= encode($discount->product_pic1) ?>" class="product-image" alt="Product Image">
                                    <div>
                                        <div><strong><?= encode($discount->product_id) ?></strong></div>
                                        <div><?= encode($discount->product_name) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="discount-rate"><?= encode($discount->discount_rate) ?>%</td>
                            <td><?= encode($discount->start_date) ?></td>
                            <td><?= encode($discount->end_date) ?></td>
                            <td>
                                <?php
                                $today = date('Y-m-d');
                                $statusClass = '';
                                
                                if ($discount->status == 'Active' || ($today >= $discount->start_date && $today <= $discount->end_date)) {
                                    $statusClass = 'status-active';
                                    $displayStatus = 'Active';
                                } elseif ($today < $discount->start_date) {
                                    $statusClass = 'status-upcoming';
                                    $displayStatus = 'Upcoming';
                                } else {
                                    $statusClass = 'status-expired';
                                    $displayStatus = 'Expired';
                                }
                                ?>
                                <span class="<?= $statusClass ?>"><?= $displayStatus ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit.php?id=<?= $discount->Discount_id ?>" class="edit-btn">Edit</a>
                                    <a href="delete.php?id=<?= $discount->Discount_id ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this discount?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-discounts">
                <p>No discounts found. Create a new discount to get started.</p>
                <a href="add.php" class="add-discount-btn">Add New Discount</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Simple footer -->
    <footer>
        <p>&copy; <?= date('Y') ?> K&P Fashion Admin Panel. All rights reserved.</p>
    </footer>
    
    <script>
        // Auto-update status based on date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const formattedToday = today.toISOString().split('T')[0];
            
            // Update backend statuses periodically
            function updateDiscountStatuses() {
                fetch('update_statuses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Discount statuses updated");
                    }
                }).catch(error => {
                    console.error("Error updating discount statuses:", error);
                });
            }
            
            // Update status on page load
            updateDiscountStatuses();
        });
    </script>
</body>
</html>