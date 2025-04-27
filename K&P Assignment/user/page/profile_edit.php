<?php
require_once '../../_base.php';

// Ensure user is authenticated
safe_session_start();
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to edit your profile');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;

// Initialize variables
$error_messages = [];
$success_message = temp('success');
$error_message = temp('error');

// Get user data from database
try {
    $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
    $stm->execute([$user_id]);
    $user = $stm->fetch();
    
    if (!$user) {
        error_log("Edit profile - User not found in database: $user_id");
        logout('login.php');
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $error_messages[] = "An error occurred while retrieving your profile information.";
}

// Handle profile update
if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    // Basic validation
    if (empty($name)) {
        $error_messages[] = "Name is required.";
    } elseif (strlen($name) > 255) {
        $error_messages[] = "Name must be less than 255 characters.";
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
    
    if (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'])) {
        $error_messages[] = "Please select a valid gender.";
    }
    
    // Process profile picture if uploaded
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
            $error_messages[] = "Invalid file type. Only JPEG, PNG, and GIF images are allowed.";
        } elseif ($_FILES['profile_pic']['size'] > $max_size) {
            $error_messages[] = "File size is too large. Maximum size is 2MB.";
        } else {
            // Process and save the image
            try {
                $profile_pic = save_photo_user(get_file('profile_pic'), '../../admin/Uploaded_profile', 300, 300);
            } catch (Exception $e) {
                error_log("Error saving profile picture: " . $e->getMessage());
                $error_messages[] = "Failed to save profile picture. Please try a different image.";
            }
        }
    }
    
    // If no errors, update profile
    if (empty($error_messages)) {
        try {
            $_db->beginTransaction();
            
            // Prepare SQL
            if ($profile_pic) {
                // Update with new profile picture
                $stm = $_db->prepare("
                    UPDATE user
                    SET user_name = ?, user_Email = ?, user_phone = ?, user_gender = ?, user_profile_pic = ?, user_update_time = NOW()
                    WHERE user_id = ?
                ");
                $stm->execute([$name, $email, $phone, $gender, $profile_pic, $user_id]);
                
                // Delete old profile pic if not default
                if ($user->user_profile_pic != 'default.jpg' && file_exists('../../img/' . $user->user_profile_pic)) {
                    unlink('../../img/' . $user->user_profile_pic);
                }
            } else {
                // Update without changing profile picture
                $stm = $_db->prepare("
                    UPDATE user
                    SET user_name = ?, user_Email = ?, user_phone = ?, user_gender = ?, user_update_time = NOW()
                    WHERE user_id = ?
                ");
                $stm->execute([$name, $email, $phone, $gender, $user_id]);
            }
            
            $_db->commit();
            
            // Update session user data
            $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
            $stm->execute([$user_id]);
            $_SESSION['user'] = $stm->fetch();
            
            // Set success message and redirect
            temp('success', 'Your profile has been successfully updated.');
            redirect('profile.php#personal-info');
        } catch (PDOException $e) {
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Error updating profile: " . $e->getMessage());
            $error_message = "An error occurred while updating your profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Edit Profile</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .edit-profile-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .profile-form {
            display: grid;
            grid-gap: 20px;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-header .back-link {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .form-header h1 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .profile-pic-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--primary-color);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .profile-pic-actions {
            display: flex;
            gap: 10px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="edit-profile-container">
        <div class="form-header">
            <a href="profile.php#personal-info" class="back-link">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1>Edit Your Profile</h1>
        </div>
        
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
        
        <form method="post" enctype="multipart/form-data" class="profile-form">
            <div class="profile-pic-upload">
                <img 
                    src="../../admin/Uploaded_profile/<?= ($user->user_profile_pic && file_exists('../../admin/Uploaded_profile/' . $user->user_profile_pic)) ? $user->user_profile_pic : 'default.jpg' ?>" 
                    alt="Profile Picture" 
                    id="profile-pic-preview" 
                    class="profile-pic-preview"
                >
                <div class="profile-pic-actions">
                    <label for="profile_pic" class="btn secondary-btn">
                        <i class="fas fa-camera"></i> Change Photo
                    </label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg, image/png, image/gif" style="display: none;">
                </div>
                <p class="form-hint">Maximum size: 2MB. Accepted formats: JPEG, PNG, GIF</p>
            </div>
            
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user->user_name) ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->user_Email) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user->user_phone) ?>" placeholder="e.g., 60123456789">
                    <p class="form-hint">Enter Malaysian phone number with country code (e.g., 60123456789)</p>
                </div>
            </div>
            
            <div class="form-group">
                <label>Gender</label>
                <div class="gender-options">
                    <div class="gender-option">
                        <input type="radio" id="gender_male" name="gender" value="Male" <?= $user->user_gender === 'Male' ? 'checked' : '' ?>>
                        <label for="gender_male">Male</label>
                    </div>
                    <div class="gender-option">
                        <input type="radio" id="gender_female" name="gender" value="Female" <?= $user->user_gender === 'Female' ? 'checked' : '' ?>>
                        <label for="gender_female">Female</label>
                    </div>
                    <div class="gender-option">
                        <input type="radio" id="gender_other" name="gender" value="Other" <?= $user->user_gender === 'Other' ? 'checked' : '' ?>>
                        <label for="gender_other">Other</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="profile.php#personal-info" class="btn outline-btn">Cancel</a>
                <button type="submit" class="btn primary-btn">Save Changes</button>
            </div>
        </form>
    </div>
    
    <?php include('../footer.php'); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Preview profile picture before upload
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicPreview = document.getElementById('profile-pic-preview');
            
            profilePicInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicPreview.src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>