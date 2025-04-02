document.addEventListener('DOMContentLoaded', function() {
    // Gender filter change handling
    const genderOptions = document.querySelectorAll('.gender-option input');
    genderOptions.forEach(option => {
        option.addEventListener('change', function() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            // Update gender parameter
            urlParams.set('gender', this.value);
            // Redirect to the new URL
            window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
        });
    });

    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product');
            addProductToCart(productId, 1);
        });
    });

    /**
     * Function to add product to cart
     * @param {string} productId - The ID of the product to add
     * @param {number} quantity - The quantity to add
     */
    function addProductToCart(productId, quantity) {
        // Show loading state on button
        const button = document.querySelector(`.add-to-cart[data-product="${productId}"]`);
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        button.disabled = true;

        // AJAX request to add item to cart
        fetch('add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            setTimeout(() => {
                button.disabled = false;
                
                if (data.success) {
                    // Show success state
                    button.innerHTML = '<i class="fas fa-check"></i> Added!';
                    
                    // Update cart count in header if exists
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement && data.cartCount) {
                        cartCountElement.textContent = data.cartCount;
                    }
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                } else {
                    // Show error state
                    button.innerHTML = '<i class="fas fa-times"></i> Failed';
                    alert(data.message || 'Failed to add product to cart.');
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                }
            }, 500);
        })
        .catch(error => {
            console.error('Error:', error);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-times"></i> Error';
            
            // Alert user about error
            alert('An error occurred while adding the product to cart.');
            
            // Reset button text after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        });
    }

    // Image lazy loading
    const productImages = document.querySelectorAll('.product-image img');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const image = entry.target;
                    const src = image.getAttribute('data-src');
                    if (src) {
                        image.src = src;
                        image.removeAttribute('data-src');
                    }
                    imageObserver.unobserve(image);
                }
            });
        });

        productImages.forEach(image => {
            // Store original src in data-src and use a placeholder
            if (!image.hasAttribute('data-src')) {
                image.setAttribute('data-src', image.src);
                image.src = 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 viewBox%3D%220 0 300 200%22%2F%3E';
                imageObserver.observe(image);
            }
        });
    }
});