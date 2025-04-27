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