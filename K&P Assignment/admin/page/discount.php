<?php
require_once '../../_base.php';
safe_session_start();
auth(0, 1);
require 'header.php';

// Initialize variables for sorting, filtering, and pagination
$fields = [
    'Discount_id' => 'Discount ID', 
    'product_name' => 'Product', 
    'discount_rate' => 'Discount Rate', 
    'start_date' => 'Start Date', 
    'end_date' => 'End Date', 
    'status' => 'Status'
];

// Sorting
$sort = req('sort');
key_exists($sort, $fields) || $sort = 'Discount_id';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// Paging
$page = req('page', 1);

// Search Parameters
$search = req('search', '');
$status = req('status', '');

// Prepare base query
require_once '../lib/SimplePager.php';

$sql = "SELECT d.*, p.product_name 
        FROM discount d 
        JOIN product p ON d.product_id = p.product_id
        WHERE (d.Discount_id LIKE ? OR p.product_name LIKE ?)
        " . ($status ? "AND d.status = ?" : "") . "
        ORDER BY $sort $dir";

// Prepare parameters
$params = ["%$search%", "%$search%"];
if ($status) {
    $params[] = $status;
}

// Discount statuses
$discount_status = [
    'Active' => 'Active',
    'Inactive' => 'Inactive'
];

