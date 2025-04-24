<?php
require_once '../../_base.php';
auth('admin','staff');
$user = $_SESSION['user'];
include '../headFooter/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Profile</title>
  <link rel="stylesheet" href="appAdmin.css" />
</head>
<body>
  <div class="profile-container">
    <h1>My Profile</h1>
    <table>
      <tr>
        <th>Profile Picture</th>
        <td>
          <img
            class="profile-photo"
            src="../pic/<?= htmlspecialchars($user->user_profile_pic ?: 'default.png') ?>"
            alt="Profile Picture"
            onerror="this.onerror=null; this.src='../pic/default.png';"
          />
        </td>
      </tr>
      <tr><th>ID</th><td><?= htmlspecialchars($user->user_id) ?></td></tr>
      <tr><th>Name</th><td><?= htmlspecialchars($user->user_name) ?></td></tr>
      <tr><th>Email</th><td><?= htmlspecialchars($user->user_Email) ?></td></tr>
      <tr><th>Phone</th><td><?= htmlspecialchars($user->user_phone) ?></td></tr>
      <tr><th>Gender</th><td><?= htmlspecialchars($user->user_gender) ?></td></tr>
      <tr>
        <th>Password</th>
        <td>
          ••••••••
          <a class="change-pass" href="updateProfile.php">Change Password</a>
        </td>
      </tr>
      <tr><th>Status</th><td><?= htmlspecialchars($user->status) ?></td></tr>
      <tr><th>Role</th><td><?= htmlspecialchars($user->role) ?></td></tr>
      <tr><th>Last Updated</th><td><?= htmlspecialchars($user->user_update_time) ?></td></tr>
    </table>

    <div style="text-align:center;">
      <a href="updateProfile.php" class="button">Update Profile</a>
    </div>
  </div>

  <?php include '../headFooter/footer.php'; ?>
</body>
</html>
