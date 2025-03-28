<?php
if (!empty($_SESSION['current_user'])) {
    header("location:Home.php");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="CSS/app.css" />
    <link type="text/css" rel="stylesheet" href="CSS/login.css" />
</head>

<body>
<?php
    include ('header.php');

    $student_idErr = $studpasswordErr = "";

    if (isset($_POST['submit'])) {
    
        $student_id = $_REQUEST['student_id'];
        if ($student_id == null) {
            $student_idErr = 'Please Enter Student ID.';
        } else if (!preg_match("/^S\d{4}$/", $student_id)) {
            $student_idErr = 'Please Enter Correct Format ( Sxxxx )';
        } else {
            $student_id = $_POST['student_id'];
        }
    
        $studpassword = $_REQUEST['studpassword'];
        $uppercase = preg_match('@[A-Z]@', $studpassword);
        $lowercase = preg_match('@[a-z]@', $studpassword);
        $number = preg_match('@[0-9]@', $studpassword);
        $specialChars = preg_match('@[^\w]@', $studpassword);
    
        if ($studpassword == null) {
            $studpasswordErr = 'Please Enter Your Password.';
        } else if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($studpassword) < 8) {
            $studpasswordErr = 'The Password That You Have Entered Is Incorrect.';
        } else {
            $studpassword = $_POST['studpassword'];
        }
    
        if (empty($student_idErr) && empty($studpasswordErr)) {
            $sql = "select * from student where student_id = '$student_id' and studpassword = '$studpassword'";
            $result = mysqli_query($connection, $sql);
            $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    
            $hashedPassword = $row['studpassword'];
    
            $count = mysqli_num_rows($result);
    
                if ($count == 1) {
                    $_SESSION['studID'] = $row['student_id'];
                    $_SESSION['current_user'] = json_encode($row);
                    echo '<script>
                window.location.href = "Home.php";
                alert("Login Successfully !");
                </script>';
                } else {
                    echo '<script> 
                window.location.href = "login.php";
                alert("Login Failed. Invalid Student ID Or Password!!!")
                </script> ';
                }
                
        }
    }
    
    
    
    ?>
    <div class="login_form">
        <div class="container">
            <form action="" method="POST">
                <h2>LOGIN</h2>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="person"></ion-icon>
                    </span>
                    <label>Student ID</label>
                    <input type="text" name="student_id" value="<?php if (isset($_POST['student_id']))
                        echo $_POST['student_id']; ?>">
                    <div class="error">
                        <?php echo $student_idErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="lock-closed"></ion-icon>
                    </span>
                    <label>Password</label>
                    <input type="password" name="studpassword" value="<?php if (isset($_POST['studpassword']))
                        echo $_POST['studpassword']; ?>">
                    <div class="error">
                        <?php echo $studpasswordErr; ?>
                    </div>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox">Remember Me</label>
                </div>

                <button type="submit" name="submit" class="login-btn">LOGIN</button>

                <div class="login-register">
                    <p>Dont't Have An Account? <a href="register.php" class="register-link">Register</a></p>
                </div>
            </form>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>

</html>