<?php
require '../_base.php';

// Redirect if already logged in
if (isset($_SESSION['customer_user']) || isset($_SESSION['admin_user'])) {
    redirect('index.php');
}

// Initialize variables
$email = $name = $password = $confirm_password = $gender = '';
$_err = [];

// Handle form submission
if (is_post()) {
    // Get and validate name
    $name = post('name');
    if (empty($name)) {
        $_err['name'] = 'Please enter your name';
    } elseif (strlen($name) > 255) {
        $_err['name'] = 'Name must be less than 255 characters';
    }

    // Get and validate email
    $email = post('email');
    if (empty($email)) {
        $_err['email'] = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_err['email'] = 'Please enter a valid email address';
    } elseif (!is_unique($email, 'customer', 'cus_Email')) {
        $_err['email'] = 'This email is already registered';
    }

    // Get and validate password
    $password = post('password');
    if (empty($password)) {
        $_err['password'] = 'Please enter a password';
    } elseif (!is_strong_password($password)) {
        $_err['password'] = 'Password must contain at least 8 characters with uppercase, lowercase, number and special character';
    }

    // Validate confirm password
    $confirm_password = post('confirm_password');
    if (empty($confirm_password)) {
        $_err['confirm_password'] = 'Please confirm your password';
    } elseif (!is_password_match($password, $confirm_password)) {
        $_err['confirm_password'] = 'Passwords do not match';
    }

    // Get gender (optional)
    $gender = post('gender', '');

    // If no errors, proceed with registration
    if (empty($_err)) {
        try {
            // Generate customer ID (UXXX format)
            $stm = $_db->query("SELECT MAX(CAST(SUBSTRING(cus_id, 2) AS UNSIGNED)) as max_id FROM customer WHERE cus_id LIKE 'U%'");
            $result = $stm->fetch();
            $next_id = ($result->max_id ?? 0) + 1;
            $cus_id = 'U' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

            // Hash the passwords
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $hashed_confirm_password = password_hash($confirm_password, PASSWORD_DEFAULT);

            // Generate activation token and expiry (24 hours from now)
            $activation_token = bin2hex(random_bytes(32));
            $activation_expiry = date('Y-m-d H:i:s', time() + 86400); // 24 hours

            // Insert new customer
            $stm = $_db->prepare("
                INSERT INTO customer (
                    cus_id, cus_name, cus_Email, cus_password, con_cus_password,
                    cus_gender, cus_status, role, activation_token, activation_expiry
                ) VALUES (?, ?, ?, ?, ?, ?, 'inactive', 'Member', ?, ?)
            ");
            $stm->execute([
                $cus_id,
                $name,
                $email,
                $hashed_password,
                $hashed_confirm_password,
                $gender,
                $activation_token,
                $activation_expiry
            ]);

            // Send activation email
            $activation_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=$activation_token";
            $subject = "Activate Your TARUMT Theatre Society Account";
            $message = "
                <html>
                <head>
                    <title>Account Activation</title>
                    <style>
                        .button {
                            background: #0066cc; 
                            color: white; 
                            padding: 10px 20px; 
                            text-decoration: none; 
                            border-radius: 5px;
                            display: inline-block;
                        }
                    </style>
                </head>
                <body>
                    <h2>Welcome to TARUMT Theatre Society, $name!</h2>
                    <p>Please click the button below to activate your account:</p>
                    <p><a href='$activation_link' class='button'>Activate Account</a></p>
                    <p>Or copy this link to your browser:<br>$activation_link</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </body>
                </html>
            ";

            // Email headers
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: TARUMT Theatre Society <noreply@tarumt.edu.my>\r\n";
            $headers .= "Reply-To: no-reply@tarumt.edu.my\r\n";

            if (mail($email, $subject, $message, $headers)) {
                temp('success', 'Registration successful! Please check your email (including spam folder) to activate your account.');
                redirect('login.php');
            } else {
                $_err['email'] = 'Failed to send activation email. Please try again later.';
            }
        } catch (PDOException $e) {
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
    <title>Register - K&P</title>
    <link type="text/css" rel="stylesheet" href="css/app.css">
    <link type="text/css" rel="stylesheet" href="css/register.css">
    <script src="js/app.js"></script>
</head>

<body>
    <?php include('../header.php'); ?>

    <div class="register-container">
        <div class="register-form">
            <h2>Member Registration</h2>

            <?php if (temp('success')): ?>
                <div class="alert success"><?= temp('success') ?></div>
            <?php endif; ?>

            <?php if (isset($_err['database'])): ?>
                <div class="alert error"><?= $_err['database'] ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?= encode($name) ?>" required>
                    <?php if (isset($_err['name'])): ?>
                        <span class="error"><?= $_err['name'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="mail"></ion-icon>
                    </span>
                    <label>Email</label>
                    <input type="email" name="email"
                        placeholder="example@tarumt.edu.my"
                        title="Please enter a valid TARUMT email"
                        value="<?= encode($email) ?>">
                    <div class="error"><?= $emailErr ?></div>
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <div class="gender-options">
                        <label><input type="radio" name="gender" value="1" <?= $gender == 1 ? 'checked' : '' ?>> Male</label>
                        <label><input type="radio" name="gender" value="2" <?= $gender == 2 ? 'checked' : '' ?>> Female</label>
                        <label><input type="radio" name="gender" value="0" <?= empty($gender) ? 'checked' : '' ?>> Prefer not to say</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required
                        onkeyup="checkPasswordStrength(this.value)">
                    <?php if (isset($_err['password'])): ?>
                        <span class="error"><?= $_err['password'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($_err['confirm_password'])): ?>
                        <span class="error"><?= $_err['confirm_password'] ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="register-btn">Register</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>

</html>