<?php
require_once '../../_base.php';
auth('admin','staff');
$user = $_SESSION['user'];
include '../headFooter/header.php';

// Get additional user data if needed
try {
    $stm = $_db->prepare("SELECT * FROM user WHERE user_id = ?");
    $stm->execute([$user->user_id]);
    $user_details = $stm->fetch();
} catch (PDOException $e) {
    error_log("Error fetching user details: " . $e->getMessage());
}

// Format account creation date
$created_date = date('F d, Y', strtotime($user->user_update_time ?? 'now'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Profile | K&P</title>
  <link rel="stylesheet" href="appAdmin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <div class="container">
    <h1 class="page-title">My Profile</h1>
    
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <div class="profile-layout">
      <!-- Profile Sidebar with Navigation -->
      <div class="profile-sidebar">
        <div class="profile-sidebar-header">
          <div class="profile-photo">
            <img src="../../img/<?= htmlspecialchars($user->user_profile_pic ?: 'default.png') ?>" 
                 alt="Profile Picture"
                 onerror="this.onerror=null; this.src='../../img/default.png';">
          </div>
          <h2><?= htmlspecialchars($user->user_name) ?></h2>
          <p class="text-muted"><?= htmlspecialchars(ucfirst($user->role)) ?> • Since <?= $created_date ?></p>
        </div>
        
        <nav class="profile-nav">
          <ul>
            <li class="active">
              <a href="#personal-info">
                <i class="fas fa-user"></i> Personal Information
              </a>
            </li>
            <li>
              <a href="#change-password">
                <i class="fas fa-lock"></i> Security Settings
              </a>
            </li>
            <li>
              <a href="../../admin/index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
              </a>
            </li>
            <li>
              <a href="../loginOut/logout.php" class="danger">
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
            <p>Manage your account details and contact information</p>
          </div>
          
          <div class="personal-info-summary">
            <div class="info-row">
              <div class="info-label">User ID</div>
              <div class="info-value"><?= htmlspecialchars($user->user_id) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Full Name</div>
              <div class="info-value"><?= htmlspecialchars($user->user_name) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Email Address</div>
              <div class="info-value"><?= htmlspecialchars($user->user_Email) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Phone Number</div>
              <div class="info-value"><?= htmlspecialchars($user->user_phone) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Gender</div>
              <div class="info-value"><?= htmlspecialchars($user->user_gender) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Status</div>
              <div class="info-value">
                <span class="status-badge status-<?= strtolower($user->status) ?>">
                  <?= htmlspecialchars($user->status) ?>
                </span>
              </div>
            </div>
            <div class="info-row">
              <div class="info-label">Role</div>
              <div class="info-value"><?= htmlspecialchars(ucfirst($user->role)) ?></div>
            </div>
            <div class="info-row">
              <div class="info-label">Last Updated</div>
              <div class="info-value"><?= htmlspecialchars(date('F d, Y g:i A', strtotime($user->user_update_time))) ?></div>
            </div>
            
            <div class="form-actions mt-20">
              <a href="updateProfile.php" class="btn primary-btn">
                <i class="fas fa-edit"></i> Edit Profile
              </a>
            </div>
          </div>
        </div>
        
        <!-- Security Section -->
        <div id="change-password" class="profile-section">
          <div class="section-header">
            <h2><i class="fas fa-lock"></i> Security Settings</h2>
            <p>Update your password to keep your account secure</p>
          </div>
          
          <div class="security-options">
            <div class="security-option">
              <div class="security-info">
                <h3>Password</h3>
                <p>Last changed: <?= date('F d, Y', strtotime($user->user_update_time)) ?></p>
              </div>
              <a href="updateProfile.php#password-section" class="btn secondary-btn">
                Change Password
              </a>
            </div>
            
            <div class="security-option">
              <div class="security-info">
                <h3>Profile Picture</h3>
                <p>Update your profile picture with our advanced image tools</p>
              </div>
              <a href="updateProfile.php#photo-section" class="btn secondary-btn">
                Manage Photo
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <?php include '../headFooter/footer.php'; ?>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Navigation active state
      const navLinks = document.querySelectorAll('.profile-nav a');
      
      navLinks.forEach(link => {
        if (!link.classList.contains('danger')) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId.startsWith('#')) {
              // Update active class
              document.querySelector('.profile-nav li.active').classList.remove('active');
              this.parentElement.classList.add('active');
              
              // Scroll to section
              document.querySelector(targetId).scrollIntoView({
                behavior: 'smooth'
              });
            } else {
              window.location.href = targetId;
            }
          });
        }
      });
      
      // Set active nav based on scroll position
      window.addEventListener('scroll', function() {
        const sections = document.querySelectorAll('.profile-section');
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