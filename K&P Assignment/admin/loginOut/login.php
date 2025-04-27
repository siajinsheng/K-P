<?php
$_title = 'K&P - Admin Login';
require '../../_base.php';

// Ensure session is started
safe_session_start();

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    // Check if they have appropriate role
    if ($_SESSION['user']->role === 'admin' || $_SESSION['user']->role === 'staff') {
        redirect('../home/home.php'); // Redirect to admin home if already logged in
    } else {
        // If logged in but incorrect role, log them out and show error
        logout();
        temp('error', 'You do not have permission to access the admin area.');
    }
}

// Initialize login attempt tracking variables if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = 0;
}

// Check if account is temporarily locked (10 seconds)
$lockout_time = 10; // 10 seconds
$time_passed = time() - $_SESSION['last_attempt_time'];
$time_remaining = $lockout_time - $time_passed;

if ($_SESSION['login_attempts'] >= 3 && $time_passed < $lockout_time) {
    $minutes_remaining = ceil($time_remaining);
    $_err['login'] = "Too many failed login attempts. Please try again in {$minutes_remaining} seconds.";
    $account_locked = true;
}

if (is_post() && isset($_POST['login']) && !isset($account_locked)) {
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
                // Check if user has admin or staff role
                if ($user->role !== 'admin' && $user->role !== 'staff') {
                    $_err['login'] = 'You do not have permission to access the admin area.';
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                }
                // Check account status
                else if ($user->status !== 'Active') {
                    $_err['login'] = 'Your account is inactive or has been suspended. Please contact the system administrator.';
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                } else {
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    
                    // Start session and store user data
                    safe_session_start();
                    $_SESSION['user'] = $user;
                    
                    // Set remember me cookie if requested
                    if ($remember) {
                        // Set cookies to store user_id and a secure hashed token
                        $token_base = $user->user_id . $user->user_password . 'K&P_ADMIN_SECRET_KEY';
                        $remember_token = hash('sha256', $token_base);
                        
                        // Store in cookies for 10s (more secure for admin)
                        setcookie('admin_user_id', $user->user_id, time() + (10), '/');
                        setcookie('admin_remember_token', $remember_token, time() + (10), '/');
                    }

                    // Update last login timestamp
                    $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                    $stm->execute([$user->user_id]);

                    // Log the successful admin login
                    error_log("Admin login: {$user->user_name} ({$user->user_id}) logged in as {$user->role}");

                    // Redirect to admin home
                    redirect('../home/home.php');
                }
            } else {
                $_err['login'] = 'Invalid email or password';
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $_err['database'] = 'Login failed. Please try again.';
        }
    }
}

// Handle remember me cookie (auto-login)
if (!isset($_SESSION['user']) && isset($_COOKIE['admin_user_id']) && isset($_COOKIE['admin_remember_token'])) {
    try {
        $user_id = $_COOKIE['admin_user_id'];
        $remember_token = $_COOKIE['admin_remember_token'];
        
        // Get user from database
        $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
        $stm->execute([$user_id]);
        $user = $stm->fetch();
        
        // Verify user exists, is active, has appropriate role, and token matches expected value
        if ($user && $user->status === 'Active' && ($user->role === 'admin' || $user->role === 'staff')) {
            $expected_token = hash('sha256', $user->user_id . $user->user_password . 'K&P_ADMIN_SECRET_KEY');
            
            if ($remember_token === $expected_token) {
                // Log the user in
                safe_session_start();
                $_SESSION['user'] = $user;
                
                // Update last login timestamp
                $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                $stm->execute([$user->user_id]);
                
                // Log the auto-login
                error_log("Admin auto-login: {$user->user_name} ({$user->user_id}) logged in via remember-me as {$user->role}");
                
                // Redirect to admin home
                redirect('../home/home.php');
            }
        }
        
        // If we reach here, either the user doesn't exist, isn't admin/staff, or the token is invalid
        // Clear cookies
        setcookie('admin_user_id', '', time() - 3600, '/');
        setcookie('admin_remember_token', '', time() - 3600, '/');
        
    } catch (Exception $e) {
        // Clear invalid cookies
        setcookie('admin_user_id', '', time() - 3600, '/');
        setcookie('admin_remember_token', '', time() - 3600, '/');
        error_log("Admin auto-login error: " . $e->getMessage());
    }
}

// Check for messages from other pages
$success_message = temp('success');
$info_message = temp('info');
$error_message = temp('error');

// Calculate attempts remaining - with safety check
$login_attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$attempts_remaining = isset($account_locked) ? 0 : max(0, 3 - $login_attempts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a4f66;
            --secondary-color: #5a7793;
            --accent-color: #1e2b3a;
            --text-color: #333;
            --light-color: #f8f9fa;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 450px;
            margin: 3rem auto;
            padding: 2rem;
        }
        
        .login-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 {
            color: var(--primary-color);
            text-align: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .admin-badge {
            background-color: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            vertical-align: middle;
        }
        
        .logo {
            display: block;
            width: 120px;
            margin: 0 auto 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            cursor: pointer;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(90, 119, 147, 0.2);
        }
        
        .remember-me {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
        }
        
        .checkbox-wrapper input {
            margin-right: 8px;
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        
        .checkbox-wrapper label {
            margin: 0;
            cursor: pointer;
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: var(--accent-color);
        }
        
        .btn i {
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 1.2rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .alert-danger {
            background-color: #fde8e8;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background-color: #e6f7e7;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-info {
            background-color: #e6f3f7;
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }
        
        .err {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #777;
            font-size: 0.85rem;
        }
        
        .user-login-link {
            color: var(--primary-color);
            text-decoration: none;
            margin-top: 1.5rem;
            display: block;
            text-align: center;
            font-size: 0.95rem;
        }
        
        .user-login-link:hover {
            text-decoration: underline;
        }
        
        .attempts-warning {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--warning-color);
        }
        
        @media (max-width: 500px) {
            .container {
                margin: 1.5rem auto;
                padding: 1rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <img src="../../img/K&P logo.png" alt="K&P Logo" class="logo">
            <h1>Admin Login <span class="admin-badge">Secure Area</span></h1>
            
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
            
            <?php if (!isset($account_locked)): ?>
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
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="login" class="btn">
                            <i class="fas fa-sign-in-alt"></i> Secure Login
                        </button>
                    </div>
                    
                    <?php if ($login_attempts > 0): ?>
                        <div class="attempts-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Warning: <?= $login_attempts ?> failed login attempt(s). 
                            <?= $attempts_remaining ?> attempts remaining before temporary lockout.
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            
            <div class="footer-text">
                <p>This is a secure area. Unauthorized access is prohibited and may be subject to legal action.</p>
            </div>
            
            <a href="../../user/page/login.php" class="user-login-link">
                <i class="fas fa-arrow-left"></i> Return to Customer Login
            </a>
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
    </script>
</body>
</html>