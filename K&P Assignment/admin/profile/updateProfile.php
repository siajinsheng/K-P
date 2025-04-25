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
  <link rel="stylesheet" href="appAdmin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <style>
    .tabs {
      display: flex;
      border-bottom: 1px solid #ddd;
      margin-bottom: 20px;
    }
    
    .tab {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
    }
    
    .tab.active {
      border-bottom: 2px solid var(--primary-color);
      color: var(--primary-color);
      font-weight: bold;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Photo editing styles */
    .photo-editor-container {
      margin-bottom: 30px;
    }
    
    .photo-tabs {
      display: flex;
      margin-bottom: 20px;
    }
    
    .photo-tab {
      flex: 1;
      text-align: center;
      padding: 12px;
      background-color: #f5f5f5;
      cursor: pointer;
      border: 1px solid #ddd;
    }
    
    .photo-tab:first-child {
      border-radius: 5px 0 0 5px;
    }
    
    .photo-tab:last-child {
      border-radius: 0 5px 5px 0;
    }
    
    .photo-tab.active {
      background-color: var(--primary-color);
      color: white;
      border-color: var(--primary-color);
    }
    
    .photo-content {
      display: none;
    }
    
    .photo-content.active {
      display: block;
    }
    
    .dropzone {
      border: 2px dashed #ccc;
      border-radius: 5px;
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .dropzone:hover, .dropzone.dragover {
      border-color: var(--primary-color);
      background-color: rgba(74, 111, 165, 0.05);
    }
    
    .dropzone i {
      font-size: 48px;
      color: #ccc;
      margin-bottom: 15px;
    }
    
    .dropzone p {
      margin: 0;
      color: #777;
    }
    
    #webcam-container {
      width: 100%;
      max-width: 500px;
      margin: 0 auto;
    }
    
    #webcam {
      width: 100%;
      border-radius: 5px;
      background-color: #f5f5f5;
    }
    
    .webcam-buttons {
      display: flex;
      justify-content: center;
      margin-top: 15px;
      gap: 10px;
    }
    
    .image-preview-container {
      margin: 20px 0;
      border: 1px solid #ddd;
      padding: 10px;
      border-radius: 5px;
      text-align: center;
    }
    
    #image-preview {
      max-width: 100%;
      max-height: 400px;
    }
    
    .image-actions {
      display: flex;
      justify-content: center;
      margin-top: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    .no-preview {
      color: #777;
      padding: 30px;
      background-color: #f5f5f5;
      border-radius: 5px;
      text-align: center;
    }
    
    #cropper-container {
      max-height: 400px;
      margin: 20px 0;
    }
  </style>
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
  
  <!-- Include needed libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Tab functionality
      const tabs = document.querySelectorAll('.tab');
      const tabContents = document.querySelectorAll('.tab-content');
      
      tabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-tab');
          
          // Update active tab
          tabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          
          // Show corresponding content
          tabContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === target) {
              content.classList.add('active');
            }
          });
        });
      });
      
      // Photo tab functionality
      const photoTabs = document.querySelectorAll('.photo-tab');
      const photoContents = document.querySelectorAll('.photo-content');
      
      photoTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-phototab');
          
          // Update active tab
          photoTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          
          // Show corresponding content
          photoContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === target) {
              content.classList.add('active');
              
              // Initialize webcam if selected
              if (target === 'take-photo') {
                initWebcam();
              } else {
                stopWebcam();
              }
            }
          });
        });
      });
      
      // Password visibility toggle
      const togglePasswordButtons = document.querySelectorAll('.toggle-password');
      
      togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          
          if (input.type === 'password') {
            input.type = 'text';
            this.classList.replace('fa-eye-slash', 'fa-eye');
          } else {
            input.type = 'password';
            this.classList.replace('fa-eye', 'fa-eye-slash');
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
      
      if (newPasswordInput && strengthMeter) {
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
            strength = (requirementsMet / 5) * 100;
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
          
          // Check password match
          if (confirmPasswordInput.value) {
            checkPasswordMatch();
          }
        });
        
        if (confirmPasswordInput) {
          confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
          if (newPasswordInput.value && confirmPasswordInput.value) {
            if (newPasswordInput.value === confirmPasswordInput.value) {
              passwordMatch.innerHTML = '<i class="fas fa-check"></i> Passwords match';
              passwordMatch.classList.add('match');
              passwordMatch.classList.remove('no-match');
            } else {
              passwordMatch.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
              passwordMatch.classList.add('no-match');
              passwordMatch.classList.remove('match');
            }
          }
        }
      }
      
      // Drag and Drop functionality
      const dropzone = document.getElementById('dropzone');
      const fileInput = document.getElementById('file-input');
      
      dropzone.addEventListener('click', () => fileInput.click());
      
      dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
      });
      
      dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
      });
      
      dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
          handleFileSelect(e.dataTransfer.files[0]);
        }
      });
      
      fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
          handleFileSelect(fileInput.files[0]);
        }
      });
      
      // File handling
      function handleFileSelect(file) {
        if (!file.type.match('image.*')) {
          alert('Please select an image file.');
          return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
          initCropper(e.target.result);
        }
        
        reader.readAsDataURL(file);
      }
      
      // Webcam functionality
      let webcamStream = null;
      const webcamElement = document.getElementById('webcam');
      const captureButton = document.getElementById('webcam-capture');
      const switchButton = document.getElementById('webcam-switch');
      let currentCamera = 'user'; // 'user' for front camera, 'environment' for back camera
      
      function initWebcam() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
          navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: currentCamera } 
          }).then(function(stream) {
            webcamStream = stream;
            webcamElement.srcObject = stream;
          }).catch(function(error) {
            console.error("Webcam error:", error);
            alert("Unable to access webcam. Please make sure you've granted camera permissions.");
          });
        } else {
          alert("Sorry, your browser doesn't support webcam access.");
        }
      }
      
      function stopWebcam() {
        if (webcamStream) {
          webcamStream.getTracks().forEach(track => {
            track.stop();
          });
          webcamStream = null;
        }
      }
      
      captureButton.addEventListener('click', function() {
        if (webcamStream) {
          // Create a canvas to capture the current video frame
          const canvas = document.createElement('canvas');
          canvas.width = webcamElement.videoWidth;
          canvas.height = webcamElement.videoHeight;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(webcamElement, 0, 0, canvas.width, canvas.height);
          
          // Convert to data URL
          const imageDataURL = canvas.toDataURL('image/jpeg');
          initCropper(imageDataURL);
        }
      });
      
      switchButton.addEventListener('click', function() {
        currentCamera = currentCamera === 'user' ? 'environment' : 'user';
        stopWebcam();
        initWebcam();
      });
      
      // Image cropping functionality
      let cropper = null;
      
      function initCropper(imageData) {
        const imageElement = document.getElementById('image-to-crop');
        const cropperContainer = document.getElementById('image-editor');
        
        // Stop webcam if it's running
        stopWebcam();
        
        // Reset and show the image editor
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        
        imageElement.src = imageData;
        cropperContainer.style.display = 'block';
        
        // Switch to photo tab if on webcam tab
        document.querySelector('.photo-tab[data-phototab="upload-photo"]').click();
        
        // Initialize the cropper
        cropper = new Cropper(imageElement, {
          aspectRatio: 1,
          viewMode: 1,
          guides: true,
          autoCropArea: 0.8,
          responsive: true
        });
      }
      
      // Image manipulation buttons
      document.getElementById('rotate-left').addEventListener('click', function() {
        if (cropper) cropper.rotate(-90);
      });
      
      document.getElementById('rotate-right').addEventListener('click', function() {
        if (cropper) cropper.rotate(90);
      });
      
      document.getElementById('flip-horizontal').addEventListener('click', function() {
        if (cropper) cropper.scaleX(cropper.getData().scaleX === -1 ? 1 : -1);
      });
      
      document.getElementById('flip-vertical').addEventListener('click', function() {
        if (cropper) cropper.scaleY(cropper.getData().scaleY === -1 ? 1 : -1);
      });
      
      document.getElementById('crop-image').addEventListener('click', function() {
        if (!cropper) return;
        
        const canvas = cropper.getCroppedCanvas({
          width: 300,
          height: 300,
          fillColor: '#fff'
        });
        
        if (canvas) {
          // Update the preview image
          document.getElementById('current-photo').src = canvas.toDataURL('image/jpeg');
          
          // Store the image data in the hidden input
          document.getElementById('photo-data').value = canvas.toDataURL('image/jpeg');
          
          // Hide the cropper
          document.getElementById('image-editor').style.display = 'none';
          
          // Clean up
          cropper.destroy();
          cropper = null;
        }
      });
      
      document.getElementById('cancel-crop').addEventListener('click', function() {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        document.getElementById('image-editor').style.display = 'none';
      });
      
      // Clean up before leaving the page
      window.addEventListener('beforeunload', function() {
        stopWebcam();
      });
    });
  </script>
</body>
</html>