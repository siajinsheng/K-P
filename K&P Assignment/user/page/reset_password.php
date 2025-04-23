<?php
$_title = 'K&P - Reset Password';
require '../../_base.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    redirect('../../index.php'); // Redirect to homepage if already logged in
}

$token = req('token');

if (empty($token)) {
    temp('error', 'Invalid password reset link.');
    redirect('login.php');
}

// Verify the token
$user = verify_token($token, 'password_reset');
if (!$user) {
    temp('error', 'Your password reset link is invalid or has expired. Please request a new one.');
    redirect('forgot_password.php');
}

$token_id = $user->token_id; // This comes from the verify_token function

// Handle form submission
if (is_post()) {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($password)) {
        $_err['password'] = 'Password is required';
    } else {
        // Check password strength
        $password_validation = validate_password($password);
        if ($password_validation !== true) {
            $_err['password'] = $password_validation;
        }
    }
    
    if (empty($confirm_password)) {
        $_err['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $_err['confirm_password'] = 'Passwords do not match';
    }
    
    // If no errors, update password
    if (empty($_err)) {
        try {
            $_db->beginTransaction();
            
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update the user's password
            $stm = $_db->prepare("UPDATE user SET user_password = ?, user_update_time = NOW() WHERE user_id = ?");
            $stm->execute([$hashed_password, $user->user_id]);
            
            // Delete the used token
            delete_token($token, 'password_reset');
            
            // Delete any "remember me" cookies
            setcookie('user_id', '', time() - 3600, '/');
            setcookie('remember_token', '', time() - 3600, '/');
            
            $_db->commit();
            
            temp('success', 'Your password has been successfully reset. You can now log in with your new password.');
            redirect('login.php');
            
        } catch (Exception $e) {
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Password reset error: " . $e->getMessage());
            $_err['general'] = 'An error occurred while resetting your password. Please try again.';
        }
    }
}

