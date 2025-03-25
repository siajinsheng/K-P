<?php

// ============================================================================
// PHP Setups
// ============================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================================================
// General Page Functions
// ============================================================================

// Is GET request?
function is_get()
{
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

// Is POST request? 
function is_post()
{
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

// Obtain GET parameter
function get($key, $value = null)
{
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter
function post($key, $value = null)
{
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain REQUEST (GET and POST) parameter
function req($key, $value = null)
{
    $value = $_REQUEST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Global PDO object
$_db = new PDO('mysql:dbname=k&p;charset=utf8mb4', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Encode HTML special characters
function encode($value)
{
    return htmlentities($value);
}

// Generate <input type='search'>
function html_search($key, $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
}

// Redirect to URL
function redirect($url = null)
{
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

// Set or get temporary session variable
function temp($key, $value = null)
{
    session_start(); // Ensure session is started
    
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    } else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// Logout user
function logout($role = null, $url = null)
{
    session_start(); // Ensure the session is started to modify session variables

    // Unset the session based on the role
    if ($role === 'Member') {
        unset($_SESSION['customer_user']);
    } else {
        unset($_SESSION['admin_user']);
    }

    // Clear the "remember me" cookie
    setcookie('remember_me_token', '', time() - 3600, '/'); // Expire the cookie

    // Redirect to the specified URL or the root if none is provided
    $redirect_url = $url ?? '/';
    redirect($redirect_url);
}

// Generate button
function html_button($href, $text, $attr = '')
{
    echo "<a href='$href' class='button' $attr>$text</a>";
}

// Generate table headers <th>
function table_headers($fields, $sort, $dir, $href = '')
{
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class

        if ($k == $sort) {
            $d = $dir == 'asc' ? 'desc' : 'asc';
            $c = $dir;
        }

        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}


// Authorization
function auth(...$roles)
{
    // Add debug output
    echo "Roles passed: " . print_r($roles, true) . "<br>";
    echo "Admin user: " . print_r($_SESSION['admin_user'], true) . "<br>";
    
    session_start(); // Start the session
    
    global $_db;
    
    // Check if a user is logged in
    if (!isset($_SESSION['customer_user']) && !isset($_SESSION['admin_user'])) {
        redirect('login.php'); // Not logged in, redirect to login
    }

    // Check if the user status is blocked
    if (isset($_SESSION['customer_user'])) {
        try {
            $customer_user = $_SESSION['customer_user'];

            $stm = $_db->prepare('SELECT * FROM customer WHERE cus_id = ?');
            $stm->execute([$customer_user->cus_id]);
            $u = $stm->fetch();
        
            if(!$u || $u->cus_status === "blocked"){
                temp('info','Your account has been BLOCKED');
                logout('Member','login.php');
            }
        } catch (PDOException $e) {
            // Log the error
            error_log("Customer authentication error: " . $e->getMessage());
            redirect('login.php');
        }
    }

    if (isset($_SESSION['admin_user'])) {
        try {
            $admin_user = $_SESSION['admin_user'];

            // Verify admin_user is an object and has the expected properties
            if (!is_object($admin_user)) {
                temp('info', 'Invalid admin session');
                logout('Admin', 'login.php');
            }

            $stm = $_db->prepare('SELECT * FROM admin WHERE admin_id = ?');
            $stm->execute([$admin_user->admin_id]);
            $u = $stm->fetch();
        
            if(!$u || $u->admin_status === "blocked"){
                temp('info','Your account has been BLOCKED');
                logout('Admin','login.php');
            }
        } catch (PDOException $e) {
            // Log the error
            error_log("Admin authentication error: " . $e->getMessage());
            redirect('login.php');
        }
    }

    // Determine the user roles
    $userRoles = []; // Initialize an array to hold roles

    if (isset($_SESSION['customer_user'])) {
        $userRoles[] = 'Member'; // Add Member role
    }

    if (isset($_SESSION['admin_user'])) {
        $userRoles[] = 'Admin';   // Add Admin role

        $admin_user = $_SESSION['admin_user'];

        // Safely check role, adding a null coalescing operator
        if (isset($admin_user->role) && $admin_user->role === 'Manager') {
            $userRoles[] = 'Manager'; // Add Manager role
        }
    }

    // Check if the user's role is in the allowed roles
    foreach ($roles as $role) {
        if (in_array($role, $userRoles)) {
            return; // User is authenticated and has the right role
        }
    }

    redirect('login.php'); // User does not have permission, redirect to login
}

// Additional debug function to help identify session issues
function debug_session_user($user) {
    if (!is_object($user)) {
        echo "User is not an object. Type: " . gettype($user) . "<br>";
        return;
    }

    echo "User Object Properties:<br>";
    foreach ($user as $key => $value) {
        echo "$key: " . print_r($value, true) . "<br>";
    }
}