document.addEventListener('DOMContentLoaded', function() {
    // Quantity controls
    const quantityBtns = document.querySelectorAll('.quantity-btn');
    
    quantityBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('data-input'));
            let value = parseInt(input.value);
            let min = parseInt(input.getAttribute('min') || 1);
            let max = parseInt(input.getAttribute('max') || 10);
            
            if (this.classList.contains('minus') && value > min) {
                input.value = value - 1;
            } else if (this.classList.contains('plus') && value < max) {
                input.value = value + 1;
            }
            
            // Trigger change event to update form
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        });
    });
    
    // Validate quantity inputs on change
    const quantityInputs = document.querySelectorAll('.quantity-controls input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            let min = parseInt(this.getAttribute('min') || 1);
            let max = parseInt(this.getAttribute('max') || 10);
            
            if (isNaN(value) || value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
            }
        });
    });
    
    // Add data attributes for mobile view
    const cartItems = document.querySelectorAll('.cart-item');
    
    cartItems.forEach(item => {
        // Add data labels for responsive design
        const priceCol = item.querySelector('.cart-item-price');
        const quantityCol = item.querySelector('.cart-item-quantity');
        const totalCol = item.querySelector('.cart-item-total');
        
        if (priceCol) priceCol.setAttribute('data-label', 'Price:');
        if (quantityCol) quantityCol.setAttribute('data-label', 'Quantity:');
        if (totalCol) totalCol.setAttribute('data-label', 'Total:');
    });
    
    // Confirm before removing items
    const removeLinks = document.querySelectorAll('.remove-item');
    
    removeLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this item from your shopping bag?')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto submit form on enter in quantity field
    quantityInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('update-cart-form').submit();
            }
        });
    });
});