<?php
// Staff Management Page

$_title = 'Staff Management';
require '../../_base.php';

safe_session_start();
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
auth('admin', 'staff');

// Handle POST actions: add, update, lock/unlock, ban/unban
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new staff
    if (isset($_POST['add_staff'])) {
        $stm = $_db->prepare(
            "INSERT INTO user
             (user_id, user_name, user_Email, user_password, user_gender, role)
             VALUES (UUID(), ?, ?, ?, ?, 'staff')"
        );
        $ok = $stm->execute([
            req('user_name'),
            req('user_Email'),
            password_hash(req('user_password'), PASSWORD_DEFAULT),
            req('user_gender')
        ]);
        temp($ok ? 'info' : 'error', $ok ? 'Staff added.' : 'Add failed.');
        redirect('staff.php');
    }

    // Update, lock, ban existing
    $userId = req('user_id');
    if (isset($_POST['update'])) {
        $stm = $_db->prepare(
            "UPDATE user
             SET user_name = ?, user_Email = ?, user_gender = ?
             WHERE user_id = ?"
        );
        $ok = $stm->execute([
            req('user_name'),
            req('user_Email'),
            req('user_gender'),
            $userId
        ]);
    } elseif (isset($_POST['lock'])) {
        $new = req('status') === 'Inactive' ? 'Active' : 'Inactive';
        $stm = $_db->prepare("UPDATE user SET status = ? WHERE user_id = ?");
        $ok = $stm->execute([$new, $userId]);
    } elseif (isset($_POST['ban'])) {
        $new = req('status') === 'Banned' ? 'Active' : 'Banned';
        $stm = $_db->prepare("UPDATE user SET status = ? WHERE user_id = ?");
        $ok = $stm->execute([$new, $userId]);
    }

    if (isset($ok)) {
        temp($ok ? 'info' : 'error', $ok ? 'Update succeeded.' : 'Update failed.');
        redirect('staff.php');
    }
}

// Fetch and paginate
$page   = req('page', 1);
$email  = req('email');
$status = req('status') ?: null;

$fields = ['user_Email' => 'Email', 'user_name' => 'Name', 'user_gender' => 'Gender'];
$sort   = in_array(req('sort'), array_keys($fields)) ? req('sort') : 'user_Email';
$dir    = req('dir') === 'desc' ? 'desc' : 'asc';

require_once '../../lib/SimplePager.php';
$sql = "SELECT user_id, user_Email, user_name, user_gender,
               user_profile_pic, user_update_time, status, role
          FROM user
         WHERE user_Email LIKE ?
           AND (status = ? OR ?)
           AND role = 'staff'
         ORDER BY $sort $dir";
$params = ["%$email%", $status, $status === null];
$p      = new SimplePager($sql, $params, 10, $page);
$staffs = $p->result;

// Render
require '../headFooter/header.php';
?>
<link rel="stylesheet" href="/admin/staff/staff.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container mx-auto py-6">
  <h1 class="text-2xl font-bold mb-4">Staff Management</h1>
  
  <!-- Add Staff Form -->
  <h2 class="mb-2">Add New Staff</h2>
  <form method="post" class="add-form mb-6" novalidate>
    <div class="form-group">
      <label for="user_name">Name</label>
      <input id="user_name" name="user_name" type="text" required placeholder="Full name">
    </div>
    <div class="form-group">
      <label for="user_Email">Email</label>
      <input id="user_Email" name="user_Email" type="email" required placeholder="name@domain.com">
    </div>
    <div class="form-group">
      <label for="user_password">Password</label>
      <input id="user_password" name="user_password" type="password" minlength="8" required placeholder="Min. 8 chars">
    </div>
    <div class="form-group">
      <label for="user_gender">Gender</label>
      <select id="user_gender" name="user_gender" required>
        <option value="">Select gender</option>
        <option>Male</option>
        <option>Female</option>
        <option>Other</option>
      </select>
    </div>
    <button type="submit" name="add_staff" class="button-update">Add Staff</button>
  </form>

  <!-- Search & Filter -->
  <form class="search-form mb-4" method="get" action="staff.php">
    <div class="form-group">
      <label for="search_email">Email</label>
      <?= html_search('email') ?>
    </div>
    <div class="form-group">
      <label for="filter_status">Status</label>
      <?= html_select('status', [''=>'All','Active'=>'Active','Inactive'=>'Inactive','Banned'=>'Banned'], $status) ?>
    </div>
    <button type="submit">Search</button>
  </form>

  <p class="record">Showing <?= $p->count ?> of <?= $p->item_count ?> | Page <?= $p->page ?> of <?= $p->page_count ?></p>

  <!-- Staff Table -->
  <table class="table mb-4">
    <thead>
      <tr>
        <?= table_headers($fields, $sort, $dir, "page={$page}&email={$email}&status={$status}") ?>
        <th>Photo</th><th>Updated</th><th>Status</th><th>Role</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($staffs as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s->user_Email) ?></td>
        <td><?= htmlspecialchars($s->user_name) ?></td>
        <td><?= htmlspecialchars($s->user_gender) ?></td>
        <td><img class="popup" src="/admin/pic/<?= $s->user_profile_pic ?: 'default.png' ?>"></td>
        <td><?= htmlspecialchars($s->user_update_time) ?></td>
        <td class="status-<?= strtolower($s->status) ?>"><?= htmlspecialchars($s->status) ?></td>
        <td><?= htmlspecialchars($s->role) ?></td>
        <td class="actions">
          <form method="post">
            <input type="hidden" name="user_id" value="<?= $s->user_id ?>">
            <input type="hidden" name="status" value="<?= $s->status ?>">
            <input name="user_name" type="text" value="<?= htmlspecialchars($s->user_name) ?>" required>
            <input name="user_Email" type="email" value="<?= htmlspecialchars($s->user_Email) ?>" required>
            <select name="user_gender"><?php foreach (['Male','Female','Other'] as $g): ?><option value="<?= $g ?>"<?= $s->user_gender === $g ? ' selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select>
            <button name="update" class="button-update">Update</button>
            <button name="lock" class="button-lock">Lock/Unlock</button>
            <button name="ban" class="button-block">Ban/Unban</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Pagination Controls -->
  <?php $base = "staff.php?sort={$sort}&dir={$dir}&email=" . urlencode($email) . "&status=" . urlencode($status); ?>
  <nav class="pagination-nav">
    <a href="<?= $base ?>&page=1">First</a>
    <?php if ($p->page > 1): ?>
      <a href="<?= $base ?>&page=<?= $p->page - 1 ?>">Previous</a>
    <?php else: ?>
      <span class="disabled">Previous</span>
    <?php endif; ?>
    <?php if ($p->page < $p->page_count): ?>
      <a href="<?= $base ?>&page=<?= $p->page + 1 ?>">Next</a>
    <?php else: ?>
      <span class="disabled">Next</span>
    <?php endif; ?>
    <a href="<?= $base ?>&page=<?= $p->page_count ?>">Last</a>
  </nav>
</div>