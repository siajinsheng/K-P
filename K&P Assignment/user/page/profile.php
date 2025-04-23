<?php
require_once '../../_base.php';

// Ensure session is started and user is authenticated
safe_session_start();

// Authentication check
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to view your profile');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$page_title = "My Profile";

// Initialize variables
$error_messages = [];
$success_message = temp('success');
$info_message = temp('info');
$error_message = temp('error');

// Get user data from database
try {
    $stm = $_db->prepare("
        SELECT * FROM user 
        WHERE user_id = ?
    ");
    $stm->execute([$user_id]);
    $user = $stm->fetch();
    
    if (!$user) {
        // This shouldn't happen if auth worked, but just in case
        error_log("Profile page - User not found in database: $user_id");
        logout('login.php');
    }
    
    // Get user's addresses
    $stm = $_db->prepare("
        SELECT * FROM address
        WHERE user_id = ?
        ORDER BY address_id ASC
    ");
    $stm->execute([$user_id]);
    $addresses = $stm->fetchAll();
    
    // Get user's order history
    $stm = $_db->prepare("
        SELECT o.order_id, o.order_date, o.orders_status, o.total_price,
               COUNT(od.product_id) as item_count
        FROM orders o
        LEFT JOIN order_details od ON o.order_id = od.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stm->execute([$user_id]);
    $recent_orders = $stm->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching user profile data: " . $e->getMessage());
    $error_messages[] = "An error occurred while retrieving your profile information.";
}

// Handle profile update
if (is_post() && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Basic validation
    if (empty($name)) {
        $error_messages[] = "Name is required.";
    }
    
    if (empty($email)) {
        $error_messages[] = "Email is required.";
    } elseif (!is_email($email)) {
        $error_messages[] = "Invalid email format.";
    } elseif ($email !== $user->user_Email && !is_unique($email, 'user', 'user_Email')) {
        $error_messages[] = "This email is already in use by another account.";
    }
    
    if (!empty($phone)) {
        $validated_phone = validate_malaysian_phone($phone);
        if ($validated_phone === false) {
            $error_messages[] = "Invalid phone number format. Please use a valid Malaysian phone number.";
        } else {
            $phone = $validated_phone;
        }
    }
    
    // Password validation
    if (!empty($new_password)) {
        // Verify current password
        if (empty($current_password)) {
            $error_messages[] = "Current password is required to set a new password.";
        } elseif (!password_verify($current_password, $user->user_password)) {
            $error_messages[] = "Current password is incorrect.";
        }
        
        // Validate new password strength
        $password_validation = validate_password($new_password);
        if ($password_validation !== true) {
            $error_messages[] = $password_validation;
        }
        
        // Confirm passwords match
        if ($new_password !== $confirm_password) {
            $error_messages[] = "New password and confirmation do not match.";
        }
    }
    
    // If no errors, update the profile
    if (empty($error_messages)) {
        try {
            // Start transaction
            $_db->beginTransaction();
            
            // Handle profile photo upload if present
            $photo = $user->profile_pic; // Default to current photo
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
                $file = $_FILES['profile_photo'];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPEG, PNG, and GIF images are allowed.");
                }
                
                // Save the photo
                $photo = save_photo_user((object)$file, 'user/uploads', 250, 250);
                
                // Delete old photo if it exists and isn't the default
                if ($user->profile_pic && $user->profile_pic !== 'default.jpg' && file_exists('user/uploads/' . $user->profile_pic)) {
                    unlink('user/uploads/' . $user->profile_pic);
                }
            }
            
            // Prepare SQL based on whether we're updating the password or not
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stm = $_db->prepare("
                    UPDATE user 
                    SET user_name = ?, user_Email = ?, phone = ?, user_password = ?, profile_pic = ?, user_update_time = NOW()
                    WHERE user_id = ?
                ");
                $stm->execute([$name, $email, $phone, $hashed_password, $photo, $user_id]);
            } else {
                $stm = $_db->prepare("
                    UPDATE user 
                    SET user_name = ?, user_Email = ?, phone = ?, profile_pic = ?, user_update_time = NOW()
                    WHERE user_id = ?
                ");
                $stm->execute([$name, $email, $phone, $photo, $user_id]);
            }
            
            // Commit the transaction
            $_db->commit();
            
            // Update session user data
            $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
            $stm->execute([$user_id]);
            $_SESSION['user'] = $stm->fetch();
            
            // Set success message
            temp('success', 'Your profile has been updated successfully.');
            
            // Redirect to remove POST data
            redirect('profile.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Error updating profile: " . $e->getMessage());
            $error_messages[] = "An error occurred while updating your profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <h1 class="page-title">My Profile</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= $info_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_messages)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Please correct the following errors:
                <ul>
                    <?php foreach ($error_messages as $msg): ?>
                        <li><?= $msg ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="profile-layout">
            <!-- Profile Sidebar with Navigation -->
            <div class="profile-sidebar">
                <div class="profile-sidebar-header">
                    <div class="profile-photo">
                        <img src="<?= isset($user->profile_pic) && $user->profile_pic ? '/admin/Uploaded_profile/' . $user->profile_pic : '../img/default_avatar.png' ?>" alt="Profile Photo">
                    </div>
                    <h2><?= htmlspecialchars($user->user_name) ?></h2>
                    <p class="text-muted">Member since <?= date('M d, Y', strtotime($user->user_update_time)) ?></p>
                </div>
                
                <nav class="profile-nav">
                    <ul>
                        <li class="active">
                            <a href="#personal-info">
                                <i class="fas fa-user"></i> Personal Information
                            </a>
                        </li>
                        <li>
                            <a href="#addresses">
                                <i class="fas fa-map-marker-alt"></i> My Addresses
                            </a>
                        </li>
                        <li>
                            <a href="#order-history">
                                <i class="fas fa-history"></i> Order History
                            </a>
                        </li>
                        <li>
                            <a href="#change-password">
                                <i class="fas fa-lock"></i> Change Password
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            
            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Personal Information Section -->
                <div id="personal-info" class="profile-section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> Personal Information</h2>
                        <p>Manage your personal details and contact information</p>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" class="profile-form">
                        <div class="form-group">
                            <label for="profile_photo">Profile Photo</label>
                            <div class="profile-photo-upload">
                                <div class="current-photo">
                                    <img src="<?= isset($user->profile_pic) && $user->profile_pic ? '/admin/Uploaded_profile/' . $user->profile_pic : '../img/default_avatar.png' ?>" alt="Current Profile Photo" id="preview-photo">
                                </div>
                                <div class="upload-controls">
                                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="file-input">
                                    <label for="profile_photo" class="file-label">Choose New Photo</label>
                                    <p class="text-muted">Max size: 2MB. Supported formats: JPEG, PNG, GIF</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user->user_name) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->user_Email) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user->phone ?? '') ?>" placeholder="e.g., 60123456789">
                                <p class="form-hint">Enter your Malaysian phone number</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Account Type</label>
                                <input type="text" value="<?= htmlspecialchars(ucfirst($user->role)) ?>" readonly class="readonly-field">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Addresses Section -->
                <div id="addresses" class="profile-section">
                    <div class="section-header">
                        <h2><i class="fas fa-map-marker-alt"></i> My Addresses</h2>
                        <p>Manage your delivery and billing addresses</p>
                    </div>
                    
                    <div class="addresses-container">
                        <?php if (empty($addresses)): ?>
                            <div class="empty-state">
                                <i class="fas fa-home"></i>
                                <p>You don't have any saved addresses yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($addresses as $address): ?>
                                <div class="address-card">
                                    <div class="address-card-header">
                                        <h3><?= htmlspecialchars($address->address_name) ?></h3>
                                        <?php if ($address->is_default): ?>
                                            <span class="default-badge">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="address-details">
                                        <p><?= htmlspecialchars($address->recipient_name) ?></p>
                                        <p><?= htmlspecialchars($address->phone) ?></p>
                                        <p>
                                            <?= htmlspecialchars($address->address_line1) ?>
                                            <?= $address->address_line2 ? ', ' . htmlspecialchars($address->address_line2) : '' ?>
                                        </p>
                                        <p>
                                            <?= htmlspecialchars($address->city) ?>, 
                                            <?= htmlspecialchars($address->state) ?>, 
                                            <?= htmlspecialchars($address->postal_code) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="address-actions">
                                        <a href="edit_address.php?id=<?= $address->address_id ?>" class="btn secondary-btn sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if (!$address->is_default): ?>
                                            <a href="set-default-address.php?id=<?= $address->address_id ?>" class="btn outline-btn sm">
                                                Set as Default
                                            </a>
                                        <?php endif; ?>
                                        <a href="delete-address.php?id=<?= $address->address_id ?>" class="btn danger-btn sm" onclick="return confirm('Are you sure you want to delete this address?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="add-address">
                            <a href="add_address.php" class="btn primary-btn">
                                <i class="fas fa-plus"></i> Add New Address
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Order History Section -->
                <div id="order-history" class="profile-section">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Order History</h2>
                        <p>View and track your recent orders</p>
                    </div>
                    
                    <div class="orders-container">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>You haven't placed any orders yet.</p>
                                <a href="products.php" class="btn secondary-btn">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="orders-list">
                                <div class="order-header">
                                    <div class="order-col">Order ID</div>
                                    <div class="order-col">Date</div>
                                    <div class="order-col">Items</div>
                                    <div class="order-col">Total</div>
                                    <div class="order-col">Status</div>
                                    <div class="order-col">Action</div>
                                </div>
                                
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-row">
                                        <div class="order-col order-id">
                                            <span class="mobile-label">Order ID:</span>
                                            <?= htmlspecialchars($order->order_id) ?>
                                        </div>
                                        <div class="order-col order-date">
                                            <span class="mobile-label">Date:</span>
                                            <?= date('M d, Y', strtotime($order->order_date)) ?>
                                        </div>
                                        <div class="order-col order-items">
                                            <span class="mobile-label">Items:</span>
                                            <?= $order->item_count ?> item<?= $order->item_count > 1 ? 's' : '' ?>
                                        </div>
                                        <div class="order-col order-total">
                                            <span class="mobile-label">Total:</span>
                                            RM <?= number_format($order->total_price, 2) ?>
                                        </div>
                                        <div class="order-col order-status">
                                            <span class="mobile-label">Status:</span>
                                            <span class="status-badge status-<?= strtolower($order->orders_status) ?>">
                                                <?= htmlspecialchars($order->orders_status) ?>
                                            </span>
                                        </div>
                                        <div class="order-col order-action">
                                            <a href="order-details.php?id=<?= $order->order_id ?>" class="btn outline-btn sm">View Details</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="view-all-orders">
                                <a href="orders.php" class="btn secondary-btn">View All Orders</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Change Password Section -->
                <div id="change-password" class="profile-section">
                    <div class="section-header">
                        <h2><i class="fas fa-lock"></i> Change Password</h2>
                        <p>Update your password to keep your account secure</p>
                    </div>
                    
                    <form method="post" class="profile-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="current_password" name="current_password" class="password-input">
                                <i class="fas fa-eye-slash toggle-password" data-target="current_password"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" class="password-input">
                                <i class="fas fa-eye-slash toggle-password" data-target="new_password"></i>
                            </div>
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
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="password-input">
                                <i class="fas fa-eye-slash toggle-password" data-target="confirm_password"></i>
                            </div>
                            <div id="password-match" class="password-match">
                                <i class="fas fa-times"></i> Passwords do not match
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn primary-btn" id="save-password-btn" disabled>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile photo preview
        const photoInput = document.getElementById('profile_photo');
        const photoPreview = document.getElementById('preview-photo');
        
        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Password visibility toggle
        const togglePasswordButtons = document.querySelectorAll('.toggle-password');
        
        togglePasswordButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                } else {
                    passwordInput.type = 'password';
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                }
            });
        });
        
        // Password strength meter
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthMeter = document.querySelector('.strength-meter-fill');
        const strengthText = document.querySelector('.strength-text span');
        const requirements = document.querySelectorAll('.password-requirements li');
        const passwordMatch = document.getElementById('password-match');
        const savePasswordBtn = document.getElementById('save-password-btn');
        
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
                
                // Check if password button should be enabled
                updateSaveButtonState(requirementsMet === 5);
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
                    
                    updateSaveButtonState(doMatch);
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                    passwordMatch.classList.remove('match');
                    passwordMatch.classList.add('no-match');
                    
                    updateSaveButtonState(false);
                }
            }
            
            function updateSaveButtonState(isValid) {
                const currentPasswordHasValue = document.getElementById('current_password').value.length > 0;
                const newPasswordHasValue = newPasswordInput.value.length > 0;
                const confirmPasswordHasValue = confirmPasswordInput.value.length > 0;
                
                savePasswordBtn.disabled = !(isValid && currentPasswordHasValue && newPasswordHasValue && confirmPasswordHasValue);
            }
        }
        
        // Smooth scrolling for navigation
        const navLinks = document.querySelectorAll('.profile-nav a');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Skip for logout link
                if (this.classList.contains('danger')) {
                    return;
                }
                
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                // Update active navigation item
                document.querySelector('.profile-nav li.active').classList.remove('active');
                this.parentElement.classList.add('active');
                
                // Smooth scroll to target
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Set active navigation based on scroll position
        const sections = document.querySelectorAll('.profile-section');
        
        window.addEventListener('scroll', function() {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                const sectionHeight = section.clientHeight;
                
                if (window.pageYOffset >= sectionTop && window.pageYOffset < sectionTop + sectionHeight) {
                    current = '#' + section.getAttribute('id');
                }
            });
            
            if (current) {
                navLinks.forEach(link => {
                    link.parentElement.classList.remove('active');
                    if (link.getAttribute('href') === current) {
                        link.parentElement.classList.add('active');
                    }
                });
            }
        });
    });
    </script>
</body>
</html>