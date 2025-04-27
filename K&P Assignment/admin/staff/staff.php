<?php
$_title = 'Staff Management';
require '../../_base.php';

safe_session_start();
$_SESSION['previous_url'] = $_SERVER['REQUEST_URI'];
auth('admin');

// Generate MBXXX ID
function generateMBId($db)
{
    $result = $db->query("SELECT MAX(CAST(SUBSTRING(user_id, 3) AS UNSIGNED)) AS max_id FROM user WHERE user_id LIKE 'MB%'");
    $row = $result->fetch(PDO::FETCH_OBJ);
    $next = $row && $row->max_id ? (int)$row->max_id + 1 : 1;
    return 'MB' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_staff'])) {
        if (
            empty(trim(req('user_name')))
            || empty(trim(req('user_Email')))
            || empty(trim(req('user_password')))
            || empty(req('user_gender'))
        ) {
            echo "<script>alert('All fields are required.'); window.location.href='staff.php';</script>";
            exit();
        }
        if (strlen(req('user_password')) < 8) {
            echo "<script>alert('Password must be at least 8 characters.'); window.location.href='staff.php';</script>";
            exit();
        }

        $userId = generateMBId($_db);
        $stm = $_db->prepare("INSERT INTO user (user_id, user_name, user_Email, user_password, user_gender, role)
             VALUES (?, ?, ?, ?, ?, 'staff')");
        $ok = $stm->execute([
            $userId,
            req('user_name'),
            req('user_Email'),
            password_hash(req('user_password'), PASSWORD_DEFAULT),
            req('user_gender')
        ]);
        temp($ok ? 'info' : 'error', $ok ? 'Staff added.' : 'Add failed.');
        redirect('staff.php');
    }

    if (isset($_POST['batch_delete']) && isset($_POST['selected_ids'])) {
        $ids = $_POST['selected_ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("DELETE FROM user WHERE user_id IN ($placeholders)");
        $ok = $stm->execute($ids);
        temp($ok ? 'info' : 'error', $ok ? 'Batch deletion succeeded.' : 'Batch deletion failed.');
        redirect('staff.php');
    }

    $userId = req('user_id');
    if (isset($_POST['update'])) {
        $stm = $_db->prepare("UPDATE user SET user_name = ?, user_Email = ?, user_gender = ? WHERE user_id = ?");
        $ok = $stm->execute([
            req('user_name'),
            req('user_Email'),
            req('user_gender'),
            $userId
        ]);
    } elseif (isset($_POST['ban'])) {
        $new = req('status') === 'Banned' ? 'Active' : 'Banned';
        $stm = $_db->prepare("UPDATE user SET status = ? WHERE user_id = ?");
        $ok = $stm->execute([$new, $userId]);
    } elseif (isset($_POST['delete'])) {
        $stm = $_db->prepare("DELETE FROM user WHERE user_id = ?");
        $ok = $stm->execute([$userId]);
    }

    if (isset($ok)) {
        temp($ok ? 'info' : 'error', $ok ? 'Update succeeded.' : 'Update failed.');
        redirect('staff.php');
    }
}

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
$p = new SimplePager($sql, $params, 10, $page);
$staffs = $p->result;

require '../headFooter/header.php';
?>

<link rel="stylesheet" href="/admin/staff/staff.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container mx-auto py-6">
  <h1 class="text-2xl font-bold mb-4">Staff Management</h1>

  <h2 class="mb-2">Add New Staff</h2>
  <form method="post" class="add-form mb-6" id="add-staff-form" novalidate>
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

  <form class="search-form mb-4" method="get" action="staff.php">
    <div class="form-group">
      <label for="search_email">Email</label>
      <?= html_search('email') ?>
    </div>
    <div class="form-group">
      <label for="filter_status">Status</label>
      <?= html_select('status', ['' => 'All', 'Active' => 'Active', 'Banned' => 'Banned'], $status) ?>
    </div>
    <button type="submit">Search</button>
  </form>

  <form method="post">
    <p class="record">Showing <?= $p->count ?> of <?= $p->item_count ?> | Page <?= $p->page ?> of <?= $p->page_count ?></p>
    <table class="table mb-4">
      <thead>
        <tr>
          <th><input type="checkbox" id="select-all"></th>
          <th>Email</th>
          <th>Username</th>
          <th>Gender</th>
          <th>Photo</th>
          <th>Updated</th>
          <th>Status</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <button type="submit" name="batch_delete" class="button-delete">Delete Selected</button>
      <tbody>
        <?php foreach ($staffs as $s): ?>
        <tr>
          <td><input type="checkbox" name="selected_ids[]" value="<?= $s->user_id ?>"></td>
          <td><?= htmlspecialchars($s->user_Email) ?></td>
          <td><?= htmlspecialchars($s->user_name) ?></td>
          <td><?= htmlspecialchars($s->user_gender) ?></td>
          <td><img class="popup" src="../../img/<?= htmlspecialchars($s->user_profile_pic ?: 'default.png') ?>" alt="Staff Photo" onerror="this.onerror=null; this.src='../../img/default.png';"></td>
          <td><?= htmlspecialchars($s->user_update_time) ?></td>
          <td class="status-<?= strtolower($s->status) ?>"><?= htmlspecialchars($s->status) ?></td>
          <td><?= htmlspecialchars($s->role) ?></td>
          <td class="actions">
            <form method="post">
              <input type="hidden" name="user_id" value="<?= $s->user_id ?>">
              <input type="hidden" name="status" value="<?= $s->status ?>">
              <input name="user_name" type="text" value="<?= htmlspecialchars($s->user_name) ?>" required>
              <input name="user_Email" type="email" value="<?= htmlspecialchars($s->user_Email) ?>" required>
              <select name="user_gender">
                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                  <option value="<?= $g ?>" <?= $s->user_gender === $g ? ' selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
              <button name="update" class="button-update" type="submit">Update</button>
              <button name="ban" class="button-block" type="submit">
                <?= $s->status === 'Banned' ? 'Unban' : 'Ban' ?>
              </button>
              <button name="delete" class="button-delete" type="submit" onclick="return confirm('Are you sure you want to delete this staff?');">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

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

<script>
document.getElementById('add-staff-form').addEventListener('submit', function(e) {
  let errors = [];

  const name = document.getElementById('user_name').value.trim();
  const email = document.getElementById('user_Email').value.trim();
  const password = document.getElementById('user_password').value.trim();
  const gender = document.getElementById('user_gender').value.trim();

  if (!name) errors.push('Name is required.');
  if (!email) {
    errors.push('Email is required.');
  } else if (!/^[\w-.]+@[\w-]+\.[a-z]{2,}$/i.test(email)) {
    errors.push('Email format is invalid.');
  }
  if (!password) {
    errors.push('Password is required.');
  } else if (password.length < 8) {
    errors.push('Password must be at least 8 characters.');
  }
  if (!gender) errors.push('Please select a gender.');

  if (errors.length > 0) {
    e.preventDefault();
    alert(errors.join('\n'));
  }
});

document.getElementById('select-all').addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
  checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>
