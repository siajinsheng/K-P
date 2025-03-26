<?php
$_title = 'Customer';
require '../../_base.php';
// -------------------------------
// Admin role
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
//auth('Admin', 'Manager');
require 'header.php';

$_title = 'Customer List';

// ----------------------------------------------------------------------------
$user_gender = [
    '0' => 'Female',
    '1' => 'Male'
];

$user_status = [
    'blocked' => 'Blocked',
    'active'  => 'Active'
];

// (1) Sorting
$fields = [
    'cus_Email'     => 'Email',
    'cus_name'      => 'Customer Name',
    'cus_gender'    => 'Gender',
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'cus_Email';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// (2) Paging
$page = req('page', 1);

// (3) Search Parameters
$email = req('email');
$status = req('status');

// (4) Prepare SQL Query with search, sort, and paging
require_once '../lib/SimplePager.php';

$sql = 'SELECT * FROM customer 
        WHERE cus_Email LIKE ? AND (cus_status = ? OR ?) 
        ORDER BY ' . $sort . ' ' . $dir;

$params = ["%$email%", $status, $status == null];

// Using SimplePager for pagination
$p = new SimplePager($sql, $params, 10, $page);
$arr = $p->result;

// Update customer status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = req('customer_id');
    $current_status = req('status');

    // Determine new status
    $new_status = ($current_status === 'blocked') ? 'active' : 'blocked';

    // Prepare the SQL statement
    $stm = $_db->prepare('UPDATE customer SET cus_status = ? WHERE cus_id = ?');
    $success = $stm->execute([$new_status, $customer_id]);

    if ($success) {
        temp('info', 'Customer status updated successfully');
        redirect('customer.php');
    } else {
        temp('error', 'Failed to update customer status');
    }
}
?>

<head>
    <link rel="stylesheet" href="/admin/css/cusStaff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<h1 style="text-align: center;">Customer List</h1><br> 
<!-- Search Form -->
<form>
    <p>Search Email:</p>
    <?= html_search('email') ?>
    <p>Filter Status:</p>
    <?= html_select('status', $user_status, 'All') ?>
    <button>Search
        <i class="fas fa-search"></i>
    </button>
</form>

<!-- Show record counts and paging info -->
<p>
    <?= $p->count ?> of <?= $p->item_count ?> record(s) |
    Page <?= $p->page ?> of <?= $p->page_count ?>
</p>

<!-- Table to display results -->
<table class="table">
    <tr>
        <?= table_headers($fields, $sort, $dir, "page=$page&email=$email&status=$status") ?>
        <th>Profile Photo</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <?php foreach ($arr as $customer): ?>
        <tr>
            <td><?= htmlspecialchars($customer->cus_Email) ?></td>
            <td><?= htmlspecialchars($customer->cus_name) ?></td>
            <td><?= $user_gender[$customer->cus_gender] ?? 'Unknown' ?></td>
            <td>
                <?php 
                $profile_pic = $customer->cus_profile_pic ?? 'default.png';
                $pic_path = "/admin/pic/" . htmlspecialchars($profile_pic);
                ?>
                <img src="<?= $pic_path ?>" class="popup" alt="Profile Photo">
            </td>
            <td class="<?= $customer->cus_status === 'blocked' ? 'status-blocked' : 'status-active' ?>">
                <?= $user_status[$customer->cus_status] ?? 'Unknown' ?>
            </td>
            <td>
                <form action="" method="post" style="display:inline;">
                    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer->cus_id) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($customer->cus_status) ?>">
                    <button type="submit" class="<?= $customer->cus_status === 'blocked' ? 'button-unblock' : 'button-block' ?>">
                        <?= $customer->cus_status === 'blocked' ? 'Unblock' : 'Block Customer' ?>
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach ?>
</table>

<!-- Pagination Links -->
<br>
<?= $p->html("sort=$sort&dir=$dir&email=$email&status=$status") ?>