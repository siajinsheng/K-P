<?php
$_title = 'K&P - Email Verification';
require_once '../../_base.php';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$status = 'error'; // default status
$message = '';

if (empty($token)) {
    $message = 'Verification link is invalid. No token was provided.';
} else {
    try {
        // Query the database for this token
        $stm = $_db->prepare("
            SELECT user_id, user_name, user_Email, activation_expiry
            FROM user
            WHERE activation_token = ? AND status = 'Pending'
        ");
        $stm->execute([$token]);
        $user = $stm->fetch();
        
        if (!$user) {
            $message = 'Verification link is invalid or has already been used.';
        } else {
            // Check if token has expired
            $now = new DateTime();
            
            if ($user->activation_expiry) {
                $expiry = new DateTime($user->activation_expiry);
                
                if ($now > $expiry) {
                    $status = 'expired';
                    $message = 'This verification link has expired. Please request a new one.';
                } else {
                    // Activate the user account
                    $stm = $_db->prepare("
                        UPDATE user
                        SET status = 'Active', activation_token = NULL, activation_expiry = NULL
                        WHERE user_id = ?
                    ");
                    $stm->execute([$user->user_id]);
                    
                    $status = 'success';
                    $message = 'Your email has been successfully verified! Your account is now active.';
                }
            } else {
                // No expiry set, just activate
                $stm = $_db->prepare("
                    UPDATE user
                    SET status = 'Active', activation_token = NULL, activation_expiry = NULL
                    WHERE user_id = ?
                ");
                $stm->execute([$user->user_id]);
                
                $status = 'success';
                $message = 'Your email has been successfully verified! Your account is now active.';
            }
        }
    } catch (Exception $e) {
        error_log("Error during email verification: " . $e->getMessage());
        $message = 'An error occurred while processing your verification. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .success .icon {
            color: #28a745;
        }
        
        .error .icon, .expired .icon {
            color: #dc3545;
        }
        
        h1 {
            margin-bottom: 20px;
            color: #4a6fa5;
        }
        
        .message {
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #4a6fa5;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #3a5a85;
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>
    
    <div class="container <?= $status ?>">
        <?php if ($status === 'success'): ?>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Email Verified!</h1>
            <p class="message"><?= $message ?></p>
            <a href="login.php" class="btn">Log In Now</a>
        <?php elseif ($status === 'expired'): ?>
            <div class="icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h1>Link Expired</h1>
            <p class="message"><?= $message ?></p>
            <a href="login.php" class="btn">Log In to Request New Link</a>
        <?php else: ?>
            <div class="icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Verification Failed</h1>
            <p class="message"><?= $message ?></p>
            <a href="login.php" class="btn">Return to Login</a>
        <?php endif; ?>
    </div>
    
    <?php include '../footer.php'; ?>
</body>
</html>