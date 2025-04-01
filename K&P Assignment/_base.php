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
    require_once 'Admin/lib/SimpleImage.php';
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

    require_once 'Admin/lib/SimpleImage.php';
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

// Logout user
function logout($role = null, $url = null)
{
    safe_session_start(); // Ensure the session is started to modify session variables

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
    
    safe_session_start(); // Start the session
    
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
function get_mail() {
    require_once 'path/to/PHPMailer/src/PHPMailer.php';
    require_once 'path/to/PHPMailer/src/SMTP.php';
    require_once 'path/to/PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.siajinsheng@gmail.com'; // e.g., smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@your-domain.com';   // SMTP username
        $mail->Password   = 'your-email-password';          // SMTP password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                            // TCP port to connect to
        
        // Sender info
        $mail->setFrom('no-reply@your-domain.com', 'K&P Store');
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
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