// Using SimplePager for pagination
$p = new SimplePager($sql, $params, 10, $page);
$discounts = $p->result;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = req('action');

    try {
        switch ($action) {
            case 'add':
                // Validate input
                $discount_id = req('Discount_id');
                $product_id = req('product_id');
                $discount_rate = req('discount_rate');
                $start_date = req('start_date');
                $end_date = req('end_date');
                $status = req('status');
                
                // Check if discount ID already exists
                if (!is_unique($discount_id, 'discount', 'Discount_id')) {
                    temp('error', 'Discount ID already exists. Please use a different ID.');
                    redirect('discount.php');
                    break;
                }
                
                // Validate discount rate
                if (!is_numeric($discount_rate) || $discount_rate < 0 || $discount_rate > 100) {
                    temp('error', 'Discount rate must be between 0 and 100.');
                    redirect('discount.php');
                    break;
                }
                
                // Validate dates
                if (strtotime($end_date) < strtotime($start_date)) {
                    temp('error', 'End date cannot be before start date.');
                    redirect('discount.php');
                    break;
                }
                
                $stm = $_db->prepare("INSERT INTO discount 
                    (Discount_id, product_id, discount_rate, start_date, end_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stm->execute([
                    $discount_id,
                    $product_id,
                    $discount_rate,
                    $start_date,
                    $end_date,
                    $status
                ]);
                temp('info', 'Discount Added Successfully');
                redirect('discount.php');
                break;

            case 'edit':
                // Validate input
                $discount_id = req('Discount_id');
                $product_id = req('product_id');
                $discount_rate = req('discount_rate');
                $start_date = req('start_date');
                $end_date = req('end_date');
                $status = req('status');
                
                // Validate discount rate
                if (!is_numeric($discount_rate) || $discount_rate < 0 || $discount_rate > 100) {
                    temp('error', 'Discount rate must be between 0 and 100.');
                    redirect('discount.php');
                    break;
                }
                
                // Validate dates
                if (strtotime($end_date) < strtotime($start_date)) {
                    temp('error', 'End date cannot be before start date.');
                    redirect('discount.php');
                    break;
                }
                
                $stm = $_db->prepare("UPDATE discount 
                    SET product_id = ?, discount_rate = ?, 
                    start_date = ?, end_date = ?, status = ?
                    WHERE Discount_id = ?");
                $stm->execute([
                    $product_id,
                    $discount_rate,
                    $start_date,
                    $end_date,
                    $status,
                    $discount_id
                ]);
                temp('info', 'Discount Updated Successfully');
                redirect('discount.php');
                break;

            case 'delete':
                $stm = $_db->prepare("DELETE FROM discount WHERE Discount_id = ?");
                $stm->execute([req('Discount_id')]);
                temp('info', 'Discount Deleted Successfully');
                redirect('discount.php');
                break;

            case 'toggle_status':
                $discount_id = req('Discount_id');
                $stm = $_db->prepare("SELECT status FROM discount WHERE Discount_id = ?");
                $stm->execute([$discount_id]);
                $current_status = $stm->fetchColumn();
                
                $new_status = ($current_status == 'Active') ? 'Inactive' : 'Active';
                
                $stm = $_db->prepare("UPDATE discount SET status = ? WHERE Discount_id = ?");
                $stm->execute([$new_status, $discount_id]);
                
                temp('info', 'Discount Status Updated to ' . $new_status);
                redirect('discount.php');
                break;
        }
    } catch (PDOException $e) {
        temp('error', 'Operation failed: ' . $e->getMessage());
        redirect('discount.php');
    }
}

// Get products for dropdown
$stm_products = $_db->query("SELECT product_id, product_name FROM product ORDER BY product_name ASC");
$products = $stm_products->fetchAll(PDO::FETCH_KEY_PAIR);

// Display any session messages
$info = temp('info');
$error = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discount Management</title>
    <link rel="stylesheet" href="/admin/css/cusStaff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/User/JS/basic.js"></script>
    <style>
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .info {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-active {
            color: green;
            font-weight: bold;
        }
        .status-inactive {
            color: red;
            font-weight: bold;
        }
        .btn-add-discount {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        .button-edit {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 5px;
        }
        .button-delete {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 5px;
        }
        .button-block {
            background-color: #ffc107;
            color: black;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .button-unblock {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 500px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
    </style>
</head>
<body>
    <h1 style="text-align: center;">Discount Management</h1><br>

    <!-- Display Messages -->
    <?php if ($info): ?>
        <div class="message info">
            <?= htmlspecialchars($info) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Form -->
    <form method="get" action="">
        <div style="display: flex; align-items: center; margin-bottom: 20px;">
            <div style="margin-right: 20px;">
                <p>Search Discount:</p>
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by ID or product">
            </div>
            
            <div style="margin-right: 20px;">
                <p>Filter Status:</p>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach ($discount_status as $key => $value): ?>
                        <option value="<?= $key ?>" <?= $status == $key ? 'selected' : '' ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-top: 25px;">
                <button type="submit">
                    Search <i class="fas fa-search"></i>
                </button>
                
                <a href="#" onclick="openModal('addModal')" class="btn-add-discount">
                    <i class="fas fa-plus"></i> Add New Discount
                </a>
            </div>
        </div>
    </form>

    <!-- Show record counts and paging info -->
    <p>
        <?= $p->count ?> of <?= $p->item_count ?> record(s) |
        Page <?= $p->page ?> of <?= $p->page_count ?>
    </p>

    <!-- Discount Table -->
    <table class="table">
        <tr>
            <?= table_headers($fields, $sort, $dir, "page=$page&search=$search&status=$status") ?>
            <th>Action</th>
        </tr>

        <?php if ($p->count > 0): ?>
            <?php foreach ($discounts as $discount): ?>
                <tr>
                    <td><?= htmlspecialchars($discount->Discount_id) ?></td>
                    <td><?= htmlspecialchars($discount->product_name) ?></td>
                    <td><?= htmlspecialchars($discount->discount_rate) ?>%</td>
                    <td><?= htmlspecialchars($discount->start_date) ?></td>
                    <td><?= htmlspecialchars($discount->end_date) ?></td>
                    <td class="status-<?= strtolower($discount->status) ?>">
                        <?= htmlspecialchars($discount->status) ?>
                    </td>
                    <td>
                        <form action="" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to change this discount status?');">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="Discount_id" value="<?= htmlspecialchars($discount->Discount_id) ?>">
                            <button type="submit" class="<?= $discount->status === 'Inactive' ? 'button-unblock' : 'button-block' ?>">
                                <?= $discount->status === 'Inactive' ? 'Activate' : 'Deactivate' ?>
                            </button>
                        </form>
                        
                        <a href="#" onclick="editDiscount(
                            '<?= htmlspecialchars($discount->Discount_id) ?>',
                            '<?= htmlspecialchars($discount->product_id) ?>',
                            '<?= htmlspecialchars($discount->discount_rate) ?>',
                            '<?= htmlspecialchars($discount->start_date) ?>',
                            '<?= htmlspecialchars($discount->end_date) ?>',
                            '<?= htmlspecialchars($discount->status) ?>'
                        )" class="button-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        
                        <form action="" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this discount?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="Discount_id" value="<?= htmlspecialchars($discount->Discount_id) ?>">
                            <button type="submit" class="button-delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align: center;">No discounts found.</td>
            </tr>
        <?php endif; ?>
    </table>

    <!-- Pagination Links -->
    <br>
    <?= $p->html("sort=$sort&dir=$dir&search=$search&status=$status") ?>

    <!-- Add Discount Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Discount</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                
                <label for="Discount_id">Discount ID:</label>
                <input type="text" name="Discount_id" id="Discount_id" required 
                       pattern="[A-Za-z0-9-_]+" title="Only letters, numbers, hyphens and underscores are allowed"
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="product_id">Product:</label>
                <select name="product_id" id="product_id" required style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                    <option value="">- Select Product -</option>
                    <?php foreach ($products as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label for="discount_rate">Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="discount_rate" min="0" max="100" step="0.01" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="start_date">Start Date:</label>
                <input type="date" name="start_date" id="start_date" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="end_date">End Date:</label>
                <input type="date" name="end_date" id="end_date" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="status">Status:</label>
                <select name="status" id="status" required style="width:100%; padding:10px; margin-bottom:20px; box-sizing: border-box;">
                    <option value="">- Select Status -</option>
                    <?php foreach ($discount_status as $key => $value): ?>
                        <option value="<?= $key ?>"><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" style="width:100%; padding:10px; background-color:#28a745; color:white; border:none; cursor:pointer; border-radius:4px;">
                    <i class="fas fa-plus"></i> Add Discount
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Discount Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Discount</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="edit">
                
                <label for="edit_Discount_id">Discount ID:</label>
                <input type="text" name="Discount_id" id="edit_Discount_id" readonly 
                       style="width:100%; padding:10px; margin-bottom:10px; background-color:#f0f0f0; box-sizing: border-box;">
                
                <label for="edit_product_id">Product:</label>
                <select name="product_id" id="edit_product_id" required style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                    <option value="">- Select Product -</option>
                    <?php foreach ($products as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label for="edit_discount_rate">Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="edit_discount_rate" min="0" max="100" step="0.01" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="edit_start_date">Start Date:</label>
                <input type="date" name="start_date" id="edit_start_date" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="edit_end_date">End Date:</label>
                <input type="date" name="end_date" id="edit_end_date" required 
                       style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                
                <label for="edit_status">Status:</label>
                <select name="status" id="edit_status" required style="width:100%; padding:10px; margin-bottom:20px; box-sizing: border-box;">
                    <option value="">- Select Status -</option>
                    <?php foreach ($discount_status as $key => $value): ?>
                        <option value="<?= $key ?>"><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" style="width:100%; padding:10px; background-color:#007BFF; color:white; border:none; cursor:pointer; border-radius:4px;">
                    <i class="fas fa-save"></i> Update Discount
                </button>
            </form>
        </div>
    </div>

    <script>
    // Show modal functions
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Set values for edit modal
    function editDiscount(id, productId, discountRate, startDate, endDate, status) {
        document.getElementById('edit_Discount_id').value = id;
        document.getElementById('edit_product_id').value = productId;
        document.getElementById('edit_discount_rate').value = discountRate;
        document.getElementById('edit_start_date').value = startDate;
        document.getElementById('edit_end_date').value = endDate;
        document.getElementById('edit_status').value = status;
        
        openModal('editModal');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    }

    // Current date for start date minimum
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        
        // Ensure end date is after start date
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
        
        document.getElementById('edit_start_date').addEventListener('change', function() {
            document.getElementById('edit_end_date').min = this.value;
        });
    });
    </script>
</body>
</html>