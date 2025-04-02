document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.navbar');
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    // Toggle functionality
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });
    
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
    
    // Scroll effect for header
    window.addEventListener('scroll', function() {
        if (window.scrollY > 10) { // Smaller threshold for quicker transition
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
    
    // Initialize scroll state
    if (window.scrollY > 10) {
        header.classList.add('scrolled');
    }
});