document.addEventListener('DOMContentLoaded', function() {
    // Get all plus buttons
    const plusButtons = document.querySelectorAll('.quantity-btn.plus');
    
    // Get all minus buttons
    const minusButtons = document.querySelectorAll('.quantity-btn.minus');
    
    // Get all remove buttons
    const removeButtons = document.querySelectorAll('.remove-item-btn');
    
    // Get checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    
    // Handle quantity increase
    plusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.getAttribute('data-cart-id');
            const quantityInput = document.querySelector(`.quantity-input[data-cart-id="${cartId}"]`);
            let quantity = parseInt(quantityInput.value);
            
            if (quantity < 10) {
                quantity++;
                updateCartItemQuantity(cartId, quantity, quantityInput);
            }
        });
    });
    
    // Handle quantity decrease
    minusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.getAttribute('data-cart-id');
            const quantityInput = document.querySelector(`.quantity-input[data-cart-id="${cartId}"]`);
            let quantity = parseInt(quantityInput.value);
            
            if (quantity > 1) {
                quantity--;
                updateCartItemQuantity(cartId, quantity, quantityInput);
            }
        });
    });
    
    // Handle item removal
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.getAttribute('data-cart-id');
            removeCartItem(cartId);
        });
    });
    
    // Handle checkout
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            window.location.href = 'checkout.php';
        });
    }
    
    // Update cart item quantity
    function updateCartItemQuantity(cartId, quantity, inputElement) {
        // Show loading state
        inputElement.classList.add('loading');
        
        // Prepare form data
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('quantity', quantity);
        formData.append('action', 'update');
        
        // Convert to URL encoded string
        const urlEncodedData = new URLSearchParams(formData).toString();
        
        // Make AJAX request
        fetch('update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: urlEncodedData
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading state
            inputElement.classList.remove('loading');
            
            if (data.success) {
                // Update quantity input
                inputElement.value = quantity;
                
                // Update item total
                const cartItem = inputElement.closest('.cart-item');
                const itemTotalElement = cartItem.querySelector('.item-total');
                itemTotalElement.textContent = `RM ${data.itemTotal.toFixed(2)}`;
                
                // Update cart summary
                document.querySelector('.cart-subtotal').textContent = `RM ${data.subtotal.toFixed(2)}`;
                
                // Update shipping fee
                const shippingFeeElement = document.querySelector('.shipping-fee');
                if (data.shippingFee > 0) {
                    shippingFeeElement.textContent = `RM ${data.shippingFee.toFixed(2)}`;
                    shippingFeeElement.classList.remove('free');
                    
                    // Show free shipping message
                    const freeShippingMessage = document.querySelector('.free-shipping-message');
                    if (freeShippingMessage) {
                        freeShippingMessage.style.display = 'block';
                    }
                } else {
                    shippingFeeElement.textContent = 'FREE';
                    shippingFeeElement.classList.add('free');
                    
                    // Hide free shipping message
                    const freeShippingMessage = document.querySelector('.free-shipping-message');
                    if (freeShippingMessage) {
                        freeShippingMessage.style.display = 'none';
                    }
                }
                
                // Update cart total
                document.querySelector('.cart-total').textContent = `RM ${data.total.toFixed(2)}`;
                
                // Show notification
                showNotification('Cart updated successfully');
            } else {
                // Revert changes
                inputElement.value = data.currentQuantity;
                
                // Show error notification
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            inputElement.classList.remove('loading');
            showNotification('An error occurred while updating the cart', 'error');
        });
    }
    
    // Remove cart item
    function removeCartItem(cartId) {
        // Show confirmation dialog
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        // Get cart item element
        const cartItem = document.querySelector(`.cart-item[data-cart-id="${cartId}"]`);
        
        // Add removing class for animation
        cartItem.classList.add('removing');
        
        // Prepare form data
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('action', 'remove');
        
        // Convert to URL encoded string
        const urlEncodedData = new URLSearchParams(formData).toString();
        
        // Make AJAX request
        fetch('update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: urlEncodedData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item from DOM after animation
                setTimeout(() => {
                    cartItem.remove();
                    
                    // Check if cart is empty
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        // Show empty cart message
                        const cartContainer = document.querySelector('.cart-container');
                        cartContainer.innerHTML = `
                            <div class="empty-cart">
                                <i class="fas fa-shopping-cart fa-4x"></i>
                                <h2>Your cart is empty</h2>
                                <p>Looks like you haven't added anything to your cart yet.</p>
                                <a href="products.php" class="continue-shopping-btn">Continue Shopping</a>
                            </div>
                        `;
                    } else {
                        // Update cart summary
                        document.querySelector('.cart-subtotal').textContent = `RM ${data.subtotal.toFixed(2)}`;
                        
                        // Update shipping fee
                        const shippingFeeElement = document.querySelector('.shipping-fee');
                        if (data.shippingFee > 0) {
                            shippingFeeElement.textContent = `RM ${data.shippingFee.toFixed(2)}`;
                            shippingFeeElement.classList.remove('free');
                            
                            // Show free shipping message
                            const freeShippingMessage = document.querySelector('.free-shipping-message');
                            if (freeShippingMessage) {
                                freeShippingMessage.style.display = 'block';
                            }
                        } else {
                            shippingFeeElement.textContent = 'FREE';
                            shippingFeeElement.classList.add('free');
                            
                            // Hide free shipping message
                            const freeShippingMessage = document.querySelector('.free-shipping-message');
                            if (freeShippingMessage) {
                                freeShippingMessage.style.display = 'none';
                            }
                        }
                        
                        // Update cart total
                        document.querySelector('.cart-total').textContent = `RM ${data.total.toFixed(2)}`;
                    }
                    
                    // Update cart count in header
                    updateCartCount(data.cartCount);
                    
                    // Show notification
                    showNotification('Item removed from cart');
                }, 300);
            } else {
                // Remove removing class
                cartItem.classList.remove('removing');
                
                // Show error notification
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            cartItem.classList.remove('removing');
            showNotification('An error occurred while removing the item', 'error');
        });
    }
    
    // Show notification
    function showNotification(message, type = 'success') {
        // Check if notification container exists
        let notificationContainer = document.querySelector('.notification-container');
        
        // Create notification container if it doesn't exist
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.className = 'notification-container';
            document.body.appendChild(notificationContainer);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Add icon based on type
        let icon = 'check-circle';
        if (type === 'error') {
            icon = 'times-circle';
        } else if (type === 'warning') {
            icon = 'exclamation-circle';
        } else if (type === 'info') {
            icon = 'info-circle';
        }
        
        // Set notification content
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
        `;
        
        // Add notification to container
        notificationContainer.appendChild(notification);
        
        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            
            // Remove notification from DOM after animation
            setTimeout(() => {
                notification.remove();
                
                // Remove container if empty
                if (notificationContainer.children.length === 0) {
                    notificationContainer.remove();
                }
            }, 300);
        }, 3000);
    }
    
    // Update cart count in header
    function updateCartCount(count) {
        const cartCountElement = document.querySelector('.cart-count');
        if (cartCountElement) {
            cartCountElement.textContent = count;
            
            // Show or hide based on count
            if (count > 0) {
                cartCountElement.style.display = 'flex';
            } else {
                cartCountElement.style.display = 'none';
            }
        }
    }
});