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

    // Check authentication status first before allowing add to cart
    function checkAuthentication() {
        return fetch('check-auth.php')
            .then(response => response.json())
            .then(data => {
                console.log('Auth check result:', data);
                return data.authenticated;
            })
            .catch(error => {
                console.error('Auth check error:', error);
                return false;
            });
    }

    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product');
            
            // First check if user is authenticated
            checkAuthentication().then(isAuthenticated => {
                if (isAuthenticated) {
                    addProductToCart(productId, 1);
                } else {
                    showNotification('Please log in to add items to your cart', 'error');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                }
            });
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

        // Log the request parameters (for debugging)
        console.log('Adding to cart:', { productId, quantity });

        // AJAX request to add item to cart
        fetch('add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&quantity=${quantity}`,
            credentials: 'same-origin' // Important: send cookies with the request
        })
        .then(response => {
            // Log the raw response for debugging
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if response is ok before parsing
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Log the parsed data for debugging
            console.log('Response data:', data);
            
            // Reset button state
            setTimeout(() => {
                button.disabled = false;
                
                if (data.success) {
                    // Show success state
                    button.innerHTML = '<i class="fas fa-check"></i> Added!';
                    
                    // Update cart count in header if exists
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement) {
                        // Try each possible property name for cart count
                        if (data.cartCount !== undefined) {
                            cartCountElement.textContent = data.cartCount;
                        } else if (data.cartTotalItems !== undefined) {
                            cartCountElement.textContent = data.cartTotalItems;
                        } else if (data.cartTotalQuantity !== undefined) {
                            cartCountElement.textContent = data.cartTotalQuantity;
                        }
                    }
                    
                    // Show a success message to the user
                    showNotification(data.message || 'Product added to cart successfully', 'success');
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                } else {
                    // Show error state
                    button.innerHTML = '<i class="fas fa-times"></i> Failed';
                    
                    // Handle authentication error separately
                    if (data.message && data.message.includes('log in')) {
                        showNotification('Please log in to add items to your cart', 'error');
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        // Show error message
                        showNotification(data.message || 'Failed to add product to cart', 'error');
                    }
                    
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
            
            // Show error notification
            showNotification('An error occurred while adding the product to cart.', 'error');
            
            // Reset button text after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        });
    }

    /**
     * Show a notification to the user
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, error, info)
     */
    function showNotification(message, type = 'info') {
        // Check if notification container exists, if not create it
        let container = document.querySelector('.notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
            
            // Add some basic styles
            const style = document.createElement('style');
            style.textContent = `
                .notification-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                }
                .notification {
                    margin-bottom: 10px;
                    padding: 15px 20px;
                    border-radius: 4px;
                    color: white;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    display: flex;
                    align-items: center;
                    animation: slideIn 0.3s ease-out forwards;
                }
                .notification-success {
                    background-color: #4CAF50;
                }
                .notification-error {
                    background-color: #F44336;
                }
                .notification-info {
                    background-color: #2196F3;
                }
                .notification i {
                    margin-right: 10px;
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        // Add icon based on type
        let icon = '';
        switch(type) {
            case 'success':
                icon = '<i class="fas fa-check-circle"></i>';
                break;
            case 'error':
                icon = '<i class="fas fa-exclamation-circle"></i>';
                break;
            default:
                icon = '<i class="fas fa-info-circle"></i>';
        }
        
        notification.innerHTML = `${icon} ${message}`;
        container.appendChild(notification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease-in forwards';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
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