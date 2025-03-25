<?php
require_once '../../_base.php';
session_start();

$current_user = json_decode($_SESSION['current_user'], true);
$photo = isset($current_user['admin_profile_pic']) 
    ? $current_user['admin_profile_pic'] 
    : 'default.png';

$photoPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/pic/' . $photo;
if (!file_exists($photoPath)) {
    $photo = 'default.png';
    error_log("Missing profile photo: " . $photoPath);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="../css/appAdmin.css" />
    <title>TARUMT Theatre Society | Admin Dashboard</title>
    <style>
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .profile-dropdown.show .dropdown-content {
            display: block;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="navbar">
            <a href="home.php"><img src="pic/logo.png"></a>
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="product.php">Product</a></li>
                <li><a href="discount.php">Discount</a></li>
                <li><a href="order.php">Order</a></li>
                <li><a href="payment.php">Payment</a></li>
                <li><a href="customer.php">Customer</a></li>
                <li><a href="staff.php">Staff</a></li>

                <div class="profile-dropdown">
                    <a href="#" class="profile-icon" onclick="toggleDropdown(event)">
                        <div class="profile-photo-container-header">
                            <img src="/admin/pic/<?= htmlspecialchars($photo) ?>" alt="Profile Photo" class="profile-photo-header">
                        </div>
                    </a>
                    <div class="dropdown-content" id="dropdown">
                        <a href="/admin/page/profile.php">Profile</a>
                        <a href="/admin/page/logout.php">Logout</a>
                    </div>
                </div>
            </ul>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
    <script>
    function toggleDropdown(event) {
        event.stopPropagation();
        document.querySelector('.profile-dropdown').classList.toggle('show');
    }

    window.addEventListener('click', function() {
        document.querySelector('.profile-dropdown').classList.remove('show');
    });
    </script>
</body>
</html>