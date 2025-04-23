<?php
require_once '../../_base.php';
safe_session_start();

$current_user = json_decode($_SESSION['current_user'], true);
$photo = isset($current_user['admin_profile_pic']) 
    ? $current_user['admin_profile_pic'] 
    : 'default.png';

$photoPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/pic/' . $photo;
if (!file_exists($photoPath)) {
    $photo = 'default.png';
    error_log("Missing profile photo: " . $photoPath);
}

// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/headFooter/header.css">
    <title>K&P Fashion | Admin Dashboard</title>
</head>
<body>
    <header class="navbar bg-white w-full py-3 px-4 fixed top-0 left-0 right-0 z-30">
        <div class="container mx-auto">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="../home/home.php" class="flex items-center">
                        <img src="../../img/K&P logo.png" alt="K&P Fashion Logo" class="h-12 mr-3">
                        <span class="text-xl font-bold text-gray-800 hidden md:block">K&P Admin</span>
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-button" class="md:hidden text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="../home/home.php" class="nav-item <?= $current_page === '../home/home.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-home mr-1"></i> Home
                    </a>
                    <a href="../product/product.php" class="nav-item <?= $current_page === '../product/product.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-tshirt mr-1"></i> Products
                    </a>
                    <a href="../category/category.php" class="nav-item <?= $current_page === '../category/category.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-tshirt mr-1"></i> Category
                    </a>
                    <a href="../discount/discount.php" class="nav-item <?= $current_page === '../discount/discount.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-percentage mr-1"></i> Discounts
                    </a>
                    <a href="../order/orders.php" class="nav-item <?= $current_page === '../order/orders.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-shopping-cart mr-1"></i> Orders
                    </a>
                    <a href="../payment/payment.php" class="nav-item <?= $current_page === '../payment/payment.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-credit-card mr-1"></i> Payments
                    </a>
                    <a href="../customer/customers.php" class="nav-item <?= $current_page === '../customer/customers.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-users mr-1"></i> Customers
                    </a>
                    <a href="../staff/staff.php" class="nav-item <?= $current_page === '../staff/staff.php' ? 'active' : '' ?> text-gray-700 hover:text-indigo-600 font-medium">
                        <i class="fas fa-user-tie mr-1"></i> Staff
                    </a>
                    
                    <!-- User Profile Dropdown -->
                    <div class="profile-dropdown ml-2">
                        <a href="#" class="flex items-center" onclick="toggleDropdown(event)">
                            <div class="profile-photo-container">
                                <img src="/admin/pic/<?= htmlspecialchars($photo) ?>" alt="Profile" class="profile-photo">
                            </div>
                            <span class="ml-2 text-gray-700"><?= htmlspecialchars($current_user['user_name'] ?? 'Admin') ?></span>
                            <i class="fas fa-chevron-down ml-1 text-gray-500 text-xs"></i>
                        </a>
                        <div class="dropdown-content mt-2">
                            <div class="py-2 px-4 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($current_user['user_name'] ?? 'Admin') ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($current_user['user_Email'] ?? '') ?></p>
                            </div>
                            <a href="/admin/profile/profile.php" class="text-gray-700 hover:text-indigo-600">
                                <i class="fas fa-user-cog mr-3 text-gray-400"></i> Profile Settings
                            </a>
                            <div class="border-t border-gray-200"></div>
                            <a href="/admin/loginOut/logout.php" class="text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-3 text-red-400"></i> Logout
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu md:hidden" id="mobile-nav">
        <div class="flex justify-between items-center mb-6">
            <a href="../home/home.php" class="flex items-center">
                <img src="../../img/K&P logo.png" alt="K&P Fashion Logo" class="h-10">
                <span class="text-lg font-bold text-gray-800 ml-2">K&P Admin</span>
            </a>
            <button id="close-mobile-menu" class="text-gray-700 focus:outline-none">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="flex flex-col space-y-4">
            <a href="../home/home.php" class="py-2 px-4 rounded-lg <?= $current_page === '../home/home.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-home mr-3"></i> Home
            </a>
            <a href="../product/product.php" class="py-2 px-4 rounded-lg <?= $current_page === '../product/product.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-tshirt mr-3"></i> Products
            </a>
            <a href="../category/category.php" class="py-2 px-4 rounded-lg <?= $current_page === '../category/category.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-tshirt mr-3"></i> Category
            </a>
            <a href="../discount/discount.php" class="py-2 px-4 rounded-lg <?= $current_page === '../discount/discount.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-percentage mr-3"></i> Discounts
            </a>
            <a href="../order/orders.php" class="py-2 px-4 rounded-lg <?= $current_page === '../order/orders.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-shopping-cart mr-3"></i> Orders
            </a>
            <a href="../payment/payment.php" class="py-2 px-4 rounded-lg <?= $current_page === '../payment/payment.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-credit-card mr-3"></i> Payments
            </a>
            <a href="../customer/customers.php" class="py-2 px-4 rounded-lg <?= $current_page === '../customer/customers.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-users mr-3"></i> Customers
            </a>
            <a href="../staff/staff.php" class="py-2 px-4 rounded-lg <?= $current_page === '../staff/staff.php' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700' ?>">
                <i class="fas fa-user-tie mr-3"></i> Staff
            </a>
            
            <div class="border-t border-gray-200 my-2 pt-2">
                <a href="/admin/page/profile.php" class="py-2 px-4 rounded-lg text-gray-700">
                    <i class="fas fa-user-cog mr-3"></i> Profile Settings
                </a>
                <a href="/admin/page/logout.php" class="py-2 px-4 rounded-lg text-red-600 mt-2 block">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content Wrapper - adds padding for fixed header -->
    <div class="pt-20">
        <!-- Main content goes here -->

        <script>
    function toggleDropdown(event) {
        event.preventDefault();
        const dropdown = event.currentTarget.parentElement;
        dropdown.classList.toggle('show');
    }

    // Close dropdown if clicked outside
    window.addEventListener('click', function(event) {
        if (!event.target.closest('.profile-dropdown')) {
            const dropdowns = document.querySelectorAll('.profile-dropdown');
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
</script>
    
