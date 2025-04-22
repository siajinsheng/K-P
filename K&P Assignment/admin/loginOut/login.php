<?php
session_start();
$_title = 'K&P - Login';
require '../../_base.php';

$user_EmailErr = $passwordErr = "";

// Check if cookies are set and pre-fill the fields
if (isset($_COOKIE['user_Email']) && isset($_COOKIE['password'])) {
    $saved_user_Email = $_COOKIE['user_Email'];
    $saved_password = $_COOKIE['password'];
} else {
    $saved_user_Email = "";
    $saved_password = "";
}

// Redirect to home if already logged in
if (!empty($_SESSION['user'])) {
    header("location:home.php");
    exit;
}

// Login attempt tracking
if (isset($_SESSION['last_attempt_time']) && (time() - $_SESSION['last_attempt_time']) > 10) {
    $_SESSION['login_attempts'] = 0;
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Too many failed attempts
if ($_SESSION['login_attempts'] >= 2 && (time() - $_SESSION['last_attempt_time']) < 10) {
    echo '<script>
            alert("Too many failed login attempts. Please try again after 10 seconds.");
            window.location.href = "login.php";
          </script>';
    exit;
}

if (isset($_POST['submit'])) {
    $user_Email = $_REQUEST['user_Email'];
    
    // Validate email
    if ($user_Email == null) {
        $user_EmailErr = 'Please enter your email address.';
    } else if (!is_email($user_Email)) {
        $user_EmailErr = 'Please enter a valid email address.';
    }

    $password = $_REQUEST['password'];
    
    // Password validation
    if ($password == null) {
        $passwordErr = 'Please enter your password.';
    }

    // If no validation errors, proceed with login
    if (empty($user_EmailErr) && empty($passwordErr)) {
        $sql = "SELECT * FROM user WHERE user_Email = :user_Email AND user_password = :password AND status = 'Active'";
        $stmt = $_db->prepare($sql);
        $stmt->bindParam(':user_Email', $user_Email);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        $row = $stmt->fetch();
        $count = $stmt->rowCount();

        if ($count == 1) {
            // Set multiple session variables for robust authentication
            $_SESSION['userID'] = $row->user_id;
            $_SESSION['user'] = $row; // Full user object
            $_SESSION['current_user'] = json_encode($row);
            $_SESSION['user_role'] = $row->role;

            // Remember Me functionality
            if (isset($_POST['remember_me'])) {
                setcookie('user_Email', $user_Email, time() + (86400 * 30), "/"); 
                setcookie('password', $password, time() + (86400 * 30), "/"); 
            } else {
                // Clear cookies if Remember Me is not checked
                setcookie('user_Email', '', time() - 3600, "/");
                setcookie('password', '', time() - 3600, "/");
            }

            echo "<script>
            alert('Login Successfully!');
            window.location.href = '../home/home.php';
            </script>";
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            echo '<script>
                    window.location.href = "login.php";
                    alert("Login Failed. Invalid Email or Password or Account is Inactive!")
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
    <title>K&P Store | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="login.css" />
</head>

<body>
    <div class="login_form">
        <div class="container">
            <form action="" method="POST">
                <h2>K&P STORE LOGIN</h2>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="mail"></ion-icon>
                    </span>
                    <label>Email</label>
                    <input type="email" name="user_Email"
                        value="<?php echo isset($_POST['user_Email']) ? $_POST['user_Email'] : $saved_user_Email; ?>">
                    <div class="error">
                        <?php echo $user_EmailErr; ?>
                    </div>
                </div>

                <div class="inside-box">
                    <span class="icon">
                        <ion-icon name="lock-closed"></ion-icon>
                    </span>
                    <label>Password</label>
                    <input type="password" name="password"
                        value="<?php echo isset($_POST['password']) ? $_POST['password'] : $saved_password; ?>">
                    <div class="error">
                        <?php echo $passwordErr; ?>
                    </div>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox" name="remember_me" <?php if (isset($_COOKIE['user_Email'])) echo 'checked'; ?>>Remember Me</label>
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button type="submit" name="submit" class="login-btn">LOGIN</button>
                
                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Register Now</a></p>
                </div>
            </form>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

</body>

</html>