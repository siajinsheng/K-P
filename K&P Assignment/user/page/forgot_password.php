<?php
$_title = 'K&P - Forgot Password';
require '../../_base.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    redirect('../../index.php'); // Redirect to homepage if already logged in
}

// Handle form submission
if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    
    // Basic validation
    if (empty($email)) {
        $_err['email'] = 'Email address is required';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Invalid email format';
    } else {
        try {
            // Check if email exists in the database
            $stm = $_db->prepare("SELECT user_id, user_name, user_Email, status FROM user WHERE user_Email = ?");
            $stm->execute([$email]);
            $user = $stm->fetch();
            
            if ($user) {
                if ($user->status === 'Active') {
                    // Generate password reset token
                    $token = create_token($user->user_id, 'password_reset', 1); // Token valid for 1 hour
                    
                    // Send password reset email
                    if (send_reset_email($email, $user->user_name, $token)) {
                        temp('success', 'Password reset instructions have been sent to your email address.');
                        redirect('login.php');
                    } else {
                        $_err['email'] = 'Failed to send password reset email. Please try again later.';
                    }
                } else if ($user->status === 'Pending') {
                    $_err['email'] = 'Your account is not yet verified. Please verify your email first.';
                } else {
                    $_err['email'] = 'This account is inactive or has been suspended. Please contact support.';
                }
            } else {
                // We still show a success message for security reasons
                // This prevents email enumeration attacks
                temp('success', 'If the email exists in our system, password reset instructions have been sent.');
                redirect('login.php');
            }
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $_err['email'] = 'An error occurred. Please try again later.';
        }
    }
}

// Get any messages from session
$success_message = temp('success');
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
    <style>
        .forgot-password-form {
            max-width: 500px;
            margin: 80px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .forgot-password-form h1 {
            font-size: 1.8rem;
            color: #4a6fa5;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .forgot-password-form p {
            color: #666;
            margin-bottom: 25px;
            text-align: center;
            line-height: 1.6;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #4a6fa5;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .back-to-login i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="container">
        <div class="forgot-password-form">
            <h1>Forgot Your Password?</h1>
            <p>Enter your email address below and we'll send you instructions to reset your password.</p>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <?= isset($_err['email']) ? '<div class="error-message">' . $_err['email'] . '</div>' : '' ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </div>
            </form>
            
            <div class="back-to-login">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>