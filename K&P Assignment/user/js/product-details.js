// Modified section of the add-to-cart functionality in product-details.js
// Replace the existing check-auth.php fetch and authentication check with this:

// Add to cart functionality
const addToCartForm = document.getElementById('add-to-cart-form');
const addToCartBtn = document.getElementById('add-to-cart-btn');

if (addToCartBtn) {
    addToCartBtn.addEventListener('click', function() {
        // Reset error messages
        const sizeError = document.getElementById('size-error');
        const quantityError = document.getElementById('quantity-error');
        
        if (sizeError) sizeError.textContent = '';
        if (quantityError) quantityError.textContent = '';
        
        // Get form data
        const productId = document.querySelector('input[name="product_id"]').value;
        let selectedSize = '';
        let quantity = 1;
        
        // Get size if size selection exists
        const sizeRadios = document.querySelectorAll('input[name="size"]');
        if (sizeRadios.length) {
            const selected = Array.from(sizeRadios).find(radio => radio.checked);
            if (!selected) {
                if (sizeError) sizeError.textContent = 'Please select a size';
                return;
            }
            selectedSize = selected.value;
        }
        
        // Get quantity
        if (quantityInput) {
            quantity = parseInt(quantityInput.value);
            if (isNaN(quantity) || quantity < 1) {
                if (quantityError) quantityError.textContent = 'Please select a valid quantity';
                return;
            }
        }
        
        // Show loading state
        const originalText = addToCartBtn.innerHTML;
        addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        addToCartBtn.disabled = true;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('product_id', productId);
        if (selectedSize) formData.append('size', selectedSize);
        formData.append('quantity', quantity);
        
        // Convert to URL encoded string
        const urlEncodedData = new URLSearchParams(formData).toString();
        
        // Make AJAX request directly to add to cart
        // We'll let the server handle authentication checks
        fetch('add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: urlEncodedData
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state after a delay
            setTimeout(() => {
                addToCartBtn.disabled = false;
                
                // Check if we need to redirect to login
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                
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
                    
                    // Update cart count in header
                    updateCartCount(data.cartTotalQuantity || data.totalQuantity);
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        addToCartBtn.innerHTML = originalText;
                    }, 2000);
                    
                    // Show notification
                    showNotification('Product added to cart successfully!');
                } else {
                    // Show error state
                    addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Failed';
                    
                    // Display error message
                    if (data.message) {
                        if (data.field === 'size' && sizeError) {
                            sizeError.textContent = data.message;
                        } else if (data.field === 'quantity' && quantityError) {
                            quantityError.textContent = data.message;
                        } else {
                            showNotification(data.message, 'error');
                        }
                    } else {
                        showNotification('Failed to add product to cart.', 'error');
                    }
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        addToCartBtn.innerHTML = originalText;
                    }, 2000);
                }
            }, 800);
        })
        .catch(error => {
            console.error('Error:', error);
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Error';
            
            // Show error notification
            showNotification('An error occurred while adding the product to cart.', 'error');
            
            // Reset button text after 2 seconds
            setTimeout(() => {
                addToCartBtn.innerHTML = originalText;
            }, 2000);
        });
    });
}