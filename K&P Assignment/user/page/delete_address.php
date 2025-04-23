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
    
    // Check if this is the only address
    $stm = $_db->prepare("
        SELECT COUNT(*) FROM address 
        WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $address_count = $stm->fetchColumn();
    
    if ($address_count <= 1) {
        temp('error', 'You cannot delete your only address. Please add another address first.');
        redirect('profile.php#addresses');
    }
    
    // Check if address is being used in any pending orders
    $stm = $_db->prepare("
        SELECT COUNT(*) FROM delivery d
        JOIN orders o ON d.delivery_id = o.delivery_id
        WHERE d.address_id = ? AND o.orders_status IN ('Pending', 'Processing', 'Shipped')
    ");
    $stm->execute([$address_id]);
    $has_active_orders = $stm->fetchColumn() > 0;
    
    if ($has_active_orders) {
        temp('error', 'This address cannot be deleted because it is being used in active orders.');
        redirect('profile.php#addresses');
    }
    
    // If address is default, need to set another address as default
    if ($address->is_default) {
        $stm = $_db->prepare("
            SELECT address_id FROM address
            WHERE user_id = ? AND address_id != ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stm->execute([$user_id, $address_id]);
        $new_default = $stm->fetchColumn();
        
        $stm = $_db->prepare("
            UPDATE address
            SET is_default = 1
            WHERE address_id = ?
        ");
        $stm->execute([$new_default]);
    }
    
    // Delete the address
    $stm = $_db->prepare("
        DELETE FROM address 
        WHERE address_id = ? AND user_id = ?
    ");
    $stm->execute([$address_id, $user_id]);
    
    temp('success', 'Address has been deleted successfully.');
    
} catch (PDOException $e) {
    error_log("Error deleting address: " . $e->getMessage());
    temp('error', 'An error occurred while deleting the address. Please try again.');
}

redirect('profile.php#addresses');
?>