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


// Authorization
function auth(...$roles)
{
    safe_session_start(); // Ensure the session is started
    
    global $_db;
    
    // Check if a user is logged in
    if (!isset($_SESSION['user'])) {
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
        
        if (!$db_user || $db_user->status !== "Active") {
            temp('info', 'Your account has been blocked or is inactive');
            logout('user', 'login.php');
        }
        
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
    setcookie('password', '', time() - 3600, '/');  // For backward compatibility

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
 * Check if email is unique in customer table
 */
function is_email_unique($email) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM customer WHERE cus_Email = ?");
    $stm->execute([$email]);
    return $stm->fetchColumn() == 0;
}

/**
 * Check if phone is unique in customer table
 */
function is_phone_unique($phone) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM customer WHERE cus_phone = ?");
    $stm->execute([$phone]);
    return $stm->fetchColumn() == 0;
}

/**
 * Activate user account using token
 */
function activate_account($token) {
    global $_db;
    
    // Check if token exists and not expired
    $stm = $_db->prepare("SELECT cus_id FROM customer WHERE activation_token = ? AND activation_expiry > NOW()");
    $stm->execute([$token]);
    $user = $stm->fetch();
    
    if ($user) {
        // Activate the account
        $stm = $_db->prepare("UPDATE customer SET cus_status = 'Active', activation_token = NULL, activation_expiry = NULL WHERE cus_id = ?");
        $stm->execute([$user->cus_id]);
        return true;
    }
    
    return false;
}

/**
 * Send activation email using PHPMailer
 */
function send_activation_email($email, $name, $activationToken) {
    try {
        $mail = get_mail();
        $mail->addAddress($email, $name);
        
        $subject = "Activate Your K&P Account";
        $activationLink = "https://your-kp-website.com/activate.php?token=" . $activationToken;
        
        $message = "
        <html>
        <head>
            <title>Account Activation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    background-color: #4CAF50; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 20px 0;
                }
                .footer { margin-top: 30px; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Welcome to K&P, $name!</h2>
                <p>Thank you for registering with K&P. To complete your registration, please click the button below to activate your account:</p>
                
                <p><a href='$activationLink' class='button'>Activate My Account</a></p>
                
                <p>Or copy and paste this link into your browser:<br>
                <small>$activationLink</small></p>
                
                <p>This activation link will expire in 24 hours.</p>
                
                <div class='footer'>
                    <p>If you didn't request this account, please ignore this email.</p>
                    <p>&copy; " . date('Y') . " K&P. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = "Welcome to K&P, $name!\n\nPlease click this link to activate your account: $activationLink\n\nThis link expires in 24 hours.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Failed to send activation email: " . $mail->ErrorInfo);
        return false;
    }
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