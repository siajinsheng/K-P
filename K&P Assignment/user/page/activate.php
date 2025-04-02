<?php
require_once '../../_base.php';

// Get token from URL
$token = req('token', '');

// Check if token is provided
if (empty($token)) {
    temp('error', 'Invalid activation link. Please check your email or contact support.');
    redirect('login.php');
    exit;
}

// Initialize message variables
$status = false;
$message = '';

try {
    // Check if token exists and not expired
    $stm = $_db->prepare("SELECT user_id FROM user WHERE activation_token = ? AND activation_expiry > NOW()");
    $stm->execute([$token]);
    $user = $stm->fetch();
    
    if ($user) {
        // Activate the account and set role to 'member'
        $stm = $_db->prepare("UPDATE user SET status = 'Active', role = 'member', activation_token = NULL, activation_expiry = NULL WHERE user_id = ?");
        $result = $stm->execute([$user->user_id]);
        
        if ($result) {
            $status = true;
            $message = 'Your account has been successfully activated! You can now login.';
            temp('success', $message);
            
            // Redirect after a short delay (using JavaScript in the template)
        } else {
            $message = 'Failed to activate your account. Please try again or contact support.';
            temp('error', $message);
        }
    } else {
        // Token is invalid or expired
        $message = 'Invalid or expired activation link. Please register again or contact support.';
        temp('error', $message);
    }
} catch (PDOException $e) {
    error_log("Activation error: " . $e->getMessage());
    $message = 'An error occurred during activation. Please try again later or contact support.';
    temp('error', $message);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Account Activation</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .activation-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .activation-success {
            color: #28a745;
            font-size: 70px;
            margin-bottom: 20px;
        }
        
        .activation-error {
            color: #dc3545;
            font-size: 70px;
            margin-bottom: 20px;
        }
        
        .message {
            margin-bottom: 30px;
            font-size: 18px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #4a6fa5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #3a5a85;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>
    
    <div class="activation-container">
        <?php if ($status): ?>
            <div class="activation-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Account Activated!</h1>
            <p class="message"><?= $message ?></p>
            <a href="login.php" class="btn">Log In Now</a>
        <?php else: ?>
            <div class="activation-error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Activation Failed</h1>
            <p class="message"><?= $message ?></p>
            <a href="register.php" class="btn">Register Again</a>
        <?php endif; ?>
    </div>
    
    <?php include '../footer.php'; ?>

    <?php if ($status): ?>
    <script>
        // Redirect to login page after 5 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>