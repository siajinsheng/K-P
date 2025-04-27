<?php
$_title = 'K&P - Login';
require '../../_base.php';

// Start safe session
safe_session_start();

// Generate simple math captcha (if not already created)
if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
    $_SESSION['captcha_answer'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
}

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    redirect('../../index.php');
}

// Handle resend verification email
if (isset($_POST['resend_verification'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        temp('error', 'Please provide your email address.');
        redirect('login.php');
    }
    
    try {
        $stm = $_db->prepare("SELECT user_id, user_name, user_Email, status FROM user WHERE user_Email = ?");
        $stm->execute([$email]);
        $user = $stm->fetch();
        
        if ($user && $user->status === 'Pending') {
            $token = create_token($user->user_id, 'email_verification', 24);
            send_verification_email($email, $user->user_name, $token);
            temp('success', 'Verification email sent to ' . htmlspecialchars($email));
        } else if ($user && $user->status === 'Active') {
            temp('info', 'Your account is already active. You can login.');
        } else {
            temp('error', 'No pending account found with this email.');
        }
        
        redirect('login.php');
    } catch (Exception $e) {
        error_log("Resend verification error: " . $e->getMessage());
        temp('error', 'Error processing your request.');
        redirect('login.php');
    }
}

// Handle login
if (is_post() && isset($_POST['login'])) {
    $email = req('email');
    $password = req('password');
    $remember = req('remember') ? true : false;
    $user_captcha = intval($_POST['captcha_input'] ?? 0);

    // Basic validation
    if (empty($email)) {
        $_err['email'] = 'Email is required';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $_err['password'] = 'Password is required';
    }

    // Captcha validation
    if (!isset($_SESSION['captcha_answer']) || $user_captcha !== $_SESSION['captcha_answer']) {
        $_err['captcha'] = 'Incorrect CAPTCHA answer. Please try again.';
    }

    // If no errors, attempt login
    if (empty($_err)) {
        try {
            $stm = $_db->prepare("SELECT * FROM user WHERE user_Email = ?");
            $stm->execute([$email]);
            $user = $stm->fetch();

            if ($user && password_verify($password, $user->user_password)) {
                if ($user->status === 'Pending') {
                    $_err['login'] = 'Your email is not verified. Please verify first.';
                    $show_verification_form = true;
                    $pending_email = $email;
                } else if ($user->status !== 'Active') {
                    $_err['login'] = 'Your account is inactive. Contact support.';
                } else {
                    safe_session_start();
                    $_SESSION['user'] = $user;

                    if ($remember) {
                        $token_base = $user->user_id . $user->user_password . 'K&P_SECRET_KEY';
                        $remember_token = hash('sha256', $token_base);
                        setcookie('user_id', $user->user_id, time() + (30 * 24 * 60 * 60), '/');
                        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/');
                    }

                    $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                    $stm->execute([$user->user_id]);

                    if ($user->role === 'admin' || $user->role === 'staff') {
                        redirect('../../admin/home/home.php');
                    } else {
                        redirect('../../index.php');
                    }
                }
            } else {
                $_err['login'] = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $_err['database'] = 'Login failed. Please try again.';
        }
    }

    // Refresh captcha after every login attempt
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
    $_SESSION['captcha_answer'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
}

// Handle remember me cookies (auto-login)
if (!isset($_SESSION['user']) && isset($_COOKIE['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $user_id = $_COOKIE['user_id'];
        $remember_token = $_COOKIE['remember_token'];
        
        $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
        $stm->execute([$user_id]);
        $user = $stm->fetch();
        
        if ($user && $user->status === 'Active') {
            $expected_token = hash('sha256', $user->user_id . $user->user_password . 'K&P_SECRET_KEY');
            
            if ($remember_token === $expected_token) {
                safe_session_start();
                $_SESSION['user'] = $user;

                $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                $stm->execute([$user->user_id]);
                
                redirect('../../index.php');
            }
        }

        // Clear bad cookies
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
    } catch (Exception $e) {
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
        error_log("Auto-login error: " . $e->getMessage());
    }
}

// Flash messages
$success_message = temp('success');
$info_message = temp('info');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="container">
        <div class="login-form">
            <h1>Login to Your K&P Account</h1>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if ($info_message): ?>
                <div class="alert alert-info"><?= $info_message ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>

            <?php if (isset($_err['login']) || isset($_err['database'])): ?>
                <div class="alert alert-danger"><?= $_err['login'] ?? $_err['database'] ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
                    </div>
                    <?= err('email') ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <i class="fas fa-eye-slash toggle-password"></i>
                    </div>
                    <?= err('password') ?>
                </div>

                <div class="form-group">
                    <label for="captcha_input">Solve the CAPTCHA: What is <?= $_SESSION['captcha_num1'] ?> + <?= $_SESSION['captcha_num2'] ?>?</label>
                    <div class="input-with-icon">
                        <i class="fas fa-question"></i>
                        <input type="number" id="captcha_input" name="captcha_input" class="form-control" required>
                    </div>
                    <?= err('captcha') ?>
                </div>

                <div class="form-group remember-me">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <div class="form-group">
                    <button type="submit" name="login" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>

                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>

            <?php if (isset($show_verification_form) && $show_verification_form): ?>
                <div class="verification-form">
                    <h3>Email Verification Required</h3>
                    <form method="post">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($pending_email) ?>">
                        <button type="submit" name="resend_verification" class="secondary-btn">Resend Verification Email</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>

    <script>
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
