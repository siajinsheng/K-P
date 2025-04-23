<?php
$_title = 'K&P - Email Verification';
require_once '../../_base.php';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$status = 'error'; // default status
$message = '';

// For debugging
error_log("Verification page accessed with token: " . $token);

if (empty($token)) {
    $message = 'Verification link is invalid. No token was provided.';
} else {
    try {
        // Verify token
        $user = verify_token($token, 'email_verification');
        
        // Log result for debugging
        error_log("Verification results: " . ($user ? "User found: " . $user->user_id : "No valid token found"));
        
        if (!$user) {
            $message = 'Verification link is invalid, has expired, or has already been used.';
        } else {
            // Activate the user account
            $stm = $_db->prepare("
                UPDATE user
                SET status = 'Active'
                WHERE user_id = ?
            ");
            $stm->execute([$user->user_id]);
            
            // Delete the token
            delete_token($token, 'email_verification');
            
            // Log for debugging
            error_log("User account activated: " . $user->user_id);
            
            $status = 'success';
            $message = 'Your email has been successfully verified! Your account is now active.';
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
    <link rel="stylesheet" href="../css/verify.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Fallback styles in case the CSS file isn't loaded */
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .icon {
            font-size: 72px;
            margin-bottom: 20px;
        }
        
        .success .icon {
            color: #28a745;
        }
        
        .error .icon {
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