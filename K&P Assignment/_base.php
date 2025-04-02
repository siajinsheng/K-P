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
    // Only start a new session if one isn't already active
    if (session_status() == PHP_SESSION_NONE) {
        // Set the session cookie parameters
        $current_domain = $_SERVER['HTTP_HOST'];
        $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => 86400, // 24 hours (adjust as needed)
            'path' => '/',       // Make sure cookie works across entire site
            'domain' => $current_domain,
            'secure' => $is_secure,
            'httponly' => true,  // Prevent JavaScript access
            'samesite' => 'Lax' // Relaxed CSRF protection (change to 'Strict' for more security)
        ]);
        
        // Start the session
        session_start();
        
        // Set headers to prevent caching for pages with session data
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        
        // Regenerate session ID periodically to prevent fixation
        $session_max_lifetime = 30 * 60; // 30 minutes
        if (!isset($_SESSION['_session_started'])) {
            $_SESSION['_session_started'] = time();
            $_SESSION['_last_regeneration'] = time();
        } elseif (time() - $_SESSION['_last_regeneration'] > $session_max_lifetime) {
            // Regenerate session ID and update timestamp
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
        }
    }
    
    // Always make sure user object is properly structured
    if (isset($_SESSION['user']) && is_object($_SESSION['user'])) {
        // Make sure critical properties are defined
        if (!isset($_SESSION['user']->user_id) || empty($_SESSION['user']->user_id)) {
            // Log the issue
            error_log('Session user exists but has no user_id - session may be corrupted');
            
            // Reset corrupted session
            unset($_SESSION['user']);
        }
    }
}

