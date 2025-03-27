<?php
require_once '../../_base.php';
safe_session_start();
//auth('Admin', 'Manager'); // Ensure only Admin and Manager can access
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
$search = req('search');
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
    'active' => 'Active',
    'inactive' => 'Inactive'
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
                $stm = $_db->prepare("INSERT INTO discount 
                    (Discount_id, product_id, discount_rate, start_date, end_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stm->execute([
                    req('Discount_id'),
                    req('product_id'),
                    req('discount_rate'),
                    req('start_date'),
                    req('end_date'),
                    req('status')
                ]);
                temp('info', 'Discount Added Successfully');
                redirect('discount.php');
                break;

            case 'edit':
                $stm = $_db->prepare("UPDATE discount 
                    SET product_id = ?, discount_rate = ?, 
                    start_date = ?, end_date = ?, status = ?
                    WHERE Discount_id = ?");
                $stm->execute([
                    req('product_id'),
                    req('discount_rate'),
                    req('start_date'),
                    req('end_date'),
                    req('status'),
                    req('Discount_id')
                ]);
                temp('info', 'Discount Updated Successfully');
                redirect('discount.php');
                break;

            case 'toggle_status':
                $stm = $_db->prepare("UPDATE discount 
                    SET status = CASE 
                        WHEN status = 'active' THEN 'inactive' 
                        ELSE 'active' 
                    END 
                    WHERE Discount_id = ?");
                $stm->execute([req('Discount_id')]);
                temp('info', 'Discount Status Updated');
                redirect('discount.php');
                break;
        }
    } catch (PDOException $e) {
        temp('error', 'Operation failed: ' . $e->getMessage());
        redirect('discount.php');
    }
}

// Get products for dropdown
$stm_products = $_db->query("SELECT product_id, product_name FROM product");
$products = $stm_products->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discount Management</title>
    <link rel="stylesheet" href="/admin/css/cusStaff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/User/JS/basic.js"></script>
</head>
<body>
    <h1 style="text-align: center;">Discount Management</h1><br>

    <!-- Search and Filter Form -->
    <form>
        <p>Search Discount:</p>
        <?= html_search('search') ?>
        <p>Filter Status:</p>
        <?= html_select('status', $discount_status, 'All') ?>
        <button>
            Search <i class="fas fa-search"></i>
        </button>
        <a href="#" onclick="openModal('addModal')" class="btn-add-staff">
            <i class="fas fa-plus"></i>Add New Discount
        </a>
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

        <?php foreach ($discounts as $discount): ?>
            <tr>
                <td><?= htmlspecialchars($discount->Discount_id) ?></td>
                <td><?= htmlspecialchars($discount->product_name) ?></td>
                <td><?= htmlspecialchars($discount->discount_rate) ?>%</td>
                <td><?= htmlspecialchars($discount->start_date) ?></td>
                <td><?= htmlspecialchars($discount->end_date) ?></td>
                <td class="<?= $discount->status === 'inactive' ? 'status-blocked' : 'status-active' ?>">
                    <?= $discount_status[$discount->status] ?? 'Unknown' ?>
                </td>
                <td>
                    <form action="" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="Discount_id" value="<?= htmlspecialchars($discount->Discount_id) ?>">
                        <button type="submit" class="<?= $discount->status === 'inactive' ? 'button-unblock' : 'button-block' ?>">
                            <?= $discount->status === 'inactive' ? 'Activate' : 'Deactivate' ?>
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
                        Edit
                    </a>
                </td>
            </tr>
        <?php endforeach ?>
    </table>

    <!-- Pagination Links -->
    <br>
    <?= $p->html("sort=$sort&dir=$dir&search=$search&status=$status") ?>

    <!-- Add Discount Modal -->
    <div id="addModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="modal-content" style="background:white; margin:10% auto; padding:20px; border:1px solid #888; width:500px;">
            <span onclick="closeModal('addModal')" style="color:#aaa; float:right; font-size:28px; cursor:pointer;">&times;</span>
            <h2>Add New Discount</h2>
            <form method="post">
                <input type="hidden" name="action" value="add">
                
                <label>Discount ID:</label>
                <input type="text" name="Discount_id" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Product:</label>
                <?php 
                html_select('product_id', $products, '- Select Product -', 'required style="width:100%; padding:10px; margin-bottom:10px;"'); 
                ?>
                
                <label>Discount Rate (%):</label>
                <input type="number" name="discount_rate" min="0" max="100" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Start Date:</label>
                <input type="date" name="start_date" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>End Date:</label>
                <input type="date" name="end_date" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Status:</label>
                <select name="status" required style="width:100%; padding:10px; margin-bottom:10px;">
                    <option value="">- Select Status -</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <button type="submit" style="width:100%; padding:10px; background-color:#007BFF; color:white; border:none; cursor:pointer;">
                    Add Discount
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Discount Modal -->
    <div id="editModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="modal-content" style="background:white; margin:10% auto; padding:20px; border:1px solid #888; width:500px;">
            <span onclick="closeModal('editModal')" style="color:#aaa; float:right; font-size:28px; cursor:pointer;">&times;</span>
            <h2>Edit Discount</h2>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                
                <label>Discount ID:</label>
                <input type="text" name="Discount_id" id="edit_Discount_id" readonly style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Product:</label>
                <?php 
                html_select('product_id', $products, '- Select Product -', 'id="edit_product_id" required style="width:100%; padding:10px; margin-bottom:10px;"'); 
                ?>
                
                <label>Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="edit_discount_rate" min="0" max="100" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Start Date:</label>
                <input type="date" name="start_date" id="edit_start_date" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>End Date:</label>
                <input type="date" name="end_date" id="edit_end_date" required style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label>Status:</label>
                <select name="status" id="edit_status" required style="width:100%; padding:10px; margin-bottom:10px;">
                    <option value="">- Select Status -</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <button type="submit" style="width:100%; padding:10px; background-color:#007BFF; color:white; border:none; cursor:pointer;">
                    Update Discount
                </button>
            </form>
        </div>
    </div>

    <script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function editDiscount(id, productId, discountRate, startDate, endDate, status) {
        document.getElementById('edit_Discount_id').value = id;
        document.getElementById('edit_product_id').value = productId;
        document.getElementById('edit_discount_rate').value = discountRate;
        document.getElementById('edit_start_date').value = startDate;
        document.getElementById('edit_end_date').value = endDate;
        document.getElementById('edit_status').value = status;
        
        openModal('editModal');
    }
    </script>
</body>
</html>