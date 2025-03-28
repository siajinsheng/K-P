<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="css/header.css" />
    <title>Header</title>
</head>

<body>

    <div class="navbar">
        <div class="header">
            <div class="navbar">
                <a href="#" onclick="toggleSidebar();"><i class="fa-solid fa-bars"></i></a>
                <a href="index.html"><img src="..\image\logo.jpeg" alt=""></a>
                <ul>
                    <li>
                        <a href="index.php">Home</a>
                    </li>
                    <li>
                        <a href="products.php">Product</a>
                    </li>
                    <li>
                        <a href="shopping-bag.php"><i class="fa-solid fa-bag-shopping"></i></a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar">
            <div class="overlay" onclick="toggleSidebar();"></div>
            <nav>
                <ul>
                    <li>
                        <a href="index.php">Home</a>
                    </li>
                    <li>
                        <a href="products.php">Product</a>
                    </li>
                    <li>
                        <a href="about-us.php">About Us</a>
                    </li>
                    <li>
                        <a href="membership.php">Log In</a>
                    </li>
                    <li>
                        <a href="faq.php">FAQ</a>
                    </li>
                    <li>
                        <a href="delivery.php">Delivery</a>
                    </li>
                    <li>
                        <a href="shopping-bag.php">Shopping Bag</a>
                    </li>
                    <li>
                        <a href="review.php">Review</a>
                    </li>
                    <li>
                        <a href="help&support.php">Help & Support</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
    <script src="js/app.js"></script>
</body>

</html>