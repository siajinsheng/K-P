<?php
require_once '../../_base.php';
auth('admin','staff');
include '../headFooter/header.php';

$errors = [];
$success = false;
$user = $_SESSION['user'];
$user_id = $user->user_id;

// Get user data
$query = "SELECT * FROM user WHERE user_id = :user_id";
$stmt = $_db->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$admin = $stmt->fetch();

if (!$admin) {
    die("Failed to fetch user details");
}

// Extract only digits after +60 or 60 for display
$phone_display = preg_replace('/^\+?60/', '', $admin->user_phone);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle form submission
    if (isset($_POST['update_profile'])) {
        $admin_name = trim($_POST['admin_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_contact = trim($_POST['admin_contact'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        if (empty($admin_name)) {
            $errors['admin_name'] = "Name is required.";
        } elseif (strlen($admin_name) > 50) {
            $errors['admin_name'] = "Name cannot exceed 50 characters.";
        }

        if (empty($admin_email)) {
            $errors['admin_email'] = "Email is required.";
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = "Invalid email format.";
        }

        if (!empty($admin_contact)) {
            if (!preg_match("/^\d{7,11}$/", $admin_contact)) {
                $errors['admin_contact'] = "Enter 7–11 digits only (excluding +60).";
            } else {
                // Format with +60 prefix
                $admin_contact = '+60' . ltrim($admin_contact, '0');
            }
        }

        // Password validation if attempting to change password
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors['current_password'] = "Current password is required to set a new password.";
            } elseif (!password_verify($current_password, $admin->user_password)) {
                $errors['current_password'] = "Current password is incorrect.";
            }

            $password_validation = validate_password($new_password);
            if ($password_validation !== true) {
                $errors['new_password'] = $password_validation;
            }

            if ($new_password !== $confirm_password) {
                $errors['confirm_password'] = "Passwords do not match.";
            }
        }

        // Process the uploaded photo if any
        $profile_pic = $admin->user_profile_pic;
        
        if (isset($_POST['photo_data']) && !empty($_POST['photo_data'])) {
            // Process base64 image data
            $photoData = $_POST['photo_data'];
            $photoData = str_replace('data:image/png;base64,', '', $photoData);
            $photoData = str_replace('data:image/jpeg;base64,', '', $photoData);
            $photoData = str_replace(' ', '+', $photoData);
            $photoData = base64_decode($photoData);
            
            $fileName = uniqid() . '.jpg';
            $filePath = "../../img/" . $fileName;
            
            if (file_put_contents($filePath, $photoData)) {
                // If successful, update the profile pic filename
                $profile_pic = $fileName;
                
                // Delete old profile pic if it exists and isn't default
                if ($admin->user_profile_pic && $admin->user_profile_pic !== 'default.png' && file_exists("../../img/" . $admin->user_profile_pic)) {
                    unlink("../../img/" . $admin->user_profile_pic);
                }
            } else {
                $errors['photo'] = "Failed to save the photo. Please try again.";
            }
        }
        
        // If no errors, update the profile
        if (empty($errors)) {
            try {
                $_db->beginTransaction();
                
                $update_query = "UPDATE user SET user_name = ?, user_Email = ?, user_phone = ?, user_profile_pic = ?";
                $params = [$admin_name, $admin_email, $admin_contact, $profile_pic];
                
                if (!empty($new_password)) {
                    $update_query .= ", user_password = ?";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                
                $update_query .= ", user_update_time = NOW() WHERE user_id = ?";
                $params[] = $user_id;
                
                $stmt = $_db->prepare($update_query);
                if ($stmt->execute($params)) {
                    $_db->commit();
                    
                    // Update session data
                    $stmt = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $_SESSION['user'] = $stmt->fetch();
                    
                    // Set success message
                    $_SESSION['success_message'] = "Profile updated successfully!";
                    
                    // Redirect to profile
                    header("Location: profile.php");
                    exit;
                } else {
                    throw new Exception("Failed to update profile.");
                }
            } catch (Exception $e) {
                if ($_db->inTransaction()) {
                    $_db->rollBack();
                }
                $errors['database'] = "Update failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Profile | K&P</title>
  <link rel="stylesheet" href="profile.css">
  <link rel="stylesheet" href="updateProfile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script src="updateProfile.js"></script>
</head>
<body>
  <div class="container">
    <div class="edit-profile-container">
      <div class="form-header">
        <a href="profile.php" class="back-link">
          <i class="fas fa-arrow-left"></i>
        </a>
        <h1>Update Profile</h1>
      </div>
      
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i> Please correct the following errors:
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= $error ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      
      <div class="tabs">
        <div class="tab active" data-tab="profile-info">Personal Information</div>
        <div class="tab" data-tab="photo-section">Profile Photo</div>
        <div class="tab" data-tab="password-section">Change Password</div>
      </div>
      
      <form method="POST" enctype="multipart/form-data" id="profile-form">
        <!-- Profile Information Tab -->
        <div id="profile-info" class="tab-content active">
          <div class="form-group">
            <label for="admin_name">Full Name</label>
            <input type="text" name="admin_name" id="admin_name" value="<?= htmlspecialchars($admin->user_name) ?>" required>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="admin_email">Email Address</label>
              <input type="email" name="admin_email" id="admin_email" value="<?= htmlspecialchars($admin->user_Email) ?>" required>
            </div>
            
            <div class="form-group">
              <label for="admin_contact">Phone Number</label>
              <div class="phone-input-wrapper">
                <span class="phone-prefix">+60</span>
                <input type="tel" name="admin_contact" id="admin_contact" value="<?= htmlspecialchars($phone_display) ?>" placeholder="e.g., 123456789">
              </div>
              <p class="form-hint">Enter digits only, without country code</p>
            </div>
          </div>
        </div>
        
        <!-- Profile Photo Tab -->
        <div id="photo-section" class="tab-content">
          <div class="photo-editor-container">
            <div class="current-photo-container">
              <h3>Current Profile Picture</h3>
              <div class="current-photo">
                <img 
                  src="../../img/<?= $admin->user_profile_pic ?: 'default.png' ?>" 
                  alt="Current Profile Picture" 
                  id="current-photo"
                  onerror="this.onerror=null; this.src='../../img/default.png';"
                >
              </div>
            </div>
            
            <h3>Update Profile Picture</h3>
            <div class="photo-tabs">
              <div class="photo-tab active" data-phototab="upload-photo">
                <i class="fas fa-upload"></i> Upload Photo
              </div>
              <div class="photo-tab" data-phototab="take-photo">
                <i class="fas fa-camera"></i> Take Photo
              </div>
            </div>
            
            <div class="photo-content active" id="upload-photo">
              <div id="dropzone" class="dropzone">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Drag & drop your photo here or click to select</p>
                <input type="file" id="file-input" accept="image/*" style="display: none;">
              </div>
            </div>
            
            <div class="photo-content" id="take-photo">
              <div id="webcam-container">
                <video id="webcam" autoplay playsinline width="100%"></video>
                <div class="webcam-buttons">
                  <button type="button" id="webcam-capture" class="btn secondary-btn">
                    <i class="fas fa-camera"></i> Capture Photo
                  </button>
                  <button type="button" id="webcam-switch" class="btn outline-btn">
                    <i class="fas fa-sync"></i> Switch Camera
                  </button>
                </div>
              </div>
            </div>
            
            <div id="image-editor" style="display: none;">
              <h3>Edit Your Photo</h3>
              <div id="cropper-container">
                <img id="image-to-crop" src="" alt="Image to crop">
              </div>
              
              <div class="image-actions">
                <button type="button" class="btn outline-btn" id="rotate-left">
                  <i class="fas fa-undo"></i> Rotate Left
                </button>
                <button type="button" class="btn outline-btn" id="rotate-right">
                  <i class="fas fa-redo"></i> Rotate Right
                </button>
                <button type="button" class="btn outline-btn" id="flip-horizontal">
                  <i class="fas fa-arrows-alt-h"></i> Flip Horizontally
                </button>
                <button type="button" class="btn outline-btn" id="flip-vertical">
                  <i class="fas fa-arrows-alt-v"></i> Flip Vertically
                </button>
                <button type="button" class="btn primary-btn" id="crop-image">
                  <i class="fas fa-crop"></i> Crop & Save
                </button>
                <button type="button" class="btn danger-btn" id="cancel-crop">
                  <i class="fas fa-times"></i> Cancel
                </button>
              </div>
            </div>
            
            <input type="hidden" name="photo_data" id="photo-data">
          </div>
        </div>
        
        <!-- Password Tab -->
        <div id="password-section" class="tab-content">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <div class="password-input-wrapper">
              <input type="password" name="current_password" id="current_password" class="password-input">
              <i class="fas fa-eye-slash toggle-password" data-target="current_password"></i>
            </div>
          </div>
          
          <div class="form-group">
            <label for="new_password">New Password</label>
            <div class="password-input-wrapper">
              <input type="password" name="new_password" id="new_password" class="password-input">
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
              <input type="password" name="confirm_password" id="confirm_password" class="password-input">
              <i class="fas fa-eye-slash toggle-password" data-target="confirm_password"></i>
            </div>
            <div id="password-match" class="password-match">
              <i class="fas fa-times"></i> Passwords do not match
            </div>
          </div>
        </div>
        
        <div class="form-actions">
          <input type="hidden" name="update_profile" value="1">
          <a href="profile.php" class="btn outline-btn">Cancel</a>
          <button type="submit" class="btn primary-btn">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  
  <?php include '../headFooter/footer.php'; ?>
  
</body>
</html>