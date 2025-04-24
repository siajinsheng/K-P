<?php
require_once '../../_base.php';
auth('admin', 'staff');

// Get discount ID from URL
$discount_id = get('id');

if (!$discount_id) {
    $_SESSION['temp_error'] = 'Invalid discount ID';
    redirect('index.php');
}

// Check if the discount exists
$stm = $_db->prepare("SELECT * FROM discount WHERE Discount_id = ?");
$stm->execute([$discount_id]);
$discount = $stm->fetch();

if (!$discount) {
    $_SESSION['temp_error'] = 'Discount not found';
    redirect('index.php');
}

// Delete the discount
try {
    $stm = $_db->prepare("DELETE FROM discount WHERE Discount_id = ?");
    $stm->execute([$discount_id]);
    
    $_SESSION['temp_success'] = 'Discount deleted successfully';
} catch (PDOException $e) {
    $_SESSION['temp_error'] = 'Failed to delete discount: ' . $e->getMessage();
}

redirect('index.php');