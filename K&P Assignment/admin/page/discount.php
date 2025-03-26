<?php
require_once '../../_base.php';
safe_session_start();
//auth('Admin', 'Manager'); // Ensure only Admin and Manager can access

// Initialize variables
$sort = get('sort', 'Discount_id');
$dir = get('dir', 'asc');
$search = get('search', '');

try {
    // Prepare base query
    $query = "SELECT d.*, p.product_name 
              FROM discount d 
              JOIN product p ON d.product_id = p.product_id
              WHERE 1=1";
    
    $params = [];

    // Add search conditions
    if (!empty($search)) {
        $query .= " AND (d.Discount_id LIKE ? OR p.product_name LIKE ? OR d.status LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    // Add sorting
    $query .= " ORDER BY $sort $dir";

    // Prepare and execute statement
    $stm = $_db->prepare($query);
    $stm->execute($params);
    $discounts = $stm->fetchAll();

    // Get products for dropdown
    $stm_products = $_db->query("SELECT product_id, product_name FROM product");
    $products = $stm_products->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    temp('error', 'Database error: ' . $e->getMessage());
    redirect();
}

// Handle form submissions
if (is_post()) {
    $action = post('action');

    try {
        switch ($action) {
            case 'add':
                $stm = $_db->prepare("INSERT INTO discount 
                    (Discount_id, product_id, discount_rate, start_date, end_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stm->execute([
                    post('Discount_id'),
                    post('product_id'),
                    post('discount_rate'),
                    post('start_date'),
                    post('end_date'),
                    post('status')
                ]);
                temp('success', 'Discount added successfully');
                redirect('discount.php');
                break;

            case 'edit':
                $stm = $_db->prepare("UPDATE discount 
                    SET product_id = ?, discount_rate = ?, 
                    start_date = ?, end_date = ?, status = ?
                    WHERE Discount_id = ?");
                $stm->execute([
                    post('product_id'),
                    post('discount_rate'),
                    post('start_date'),
                    post('end_date'),
                    post('status'),
                    post('Discount_id')
                ]);
                temp('success', 'Discount updated successfully');
                redirect('discount.php');
                break;

            case 'delete':
                $stm = $_db->prepare("DELETE FROM discount WHERE Discount_id = ?");
                $stm->execute([post('Discount_id')]);
                temp('success', 'Discount deleted successfully');
                redirect('discount.php');
                break;
        }
    } catch (PDOException $e) {
        temp('error', 'Operation failed: ' . $e->getMessage());
        redirect('discount.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discount Management</title>
    <?php include 'header.php'; ?>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Discount Management</h1>

        <?php 
        // Display any temporary messages
        if ($msg = temp('success')) {
            echo "<div class='alert success'>$msg</div>";
        }
        if ($msg = temp('error')) {
            echo "<div class='alert error'>$msg</div>";
        }
        ?>

        <!-- Search Form -->
        <form method="get" class="search-form">
            <input type="search" name="search" placeholder="Search Discounts" 
                value="<?= encode($search) ?>">
            <button type="submit">Search</button>
        </form>

        <!-- Add Discount Button -->
        <button onclick="openModal('addModal')">Add New Discount</button>

        <!-- Discount List -->
        <table>
            <thead>
                <?php 
                $fields = [
                    'Discount_id' => 'Discount ID', 
                    'product_name' => 'Product', 
                    'discount_rate' => 'Discount Rate', 
                    'start_date' => 'Start Date', 
                    'end_date' => 'End Date', 
                    'status' => 'Status'
                ];
                table_headers($fields, $sort, $dir); 
                ?>
            </thead>
            <tbody>
                <?php foreach ($discounts as $discount): ?>
                <tr>
                    <td><?= encode($discount->Discount_id) ?></td>
                    <td><?= encode($discount->product_name) ?></td>
                    <td><?= encode($discount->discount_rate) ?>%</td>
                    <td><?= encode($discount->start_date) ?></td>
                    <td><?= encode($discount->end_date) ?></td>
                    <td><?= encode($discount->status) ?></td>
                    <td>
                        <button onclick="editDiscount(
                            '<?= encode($discount->Discount_id) ?>',
                            '<?= encode($discount->product_id) ?>',
                            '<?= encode($discount->discount_rate) ?>',
                            '<?= encode($discount->start_date) ?>',
                            '<?= encode($discount->end_date) ?>',
                            '<?= encode($discount->status) ?>'
                        )">Edit</button>
                        <button onclick="deleteDiscount('<?= encode($discount->Discount_id) ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Discount Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Discount</h2>
            <form method="post">
                <input type="hidden" name="action" value="add">
                
                <label>Discount ID:</label>
                <input type="text" name="Discount_id" required>
                
                <label>Product:</label>
                <?php 
                html_select('product_id', $products, '- Select Product -', 'required'); 
                ?>
                
                <label>Discount Rate (%):</label>
                <input type="number" name="discount_rate" min="0" max="100" required>
                
                <label>Start Date:</label>
                <input type="date" name="start_date" required>
                
                <label>End Date:</label>
                <input type="date" name="end_date" required>
                
                <label>Status:</label>
                <select name="status" required>
                    <option value="">- Select Status -</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <button type="submit">Add Discount</button>
            </form>
        </div>
    </div>

    <!-- Edit Discount Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Discount</h2>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                
                <label>Discount ID:</label>
                <input type="text" name="Discount_id" id="edit_Discount_id" readonly>
                
                <label>Product:</label>
                <?php 
                html_select('product_id', $products, '- Select Product -', 'id="edit_product_id" required'); 
                ?>
                
                <label>Discount Rate (%):</label>
                <input type="number" name="discount_rate" id="edit_discount_rate" min="0" max="100" required>
                
                <label>Start Date:</label>
                <input type="date" name="start_date" id="edit_start_date" required>
                
                <label>End Date:</label>
                <input type="date" name="end_date" id="edit_end_date" required>
                
                <label>Status:</label>
                <select name="status" id="edit_status" required>
                    <option value="">- Select Status -</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <button type="submit">Update Discount</button>
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

    function deleteDiscount(id) {
        if (confirm('Are you sure you want to delete this discount?')) {
            var form = document.createElement('form');
            form.method = 'post';
            
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            form.appendChild(actionInput);
            
            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'Discount_id';
            idInput.value = id;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>