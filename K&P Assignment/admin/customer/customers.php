<?php
$_title = 'Customer Management';
require '../../_base.php';

safe_session_start();
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
auth('admin', 'staff');
require '../headFooter/header.php';

$user_gender = ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'];
$user_status = ['Active' => 'Active', 'Banned' => 'Banned'];

$fields = ['user_Email' => 'Email', 'user_name' => 'Customer Name', 'user_gender' => 'Gender'];
$sort = req('sort'); key_exists($sort, $fields) || $sort = 'user_Email';
$dir  = req('dir'); in_array($dir, ['asc', 'desc']) || $dir = 'asc';

$page   = req('page', 1);
$email  = req('email');
$status = req('status') ?: null;

require_once '../../lib/SimplePager.php';
$sql = "SELECT user_id, user_Email, user_name, user_gender, user_profile_pic, status
        FROM user
        WHERE user_Email LIKE ?
          AND (status = ? OR ?)
          AND role = 'member'
        ORDER BY $sort $dir";
$params = ["%$email%", $status, $status === null];
$p = new SimplePager($sql, $params, 10, $page);
$arr = $p->result;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = req('user_id');
    if (isset($_POST['update'])) {
        $stm = $_db->prepare("UPDATE user SET user_name=?, user_Email=?, user_gender=? WHERE user_id=?");
        $success = $stm->execute([
            req('user_name'),
            req('user_Email'),
            req('user_gender'),
            $user_id
        ]);
    } elseif (isset($_POST['ban'])) {
        $new = req('status') === 'Banned' ? 'Active' : 'Banned';
        $stm = $_db->prepare("UPDATE user SET status=? WHERE user_id=?");
        $success = $stm->execute([$new, $user_id]);
    }
    temp($success ? 'info' : 'error', $success ? 'Customer updated.' : 'Update failed.');
    redirect('customers.php');
}
?>

<link rel="stylesheet" href="/admin/customer/cusStaff.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container mx-auto py-6">
  <h1 class="text-2xl font-bold mb-4">Customer List</h1>

  <form class="search-form mb-4" method="get">
    <div class="form-group">
      <label for="email">Search Email:</label>
      <?= html_search('email') ?>
    </div>
    <div class="form-group">
      <label for="status">Filter Status:</label>
      <?= html_select('status', $user_status, $status) ?>
    </div>
    <button type="submit"><i class="fas fa-search"></i> Search</button>
  </form>

  <p class="record">Showing <?= $p->count ?> of <?= $p->item_count ?> | Page <?= $p->page ?> of <?= $p->page_count ?></p>

  <table class="table">
    <thead>
      <tr>
      <th>Email</th>
        <th>Username</th>
        <th>Genders</th>
        <th>Photo</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($arr as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c->user_Email) ?></td>
        <td><?= htmlspecialchars($c->user_name) ?></td>
        <td><?= htmlspecialchars($c->user_gender) ?></td>
        <td>
          <img src="../../img/<?= $c->user_profile_pic ?: 'default.png' ?>"
               onerror="this.src='../../img/default.png';"
               class="popup" />
        </td>
        <td class="status-<?= strtolower($c->status) ?>">
          <?= $user_status[$c->status] ?? 'Unknown' ?>
        </td>
        <td class="actions">
          <form method="post">
            <input type="hidden" name="user_id" value="<?= $c->user_id ?>">
            <input type="hidden" name="status"  value="<?= $c->status ?>">
            <input type="text" name="user_name" value="<?= htmlspecialchars($c->user_name) ?>" required>
            <input type="email" name="user_Email" value="<?= htmlspecialchars($c->user_Email) ?>" required>
            <select name="user_gender">
              <?php foreach ($user_gender as $g): ?>
                <option value="<?= $g ?>" <?= $c->user_gender === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
            <button name="update" class="button-update">Update</button>
            <button name="ban" class="<?= $c->status==='Banned' ? 'button-unblock' : 'button-block' ?>">
              <?= $c->status==='Banned' ? 'Unban' : 'Ban' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav class="pagination-nav" aria-label="Customer Pages">
    <?php
      $base = "customers.php?sort=$sort&dir=$dir&email=" . urlencode($email) . "&status=" . urlencode($status);
    ?>
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
