<?php
include('connectDatabase.php');
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="CSS/header.css" />
    <title>Header</title>
</head>

<body>

    <div class="navbar">
        <a href="Home.php"><img src="pic/logo.png"></a>
        <ul>
            <li>
                <a href="Home.php">Home</a>
            </li>
            <li>
                <a href="event.php">Events</a>
            </li>
            <li>
                <a href="AboutUs.php">About Us</a>
            </li>
            <?php
            if (empty ($_SESSION['current_user'])) {
                echo '
                        <li>
                        <a href = "login.php" > Login</a >
                    </li>
                    <li>
                        <a href = "register.php" > Sign Up </a >
                    </li>';
            }
            if (!empty ($_SESSION['current_user'])) {
                echo '
                    <li>
                        <a href = "profile.php" > Profile</a >
                    </li>
                        <li>
                        <a href = "logOut.php" > Logout</a >
                    </li>';
            }
            ?>

        </ul>
    </div>
</body>

</html>