/**
 * Password Strength Checker
 * Provides real-time feedback on password requirements
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize password strength checker if on registration page
    const passwordField = document.getElementById('password');
    if (passwordField) {
        setupPasswordStrengthChecker();
    }
});

function setupPasswordStrengthChecker() {
    const passwordField = document.getElementById('password');
    
    // Check password strength on keyup
    passwordField.addEventListener('keyup', function() {
        checkPasswordStrength(this.value);
    });
    
    // Initial check if returning from failed submission
    if (passwordField.value) {
        checkPasswordStrength(passwordField.value);
    }
}

function checkPasswordStrength(password) {
    // Check each requirement
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    // Update requirement indicators
    updateRequirement('req-length', hasLength);
    updateRequirement('req-uppercase', hasUppercase);
    updateRequirement('req-lowercase', hasLowercase);
    updateRequirement('req-number', hasNumber);
    updateRequirement('req-special', hasSpecial);

    // Update strength meter if exists
    updateStrengthMeter(calculatePasswordStrength(
        hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial
    ));
}

function updateRequirement(elementId, isMet) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    if (isMet) {
        element.classList.add('requirement-met');
        element.classList.remove('requirement-not-met');
    } else {
        element.classList.add('requirement-not-met');
        element.classList.remove('requirement-met');
    }
}

function calculatePasswordStrength(hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial) {
    let strength = 0;
    if (hasLength) strength += 20;
    if (hasUppercase) strength += 20;
    if (hasLowercase) strength += 20;
    if (hasNumber) strength += 20;
    if (hasSpecial) strength += 20;
    return strength;
}

function updateStrengthMeter(strength) {
    const meter = document.getElementById('password-strength-meter');
    if (!meter) return;
    
    meter.value = strength;
    
    // Update meter color based on strength
    if (strength < 40) {
        meter.className = 'strength-weak';
    } else if (strength < 70) {
        meter.className = 'strength-medium';
    } else {
        meter.className = 'strength-strong';
    }
}

/**
 * Form Validation Helpers
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Remember Me Functionality
 */
function setupRememberMe() {
    const rememberMe = document.getElementById('remember-me');
    if (rememberMe) {
        rememberMe.addEventListener('change', function() {
            // Implement remember me functionality
        });
    }
}

// Initialize all functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupPasswordStrengthChecker();
    setupRememberMe();
});

function toggleSidebar() {
    const sidebar = document.getElementsByClassName("sidebar")[0];

    if (sidebar.classList.contains("active")) {
        sidebar.classList.remove("active");
    } else {
        sidebar.classList.add("active");
    }
}