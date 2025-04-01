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

// Prepare base query
require_once '../lib/SimplePager.php';

// Check for expired discounts and update status automatically
$current_date = date('Y-m-d H:i:s');
$update_expired = $_db->prepare("UPDATE discount SET status = 'Inactive' WHERE end_date < ? AND status = 'Active'");
$update_expired->execute([$current_date]);

// Enhanced search across all fields
$sql = "SELECT d.*, 
        GROUP_CONCAT(p.product_name SEPARATOR ', ') AS product_names
        FROM discount d
        LEFT JOIN discount_products dp ON d.Discount_id = dp.discount_id
        LEFT JOIN product p ON dp.product_id = p.product_id
        WHERE d.Discount_id LIKE ? 
           OR p.product_name LIKE ? 
           OR d.discount_rate LIKE ? 
           OR d.start_date LIKE ? 
           OR d.end_date LIKE ? 
           OR d.status LIKE ?
        GROUP BY d.Discount_id
        ORDER BY $sort $dir";

// Prepare parameters for the enhanced search
$search_param = "%$search%";
$params = [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param];

// Discount statuses
$discount_status = [
    'Active' => 'Active',
    'Inactive' => 'Inactive'
];

// Using SimplePager for pagination
$p = new SimplePager($sql, $params, 10, $page);
$discounts = $p->result;

