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