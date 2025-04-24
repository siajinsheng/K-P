<?php
$_title = 'Customer Management';
require '../../_base.php';

// Admin role authentication
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
auth('admin', 'staff');
require '../headFooter/header.php';

$_title = 'Customer List';

// Gender and Status Mapping
$user_gender = ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'];
$user_status = ['Active' => 'Active', 'Inactive' => 'Inactive', 'Banned' => 'Banned'];

// Sorting Configuration
$fields = ['user_Email' => 'Email', 'user_name' => 'Customer Name', 'user_gender' => 'Gender'];
$sort = req('sort');
key_exists($sort, $fields) || $sort = 'user_Email';
$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

// Pagination & Search Filters
$page = req('page', 1);
$email  = req('email');
$status = req('status');

// Fetch Members Only
require_once '../../lib/SimplePager.php';
$sql = "SELECT user_id, user_Email, user_name, user_gender, user_profile_pic, status 
        FROM user 
        WHERE user_Email LIKE ? 
          AND (status = ? OR ?) 
          AND role = 'member' 
        ORDER BY $sort $dir";
$params = ["%$email%", $status, $status == null];
$p = new SimplePager($sql, $params, 10, $page);
$arr = $p->result;

// Handle Updates & Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = req('user_id');
    if (isset($_POST['update'])) {
        // Update User Details
        $name = req('user_name');
        $email = req('user_Email');
        $gender = req('user_gender');
        
        $stm = $_db->prepare("UPDATE user SET user_name = ?, user_Email = ?, user_gender = ? WHERE user_id = ?");
        $success = $stm->execute([$name, $email, $gender, $user_id]);
    } elseif (isset($_POST['ban'])) {
        // Ban/Unban User
        $new_status = req('status') === 'Banned' ? 'Active' : 'Banned';
        $stm = $_db->prepare("UPDATE user SET status = ? WHERE user_id = ?");
        $success = $stm->execute([$new_status, $user_id]);
    } elseif (isset($_POST['lock'])) {
        // Lock/Unlock Account
        $new_status = req('status') === 'Inactive' ? 'Active' : 'Inactive';
        $stm = $_db->prepare("UPDATE user SET status = ? WHERE user_id = ?");
        $success = $stm->execute([$new_status, $user_id]);
    }
    if ($success) {
        temp('info', 'Customer updated successfully');
        redirect('customers.php');
    } else {
        temp('error', 'Update failed');
    }
}
?>

<head>
    <link rel="stylesheet" href="/admin/customer/cusStaff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<h1 style="text-align: center;">Customer List</h1>
<br>
<form>
    <p>Search Email:</p>
    <?= html_search('email') ?>
    <p>Filter Status:</p>
    <?= html_select('status', $user_status, 'All') ?>
    <button>Search <i class="fas fa-search"></i></button>
</form>

<p class="record"><?= $p->count ?> of <?= $p->item_count ?> record(s) | Page <?= $p->page ?> of <?= $p->page_count ?></p>

<table class="table">
    <tr>
        <?= table_headers($fields, $sort, $dir, "page=$page&email=$email&status=$status") ?>
        <th>Profile Photo</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($arr as $customer): ?>
        <tr>
            <td><?= htmlspecialchars($customer->user_Email) ?></td>
            <td><?= htmlspecialchars($customer->user_name) ?></td>
            <td><?= isset($user_gender[$customer->user_gender]) ? $user_gender[$customer->user_gender] : 'Not Specified' ?></td>
            <td>
                <img src="/admin/pic/<?= !empty($customer->user_profile_pic) ? htmlspecialchars($customer->user_profile_pic) : 'default.png' ?>" 
                     class="popup" alt="Profile Photo" 
                     onerror="this.onerror=null;this.src='/admin/pic/default.png';">
            </td>
            <td class="<?= $customer->status === 'Banned' ? 'status-blocked' : 'status-active' ?>">
                <?= isset($user_status[$customer->status]) ? $user_status[$customer->status] : 'Unknown' ?>
            </td>
            <td>
                <td class="actions">
                <form action="" method="post" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($customer->user_id) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($customer->status) ?>">
                    <input type="text" name="user_name" value="<?= htmlspecialchars($customer->user_name) ?>" required>
                    <input type="email" name="user_Email" value="<?= htmlspecialchars($customer->user_Email) ?>" required>
                    <select name="user_gender">
                        <?php foreach ($user_gender as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($customer->user_gender === $key) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="action-wrapper">
                    <button type="submit" name="update" class="button-update">Update</button>
                    <button type="submit" name="lock" class="<?= $customer->status === 'Inactive' ? 'button-unlock' : 'button-lock' ?>">
                        <?= $customer->status === 'Inactive' ? 'Unlock' : 'Lock' ?>
                    </button>
                    <button type="submit" name="ban" class="<?= $customer->status === 'Banned' ? 'button-unblock' : 'button-block' ?>">
                        <?= $customer->status === 'Banned' ? 'Unban' : 'Ban' ?>
                    </button>
                        </div>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<br>
<?= $p->html("sort=$sort&dir=$dir&email=$email&status=$status") ?>