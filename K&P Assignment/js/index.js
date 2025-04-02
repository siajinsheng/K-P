document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.fullpage-section');
    let currentSection = 0;
    let isScrolling = false;
    const scrollDelay = 1000; // 1 second cooldown
    
    // Disable regular scroll
    window.addEventListener('wheel', preventDefaultScroll, { passive: false });
    window.addEventListener('touchmove', preventDefaultScroll, { passive: false });
    
    function preventDefaultScroll(e) {
        if (!isScrolling) {
            e.preventDefault();
        }
    }
    
    // Handle wheel/touch events
    window.addEventListener('wheel', handleScroll, { passive: false });
    window.addEventListener('touchstart', handleTouchStart, { passive: true });
    window.addEventListener('touchend', handleTouchEnd, { passive: true });
    
    let touchStartY = 0;
    
    function handleTouchStart(e) {
        touchStartY = e.touches[0].clientY;
    }
    
    function handleTouchEnd(e) {
        const touchEndY = e.changedTouches[0].clientY;
        const diff = touchStartY - touchEndY;
        
        if (Math.abs(diff) > 50) {
            if (diff > 0 && currentSection < sections.length - 1) {
                // Swipe up - next section
                scrollToSection(currentSection + 1);
            } else if (diff < 0 && currentSection > 0) {
                // Swipe down - previous section
                scrollToSection(currentSection - 1);
            }
        }
    }
    
    function handleScroll(e) {
        if (isScrolling) return;
        
        if (e.deltaY > 0 && currentSection < sections.length - 1) {
            // Scroll down
            scrollToSection(currentSection + 1);
        } else if (e.deltaY < 0 && currentSection > 0) {
            // Scroll up
            scrollToSection(currentSection - 1);
        }
    }
    
    function scrollToSection(index) {
        isScrolling = true;
        currentSection = index;
        
        window.scrollTo({
            top: sections[index].offsetTop,
            behavior: 'smooth'
        });
        
        setTimeout(() => {
            isScrolling = false;
        }, scrollDelay);
    }
    
    // Initialize
    window.scrollTo(0, 0);
    
    // Click handler for sections
    document.querySelectorAll('.fullpage-section').forEach(section => {
        section.addEventListener('click', function() {
            if (!isScrolling) {
                window.location.href = 'user/page/products.php';
            }
        });
    });
});