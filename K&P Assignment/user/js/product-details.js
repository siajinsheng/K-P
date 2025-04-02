document.addEventListener('DOMContentLoaded', function() {
    // Image gallery functionality
    const mainImage = document.getElementById('main-product-image');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            // Update main image
            mainImage.src = this.getAttribute('data-image');
            
            // Update active state
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Quantity controls
    const quantityInput = document.getElementById('quantity');
    const decreaseBtn = document.getElementById('decrease-quantity');
    const increaseBtn = document.getElementById('increase-quantity');
    
    decreaseBtn.addEventListener('click', function() {
        let value = parseInt(quantityInput.value);
        if (value > 1) {
            quantityInput.value = value - 1;
        }
    });
    
    increaseBtn.addEventListener('click', function() {
        let value = parseInt(quantityInput.value);
        let max = parseInt(quantityInput.getAttribute('max'));
        if (value < max) {
            quantityInput.value = value + 1;
        }
    });
    
    // Validate quantity on input
    quantityInput.addEventListener('change', function() {
        let value = parseInt(this.value);
        let min = parseInt(this.getAttribute('min'));
        let max = parseInt(this.getAttribute('max'));
        
        if (isNaN(value) || value < min) {
            this.value = min;
        } else if (value > max) {
            this.value = max;
        }
    });
    
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab');
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabId + '-tab') {
                    content.classList.add('active');
                }
            });
        });
    });
    
    // Add to cart functionality
    const addToCartForm = document.getElementById('add-to-cart-form');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const sizeError = document.getElementById('size-error');
    const quantityError = document.getElementById('quantity-error');
    
    addToCartBtn.addEventListener('click', function() {
        // Reset error messages
        sizeError.textContent = '';
        quantityError.textContent = '';
        
        // Get form data
        const sizeRadios = document.querySelectorAll('input[name="size"]');
        const selectedSize = Array.from(sizeRadios).find(radio => radio.checked);
        const quantity = quantityInput.value;
        const productId = document.querySelector('input[name="product_id"]').value;
        
        // Validate size selection
        if (!selectedSize) {
            sizeError.textContent = 'Please select a size';
            return;
        }
        
        // Validate quantity
        if (quantity < 1) {
            quantityError.textContent = 'Please select a valid quantity';
            return;
        }
        
        // Show loading state
        const originalText = addToCartBtn.innerHTML;
        addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        addToCartBtn.disabled = true;
        
        // Check if user is logged in
        fetch('check-auth.php')
            .then(response => response.json())
            .then(data => {
                if (!data.authenticated) {
                    // If not logged in, redirect to login page
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                    return false;
                }
                return true;
            })
            .then(isAuthenticated => {
                if (!isAuthenticated) return;
                
                // Make AJAX request to add to cart
                return fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&size=${selectedSize.value}&quantity=${quantity}`
                });
            })
            .then(response => {
                if (!response) return; // User not authenticated
                return response.json();
            })
            .then(data => {
                if (!data) return; // User not authenticated
                
                // Reset button state after a delay
                setTimeout(() => {
                    addToCartBtn.disabled = false;
                    
                    if (data.success) {
                        // Show success state
                        addToCartBtn.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
                        
                        // Create or update "in cart" message
                        let inCartMessage = document.querySelector('.in-cart-message');
                        if (!inCartMessage) {
                            inCartMessage = document.createElement('div');
                            inCartMessage.className = 'in-cart-message';
                            document.querySelector('.cart-actions').appendChild(inCartMessage);
                        }
                        inCartMessage.innerHTML = `<i class="fas fa-check-circle"></i> This item is in your cart (Quantity: ${data.totalQuantity})`;
                        
                        // Update cart count in header if exists
                        const cartCountElement = document.querySelector('.cart-count');
                        if (cartCountElement && data.cartTotalItems) {
                            cartCountElement.textContent = data.cartTotalItems;
                        }
                        
                        // Reset button text after 2 seconds
                        setTimeout(() => {
                            addToCartBtn.innerHTML = originalText;
                        }, 2000);
                    } else {
                        // Show error state
                        addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Failed';
                        
                        // Display error message
                        if (data.message) {
                            if (data.field === 'size') {
                                sizeError.textContent = data.message;
                            } else if (data.field === 'quantity') {
                                quantityError.textContent = data.message;
                            } else {
                                alert(data.message);
                            }
                        } else {
                            alert('Failed to add product to cart.');
                        }
                        
                        // Reset button text after 2 seconds
                        setTimeout(() => {
                            addToCartBtn.innerHTML = originalText;
                        }, 2000);
                    }
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Error';
                
                // Alert user about error
                alert('An error occurred while adding the product to cart.');
                
                // Reset button text after 2 seconds
                setTimeout(() => {
                    addToCartBtn.innerHTML = originalText;
                }, 2000);
            });
    });
});