<?php
if(!empty($_SESSION['current_user'])){
    header("location:Home.php");
}

include('connectDatabase.php');
$studNameErr = $student_idErr = $emailErr = $studpasswordErr = $constudpasswordErr = "";

if (isset($_POST['submit'])) {

    $studName = $_REQUEST['studName'];
    if ($studName == null) {
        $studNameErr = "Please Enter Student Name.";
    } else if (!preg_match("/^[a-zA-Z-' ]*$/", $studName)) {
        $studNameErr = 'Only Letters Can Be Allowed.';
    } else {
        $s_studName = $_POST['studName'];
    }

    $student_id = $_REQUEST['student_id'];
    if ($student_id == null) {
        $student_idErr = 'Please Enter Student ID.';
    } else if (!preg_match("/^S\d{4}$/", $student_id)) {
        $student_idErr = 'Please Enter Correct Format ( Sxxxx )';
    } else {
        $s_student_id = $_POST['student_id'];
    }

    $email = $_REQUEST['email'];
    if ($email == null) {
        $emailErr = 'Please Enter Your Email.';
    } else if (!preg_match("/^[_a-z0-9-+]+(\.[a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/", $email)) {
        $emailErr = 'Please Enter Email Format.';
    } else {
        $s_studEmail = $_POST['email'];
    }

    $studpassword = $_REQUEST['studpassword'];
    $uppercase = preg_match('@[A-Z]@', $studpassword);
    $lowercase = preg_match('@[a-z]@', $studpassword);
    $number = preg_match('@[0-9]@', $studpassword);
    $specialChars = preg_match('@[^\w]@', $studpassword);

    if ($studpassword == null) {
        $studpasswordErr = 'Please Enter Your Password.';
    } else if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($studpassword) < 8) {
        $studpasswordErr = 'Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character..';
    } else {
        $s_studpassword = $_POST['studpassword'];
    }

    $constudpassword = $_REQUEST['constudpassword'];
    if ($constudpassword == null) {
        $constudpasswordErr = 'Please Enter Password To Confirm.';
    } else if ($constudpassword != $studpassword) {
        $constudpasswordErr = 'Confirm Password Not Match With Password';
    } else {
        $s_constudpassword = $_POST['constudpassword'];
    }

    if (empty($studNameErr) && empty($student_idErr) && empty($emailErr) && empty($studpasswordErr) && empty($constudpasswordErr)) {

        $sql = "insert into student (student_id, studName, studEmail, studpassword, constudpassword)
        values ( '$s_student_id', '$s_studName', '$s_studEmail', '$s_studpassword', '$s_constudpassword')";

        $result = mysqli_query($connection, $sql);
        $count = mysqli_affected_rows($connection);
        if ($count == 1) {
            echo "<script>
            alert('Register Successfully !');
                </script>";
            header("location:login.php");
        } else {
            echo '<script> 
            window.location.href = "register.php";
            alert("Register Failed... Student Existed Already!!!")
            </script> ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />

    <link type="text/css" rel="stylesheet" href="CSS/app.css" />
    <link type="text/css" rel="stylesheet" href="CSS/register.css" />
</head>

<body>

    <?php
    include ('header.php');
    ?>

    <div class="register_form">
        <div class="container">
            <form method="POST" action="">
                <h2>REGISTRATION</h2>
                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="person"></ion-icon>
                    </span>
                    <label>Name</label>
                    <input type="text" name="studName"
                        value="<?php if (isset ($_POST['studName']))
                            echo $_POST['studName']; ?>">
                    <div class="error">
                        <?php echo $studNameErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="person"></ion-icon>
                    </span>
                    <label>Student ID</label>
                    <input type="text" name="student_id"
                        value="<?php if (isset ($_POST['student_id']))
                            echo $_POST['student_id']; ?>">
                    <div class="error">
                        <?php echo $student_idErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="mail"></ion-icon>
                    </span>
                    <label>Email</label>
                    <input type="text" name="email" value="<?php if (isset ($_POST['email']))
                        echo $_POST['email']; ?>">
                    <div class="error">
                        <?php echo $emailErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="lock-closed"></ion-icon>
                    </span>
                    <label>Password</label>
                    <input type="password" name="studpassword" id="passwordField"
                        value="<?php if (isset ($_POST['studpassword']))
                            echo $_POST['studpassword']; ?>">
                    <div class="error">
                        <?php echo $studpasswordErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="lock-closed"></ion-icon>
                    </span>
                    <label>Confirm Password</label>
                    <input type="password" name="constudpassword"
                        value="<?php if (isset ($_POST['constudpassword']))
                            echo $_POST['constudpassword']; ?>">
                    <div class="error">
                        <?php echo $constudpasswordErr; ?>
                    </div>
                </div>

                <div class="remember-forgot">
                    <label><input type="checkbox"> I Agree To The Terms & Conditions</label>
                </div>

                <button type="submit" name="submit" class="login-btn">Register</button>

                <div class="login-register">
                    <p>Already Have An Account? <a href="login.php" class="login-link">Login</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById("passwordField");
            if (passwordField.type === "password") {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
    </script>



    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <?php
    include ('footer.php');
    ?>
</body>

</html>