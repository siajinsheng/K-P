<?php
$_title = 'K&P - Admin Login';
require '../../_base.php';

// Check if user is already logged in as admin or staff
if (isset($_SESSION['user']) && ($_SESSION['user']->role === 'admin' || $_SESSION['user']->role === 'staff')) {
    redirect('../home/home.php'); // Redirect to admin dashboard if already logged in
}

// Initialize login attempts counter if not already set
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
}

// Check if account is temporarily locked
if (isset($_SESSION['admin_lockout_time'])) {
    $lockout_time = $_SESSION['admin_lockout_time'];
    $time_now = time();
    $time_remaining = $lockout_time - $time_now;
    
    if ($time_remaining > 0) {
        // Account is still locked
        $_err['login'] = 'Account temporarily locked. Please try again in ' . ceil($time_remaining / 60) . ' minutes.';
    } else {
        // Lock period has expired, reset counters
        unset($_SESSION['admin_login_attempts']);
        unset($_SESSION['admin_lockout_time']);
    }
}

if (is_post() && !isset($_SESSION['admin_lockout_time'])) {
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

            // Check if user exists, password is correct, and user is admin or staff
            if ($user && password_verify($password, $user->user_password)) {
                // Check if user is admin or staff
                if ($user->role !== 'admin' && $user->role !== 'staff') {
                    $_err['login'] = 'You do not have permission to access the admin panel.';
                    $_SESSION['admin_login_attempts']++;
                    
                    // Check if max attempts reached
                    if ($_SESSION['admin_login_attempts'] >= 3) {
                        $_SESSION['admin_lockout_time'] = time() + (15 * 60); // Lock for 15 minutes
                        $_err['login'] = 'Too many failed attempts. Account locked for 15 minutes.';
                    }
                } 
                // Check account status
                else if ($user->status !== 'Active') {
                    $_err['login'] = 'Your account is inactive or has been suspended. Please contact the system administrator.';
                    $_SESSION['admin_login_attempts']++;
                    
                    // Check if max attempts reached
                    if ($_SESSION['admin_login_attempts'] >= 3) {
                        $_SESSION['admin_lockout_time'] = time() + (15 * 60); // Lock for 15 minutes
                        $_err['login'] = 'Too many failed attempts. Account locked for 15 minutes.';
                    }
                } else {
                    // Start session and store user data
                    safe_session_start();
                    $_SESSION['user'] = $user;
                    
                    // Reset login attempts
                    unset($_SESSION['admin_login_attempts']);
                    unset($_SESSION['admin_lockout_time']);
                    
                    // Set remember me cookie if requested
                    if ($remember) {
                        // Set cookies to store user_id and a secure hashed token
                        $token_base = $user->user_id . $user->user_password . 'K&P_ADMIN_SECRET_KEY';
                        $remember_token = hash('sha256', $token_base);
                        
                        // Store in cookies for 7 days (shorter for admin security)
                        setcookie('admin_id', $user->user_id, time() + (7 * 24 * 60 * 60), '/');
                        setcookie('admin_token', $remember_token, time() + (7 * 24 * 60 * 60), '/');
                    }

                    // Update last login timestamp
                    $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                    $stm->execute([$user->user_id]);

                    // Redirect to admin dashboard
                    redirect('../home/home.php');
                }
            } else {
                // Increment login attempts
                $_SESSION['admin_login_attempts']++;
                
                // Check if max attempts reached
                if ($_SESSION['admin_login_attempts'] >= 3) {
                    $_SESSION['admin_lockout_time'] = time() + (15 * 60); // Lock for 15 minutes
                    $_err['login'] = 'Too many failed attempts. Account locked for 15 minutes.';
                } else {
                    $_err['login'] = 'Invalid email or password. Attempts remaining: ' . (3 - $_SESSION['admin_login_attempts']);
                }
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $_err['database'] = 'Login failed. Please try again later.';
        }
    }
}

