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
    
    if (decreaseBtn && increaseBtn && quantityInput) {
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
    }
    
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
            
            // Check if user is logged in first
            fetch('check-auth.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.authenticated) {
                        // If not logged in, redirect to login page with return URL
                        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                        return false;
                    }
                    return true;
                })
                .then(isAuthenticated => {
                    if (!isAuthenticated) return;
                    
                    // Prepare form data
                    const formData = new FormData();
                    formData.append('product_id', productId);
                    if (selectedSize) formData.append('size', selectedSize);
                    formData.append('quantity', quantity);
                    
                    // Convert to URL encoded string
                    const urlEncodedData = new URLSearchParams(formData).toString();
                    
                    // Make AJAX request to add to cart
                    return fetch('add-to-cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: urlEncodedData
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
    
    // Update cart count in header
    function updateCartCount(count) {
        // Update cart count in header if exists
        let cartCountElement = document.querySelector('.cart-count');
        
        if (count > 0) {
            if (!cartCountElement) {
                // Create the count element if it doesn't exist
                cartCountElement = document.createElement('span');
                cartCountElement.className = 'cart-count';
                
                // Find the cart icon and append the count
                const cartIconContainer = document.querySelector('.cart-icon-container');
                if (cartIconContainer) {
                    cartIconContainer.appendChild(cartCountElement);
                }
            }
            
            // Update the count
            cartCountElement.textContent = count;
            cartCountElement.style.display = 'flex';
        } else if (cartCountElement) {
            cartCountElement.style.display = 'none';
        }
        
        // Also update in sidebar if exists
        const sidebarCartCount = document.querySelector('.cart-count-sidebar');
        if (sidebarCartCount) {
            if (count > 0) {
                sidebarCartCount.textContent = count;
                sidebarCartCount.style.display = 'inline-flex';
            } else {
                sidebarCartCount.style.display = 'none';
            }
        }
    }
    
    // Show notification
    function showNotification(message, type = 'success') {
        // Create notification element if it doesn't exist
        let notification = document.getElementById('notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'notification';
            document.body.appendChild(notification);
            
            // Add styles
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.borderRadius = '4px';
            notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '300px';
            notification.style.display = 'flex';
            notification.style.alignItems = 'center';
            notification.style.transform = 'translateX(120%)';
            notification.style.transition = 'transform 0.3s ease-out';
            notification.style.fontSize = '14px';
            notification.style.fontWeight = '500';
        }
        
        // Set type-specific styles
        if (type === 'success') {
            notification.style.backgroundColor = '#d4edda';
            notification.style.color = '#155724';
            notification.style.borderLeft = '4px solid #28a745';
            notification.innerHTML = '<i class="fas fa-check-circle" style="margin-right: 10px; font-size: 18px;"></i>' + message;
        } else if (type === 'error') {
            notification.style.backgroundColor = '#f8d7da';
            notification.style.color = '#721c24';
            notification.style.borderLeft = '4px solid #dc3545';
            notification.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 10px; font-size: 18px;"></i>' + message;
        } else if (type === 'info') {
            notification.style.backgroundColor = '#d1ecf1';
            notification.style.color = '#0c5460';
            notification.style.borderLeft = '4px solid #17a2b8';
            notification.innerHTML = '<i class="fas fa-info-circle" style="margin-right: 10px; font-size: 18px;"></i>' + message;
        } else if (type === 'warning') {
            notification.style.backgroundColor = '#fff3cd';
            notification.style.color = '#856404';
            notification.style.borderLeft = '4px solid #ffc107';
            notification.innerHTML = '<i class="fas fa-exclamation-triangle" style="margin-right: 10px; font-size: 18px;"></i>' + message;
        }
        
        // Add close button
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.marginLeft = '10px';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.fontSize = '20px';
        closeBtn.style.lineHeight = '1';
        closeBtn.style.position = 'absolute';
        closeBtn.style.right = '10px';
        closeBtn.style.top = '10px';
        closeBtn.onclick = function() {
            notification.style.transform = 'translateX(120%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        };
        notification.appendChild(closeBtn);
        
        // Show notification
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(120%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }
    
    // Size selection highlight
    const sizeOptions = document.querySelectorAll('.size-option input');
    sizeOptions.forEach(option => {
        option.addEventListener('change', function() {
            const stockInfo = this.parentElement.querySelector('small');
            if (stockInfo) {
                const availableStock = parseInt(stockInfo.textContent.match(/\d+/)[0]);
                
                // Update quantity max value based on selected size stock
                if (quantityInput) {
                    quantityInput.setAttribute('max', availableStock);
                    
                    // Reset quantity if current value exceeds new max
                    if (parseInt(quantityInput.value) > availableStock) {
                        quantityInput.value = availableStock;
                    }
                }
            }
        });
    });
    
    // Handle "Buy Now" button if it exists
    const buyNowBtn = document.getElementById('buy-now-btn');
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            // First add to cart with the same validation
            addToCartBtn.click();
            
            // Then check if the add to cart was successful
            const checkSuccess = setInterval(() => {
                const successMessage = document.querySelector('.in-cart-message');
                if (successMessage) {
                    clearInterval(checkSuccess);
                    // Redirect to checkout page after a short delay
                    setTimeout(() => {
                        window.location.href = 'checkout.php';
                    }, 1000);
                }
            }, 200);
            
            // Set a timeout to stop checking after 5 seconds
            setTimeout(() => {
                clearInterval(checkSuccess);
            }, 5000);
        });
    }
    
    // Product zoom functionality on hover
    if (mainImage) {
        const productGallery = document.querySelector('.product-gallery');
        if (productGallery) {
            let isZoomed = false;
            
            mainImage.addEventListener('click', function() {
                if (!isZoomed) {
                    // Create a zoomed view
                    const zoomContainer = document.createElement('div');
                    zoomContainer.className = 'zoom-container';
                    zoomContainer.style.position = 'fixed';
                    zoomContainer.style.top = '0';
                    zoomContainer.style.left = '0';
                    zoomContainer.style.width = '100%';
                    zoomContainer.style.height = '100%';
                    zoomContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
                    zoomContainer.style.zIndex = '9999';
                    zoomContainer.style.display = 'flex';
                    zoomContainer.style.justifyContent = 'center';
                    zoomContainer.style.alignItems = 'center';
                    zoomContainer.style.cursor = 'zoom-out';
                    
                    const zoomedImage = document.createElement('img');
                    zoomedImage.src = this.src;
                    zoomedImage.style.maxWidth = '90%';
                    zoomedImage.style.maxHeight = '90%';
                    zoomedImage.style.objectFit = 'contain';
                    zoomedImage.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.5)';
                    
                    zoomContainer.appendChild(zoomedImage);
                    document.body.appendChild(zoomContainer);
                    
                    // Close zoomed view when clicking anywhere
                    zoomContainer.addEventListener('click', function() {
                        document.body.removeChild(zoomContainer);
                        isZoomed = false;
                    });
                    
                    isZoomed = true;
                }
            });
            
            // Add zoom hint
            const zoomHint = document.createElement('div');
            zoomHint.className = 'zoom-hint';
            zoomHint.innerHTML = '<i class="fas fa-search-plus"></i> Click to zoom';
            zoomHint.style.position = 'absolute';
            zoomHint.style.bottom = '10px';
            zoomHint.style.right = '10px';
            zoomHint.style.backgroundColor = 'rgba(0, 0, 0, 0.6)';
            zoomHint.style.color = '#fff';
            zoomHint.style.padding = '5px 10px';
            zoomHint.style.borderRadius = '4px';
            zoomHint.style.fontSize = '12px';
            zoomHint.style.opacity = '0';
            zoomHint.style.transition = 'opacity 0.3s';
            
            const mainImageContainer = document.querySelector('.main-image');
            if (mainImageContainer) {
                mainImageContainer.style.position = 'relative';
                mainImageContainer.appendChild(zoomHint);
                
                mainImageContainer.addEventListener('mouseenter', function() {
                    zoomHint.style.opacity = '1';
                });
                
                mainImageContainer.addEventListener('mouseleave', function() {
                    zoomHint.style.opacity = '0';
                });
            }
        }
    }
    
    // Recently viewed products functionality
    function addToRecentlyViewed() {
        try {
            // Get product ID from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const productId = urlParams.get('id');
            
            if (!productId) return;
            
            // Get current product info
            const productName = document.querySelector('.product-title').textContent;
            const productImage = document.getElementById('main-product-image').src;
            const productPrice = document.querySelector('.price').textContent;
            
            // Get existing recently viewed products from localStorage
            let recentProducts = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
            
            // Check if product already exists in the list
            const existingIndex = recentProducts.findIndex(p => p.id === productId);
            
            // If exists, remove it (will be added to the start)
            if (existingIndex !== -1) {
                recentProducts.splice(existingIndex, 1);
            }
            
            // Add current product to the beginning
            recentProducts.unshift({
                id: productId,
                name: productName,
                image: productImage,
                price: productPrice,
                timestamp: new Date().toISOString()
            });
            
            // Keep only the last 6 products
            recentProducts = recentProducts.slice(0, 6);
            
            // Save back to localStorage
            localStorage.setItem('recentlyViewed', JSON.stringify(recentProducts));
        } catch (e) {
            console.error('Error updating recently viewed products:', e);
        }
    }
    
    // Call function to add current product to recently viewed
    addToRecentlyViewed();
    
    // Show timestamp for debugging (only for admin users)
    const debugInfo = document.createElement('div');
    debugInfo.style.display = 'none';
    debugInfo.id = 'debug-info';
    debugInfo.style.position = 'fixed';
    debugInfo.style.bottom = '5px';
    debugInfo.style.left = '5px';
    debugInfo.style.fontSize = '10px';
    debugInfo.style.color = '#999';
    debugInfo.textContent = `Last updated: ${new Date().toISOString()} | User: ${document.cookie.includes('admin=') ? 'Admin' : 'User'}`;
    document.body.appendChild(debugInfo);
    
    // Show debug info only for admins (check for admin role in URL or cookie)
    if (window.location.search.includes('debug=true') || document.cookie.includes('admin=')) {
        debugInfo.style.display = 'block';
    }
    
    // Keyboard shortcuts (for accessibility)
    document.addEventListener('keydown', function(e) {
        // ESC key closes zoomed image
        if (e.key === 'Escape') {
            const zoomContainer = document.querySelector('.zoom-container');
            if (zoomContainer) {
                document.body.removeChild(zoomContainer);
                isZoomed = false;
            }
        }
        
        // Alt + A adds to cart (accessibility shortcut)
        if (e.altKey && e.key === 'a' && addToCartBtn && !addToCartBtn.disabled) {
            e.preventDefault();
            addToCartBtn.click();
        }
    });
});