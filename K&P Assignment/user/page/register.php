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
    } elseif (!is_unique($email, 'user', 'user_Email')) {
        $_err['email'] = 'Email already registered';
    }

    // Phone validation with new function
    if (empty($phone)) {
        $_err['phone'] = 'Phone number is required';
    } else {
        $formattedPhone = validate_malaysian_phone($phone);
        if ($formattedPhone === false) {
            $_err['phone'] = 'Invalid phone number format. Must be 9-10 digits starting with 1';
        } elseif (!is_unique($formattedPhone, 'user', 'user_phone')) {
            $_err['phone'] = 'Phone number already registered';
        } else {
            // Update phone with formatted version (with country code)
            $phone = $formattedPhone;
        }
    }

    if (empty($gender)) {
        $_err['gender'] = 'Gender is required';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $_err['gender'] = 'Invalid gender selection';
    }

    // Enhanced password validation
    if (empty($password)) {
        $_err['password'] = 'Password is required';
    } else {
        $passwordValidation = validate_password($password);
        if ($passwordValidation !== true) {
            $_err['password'] = $passwordValidation;
        }
    }

    if ($password !== $confirm) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if ($profilePic && !str_starts_with($profilePic->type, 'img/')) {
        $_err['profile_pic'] = 'Only image files are allowed';
    } elseif ($profilePic && $profilePic->size > 2 * 1024 * 1024) {
        $_err['profile_pic'] = 'Image must be less than 2MB';
    }

    // Process if no errors
    if (empty($_err)) {
        try {
            // Generate user ID
            $user_id = 'MB' . sprintf('%03d', rand(100, 999));
            
            // Check if user ID already exists, generate a new one if it does
            $stm = $_db->prepare("SELECT COUNT(*) FROM user WHERE user_id = ?");
            $stm->execute([$user_id]);
            while ($stm->fetchColumn() > 0) {
                $user_id = 'MB' . sprintf('%03d', rand(100, 999));
                $stm->execute([$user_id]);
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Save profile picture
            $profilePicPath = null;
            if ($profilePic) {
                $profilePicPath = save_photo_user($profilePic, '../../img', 300, 300);
            } else {
                $profilePicPath = '../../img/default.png'; // Default profile image
            }
            
            // Begin transaction
            $_db->beginTransaction();
            
            // Insert into database - Set status to 'Pending' until email verification
            $stm = $_db->prepare("
                INSERT INTO user (
                    user_id, user_name, user_Email, user_password, 
                    user_gender, user_phone, user_profile_pic, status, 
                    role
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, ?, 'Pending', 
                    'member'
                )
            ");
            
            $stm->execute([
                $user_id, $name, $email, $hashedPassword,
                $gender, $phone, $profilePicPath
            ]);
            
            // Create a verification token
            $token = create_token($user_id, 'email_verification', 24);
            
            // Commit the transaction
            $_db->commit();
            
            // Send verification email
            $email_sent = send_verification_email($email, $name, $token);
            
            // For debugging
            error_log("Registration successful for user $name ($email). Email sent: " . ($email_sent ? "Yes" : "No"));
            error_log("Verification token: $token");
            
            // Set success message and redirect to login with appropriate message
            if ($email_sent) {
                temp('success', 'Registration successful! A verification email has been sent to your email address. Please verify your email to activate your account.');
            } else {
                temp('info', 'Registration successful! However, we could not send a verification email. Please contact support.');
            }
            redirect('login.php');
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            error_log("Registration error: " . $e->getMessage());
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <img id="profilePreview" class="profile-pic-preview" src="../../img/default.png" alt="Profile Preview">
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
                    <small class="form-text">A verification email will be sent to this address.</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <?php html_phone_input('phone', 'class="form-control" required'); ?>
                    <?= err('phone') ?>
                    <small class="text-muted">Enter 9-10 digits starting with 1 (e.g., 182259156). Country code 60 will be added automatically.</small>
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
                    <?php html_password_input('password', 'class="form-control" required'); ?>
                    <?= err('password') ?>
                    <small class="text-muted">Must contain at least 8 characters with one uppercase letter, one lowercase letter, one number, and one special character (e.g., P@ssw0rd)</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control" required>
                    <?= err('confirm') ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Register</button>
                </div>
                
                <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
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