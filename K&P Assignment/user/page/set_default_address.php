<?php
require_once '../../_base.php';

// Ensure user is authenticated
safe_session_start();
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to manage addresses');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$address_id = req('id');

if (empty($address_id)) {
    temp('error', 'No address specified');
    redirect('profile.php#addresses');
}

try {
    // Check if address belongs to user
    $stm = $_db->prepare("
        SELECT * FROM address 
        WHERE address_id = ? AND user_id = ?
    ");
    $stm->execute([$address_id, $user_id]);
    $address = $stm->fetch();
    
    if (!$address) {
        temp('error', 'Address not found or does not belong to your account');
        redirect('profile.php#addresses');
    }
    
    // Update all addresses to not be default
    $stm = $_db->prepare("
        UPDATE address
        SET is_default = 0
        WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    
    // Set the selected address as default
    $stm = $_db->prepare("
        UPDATE address
        SET is_default = 1
        WHERE address_id = ?
    ");
    $stm->execute([$address_id]);
    
    temp('success', 'Address has been set as default.');
    
} catch (PDOException $e) {
    error_log("Error setting default address: " . $e->getMessage());
    temp('error', 'An error occurred while setting the default address. Please try again.');
}

redirect('profile.php#addresses');
?>