// Handle remember me cookie (auto-login)
if (!isset($_SESSION['user']) && isset($_COOKIE['admin_id']) && isset($_COOKIE['admin_token'])) {
    try {
        $user_id = $_COOKIE['admin_id'];
        $remember_token = $_COOKIE['admin_token'];
        
        // Get user from database
        $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
        $stm->execute([$user_id]);
        $user = $stm->fetch();
        
        // Verify user exists, is active, and token matches expected value
        if ($user && $user->status === 'Active' && ($user->role === 'admin' || $user->role === 'staff')) {
            $expected_token = hash('sha256', $user->user_id . $user->user_password . 'K&P_ADMIN_SECRET_KEY');
            
            if ($remember_token === $expected_token) {
                // Log the user in
                safe_session_start();
                $_SESSION['user'] = $user;
                
                // Update last login timestamp
                $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                $stm->execute([$user->user_id]);
                
                // Redirect to admin dashboard
                redirect('../home/home.php');
            }
        }
        
        // If we reach here, the cookies are invalid, so clear them
        setcookie('admin_id', '', time() - 3600, '/');
        setcookie('admin_token', '', time() - 3600, '/');
        
    } catch (Exception $e) {
        // Clear invalid cookies
        setcookie('admin_id', '', time() - 3600, '/');
        setcookie('admin_token', '', time() - 3600, '/');
        error_log("Admin auto-login error: " . $e->getMessage());
    }
}

// Check for messages from other pages
$error_message = temp('error');
$info_message = temp('info');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="admin-login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <img src="../../img/K&P logo.png" alt="K&P Admin Logo" class="admin-logo">
                <h1>Admin Panel</h1>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            
            <?php if ($info_message): ?>
                <div class="alert alert-info"><?= $info_message ?></div>
            <?php endif; ?>
            
            <?php if (isset($_err['login']) || isset($_err['database'])): ?>
                <div class="alert alert-danger"><?= $_err['login'] ?? $_err['database'] ?></div>
            <?php endif; ?>
            
            <form method="post" class="admin-login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="admin@example.com" required>
                    </div>
                    <?= err('email') ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                    <?= err('password') ?>
                </div>
                
                <div class="form-group remember-me">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Remember me for 7 days</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-admin">
                        <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
                    </button>
                </div>
                
                <div class="login-links">
                    <p><a href="../../user/page/login.php"><i class="fas fa-home"></i> Return to Customer Login</a></p>
                </div>
            </form>
        </div>
        
        <div class="admin-login-info">
            <div class="info-content">
                <h2>K&P Administration</h2>
                <p>Welcome to the K&P Admin Panel. Please sign in with your administrator credentials to access the management system.</p>
                <ul>
                    <li><i class="fas fa-shield-alt"></i> Manage Products</li>
                    <li><i class="fas fa-shopping-cart"></i> Process Orders</li>
                    <li><i class="fas fa-users"></i> Manage User Accounts</li>
                    <li><i class="fas fa-chart-line"></i> View Analytics</li>
                </ul>
            </div>
        </div>
    </div>
    
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

        // Check if we have a lockout time and update the countdown timer
        <?php if (isset($_SESSION['admin_lockout_time'])): ?>
        const lockoutTime = <?= $_SESSION['admin_lockout_time'] ?> * 1000; // Convert to milliseconds
        const countdownElement = document.querySelector('.alert-danger');
        
        if (countdownElement) {
            const updateCountdown = () => {
                const now = new Date().getTime();
                const timeRemaining = Math.max(0, lockoutTime - now);
                const minutes = Math.floor(timeRemaining / (1000 * 60));
                const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
                
                if (timeRemaining > 0) {
                    countdownElement.innerHTML = `Too many failed attempts. Account locked for ${minutes}:${seconds < 10 ? '0' : ''}${seconds} minutes.`;
                    setTimeout(updateCountdown, 1000);
                } else {
                    window.location.reload(); // Refresh when timer completes
                }
            };
            
            updateCountdown();
        }
        <?php endif; ?>
    </script>
</body>
</html>