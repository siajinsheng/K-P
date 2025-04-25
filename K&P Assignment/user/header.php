<?php
// Ensure session is started to access user info
if (!isset($_SESSION) && session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get cart count if user is logged in
$cartCount = 0;
if (isset($_SESSION['user']) && isset($_db)) {
    try {
        $stm = $_db->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stm->execute([$_SESSION['user']->user_id]);
        $cartCount = (int)$stm->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting cart count: " . $e->getMessage());
    }
}

// Current time for display
$current_time = date('Y-m-d H:i:s');
$current_user = isset($_SESSION['user']) ? $_SESSION['user']->user_name : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/user/css/header.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="navbar">
        <div class="header-left">
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <a href="../../index.php"><img src="/img/K&P logo.png" alt="K&P Logo"></a>
            </div>
        </div>

        <div class="header-right">
            <ul>
                <li><a href="/index.php">Home</a></li>
                <li><a href="/user/page/products.php">Product</a></li>
                <li>
                    <a href="/user/page/shopping-bag.php" class="cart-icon-container">
                        <i class="fas fa-shopping-bag"></i>
                        <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="user-profile-container">
                    <?php if (isset($_SESSION['user'])): ?>
                        <?php
                            $user = $_SESSION['user'];
                            $profilePic = $user->user_profile_pic ?? 'default-profile.jpg';
                            $profilePicPath = "/img/{$profilePic}";
                            $userName = $user->user_name ?? 'User';
                        ?>
                        <div class="user-profile">
                            <img src="<?= $profilePicPath ?>" alt="<?= $userName ?>" class="profile-pic">
                            <i class="fas fa-chevron-down"></i>
                            <div class="profile-dropdown">
                                <div class="profile-header">
                                    <img src="<?= $profilePicPath ?>" alt="<?= $userName ?>">
                                    <div class="profile-info">
                                        <span class="profile-name"><?= $userName ?></span>
                                        <span class="profile-email"><?= $user->user_Email ?? '' ?></span>
                                    </div>
                                </div>
                                <ul>
                                    <li><a href="/user/page/profile.php"><i class="fas fa-user"></i> View Profile</a></li>
                                    <li><a href="/user/page/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/user/page/login.php" class="login-button">
                            <i class="fas fa-user"></i>
                            <span>Login</span>
                        </a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <ul>
            <li><a href="/index.php">Home</a></li>
            <li><a href="/user/page/products.php">Product</a></li>
            <li><a href="/user/page/about-us.php">About Us</a></li>
            <?php if (!isset($_SESSION['user'])): ?>
                <li><a href="/user/page/login.php">Log In</a></li>
            <?php else: ?>
                <li><a href="/user/page/profile.php">My Profile</a></li>
                <li><a href="/user/page/logout.php">Log Out</a></li>
            <?php endif; ?>
            <li><a href="/user/page/faq.php">FAQ</a></li>
            <li><a href="/user/page/shopping-bag.php">
                Shopping Bag
                <?php if ($cartCount > 0): ?>
                    <span class="cart-count-sidebar"><?= $cartCount ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="/user/page/review.php">Review</a></li>
            <li><a href="/user/page/help&support.php">Help & Support</a></li>
        </ul>
    </div>

    <div class="overlay" id="overlay"></div>

    <!-- Current user and time info -->
    <!-- <?php if ($_SESSION['user'] && $_SESSION['user']->role === 'member'): ?>
    <div class="system-info">
        <div class="system-info-content">
            <span class="system-time"><?= $current_time ?></span>
            <span class="system-user"><?= $current_user ?></span>
        </div>
    </div>
    <?php endif; ?> -->

    <script src="/user/js/header.js"></script>
</body>
</html>