// Enhanced auth function for better session checks
function auth(...$roles) {
    safe_session_start(); // Ensure the session is started
    
    global $_db;
    
    // Debug the session data
    error_log("Auth check - Session data: " . (isset($_SESSION['user']) ? 
        "User: {$_SESSION['user']->user_name}, ID: {$_SESSION['user']->user_id}" : 
        "No user in session"));
    
    // Check if a user is logged in
    if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
        error_log("Auth failed - No user in session or missing user_id");
        temp('info', 'Please log in to access this page');
        redirect('login.php');
    }

    // Get the user object from session
    $user = $_SESSION['user'];
    
    // Check if user exists and is active
    try {
        $stm = $_db->prepare('SELECT * FROM user WHERE user_id = ?');
        $stm->execute([$user->user_id]);
        $db_user = $stm->fetch();
        
        if (!$db_user) {
            error_log("Auth failed - User ID {$user->user_id} not found in database");
            temp('info', 'Your account could not be found');
            logout('login.php');
        }
        
        if ($db_user->status !== "Active") {
            error_log("Auth failed - User ID {$user->user_id} has non-active status: {$db_user->status}");
            temp('info', 'Your account has been blocked or is inactive');
            logout('login.php');
        }
        
        // Update session with latest user data
        $_SESSION['user'] = $db_user;
        
        // If no specific roles are required (empty roles array), any authenticated user is allowed
        if (empty($roles)) {
            return; // User is authenticated, no specific role required
        }
        
        // Check if user has one of the required roles
        $hasRequiredRole = false;
        foreach ($roles as $role) {
            if ($db_user->role == $role) {
                $hasRequiredRole = true;
                break;
            }
        }
        
        // If no matching role is found, redirect
        if (!$hasRequiredRole) {
            error_log("Auth failed - User ID {$user->user_id} role {$db_user->role} does not match required roles");
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

// Obtain uploaded file --> cast to object
function get_file($key)
{
    $f = $_FILES[$key] ?? null;

    if ($f && $f['error'] == 0) {
        return (object)$f;
    }

    return null;
}

// Crop, resize and save photo
function save_photo($file, $target_dir = 'Upload_Images', $width = 200, $height = 200)
{
    $photo = uniqid() . '.jpg';
    require_once 'admin/lib/SimpleImage.php';
    $img = new SimpleImage();
    $img->fromFile($file['tmp_name'])
        ->thumbnail($width, $height)
        ->toFile("$target_dir/$photo", 'image/jpeg');

    return $photo;
}


// Crop, resize and save photo
function save_photo_user($f, $folder, $width = 200, $height = 200)
{
    $photo = uniqid() . '.jpg';

    require_once 'admin/lib/SimpleImage.php';
    $img = new SimpleImage();
    $img->fromFile($f->tmp_name)
        ->thumbnail($width, $height)
        ->toFile("$folder/$photo", 'image/jpeg');

    return $photo;
}

// Is email?
function is_email($value)
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
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


/**
 * Validate and format Malaysian phone number
 * - Validates that the phone number is 9-10 digits (without country code)
 * - Automatically adds the Malaysian country code (60) if not present
 * - Returns formatted phone number or false if invalid
 */
function validate_malaysian_phone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if phone already has country code
    if (strpos($phone, '60') === 0) {
        // Already has country code, check total length (11-12 digits)
        if (strlen($phone) < 11 || strlen($phone) > 12) {
            return false;
        }
        return $phone;
    } else {
        // Check if it's a valid Malaysian number (9-10 digits)
        if (strlen($phone) < 9 || strlen($phone) > 10) {
            return false;
        }
        
        // Check if first digit is 1 (as per Malaysian format)
        if (substr($phone, 0, 1) !== '1') {
            return false;
        }
        
        // Add country code and return
        return '60' . $phone;
    }
}

/**
 * Enhanced password validation
 * - Requires at least one uppercase letter
 * - Requires at least one lowercase letter
 * - Requires at least one number
 * - Requires at least one special character
 * - Requires at least 8 characters in length
 */
function validate_password($password) {
    $uppercase = preg_match('/[A-Z]/', $password);
    $lowercase = preg_match('/[a-z]/', $password);
    $number    = preg_match('/[0-9]/', $password);
    $special   = preg_match('/[^a-zA-Z0-9]/', $password);
    $length    = strlen($password) >= 8;
    
    if (!$uppercase) {
        return 'Password must contain at least one uppercase letter';
    }
    
    if (!$lowercase) {
        return 'Password must contain at least one lowercase letter';
    }
    
    if (!$number) {
        return 'Password must contain at least one number';
    }
    
    if (!$special) {
        return 'Password must contain at least one special character';
    }
    
    if (!$length) {
        return 'Password must be at least 8 characters long';
    }
    
    return true;
}

/**
 * Generate phone input field with placeholder for Malaysian format
 */
function html_phone_input($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='tel' id='$key' name='$key' value='$value' placeholder='Example: 182259156' $attr>";
}

/**
 * Generate password input with format hint
 */
function html_password_input($key, $attr = '') {
    echo "<input type='password' id='$key' name='$key' placeholder='Example: P@ssw0rd' $attr>";
}


// Logout function (updated for new schema)
function logout($url = null)
{
    safe_session_start();
    
    // Clear all session variables related to user
    unset($_SESSION['user']);
    
    // Additional session cleanup (for backward compatibility)
    unset($_SESSION['admin_user']);
    unset($_SESSION['current_user']);
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    
    // Destroy the session completely
    session_unset();
    session_destroy();

    // Clear the "remember me" cookies
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Redirect to the specified URL or the login page if none is provided
    $redirect_url = $url ?? 'login.php';
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



// ============================================================================
// Error Handlings
// ============================================================================

// Global error array
$_err = [];

// Generate <span class='err'>
function err($key)
{
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    } else {
        echo '<span></span>';
    }
}

function showError($message)
{
    echo "<script type='text/javascript'>alert(" . json_encode($message) . ");</script>";
}

// ============================================================================
// Email Configuration (PHPMailer)
// ============================================================================

/**
 * Initialize and return PHPMailer object
 */
function get_mail()
{
    require_once 'User/lib/PHPMailer.php';
    require_once 'User/lib/SMTP.php';

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->Username = 'siajs-wm22@student.tarc.edu.my';
    $m->Password = '20040419Sjs.';
    $m->CharSet = 'utf-8';
    $m->setFrom($m->Username, 'K&P Store');

    return $m;
}

/**
 * Development helper to bypass email sending when in development environment
 */
function is_development() {
    $dev_hosts = ['localhost', '127.0.0.1'];
    return in_array($_SERVER['SERVER_NAME'], $dev_hosts) || 
           substr($_SERVER['SERVER_NAME'], 0, 4) === 'test' ||
           substr($_SERVER['SERVER_NAME'], 0, 3) === 'dev';
}

/**
 * Generate a secure activation token
 */
function generate_activation_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Generate <input type='text'>
 */
function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='password'>
 */
function html_password($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='password' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='email'>
 */
function html_email($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='email' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='tel'>
 */
function html_tel($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='tel' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='file'>
 */
function html_file($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

/**
 * Generate radio buttons for gender selection
 */
function html_gender($key, $selected = null) {
    $genders = [
        'Male' => 'Male',
        'Female' => 'Female',
        'Other' => 'Other'
    ];
    
    echo '<div class="gender-options">';
    foreach ($genders as $value => $label) {
        $checked = ($selected === $value) ? 'checked' : '';
        echo "
        <div class='gender-option'>
            <input type='radio' id='{$key}_{$value}' name='{$key}' value='{$value}' {$checked}>
            <label for='{$key}_{$value}'>{$label}</label>
        </div>
        ";
    }
    echo '</div>';
}

// Temporary test - remove after verification
function test_err_function() {
    global $_err;
    $_err['test'] = 'This is a test error message';
    err('test');
}
// test_err_function(); // Uncomment to test, then comment out after