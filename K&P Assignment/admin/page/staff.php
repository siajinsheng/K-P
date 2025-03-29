<?php
$_title = 'Staff';
require '../../_base.php';
// -------------------------------
// Manager role authorization
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
auth(0);
require 'header.php';

// ----------------------------------------------------------------------------
$user_gender = [
    'M' => 'Male',
    'F' => 'Female'
];

$staff_status = [
    'blocked' => 'Blocked',
    'active'  => 'Active'
];

// (1) Sorting
$fields = [
    'admin_email'   => 'Email',
    'admin_name'    => 'Staff Name',
    'admin_status'  => 'Status'
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'admin_email';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// (2) Paging
$page = req('page', 1);

// (3) Search Parameters
$email = req('email');
$status = req('status');

// (4) Prepare SQL Query with search, sort, and paging
require_once '../lib/SimplePager.php';

$sql = 'SELECT * FROM admin WHERE admin_email LIKE ? AND (admin_status = ? OR ?) 
        ORDER BY ' . $sort . ' ' . $dir;

$params = ["%$email%", $status, $status == null];

// Using SimplePager for pagination
$p = new SimplePager($sql, $params, 10, $page);
$arr = $p->result;

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = req('email');
    $current_status = req('status');

    // Determine new status
    $new_status = ($current_status === 'blocked') ? 'active' : 'blocked';

    // Prepare the SQL statement
    $stm = $_db->prepare('UPDATE admin SET admin_status = ? WHERE admin_email = ?');
    $success = $stm->execute([$new_status, $email]);

    temp('info', 'Staff Record Updated');
    redirect('staff.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/admin/css/cusStaff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/User/JS/basic.js"></script>
</head>
<body>
    <h1 style="text-align: center;">Staff Management</h1><br> 

    <!-- Search Form -->
    <form>
        <p>Search Email:</p>
        <?= html_search('email') ?>
        <p>Filter Status:</p>
        <?= html_select('status', $staff_status, 'All') ?>
        <button>Search
            <i class="fas fa-search"></i>
        </button>
        <a href="staff_add.php" class="btn-add-staff">
            <i class="fas fa-plus"></i>Add New Staff
        </a>
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
            <th>Profile Picture</th>
            <th>Action</th>
        </tr>

        <?php foreach ($arr as $staff): ?>
            <tr>
                <td><?= htmlspecialchars($staff->admin_email) ?></td>
                <td><?= htmlspecialchars($staff->admin_name) ?></td>
                <td class="<?= $staff->admin_status === 'blocked' ? 'status-blocked' : 'status-active' ?>">
                    <?= $staff_status[$staff->admin_status] ?? 'Unknown' ?>
                </td>
                <td>
                    <?php 
                    $profile_pic = !empty($staff->admin_profile_pic) 
                        ? htmlspecialchars($staff->admin_profile_pic) 
                        : 'default.png'; 
                    ?>
                    <img src="/admin/pic/<?= $profile_pic ?>" 
                         alt="Profile Picture" 
                         class="staff-profile-pic">
                </td>
                <td>
                    <form action="" method="post" style="display:inline;">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($staff->admin_email) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($staff->admin_status) ?>">
                        <button type="submit" class="<?= $staff->admin_status === 'blocked' ? 'button-unblock' : 'button-block' ?>">
                            <?= $staff->admin_status === 'blocked' ? 'Unblock' : 'Block Staff' ?>
                        </button>
                    </form>
                    <a href="staff_edit.php?id=<?= htmlspecialchars($staff->admin_id) ?>" class="button-edit">
                        Edit
                    </a>
                </td>
            </tr>
        <?php endforeach ?>
    </table>

    <!-- Pagination Links -->
    <br>
    <?= $p->html("sort=$sort&dir=$dir&email=$email&status=$status") ?>
</body>
</html>