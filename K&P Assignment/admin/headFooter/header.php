<?php
// Start output buffering at the very beginning
ob_start();

require_once '../../_base.php';
safe_session_start();

// Use either direct user object or decode from JSON
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    // Make sure current_user is also set for backward compatibility
    if (!isset($_SESSION['current_user'])) {
        $_SESSION['current_user'] = json_encode($user);
    }
} elseif (isset($_SESSION['current_user'])) {
    $user = json_decode($_SESSION['current_user']);
} else {
    // No user is logged in
    redirect('/admin/loginOut/login.php');
}

// Check that we have a valid user with the right role
if (!isset($user->role) || ($user->role !== 'admin' && $user->role !== 'staff')) {
    // Log the issue for debugging
    error_log("Invalid user role: " . ($user->role ?? 'undefined'));
    // Force logout and redirect
    logout('/admin/loginOut/login.php');
    exit;
}

$photo = isset($user->admin_profile_pic) 
    ? $user->admin_profile_pic 
    : 'default.png';

$photoPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/pic/' . $photo;
if (!file_exists($photoPath)) {
    $photo = 'default.png';
    error_log("Missing profile photo: " . $photoPath);
}

// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);

// Check if current user is staff and trying to access staff.php
$isStaffPage = strpos($current_page, 'staff.php') !== false;
if ($user->role === 'staff' && $isStaffPage) {
    // Redirect staff away from staff management page
    redirect('/admin/home/home.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/headFooter/header.css">
    <link rel="stylesheet" href="/admin/headFooter/footer.css">
    <title>K&P Fashion | Admin Dashboard</title>
    <script src="/admin/headFooter/header.js"></script>
</head>
<body>
    <div class="navbar">
        <div class="header-left">
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <a href="../home/home.php"><img src="../../img/K&P logo.png" alt="K&P Logo"></a>
                <span class="logo-text">K&P Admin</span>
            </div>
        </div>

        <div class="header-right">
            <ul>
                <li><a href="../home/home.php" class="<?= $current_page === 'home.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="../product/product.php" class="<?= $current_page === 'product.php' ? 'active' : '' ?>">Products</a></li>
                <li><a href="../category/category.php" class="<?= $current_page === 'category.php' ? 'active' : '' ?>">Category</a></li>
                <li><a href="../discount/discount.php" class="<?= $current_page === 'discount.php' ? 'active' : '' ?>">Discounts</a></li>
                <li><a href="../order/orders.php" class="<?= $current_page === 'orders.php' ? 'active' : '' ?>">Orders</a></li>
                <li><a href="../payment/payment.php" class="<?= $current_page === 'payment.php' ? 'active' : '' ?>">Payments</a></li>
                <li><a href="../customer/customers.php" class="<?= $current_page === 'customers.php' ? 'active' : '' ?>">Customers</a></li>
                <?php if ($user->role === 'admin'): ?>
                <li><a href="../staff/staff.php" class="<?= $current_page === 'staff.php' ? 'active' : '' ?>">Staff</a></li>
                <?php endif; ?>
                <li class="user-profile-container">
                    <div class="user-profile">
                        <img src="../../img/<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($user->user_name ?? 'Admin') ?>" class="profile-pic">
                        <i class="fas fa-chevron-down"></i>
                        <div class="profile-dropdown">
                            <div class="profile-header">
                                <img src="../../img/<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($user->user_name ?? 'Admin') ?>">
                                <div class="profile-info">
                                    <span class="profile-name"><?= htmlspecialchars($user->user_name ?? 'Admin') ?></span>
                                    <span class="profile-email"><?= htmlspecialchars($user->user_Email ?? '') ?></span>
                                </div>
                            </div>
                            <ul>
                                <li><a href="/admin/profile/profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                                <li><a href="/admin/loginOut/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <ul>
            <li><a href="../home/home.php" class="<?= $current_page === 'home.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Home
            </a></li>
            <li><a href="../product/product.php" class="<?= $current_page === 'product.php' ? 'active' : '' ?>">
                <i class="fas fa-tshirt"></i> Products
            </a></li>
            <li><a href="../category/category.php" class="<?= $current_page === 'category.php' ? 'active' : '' ?>">
                <i class="fas fa-tag"></i> Category
            </a></li>
            <li><a href="../discount/discount.php" class="<?= $current_page === 'discount.php' ? 'active' : '' ?>">
                <i class="fas fa-percentage"></i> Discounts
            </a></li>
            <li><a href="../order/orders.php" class="<?= $current_page === 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> Orders
            </a></li>
            <li><a href="../payment/payment.php" class="<?= $current_page === 'payment.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i> Payments
            </a></li>
            <li><a href="../customer/customers.php" class="<?= $current_page === 'customers.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Customers
            </a></li>
            <?php if ($user->role === 'admin'): ?>
            <li><a href="../staff/staff.php" class="<?= $current_page === 'staff.php' ? 'active' : '' ?>">
                <i class="fas fa-user-tie"></i> Staff
            </a></li>
            <?php endif; ?>
            <li><a href="/admin/profile/profile.php">
                <i class="fas fa-user-cog"></i> Profile Settings
            </a></li>
            <li><a href="/admin/loginOut/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a></li>
        </ul>
    </div>

    <div class="overlay" id="overlay"></div>

    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
        <!-- Main content goes here -->