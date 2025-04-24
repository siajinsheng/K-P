<?php
include('../headFooter/header.php');

$errors = [];
$user = $_SESSION['user'];
$user_id = $user->user_id;

$query = "SELECT * FROM user WHERE user_id = :user_id";
$stmt = $_db->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$admin = $stmt->fetch();

if (!$admin) {
    die("Failed to fetch user details");
}

// Extract only digits after +60 or 60 for display
$phone_display = preg_replace('/^\+?60/', '', $admin->user_phone);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_contact = $_POST['admin_contact'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_profile_pic = $_FILES['admin_profile_pic'];

    // Validate
    if (empty($admin_name)) {
        $errors['admin_name'] = "Name is required.";
    } elseif (strlen($admin_name) > 30) {
        $errors['admin_name'] = "Name cannot exceed 30 characters.";
    }

    if (empty($admin_email)) {
        $errors['admin_email'] = "Email is required.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = "Invalid email format.";
    }

    if (empty($admin_contact)) {
        $errors['admin_contact'] = "Contact is required.";
    } elseif (!preg_match("/^\d{7,9}$/", $admin_contact)) {
        $errors['admin_contact'] = "Enter 7–9 digits only (excluding +60).";
    } else {
        // Normalize before storing
        $admin_contact = '+60' . ltrim($admin_contact, '0');
    }

    if (empty($current_password)) {
        $errors['current_password'] = "Current password is required.";
    } elseif (!password_verify($current_password, $admin->user_password)) {
        $errors['current_password'] = "Current password is incorrect.";
    }

    if (!empty($new_password)) {
        if (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z\d]).{8,}$/', $new_password)) {
            $errors['new_password'] = "Password must include upper, lower, number, symbol, and 8+ chars.";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    if (empty($errors)) {
        $file = $admin->user_profile_pic;

        if (!empty($admin_profile_pic['name'])) {
            $targetDirectory = "../pic/";
            $file = basename($admin_profile_pic['name']);
            $targetFilePath = $targetDirectory . $file;

            if (!move_uploaded_file($admin_profile_pic['tmp_name'], $targetFilePath)) {
                $errors['admin_profile_pic'] = "Upload failed.";
            }
        }

        if (empty($errors)) {
            $update_query = "UPDATE user SET user_name = ?, user_Email = ?, user_phone = ?, user_profile_pic = ?";
            $params = [$admin_name, $admin_email, $admin_contact, $file];

            if (!empty($new_password)) {
                $update_query .= ", user_password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $update_query .= " WHERE user_id = ?";
            $params[] = $user_id;

            $stmt = $_db->prepare($update_query);
            if ($stmt->execute($params)) {
                echo "<script>alert('Profile updated successfully!'); window.location.href = 'profile.php';</script>";
                exit;
            } else {
                $errors['database'] = "Update failed.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="appAdmin.css" />
  <title>Update Profile</title>
</head>
<body>
  <div class="profile-container">
    <h1>Update Profile</h1>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>Profile Picture</label>
        <input type="file" name="admin_profile_pic" accept=".jpg, .jpeg, .png" />
        <?php if ($admin->user_profile_pic) : ?>
          <img src="../pic/<?= htmlspecialchars($admin->user_profile_pic) ?>" alt="Profile Picture" />
        <?php endif; ?>
        <?php if (isset($errors['admin_profile_pic'])) echo "<div class='error'>{$errors['admin_profile_pic']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>Name</label>
        <input type="text" name="admin_name" value="<?= htmlspecialchars($admin->user_name) ?>" required />
        <?php if (isset($errors['admin_name'])) echo "<div class='error'>{$errors['admin_name']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="admin_email" value="<?= htmlspecialchars($admin->user_Email) ?>" required />
        <?php if (isset($errors['admin_email'])) echo "<div class='error'>{$errors['admin_email']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>Contact</label>
        <div style="display: flex; align-items: center;">
          <span style="padding: 10px; background-color: #eee; border: 1px solid #ccc; border-right: none; border-radius: 6px 0 0 6px;">+60</span>
          <input type="text" name="admin_contact"
                 value="<?= htmlspecialchars($phone_display) ?>"
                 style="flex: 1; border-radius: 0 6px 6px 0; border-left: none;"
                 placeholder="e.g., 123456789" required />
        </div>
        <?php if (isset($errors['admin_contact'])) echo "<div class='error'>{$errors['admin_contact']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>Current Password <span style="color:red">*</span></label>
        <input type="password" name="current_password" required />
        <?php if (isset($errors['current_password'])) echo "<div class='error'>{$errors['current_password']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" />
        <?php if (isset($errors['new_password'])) echo "<div class='error'>{$errors['new_password']}</div>"; ?>
      </div>

      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" />
        <?php if (isset($errors['confirm_password'])) echo "<div class='error'>{$errors['confirm_password']}</div>"; ?>
      </div>

      <div class="button-container">
        <button type="submit">Update</button>
        <a href="profile.php" class="cancel-button">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>

<?php include('../headFooter/footer.php'); ?>
