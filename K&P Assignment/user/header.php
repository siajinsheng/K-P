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
    <title>Header</title>
</head>
<body>
    <div class="navbar">
        <div class="header-left">
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <a href="../../index.php"><img src="/user/image/K&P logo.png" alt="K&P Logo"></a>
            </div>
        </div>

        <div class="header-right">
            <ul>
                <li><a href="/user/page/index.php">Home</a></li>
                <li><a href="/user/page/products.php">Product</a></li>
                <li><a href="/user/page/shopping-bag.php"><i class="fas fa-shopping-bag"></i></a></li>
            </ul>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <ul>
            <li><a href="/user/page/index.php">Home</a></li>
            <li><a href="/user/page/products.php">Product</a></li>
            <li><a href="/user/page/about-us.php">About Us</a></li>
            <li><a href="/user/page/login.php">Log In</a></li>
            <li><a href="/user/page/faq.php">FAQ</a></li>
            <li><a href="/user/page/shopping-bag.php">Shopping Bag</a></li>
            <li><a href="/user/page/review.php">Review</a></li>
            <li><a href="/user/page/help&support.php">Help & Support</a></li>
        </ul>
    </div>

    <div class="overlay" id="overlay"></div>

    <script src="/user/js/header.js"></script>
</body>
</html>