// Get any messages from session
$success_message = temp('success');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reset-password-form {
            max-width: 500px;
            margin: 80px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .reset-password-form h1 {
            font-size: 1.8rem;
            color: #4a6fa5;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-meter {
            height: 5px;
            background-color: #eee;
            border-radius: 5px;
            margin-bottom: 5px;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0;
            border-radius: 5px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-meter-fill[data-strength="0"] { background-color: transparent; }
        .strength-meter-fill[data-strength="1"] { background-color: #dc3545; width: 20%; }
        .strength-meter-fill[data-strength="2"] { background-color: #ffc107; width: 40%; }
        .strength-meter-fill[data-strength="3"] { background-color: #fd7e14; width: 60%; }
        .strength-meter-fill[data-strength="4"] { background-color: #20c997; width: 80%; }
        .strength-meter-fill[data-strength="5"] { background-color: #28a745; width: 100%; }
        
        .strength-text {
            font-size: 0.85rem;
            color: #666;
        }
        
        .password-requirements {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px;
        }
        
        .password-requirements li {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        .password-requirements li i {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        
        .password-requirements li.met {
            color: #28a745;
        }
        
        .password-requirements li i.fa-times { color: #dc3545; }
        .password-requirements li i.fa-check { color: #28a745; }
        
        .password-match {
            margin-top: 10px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .password-match.no-match { color: #dc3545; }
        .password-match.match { color: #28a745; }
        
        .password-match i {
            margin-right: 5px;
        }
        
        @media (max-width: 576px) {
            .password-requirements {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>

    <main class="container">
        <div class="reset-password-form">
            <h1>Reset Your Password</h1>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_err['general'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $_err['general'] ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <i class="fas fa-eye-slash toggle-password" data-target="password"></i>
                    </div>
                    <?= isset($_err['password']) ? '<div class="error-message">' . $_err['password'] . '</div>' : '' ?>
                    
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-meter-fill" data-strength="0"></div>
                        </div>
                        <div class="strength-text">Password strength: <span>Too weak</span></div>
                    </div>
                    
                    <ul class="password-requirements">
                        <li data-requirement="length"><i class="fas fa-times"></i> At least 8 characters</li>
                        <li data-requirement="uppercase"><i class="fas fa-times"></i> At least one uppercase letter</li>
                        <li data-requirement="lowercase"><i class="fas fa-times"></i> At least one lowercase letter</li>
                        <li data-requirement="number"><i class="fas fa-times"></i> At least one number</li>
                        <li data-requirement="special"><i class="fas fa-times"></i> At least one special character</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <i class="fas fa-eye-slash toggle-password" data-target="confirm_password"></i>
                    </div>
                    <?= isset($_err['confirm_password']) ? '<div class="error-message">' . $_err['confirm_password'] . '</div>' : '' ?>
                    
                    <div id="password-match" class="password-match">
                        <i class="fas fa-times"></i> Passwords do not match
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="reset-btn" class="btn" disabled>
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
                
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            </form>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle icon
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            });
            
            // Password strength meter
            const newPasswordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthMeter = document.querySelector('.strength-meter-fill');
            const strengthText = document.querySelector('.strength-text span');
            const requirements = document.querySelectorAll('.password-requirements li');
            const passwordMatch = document.getElementById('password-match');
            const resetBtn = document.getElementById('reset-btn');
            
            if (newPasswordInput && strengthMeter && strengthText) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let requirementsMet = 0;
                    
                    // Check each requirement
                    const hasLength = password.length >= 8;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasLowercase = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSpecial = /[^A-Za-z0-9]/.test(password);
                    
                    // Update requirement indicators
                    requirements.forEach(req => {
                        const requirement = req.getAttribute('data-requirement');
                        const icon = req.querySelector('i');
                        
                        let isMet = false;
                        
                        switch(requirement) {
                            case 'length':
                                isMet = hasLength;
                                break;
                            case 'uppercase':
                                isMet = hasUppercase;
                                break;
                            case 'lowercase':
                                isMet = hasLowercase;
                                break;
                            case 'number':
                                isMet = hasNumber;
                                break;
                            case 'special':
                                isMet = hasSpecial;
                                break;
                        }
                        
                        if (isMet) {
                            icon.className = 'fas fa-check';
                            req.classList.add('met');
                            requirementsMet++;
                        } else {
                            icon.className = 'fas fa-times';
                            req.classList.remove('met');
                        }
                    });
                    
                    // Calculate strength
                    if (password.length > 0) {
                        strength = requirementsMet / 5 * 100;
                    }
                    
                    // Update strength meter
                    strengthMeter.style.width = strength + '%';
                    strengthMeter.setAttribute('data-strength', Math.ceil(strength / 20));
                    
                    // Update strength text
                    if (strength === 0) {
                        strengthText.textContent = 'Too weak';
                    } else if (strength <= 20) {
                        strengthText.textContent = 'Very weak';
                    } else if (strength <= 40) {
                        strengthText.textContent = 'Weak';
                    } else if (strength <= 60) {
                        strengthText.textContent = 'Medium';
                    } else if (strength <= 80) {
                        strengthText.textContent = 'Strong';
                    } else {
                        strengthText.textContent = 'Very strong';
                    }
                    
                    // Check if passwords match
                    checkPasswordsMatch();
                    
                    // Check if reset button should be enabled
                    updateResetButtonState(requirementsMet === 5);
                });
                
                if (confirmPasswordInput && passwordMatch) {
                    confirmPasswordInput.addEventListener('input', checkPasswordsMatch);
                }
                
                function checkPasswordsMatch() {
                    if (newPasswordInput.value && confirmPasswordInput.value) {
                        const doMatch = newPasswordInput.value === confirmPasswordInput.value;
                        
                        if (doMatch) {
                            passwordMatch.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                            passwordMatch.classList.add('match');
                            passwordMatch.classList.remove('no-match');
                        } else {
                            passwordMatch.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                            passwordMatch.classList.add('no-match');
                            passwordMatch.classList.remove('match');
                        }
                        
                        updateResetButtonState(doMatch);
                    } else {
                        passwordMatch.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                        passwordMatch.classList.remove('match');
                        passwordMatch.classList.add('no-match');
                        
                        updateResetButtonState(false);
                    }
                }
                
                function updateResetButtonState(isValid) {
                    const allRequirementsMet = document.querySelectorAll('.password-requirements li.met').length === 5;
                    const passwordsMatch = newPasswordInput.value === confirmPasswordInput.value && 
                                        newPasswordInput.value.length > 0 &&
                                        confirmPasswordInput.value.length > 0;
                    
                    resetBtn.disabled = !(allRequirementsMet && passwordsMatch);
                }
            }
        });
    </script>
</body>
</html>