<?php
$_title = 'K&P - Register';
require '../../_base.php';

if (is_post()) {
    $name = req('name');
    $email = req('email');
    $phone = req('phone');
    $gender = req('gender');
    $password = req('password');
    $confirm = req('confirm');
    $profilePic = get_file('profile_pic');

    // Validation
    if (empty($name)) {
        $_err['name'] = 'Full name is required';
    } elseif (strlen($name) > 255) {
        $_err['name'] = 'Name must be less than 255 characters';
    }

    if (empty($email)) {
        $_err['email'] = 'Email is required';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Invalid email format';
    } elseif (!is_email_unique($email)) {
        $_err['email'] = 'Email already registered';
    }

    if (empty($phone)) {
        $_err['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $_err['phone'] = 'Invalid phone number';
    } elseif (!is_phone_unique($phone)) {
        $_err['phone'] = 'Phone number already registered';
    }

    if (empty($gender)) {
        $_err['gender'] = 'Gender is required';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $_err['gender'] = 'Invalid gender selection';
    }

    if (empty($password)) {
        $_err['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $_err['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $_err['password'] = 'Password must contain uppercase, lowercase and numbers';
    }

    if ($password !== $confirm) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if ($profilePic && !str_starts_with($profilePic->type, 'image/')) {
        $_err['profile_pic'] = 'Only image files are allowed';
    } elseif ($profilePic && $profilePic->size > 2 * 1024 * 1024) {
        $_err['profile_pic'] = 'Image must be less than 2MB';
    }

    // Process if no errors
    if (empty($_err)) {
        // Generate customer ID
        $cus_id = 'CUS_' . uniqid();
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Save profile picture
        $profilePicPath = null;
        if ($profilePic) {
            $profilePicPath = save_photo_user($profilePic, '../../uploads/profiles');
        }
        
        // Generate activation token
        $activationToken = generate_activation_token();
        $activationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert into database
        $stm = $_db->prepare("
            INSERT INTO customer (
                cus_id, cus_name, cus_Email, cus_password, con_cus_password, 
                cus_gender, cus_phone, cus_profile_pic, cus_status, 
                role, activation_token, activation_expiry
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, 'Inactive', 
                'customer', ?, ?
            )
        ");
        
        $success = $stm->execute([
            $cus_id, $name, $email, $hashedPassword, $hashedPassword,
            $gender, $phone, $profilePicPath,
            $activationToken, $activationExpiry
        ]);
        
        if ($success) {
            // Send activation email
            if (send_activation_email($email, $name, $activationToken)) {
                temp('success', 'Registration successful! Please check your email to activate your account.');
                redirect('login.php');
            } else {
                // If email fails, delete the user record
                $_db->prepare("DELETE FROM customer WHERE cus_id = ?")->execute([$cus_id]);
                $_err['email'] = 'Failed to send activation email. Please try again later.';
            }
        } else {
            $_err['database'] = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="../css/register.css">
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="container">
        <div class="registration-form">
            <h1>Create Your K&P Account</h1>
            
            <?php if (isset($_err['database'])): ?>
                <div class="alert alert-danger"><?= $_err['database'] ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_pic">Profile Picture</label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
                    <?= err('profile_pic') ?>
                    <img id="profilePreview" class="profile-pic-preview" src="/images/default-profile.jpg" alt="Profile Preview">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
                    <?= err('name') ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
                    <?= err('email') ?>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone ?? '') ?>" required>
                    <?= err('phone') ?>
                </div>
                
                <div class="form-group">
                    <label>Gender</label>
                    <div class="gender-options">
                        <div class="gender-option">
                            <input type="radio" id="gender-male" name="gender" value="Male" <?= ($gender ?? '') === 'Male' ? 'checked' : '' ?> required>
                            <label for="gender-male">Male</label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" id="gender-female" name="gender" value="Female" <?= ($gender ?? '') === 'Female' ? 'checked' : '' ?>>
                            <label for="gender-female">Female</label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" id="gender-other" name="gender" value="Other" <?= ($gender ?? '') === 'Other' ? 'checked' : '' ?>>
                            <label for="gender-other">Other</label>
                        </div>
                    </div>
                    <?= err('gender') ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <?= err('password') ?>
                    <small class="text-muted">Must be at least 8 characters with uppercase, lowercase and numbers</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control" required>
                    <?= err('confirm') ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Register</button>
                </div>
                
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </form>
        </div>
    </main>

    <?php include '../footer.php'; ?>

    <script>
        // Profile picture preview
        document.getElementById('profile_pic').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>