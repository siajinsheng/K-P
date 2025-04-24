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

function safe_session_start()
{
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
function auth(...$roles)
{
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
    require_once 'lib/SimpleImage.php';
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

    require_once 'lib/SimpleImage.php';
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
function is_strong_password($password)
{
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $special   = preg_match('@[^\w]@', $password);

    return $uppercase && $lowercase && $number && $special && strlen($password) >= 8;
}

// Verify password matches confirmation
function is_password_match($password, $confirm_password)
{
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
function validate_malaysian_phone($phone)
{
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
function validate_password($password)
{
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
function debug_session_user($user)
{
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

/**
 * Development helper to bypass email sending when in development environment
 * 
 * @return bool True if in development environment, false otherwise
 */
function is_development() {
    // TEMPORARY OVERRIDE - Force emails to be sent even in development
    return false;
    
    // Original implementation
    // $dev_hosts = ['localhost', '127.0.0.1'];
    // return in_array($_SERVER['SERVER_NAME'], $dev_hosts) || 
    //     substr($_SERVER['SERVER_NAME'], 0, 4) === 'test' ||
    //     substr($_SERVER['SERVER_NAME'], 0, 3) === 'dev';
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
    require_once 'user/lib/PHPMailer.php';
    require_once 'user/lib/SMTP.php';

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->Username = 'siajs-wm22@student.tarc.edu.my';
    $m->Password = 'wwhg dpgh abas xqzu';
    $m->CharSet = 'utf-8';
    $m->setFrom($m->Username, 'K&P Store');

    return $m;
}

/**
 * Generate a secure token
 * 
 * @param int $length Length of the token
 * @return string The generated token
 */
function generate_token($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Create a new token in the database
 * 
 * @param string $user_id User ID
 * @param string $type Token type ('email_verification' or 'password_reset')
 * @param int $expiry_hours Hours until token expires
 * @return string The generated token
 */
function create_token($user_id, $type = 'email_verification', $expiry_hours = 24)
{
    global $_db;

    // Generate token
    $token = generate_token();

    // Set expiry
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

    // Delete any existing tokens of the same type for this user
    $stm = $_db->prepare("DELETE FROM tokens WHERE user_id = ? AND type = ?");
    $stm->execute([$user_id, $type]);

    // Insert new token
    $stm = $_db->prepare("INSERT INTO tokens (user_id, token, type, expires_at) VALUES (?, ?, ?, ?)");
    $stm->execute([$user_id, $token, $type, $expires_at]);

    return $token;
}

/**
 * Verify a token from the database
 * 
 * @param string $token Token to verify
 * @param string $type Token type ('email_verification' or 'password_reset')
 * @return object|false Returns user object if valid, false otherwise
 */
function verify_token($token, $type = 'email_verification')
{
    global $_db;

    try {
        // Query token and join with user
        $stm = $_db->prepare("
            SELECT u.*, t.expires_at, t.id as token_id 
            FROM tokens t
            JOIN user u ON t.user_id = u.user_id
            WHERE t.token = ? AND t.type = ?
        ");
        $stm->execute([$token, $type]);
        $result = $stm->fetch();

        // If no result or expired token
        if (!$result || strtotime($result->expires_at) < time()) {
            return false;
        }

        return $result;
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a token from the database
 * 
 * @param string $token Token to delete
 * @param string $type Token type
 * @return bool True if deleted, false otherwise
 */
function delete_token($token, $type = 'email_verification')
{
    global $_db;

    try {
        $stm = $_db->prepare("DELETE FROM tokens WHERE token = ? AND type = ?");
        $stm->execute([$token, $type]);
        return $stm->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Token deletion error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send verification email to user
 * 
 * @param string $email The recipient's email address
 * @param string $name The recipient's name
 * @param string $token The verification token
 * @return bool True if email sent successfully, false otherwise
 */
function send_verification_email($email, $name, $token) {
    try {
        $mail = get_mail();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Verify Your K&P Account Email';

        // Create verification link using absolute URLs
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $server_name = $_SERVER['HTTP_HOST'];
        
        // Adjust this path to match your server structure
        // Try removing K&P%20Assignment if it's part of your DOCUMENT_ROOT
        $verification_link = $protocol . $server_name . "/user/page/verify_email.php?token=" . urlencode($token);
        
        // Alternative options if the above doesn't work:
        // $verification_link = $protocol . $server_name . "/verify_email.php?token=" . urlencode($token);
        // $verification_link = $protocol . $server_name . "/K-P/K&P%20Assignment/user/page/verify_email.php?token=" . urlencode($token);
        
        // For debugging
        error_log("Verification link generated: " . $verification_link);
        
        // Email body with responsive design
        $mail_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='{$protocol}{$server_name}/user/img/logo.png' alt='K&P Logo' style='max-width: 150px;'>
            </div>
            <h2 style='color: #4a6fa5; text-align: center;'>Verify Your Email Address</h2>
            <p>Hello $name,</p>
            <p>Thank you for registering with K&P. To activate your account, please verify your email address by clicking the button below:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$verification_link' style='background-color: #4a6fa5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Verify Email Address</a>
            </div>
            <p>If the button doesn't work, you can copy and paste the link below into your browser:</p>
            <p style='background-color: #f5f5f5; padding: 10px; word-break: break-all;'>$verification_link</p>
            <p>This verification link will expire in 24 hours.</p>
            <p>If you didn't create an account with us, please ignore this email.</p>
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eaeaea; font-size: 12px; color: #888;'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " K&P Fashion. All rights reserved.</p>
            </div>
        </div>";

        $mail->isHTML(true);
        $mail->Body = $mail_body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\r\n", $mail_body));
        
        // Send the email
        if (is_development()) {
            error_log("Development mode: Email would be sent to $email with subject '{$mail->Subject}'");
            return true;
        } else {
            $mail->send();
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send verification email to $email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email to user
 * 
 * @param string $email The recipient's email address
 * @param string $name The recipient's name
 * @param string $token The reset token
 * @return bool True if email sent successfully, false otherwise
 */
function send_reset_email($email, $name, $token) {
    try {
        $mail = get_mail();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Reset Your K&P Account Password';

        // Create reset link using absolute URLs
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $server_name = $_SERVER['HTTP_HOST'];
        
        $reset_link = $protocol . $server_name . "/user/page/reset_password.php?token=" . urlencode($token);
        
        // For debugging
        error_log("Password reset link generated: " . $reset_link);
        
        // Email body with responsive design
        $mail_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='{$protocol}{$server_name}/user/img/logo.png' alt='K&P Logo' style='max-width: 150px;'>
            </div>
            <h2 style='color: #4a6fa5; text-align: center;'>Reset Your Password</h2>
            <p>Hello $name,</p>
            <p>We received a request to reset your K&P account password. To reset your password, please click the button below:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$reset_link' style='background-color: #4a6fa5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Reset Password</a>
            </div>
            <p>If the button doesn't work, you can copy and paste the link below into your browser:</p>
            <p style='background-color: #f5f5f5; padding: 10px; word-break: break-all;'>$reset_link</p>
            <p>This password reset link will expire in 1 hour for security reasons.</p>
            <p>If you didn't request a password reset, you can ignore this email. Your account is safe.</p>
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eaeaea; font-size: 12px; color: #888;'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " K&P Fashion. All rights reserved.</p>
            </div>
        </div>";

        $mail->isHTML(true);
        $mail->Body = $mail_body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\r\n", $mail_body));
        
        // Send the email
        if (is_development()) {
            error_log("Development mode: Password reset email would be sent to $email with subject '{$mail->Subject}'");
            return true;
        } else {
            $mail->send();
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send password reset email to $email: " . $e->getMessage());
        return false;
    }
}

/**
 * HTML input for password with toggle visibility
 * @param string $name Input name attribute
 * @param string $attributes Additional HTML attributes
 */
function html_password_input($name, $attributes = '')
{
    echo <<<HTML
    <div class="password-input-container">
        <input type="password" id="$name" name="$name" $attributes>
        <i class="password-toggle fas fa-eye" onclick="togglePasswordVisibility('$name')"></i>
    </div>
    <script>
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling;
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
    </script>
    <style>
    .password-input-container {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #999;
    }
    .password-toggle:hover {
        color: #333;
    }
    </style>
HTML;
}

/**
 * HTML input for phone number with proper formatting
 * @param string $name Input name attribute
 * @param string $attributes Additional HTML attributes
 */
function html_phone_input($name, $attributes = '')
{
    $value = $_POST[$name] ?? '';

    echo <<<HTML
    <div class="phone-input-container">
        <span class="country-code">+60</span>
        <input type="tel" id="$name" name="$name" value="$value" $attributes>
    </div>
    <style>
    .phone-input-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .country-code {
        position: absolute;
        left: 10px;
        font-weight: bold;
        color: #555;
    }
    .phone-input-container input {
        padding-left: 40px !important;
    }
    </style>
HTML;
}

/**
 * Generate <input type='text'>
 */
function html_text($key, $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='password'>
 */
function html_password($key, $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='password' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='email'>
 */
function html_email($key, $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='email' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='tel'>
 */
function html_tel($key, $attr = '')
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='tel' id='$key' name='$key' value='$value' $attr>";
}

/**
 * Generate <input type='file'>
 */
function html_file($key, $accept = '', $attr = '')
{
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

/**
 * Generate radio buttons for gender selection
 */
function html_gender($key, $selected = null)
{
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
function test_err_function()
{
    global $_err;
    $_err['test'] = 'This is a test error message';
    err('test');
}
// test_err_function(); // Uncomment to test, then comment out after
