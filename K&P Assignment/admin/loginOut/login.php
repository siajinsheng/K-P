<?php
$_title = 'K&P - Staff Login';
require '../../_base.php';

// Use the safe_session_start function instead of directly calling session_start()
safe_session_start();

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    // Check if they have appropriate role
    if ($_SESSION['user']->role === 'admin' || $_SESSION['user']->role === 'staff') {
        // Redirect to admin home if already logged in
        header("Location: ../home/home.php");
        exit();
    } else {
        // If logged in but incorrect role, log them out and show error
        logout();
        temp('error', 'You do not have permission to access the admin area.');
    }
}

// Initialize login attempt tracking variables
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = 0;
}

// Check if account is temporarily locked
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

            // Display the user's role for debugging (only during development)
            if ($user) {
                error_log("Found user: {$user->user_name} with role: {$user->role}");
            }

            // Check if user exists and password is correct
            if ($user && password_verify($password, $user->user_password)) {
                // Check if user has admin or staff role (case-insensitive check)
                $user_role = strtolower($user->role);
                if ($user_role != 'admin' && $user_role != 'staff') {
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
                    
                    // Clear session then store user data
                    session_unset();
                    
                    // Create a new session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    // Store user in session
                    $_SESSION['user'] = $user;
                    
                    // Create a direct admin authentication token as an alternate auth mechanism
                    $_SESSION['admin_token'] = md5($user->user_id . $user->user_Email . time());
                    
                    // Update last login timestamp
                    $stm = $_db->prepare("UPDATE user SET user_update_time = NOW() WHERE user_id = ?");
                    $stm->execute([$user->user_id]);

                    // Force session data to be saved
                    session_write_close();
                    
                    // Make sure session cookie is set with proper parameters
                    setcookie(session_name(), session_id(), [
                        'expires' => time() + 86400,
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                    
                    // Redirect to admin home - using absolute path
                    header("Location: ../home/home.php?token={$_SESSION['admin_token']}");
                    exit();
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
    <link href="login.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <img src="../../img/K&P logo.png" alt="K&P Logo" class="logo">
            <h1>Staff Login <span class="admin-badge">Secure Area</span></h1>
            
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