<?php
session_start();
$_title = 'Admin - Login';
require '../../_base.php';

$admin_idErr = $passwordErr = "";

// Check if cookies are set and pre-fill the fields
if (isset($_COOKIE['admin_id']) && isset($_COOKIE['password'])) {
    $saved_admin_id = $_COOKIE['admin_id'];
    $saved_password = $_COOKIE['password'];
} else {
    $saved_admin_id = "";
    $saved_password = "";
}

// Redirect to home if already logged in
if (!empty($_SESSION['admin_user'])) {
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
    $admin_id = $_REQUEST['admin_id'];
    
    // Validate admin ID
    if ($admin_id == null) {
        $admin_idErr = 'Please Enter Your Admin ID.';
    } else if (!preg_match("/^[A-Z]{1,2}\d{3}$/", $admin_id)) {
        $admin_idErr = 'Please Enter Correct Format ( Axxx or AAxxx )';
    }

    $password = $_REQUEST['password'];
    
    // Password validation
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[^\w]@', $password);

    if ($password == null) {
        $passwordErr = 'Please Enter Your Password.';
    } else if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $passwordErr = 'The Password That You Have Entered Is Incorrect.';
    }

    // If no validation errors, proceed with login
    if (empty($admin_idErr) && empty($passwordErr)) {
        $sql = "SELECT * FROM admin WHERE admin_id = :admin_id AND admin_password = :password";
        $stmt = $_db->prepare($sql);
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        $row = $stmt->fetch();
        $count = $stmt->rowCount();

        if ($count == 1) {
            // Set multiple session variables for robust authentication
            $_SESSION['adminID'] = $row->admin_id;
            $_SESSION['admin_user'] = $row; // Full admin object
            $_SESSION['current_user'] = json_encode($row);

            // Determine role based on admin_role
            $role_map = [
                1 => 'Manager',
                2 => 'Supervisor',
                3 => 'Staff'
            ];
            $_SESSION['admin_role'] = $role_map[$row->admin_role] ?? 'Staff';

            // Remember Me functionality
            if (isset($_POST['remember_me'])) {
                setcookie('admin_id', $admin_id, time() + (86400 * 30), "/"); 
                setcookie('password', $password, time() + (86400 * 30), "/"); 
            } else {
                // Clear cookies if Remember Me is not checked
                setcookie('admin_id', '', time() - 3600, "/");
                setcookie('password', '', time() - 3600, "/");
            }

            echo "<script>
            alert('Login Successfully !');
            window.location.href = 'home.php';
            </script>";
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            echo '<script>
                    window.location.href = "login.php";
                    alert("Login Failed. Invalid ID or Password!!!")
                </script>';
        }
    }
}
echo print_r($_SESSION);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="../css/login.css" />
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
                        value="<?php echo isset($_POST['admin_id']) ? $_POST['admin_id'] : $saved_admin_id; ?>">
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
                        value="<?php echo isset($_POST['password']) ? $_POST['password'] : $saved_password; ?>">
                    <div class="error">
                        <?php echo $passwordErr; ?>
                    </div>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox" name="remember_me" <?php if (isset($_COOKIE['admin_id'])) echo 'checked'; ?>>Remember Me</label>
                </div>

                <button type="submit" name="submit" class="login-btn">LOGIN</button>

            </form>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <?php
    include('footer.php');
    ?>

</body>

</html>