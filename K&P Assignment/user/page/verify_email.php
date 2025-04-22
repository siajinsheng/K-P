<?php
$_title = 'K&P - Verify Email';
require_once '../../_base.php';

$token = $_GET['token'] ?? '';
$status = 'pending';  // Default status
$message = '';

if (empty($token)) {
    $status = 'error';
    $message = 'Invalid verification link. The token is missing.';
} else {
    try {
        // Find user with the given token
        $stm = $_db->prepare("
            SELECT user_id, user_name, user_Email, activation_expiry 
            FROM user 
            WHERE activation_token = ? AND status = 'Pending'
        ");
        $stm->execute([$token]);
        $user = $stm->fetch();
        
        if (!$user) {
            $status = 'error';
            $message = 'Invalid verification link. The token may have been used already or does not exist.';
        } else {
            // Check if token has expired
            $now = new DateTime();
            $expiry = new DateTime($user->activation_expiry);
            
            if ($now > $expiry) {
                $status = 'expired';
                $message = 'This verification link has expired. Please request a new one.';
            } else {
                // Update user status to Active
                $stm = $_db->prepare("
                    UPDATE user 
                    SET status = 'Active', activation_token = NULL, activation_expiry = NULL 
                    WHERE user_id = ?
                ");
                $stm->execute([$user->user_id]);
                
                $status = 'success';
                $message = 'Thank you! Your email has been verified and your account is now active.';
            }
        }
    } catch (PDOException $e) {
        error_log("Email verification error: " . $e->getMessage());
        $status = 'error';
        $message = 'An error occurred while processing your request. Please try again later.';
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
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="container">
        <div class="verification-container">
            <?php if ($status === 'success'): ?>
                <div class="status-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Email Verified</h1>
                <p><?= $message ?></p>
                <div class="action-buttons">
                    <a href="login.php" class="btn primary-btn">Login to Your Account</a>
                </div>
                
            <?php elseif ($status === 'expired'): ?>
                <div class="status-icon warning">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h1>Verification Link Expired</h1>
                <p><?= $message ?></p>
                <p>Please login to request a new verification email.</p>
                <div class="action-buttons">
                    <a href="login.php" class="btn primary-btn">Login</a>
                </div>
                
            <?php else: ?>
                <div class="status-icon error">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1>Verification Failed</h1>
                <p><?= $message ?></p>
                <div class="action-buttons">
                    <a href="login.php" class="btn primary-btn">Return to Login</a>
                    <a href="register.php" class="btn secondary-btn">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>