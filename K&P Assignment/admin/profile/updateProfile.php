<?php
include('../headFooter/header.php');

$errors = [];
$admin_id = $_SESSION['user_id'];

$query = "SELECT * FROM user WHERE user_id = :user_id";
$stmt = $_db->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$admin = $stmt->fetch(); 

if (!$admin) {
    die("Failed to fetch admin details");
}

// Update admin details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_name = $_POST['admin _name'];
    $admin_email = $_POST['admin_email'];
    $admin_contact = $_POST['admin_contact'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_profile_pic = $_FILES['admin_profile_pic'];

    if (empty($admin_name)) {
        $errors['admin_name'] = "Admin Name is required.";
    } elseif (strlen($admin_name) > 30) {
        $errors['admin_name'] = "Admin Name cannot be more than 30 characters.";
    }

    if (empty($admin_email)) {
        $errors['admin_email'] = "Admin Email is required.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = "Invalid email format.";
    } elseif (strlen($admin_email) > 50) {
        $errors['admin_email'] = "Admin Email cannot be more than 50 characters.";
    }

    if (empty($admin_contact)) {
        $errors['admin_contact'] = "Contact Number is required.";
    } elseif (!preg_match("/^\+60\d{1,2}-\d{7,8}$/", $admin_contact)) {
        $errors['admin_contact'] = "Contact Number must be in the format +6018-88888888.";
    }

    if (!empty($new_password)) {
        if (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z\d]).{8,}$/', $new_password)) {
            $errors['new_password'] = "Password must contain at least one uppercase letter, one lowercase letter, one symbol, one number, and at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    if (empty($errors)) {
        if (!empty($admin_profile_pic['name'])) {
            $targetDirectory = "pic/";
            $file = $admin_profile_pic['name'];
            $targetFilePath = $targetDirectory . $file;
            if (!move_uploaded_file($admin_profile_pic['tmp_name'], $targetFilePath)) {
                $errors['admin_profile_pic'] = "Failed to upload file.";
            }
        } else {
            $file = $admin->admin_profile_pic;
        }

        if (empty($errors)) {
            $file = 'pic/' . basename($file); // Ensure the file path includes 'pic/'
            $update_query = "UPDATE admin SET admin_name = ?, admin_email = ?, admin_contact = ?, admin_profile_pic = ?";
            if (!empty($new_password)) {
                $update_query .= ", admin_password = ?";
            }
            $update_query .= " WHERE admin_id = ?";
            $stmt = mysqli_prepare($connection, $update_query);
            if (!empty($new_password)) {
                mysqli_stmt_bind_param($stmt, "ssssss", $admin_name, $admin_email, $admin_contact, $file, $new_password, $admin_id);
            } else {
                mysqli_stmt_bind_param($stmt, "sssss", $admin_name, $admin_email, $admin_contact, $file, $admin_id);
            }
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                    alert('Admin profile updated successfully!');
                    window.location.href = 'profile.php';
                </script>";
                exit();
            } else {
                $errors['database'] = "Failed to update admin details: " . mysqli_error($connection);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link type="text/css" rel="stylesheet" href="../profile/appAdmin.css" />
    <title>Admin Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .form-group img {
            margin-top: 10px;
            max-width: 100px;
            border-radius: 50%;
        }

        .error {
            color: red;
            font-size: 0.9em;
        }

        .button-container {
            display: flex;
            justify-content: space-evenly;
        }

        button {
            padding: 10px 50px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #45a049;
        }

        .cancel-button {
            background-color: #f44336;
            padding: 10px 50px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .cancel-button:hover {
            background-color: #e53935;
            padding: 10px 50px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
    <script>
        function handleFormSubmit(event) {
            var errors = document.querySelectorAll('.error');
            if (errors.length > 0) {
                alert('Please fix the errors before submitting the form.');
                event.preventDefault();
            } else {
                if (confirm('Are you sure you want to update the profile?')) {
                    event.target.submit();
                } else {
                    event.preventDefault();
                }
            }
        }
    </script>
</head>

<body>
    <div class="profile-container">
        <h1>Update Profile</h1>
        <form method="POST" action="updateProfile.php" enctype="multipart/form-data" onsubmit="handleFormSubmit(event);">
            <div class="form-group">
                <label for="admin_profile_pic">Profile Picture</label>
                <input type="file" name="admin_profile_pic" accept=".jpeg, .jpg, .png">
                <?php if ($admin->admin_profile_pic) : ?>
                    <img src="../pic/<?php echo $admin->admin_profile_pic; ?>" alt="Profile Picture">
                <?php endif; ?>
                <?php if (isset($errors['admin_profile_pic'])) echo "<span class='error'>$errors[admin_profile_pic]</span>"; ?>
            </div>
            <div class="form-group">
                <label for="admin_name">Name</label>
                <input type="text" name="admin_name" value="<?php echo $admin->admin_name; ?>" required>
                <?php if (isset($errors['admin_name'])) echo "<span class='error'>$errors[admin_name]</span>"; ?>
            </div>
            <div class="form-group">
                <label for="admin_email">Email</label>
                <input type="email" name="admin_email" value="<?php echo $admin->admin_email; ?>" required>
                <?php if (isset($errors['admin_email'])) echo "<span class='error'>$errors[admin_email]</span>"; ?>
            </div>
            <div class="form-group">
                <label for="admin_contact">Contact</label>
                <input type="text" name="admin_contact" value="<?php echo $admin->admin_contact; ?>" required>
                <?php if (isset($errors['admin_contact'])) echo "<span class='error'>$errors[admin_contact]</span>"; ?>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password">
                <?php if (isset($errors['new_password'])) echo "<span class='error'>$errors[new_password]</span>"; ?>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password">
                <?php if (isset($errors['confirm_password'])) echo "<span class='error'>$errors[confirm_password]</span>"; ?>
            </div>
            <div class="button-container">
                <button type="submit">Update</button>
                <a href="profile.php" class="button cancel-button">Cancel</a>
            </div>
        </form>
    </div>
</body>

</html>
<?php
include('footer.php');
?>