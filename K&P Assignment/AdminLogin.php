<?php

session_start();
include ("connectDatabase.php");

$admin_idErr = $passwordErr = "";


if (isset ($_POST['submit'])) {

    $admin_id = $_REQUEST['admin_id'];
    if ($admin_id == null) {
        $admin_idErr = 'Please Enter Your Admin ID.';
    } else if (!preg_match("/^A\d{3}$/", $admin_id)) {
        $admin_idErr = 'Please Enter Correct Format ( Axxx )';
    } else {
        $admin_id = $_POST['admin_id'];
    }

    $password = $_REQUEST['password'];
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[^\w]@', $password);

    if ($password == null) {
        $passwordErr = 'Please Enter Your Password.';
    } else if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
        $passwordErr = 'The Password That You Have Entered Is Incorrect.';
    } else {
        $password = $_POST['password'];
    }

    if (empty ($admin_idErr) && empty ($passwordErr)) {
        $sql = "select * from admin where admin_id = '$admin_id' and password = '$password'";
        $result = mysqli_query($connection, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $count = mysqli_num_rows($result);

        if ($count == 1) {
            echo "<script>
            alert('Login Successfully !');
            window.location.href = 'adminHome.php';
            </script>";
            exit;
        } else {
            echo '<script>
                        window.location.href = "AdminLogin.php";
                        alert("Login Failed. Invalid ID or Password!!!")
                    </script>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="CSS/app.css" />
    <link type="text/css" rel="stylesheet" href="CSS/login.css" />
</head>

<body>
    <div class="login_form">
        <div class="container">
            <form action="" method="POST">
                <h2>ADMIN LOGIN</h2>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="person"></ion-icon>
                    </span>
                    <label>Admin ID</label>
                    <input type="text" name="admin_id"
                        value="<?php if (isset ($_POST['admin_id']))
                            echo $_POST['admin_id']; ?>">
                    <div class="error">
                        <?php echo $admin_idErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="lock-closed"></ion-icon>
                    </span>
                    <label>Admin Password</label>
                    <input type="password" name="password"
                        value="<?php if (isset ($_POST['password']))
                            echo $_POST['password']; ?>">
                    <div class="error">
                        <?php echo $passwordErr; ?>
                    </div>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox">Remember Me</label>
                </div>

                <button type="submit" name="submit" class="login-btn">LOGIN</button>

            </form>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <?php
    include ('footer(admin).php');
    ?>

</body>

</html>