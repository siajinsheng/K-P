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

function safe_session_start() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

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
    safe_session_start(); // Ensure session is started
    
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    } else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// Is unique?
function is_unique($value, $table, $field)
{
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

// Check password strength
function is_strong_password($password) {
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $special   = preg_match('@[^\w]@', $password);
    
    return $uppercase && $lowercase && $number && $special && strlen($password) >= 8;
}

// Verify password matches confirmation
function is_password_match($password, $confirm_password) {
    return $password === $confirm_password;
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
    safe_session_start(); // Ensure the session is started
    
    global $_db;
    
    // Detailed role mapping
    $role_map = [
        1 => 'Manager',
        2 => 'Supervisor',
        3 => 'Staff'
    ];

    // Check if a user is logged in
    if (!isset($_SESSION['admin_user']) && !isset($_SESSION['current_user'])) {
        temp('info', 'Please log in to access this page');
        redirect('login.php');
    }

    // Try to get the user object
    $user = isset($_SESSION['admin_user']) 
        ? $_SESSION['admin_user'] 
        : json_decode($_SESSION['current_user']);

    // Check if user status is blocked
    try {
        // Check admin user
        if ($user) {
            $stm = $_db->prepare('SELECT * FROM admin WHERE admin_id = ?');
            $stm->execute([$user->admin_id]);
            $db_user = $stm->fetch();
        
            if (!$db_user || $db_user->admin_status === "Inactive") {
                temp('info', 'Your admin account has been BLOCKED or INACTIVE');
                logout('Admin', 'login.php');
            }
        }

        // Prepare user roles for checking
        $userRoles = [
            'Admin',  // Generic admin role
            $role_map[$user->admin_role] ?? 'Staff',  // Mapped role
            (string)$user->admin_role,  // Numeric role
            '1'  // Backward compatibility
        ];

        // Debug logging (optional)
        error_log('User Roles: ' . print_r($userRoles, true));
        error_log('Required Roles: ' . print_r($roles, true));

        // Check if any of the required roles match user roles
        $hasRequiredRole = false;
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                $hasRequiredRole = true;
                break;
            }
        }

        // If no matching role is found, redirect
        if (!$hasRequiredRole) {
            temp('info', 'You do not have permission to access this page');
            redirect('login.php');
        }

    } catch (PDOException $e) {
        // Log the error
        error_log("Authentication error: " . $e->getMessage());
        temp('error', 'An authentication error occurred');
        redirect('login.php');
    }
}

// Logout function with improved handling
function logout($role = null, $url = null)
{
    safe_session_start();

    // Clear all session variables related to user
    unset($_SESSION['admin_user']);
    unset($_SESSION['current_user']);
    unset($_SESSION['adminID']);
    unset($_SESSION['admin_role']);

    // Clear the "remember me" cookies
    setcookie('admin_id', '', time() - 3600, '/');
    setcookie('password', '', time() - 3600, '/');

    // Redirect to the specified URL or the root if none is provided
    $redirect_url = $url ?? '/';
    redirect($redirect_url);
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

// Generate <select>
function html_select($key, $items, $default = '- Select One -', $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
    }
    echo '</select>';
}