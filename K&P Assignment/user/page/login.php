<?php
$_title = 'K&P - Login';
require '../../_base.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    redirect('/index.php'); // Redirect to homepage or dashboard if already logged in
}

// Handle resend verification email
if (isset($_POST['resend_verification'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        temp('error', 'Please provide your email address.');
        redirect('login.php');
    }
    
    try {
        // Find user with this email that has Pending status
        $stm = $_db->prepare("SELECT user_id, user_name, user_Email, status FROM user WHERE user_Email = ?");
        $stm->execute([$email]);
        $user = $stm->fetch();
        
        if ($user && $user->status === 'Pending') {
            // Create a new verification token
            $token = create_token($user->user_id, 'email_verification', 24);
            
            // Send verification email
            $result = send_verification_email($email, $user->user_name, $token);
            
            if ($result) {
                temp('success', 'A new verification email has been sent to ' . $email . '. Please check your inbox and spam folder.');
            } else {
                temp('error', 'Failed to send verification email. Please try again later.');
            }
        } else if ($user && $user->status === 'Active') {
            temp('info', 'Your account is already active. You can login now.');
        } else {
            temp('error', 'No account found with this email address or the account is not pending verification.');
        }
        
        redirect('login.php');
    } catch (Exception $e) {
        error_log("Resend verification error: " . $e->getMessage());
        temp('error', 'An error occurred while processing your request.');
        redirect('login.php');
    }
}

if (is_post() && isset($_POST['login'])) {
    $email = req('email');
    $password = req('password');
    $remember = req('remember') ? true : false;

    // Validation
    if (empty($email)) {
        $_err['email'] = 'Email is required';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $_err['password'] = 'Password is required';
    }

    // Process if no errors in basic validation
    if (empty($_err)) {
        try {
            // Query database for user with provided email
            $stm = $_db->prepare("SELECT * FROM user WHERE user_Email = ?");
            $stm->execute([$email]);
            $user = $stm->fetch();

            // Check if user exists and password is correct
            if ($user && password_verify($password, $user->user_password)) {
                // Check account status
                if ($user->status === 'Pending') {
                    $_err['login'] = 'Your email address has not been verified. Please check your inbox for the verification email or request a new one below.';
                    $show_verification_form = true;
                    $pending_email = $email; // Store the email to use in the resend form
                } else if ($user->status !== 'Active') {
                    $_err['login'] = 'Your account is inactive or has been suspended. Please contact support.';
                } else {
                    // Start session and store user data
                    safe_session_start();
                    $_SESSION['user'] = $user;
                    
                    // Set remember me cookie if requested
                    if ($remember) {
                        // Set cookies to store user_id and a simple hashed token
                        $token_base = $user->user_id . $user->user_password . 'K&P_SECRET_KEY';
                        $remember_token = hash('sha256', $token_base);
                        
                        // Store in cookies for 30 days
                        setcookie('user_id', $user->user_id, time() + (30 * 24 * 60 * 60), '/');
                        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/');
                    }

                    // Update last login timestamp
                    $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                    $stm->execute([$user->user_id]);

                    // Redirect based on user role
                    if ($user->role === 'admin') {
                        redirect('../../admin/index.php');
                    } elseif ($user->role === 'staff') {
                        redirect('../../admin/index.php');
                    } else {
                        redirect('../../index.php'); // Regular user/member
                    }
                }
            } else {
                $_err['login'] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $_err['database'] = 'Login failed. Please try again.';
        }
    }
}

// Handle remember me cookie (auto-login)
if (!isset($_SESSION['user']) && isset($_COOKIE['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $user_id = $_COOKIE['user_id'];
        $remember_token = $_COOKIE['remember_token'];
        
        // Get user from database
        $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
        $stm->execute([$user_id]);
        $user = $stm->fetch();
        
        // Verify user exists, is active, and token matches expected value
        if ($user && $user->status === 'Active') {
            $expected_token = hash('sha256', $user->user_id . $user->user_password . 'K&P_SECRET_KEY');
            
            if ($remember_token === $expected_token) {
                // Log the user in
                safe_session_start();
                $_SESSION['user'] = $user;
                
                // Update last login timestamp
                $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                $stm->execute([$user->user_id]);
                
                // Redirect based on user role
                if ($user->role === 'Member') {
                    redirect('../../index.php');
                } else {
                    redirect('../../index.php');
                }
            }
        }
        
        // If we reach here, either the user doesn't exist or the token is invalid
        // Clear cookies
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
        
    } catch (Exception $e) {
        // Clear invalid cookies
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
        error_log("Auto-login error: " . $e->getMessage());
    }
}

// Check for messages from other pages (like successful registration)
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
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($info_message): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <?= $info_message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_err['login']) || isset($_err['database'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $_err['login'] ?? $_err['database'] ?>
                </div>
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
                    <h3><i class="fas fa-envelope"></i> Email Verification Required</h3>
                    <p>Your account needs to be verified. We've sent a verification email to <strong><?= htmlspecialchars($pending_email) ?></strong>.</p>
                    <p>Haven't received it? Check your spam folder or request a new verification email.</p>
                    
                    <form method="post">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($pending_email) ?>">
                        <button type="submit" name="resend_verification" class="secondary-btn">
                            <i class="fas fa-paper-plane"></i> Resend Verification Email
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="login-links">
                    <a href="#" id="show-verify-form"><i class="fas fa-envelope"></i> Need to verify your email?</a>
                </div>
                
                <div id="verify-email-form" style="display: none;">
                    <h3>Resend Verification Email</h3>
                    <p>If you've registered but haven't verified your email, enter your email address below to receive a new verification link.</p>
                    
                    <form method="post">
                        <div class="form-group">
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" placeholder="Enter your email address" required class="form-control">
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="resend_verification" class="secondary-btn">
                                <i class="fas fa-paper-plane"></i> Send Verification Email
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    
    <script>
        // Toggle password visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Show verification form when needed
        const showVerifyLink = document.getElementById('show-verify-form');
        if (showVerifyLink) {
            showVerifyLink.addEventListener('click', function(e) {
                e.preventDefault();
                const verifyForm = document.getElementById('verify-email-form');
                verifyForm.style.display = verifyForm.style.display === 'none' ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>