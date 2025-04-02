<?php
require_once '../../_base.php';

/**
 * Send an activation email to the user
 * 
 * @param string $email User's email address
 * @param string $name User's name
 * @param string $activationToken Unique activation token
 * @return boolean True if email was sent successfully, false otherwise
 */
function send_verification_email($email, $name, $activationToken) {
    // Base URL of your website
    $baseUrl = "http://" . $_SERVER['HTTP_HOST'];
    
    // Full activation link
    $activationLink = $baseUrl . "/user/page/activate.php?token=" . $activationToken;
    
    // Email subject
    $subject = "K&P Store - Activate Your Account";
    
    // Email body
    $message = "
    <html>
    <head>
        <title>Activate Your K&P Account</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
            }
            .container {
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .header {
                background-color: #4a6fa5;
                color: white;
                padding: 15px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .content {
                padding: 20px;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #4a6fa5;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #777;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to K&P Store!</h1>
            </div>
            <div class='content'>
                <p>Hello $name,</p>
                <p>Thank you for registering with K&P Store. To complete your registration and activate your account, please click the button below:</p>
                
                <div style='text-align: center;'>
                    <a href='$activationLink' class='button'>Activate My Account</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all;'><small>$activationLink</small></p>
                
                <p>This activation link will expire in 24 hours.</p>
                
                <p>If you did not create an account with us, please ignore this email.</p>
                
                <p>Best regards,<br>The K&P Store Team</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " K&P Store. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: K&P Store <noreply@kpstore.com>" . "\r\n";
    
    // Send email
    return mail($email, $subject, $message, $headers);
}