// Function to generate a unique discount ID
function generateDiscountId()
{
    global $_db;

    // Format: DISC-{YEARMONTH}-{RANDOM4DIGITS}
    $prefix = 'DISC-' . date('Ym') . '-';
    $unique = false;
    $discount_id = '';

    while (!$unique) {
        $random = rand(1000, 9999);
        $discount_id = $prefix . $random;

        // Check if this ID already exists
        $stm = $_db->prepare("SELECT COUNT(*) FROM discount WHERE Discount_id = ?");
        $stm->execute([$discount_id]);
        if ($stm->fetchColumn() == 0) {
            $unique = true;
        }
    }

    return $discount_id;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = req('action');

    try {
        switch ($action) {
            case 'add':
                // Auto-generate discount ID
                $discount_id = generateDiscountId();
                $product_ids = req('product_id', []);
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

                // Format dates if they don't include time
                if (!strpos($start_date, ':')) {
                    $start_date = date('Y-m-d H:i:s', strtotime($start_date . ' 00:00:00'));
                }
                if (!strpos($end_date, ':')) {
                    $end_date = date('Y-m-d H:i:s', strtotime($end_date . ' 23:59:59'));
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

                foreach ($product_ids as $product_id) {
                    $stm = $_db->prepare("INSERT INTO discount_products (discount_id, product_id) VALUES (?, ?)");
                    $stm->execute([$discount_id, $product_id]);
                }

                temp('info', 'Discount Added Successfully');
                redirect('discount.php');
                break;

            case 'edit':
                // Get the discount ID from the form
                $discount_id = req('Discount_id');
                $product_ids = req('product_id', []);
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

                // Format dates if they don't include time
                if (!strpos($start_date, ':')) {
                    $start_date = date('Y-m-d H:i:s', strtotime($start_date . ' 00:00:00'));
                }
                if (!strpos($end_date, ':')) {
                    $end_date = date('Y-m-d H:i:s', strtotime($end_date . ' 23:59:59'));
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

                $_db->prepare("DELETE FROM discount_products WHERE discount_id = ?")->execute([$discount_id]);
                foreach ($product_ids as $product_id) {
                    $_db->prepare("INSERT INTO discount_products (discount_id, product_id) VALUES (?, ?)")
                        ->execute([$discount_id, $product_id]);
                }

                temp('info', 'Discount Updated Successfully');
                redirect('discount.php');
                break;

            case 'toggle_status':
                $discount_id = req('Discount_id');

                // Check if the discount end date has passed
                $stm = $_db->prepare("SELECT status, end_date FROM discount WHERE Discount_id = ?");
                $stm->execute([$discount_id]);
                $discount_data = $stm->fetch();

                $current_status = $discount_data->status;
                $end_date = $discount_data->end_date;

                // Only allow toggling to Active if end date has not passed
                if ($current_status == 'Inactive' && strtotime($end_date) < time()) {
                    temp('error', 'Cannot activate discount that has already expired.');
                    redirect('discount.php');
                    break;
                }

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
            cursor: pointer;
        }

        .status-inactive {
            color: red;
            font-weight: bold;
            cursor: pointer;
        }

        .status-expired {
            color: gray;
            font-weight: bold;
            cursor: not-allowed;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
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

        /* Datetime input fields styling */
        .datetime-container {
            display: flex;
            gap: 10px;
        }

        .datetime-container input {
            flex: 1;
        }

        select[multiple] {
            height: 150px;
            padding: 10px;
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

    <!-- Search Form -->
    <form method="get" action="">
        <div style="display: flex; align-items: center; margin-bottom: 20px;">
            <div style="margin-right: 20px;">
                <p>Search Discount:</p>
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by ID, Product, Rate, Date or Status">
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
            <?= table_headers($fields, $sort, $dir, "page=$page&search=$search") ?>
            <th>Action</th>
        </tr>

        <?php if ($p->count > 0): ?>
            <?php foreach ($discounts as $discount): ?>
                <?php
                // Check if discount has expired
                $is_expired = strtotime($discount->end_date) < time();
                $status_class = $is_expired ? 'status-expired' : 'status-' . strtolower($discount->status);
                ?>
                <tr>
                    <td><?= htmlspecialchars($discount->Discount_id) ?></td>
                    <td><?= htmlspecialchars($discount->product_names ?? 'No products') ?></td>
                    <td><?= htmlspecialchars($discount->discount_rate) ?>%</td>
                    <td><?= htmlspecialchars($discount->start_date) ?></td>
                    <td><?= htmlspecialchars($discount->end_date) ?></td>
                    <td>
                        <?php if ($is_expired): ?>
                            <span class="<?= $status_class ?>" title="Discount expired">
                                <?= htmlspecialchars($discount->status) ?> (Expired)
                            </span>
                        <?php else: ?>
                            <span class="<?= $status_class ?>"
                                onclick="toggleStatus('<?= htmlspecialchars($discount->Discount_id) ?>')"
                                title="Click to toggle status">
                                <?= htmlspecialchars($discount->status) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
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
    <?= $p->html("sort=$sort&dir=$dir&search=$search") ?>

    <!-- Add Discount Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Discount</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">

                <label for="product_id">Products:</label>
                <select name="product_id[]" id="product_id" multiple required
                    style="width:100%; margin-bottom:10px;">
                    <?php foreach ($products as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="discount_rate">Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="discount_rate" min="0" max="100" step="0.01" required
                    style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">

                <label for="start_date">Start Date and Time:</label>
                <div class="datetime-container">
                    <input type="datetime-local" name="start_date" id="start_date" required
                        style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                </div>

                <label for="end_date">End Date and Time:</label>
                <div class="datetime-container">
                    <input type="datetime-local" name="end_date" id="end_date" required
                        style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                </div>

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

                <label for="edit_product_id">Products:</label>
                <select name="product_id[]" id="edit_product_id" multiple required
                    style="width:100%; margin-bottom:10px;">
                    <?php foreach ($products as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="edit_discount_rate">Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="edit_discount_rate" min="0" max="100" step="0.01" required
                    style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">

                <label for="edit_start_date">Start Date and Time:</label>
                <div class="datetime-container">
                    <input type="datetime-local" name="start_date" id="edit_start_date" required
                        style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                </div>

                <label for="edit_end_date">End Date and Time:</label>
                <div class="datetime-container">
                    <input type="datetime-local" name="end_date" id="edit_end_date" required
                        style="width:100%; padding:10px; margin-bottom:10px; box-sizing: border-box;">
                </div>

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

    <!-- Hidden form for status toggle -->
    <form id="statusForm" method="post" action="" style="display:none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="Discount_id" id="status_discount_id" value="">
    </form>

    <script>
        // Show modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Toggle status by clicking on status text
        function toggleStatus(discountId) {
            if (confirm('Are you sure you want to change this discount status?')) {
                document.getElementById('status_discount_id').value = discountId;
                document.getElementById('statusForm').submit();
            }
        }

        // Format datetime for input fields
        function formatDateTimeForInput(dateTimeStr) {
            // Convert MySQL datetime format to HTML datetime-local input format
            if (!dateTimeStr) return '';

            // Parse the datetime string
            const dt = new Date(dateTimeStr.replace(' ', 'T'));

            // Format as YYYY-MM-DDTHH:MM
            return dt.getFullYear() + '-' +
                String(dt.getMonth() + 1).padStart(2, '0') + '-' +
                String(dt.getDate()).padStart(2, '0') + 'T' +
                String(dt.getHours()).padStart(2, '0') + ':' +
                String(dt.getMinutes()).padStart(2, '0');
        }

        // Set values for edit modal
        function editDiscount(id, productId, discountRate, startDate, endDate, status) {
            // Convert productIds (comma-separated) to array
            const productArray = productIds.split(', ');

            // Set multi-select values
            const select = document.getElementById('edit_product_id');
            Array.from(select.options).forEach(option => {
                option.selected = productArray.includes(option.value);
            });

            document.getElementById('edit_Discount_id').value = id;
            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_discount_rate').value = discountRate;

            // Format the datetime for input datetime-local fields
            document.getElementById('edit_start_date').value = formatDateTimeForInput(startDate);
            document.getElementById('edit_end_date').value = formatDateTimeForInput(endDate);
            document.getElementById('edit_status').value = status;

            openModal('editModal');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Set minimum datetime values
        document.addEventListener('DOMContentLoaded', function() {
            // Get current date and time in format required for datetime-local input
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

            // Set minimum datetime for start_date to now
            document.getElementById('start_date').min = currentDateTime;

            // Ensure end_date is after start_date
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