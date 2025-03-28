<?php
require '_base.php';

$token = get('token');

if (empty($token)) {
    temp('error', 'Invalid activation link');
    redirect('login.php');
}

try {
    // Check if token exists and is not expired
    $stm = $_db->prepare("
        SELECT cus_id FROM customer 
        WHERE activation_token = ? AND activation_expiry > NOW() AND cus_status = 'inactive'
    ");
    $stm->execute([$token]);
    $customer = $stm->fetch();

    if ($customer) {
        // Activate the account
        $stm = $_db->prepare("
            UPDATE customer 
            SET cus_status = 'active', 
                activation_token = NULL, 
                activation_expiry = NULL,
                cus_update_time = NOW()
            WHERE cus_id = ?
        ");
        $stm->execute([$customer->cus_id]);

        temp('success', 'Your account has been activated successfully! You can now login.');
    } else {
        temp('error', 'Invalid or expired activation link. Please register again.');
    }
} catch (PDOException $e) {
    error_log("Activation error: " . $e->getMessage());
    temp('error', 'Account activation failed. Please contact support.');
}

redirect('login.php');