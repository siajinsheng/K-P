document.addEventListener('DOMContentLoaded', function() {
    const productCards = document.querySelectorAll('.product-card');
    const submitButton = document.getElementById('createDiscountBtn');
    const searchInput = document.getElementById('productSearch');
    const productCountElement = document.getElementById('productCount');
    const selectedCountElement = document.getElementById('selectedProductCount');
    const selectedListElement = document.getElementById('selectedProductsList');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const discountRateInput = document.getElementById('discount_rate');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const previewContent = document.getElementById('previewContent');
    const emptyState = document.getElementById('emptyState');
    const productGrid = document.getElementById('productGrid');
    const categoryPills = document.querySelectorAll('.category-pill');
    const typeFilters = document.querySelectorAll('.type-filter');
    const selectedProductsInput = document.getElementById('selectedProductsInput');
    
    let selectedProducts = [];
    
    // Set minimum date to today for start date
    const today = new Date().toISOString().split('T')[0];
    startDateInput.min = today;
    
    // Product selection
    productCards.forEach(card => {
        card.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            
            if (this.classList.contains('selected')) {
                // Remove from selection
                this.classList.remove('selected');
                selectedProducts = selectedProducts.filter(p => p.id !== productId);
            } else {
                // Add to selection
                this.classList.add('selected');
                selectedProducts.push({
                    id: productId,
                    name: this.getAttribute('data-product-name'),
                    price: parseFloat(this.getAttribute('data-product-price')),
                    image: this.getAttribute('data-product-image')
                });
            }
            
            updateSelectedProducts();
            updateDiscountPreview();
        });
    });
    
    // Clear selection button
    clearSelectionBtn.addEventListener('click', function() {
        productCards.forEach(card => {
            card.classList.remove('selected');
        });
        selectedProducts = [];
        updateSelectedProducts();
        updateDiscountPreview();
    });
    
    // Product search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        filterProducts();
    });
    
    // Category pills click handler
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function() {
            categoryPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            filterProducts();
        });
    });
    
    // Type filter click handler
    typeFilters.forEach(filter => {
        filter.addEventListener('click', function() {
            typeFilters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
            filterProducts();
        });
    });
    
    // Filter products based on search, category and type
    function filterProducts() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedCategory = document.querySelector('.category-pill.active').getAttribute('data-category');
        const selectedType = document.querySelector('.type-filter.active').getAttribute('data-type');
        
        let visibleCount = 0;
        
        productCards.forEach(card => {
            const productId = card.getAttribute('data-product-id').toLowerCase();
            const productName = card.getAttribute('data-product-name').toLowerCase();
            const productType = card.getAttribute('data-product-type');
            
            // Check search term
            const matchesSearch = productId.includes(searchTerm) || productName.includes(searchTerm);
            
            // Check category
            let matchesCategory = true; // Default to true for "All Categories"
            if (selectedCategory !== 'all') {
                // In a real implementation, we'd have a data attribute for category on each product
                // For this example, we're assuming all match since we don't have that data
                matchesCategory = true;
            }
            
            // Check type
            let matchesType = true; // Default to true for "All"
            if (selectedType !== 'all') {
                matchesType = productType === selectedType;
            }
            
            const shouldShow = matchesSearch && matchesCategory && matchesType;
            
            card.style.display = shouldShow ? 'block' : 'none';
            
            if (shouldShow) {
                visibleCount++;
            }
        });
        
        // Update product count
        productCountElement.textContent = `Showing ${visibleCount} products`;
        
        // Show/hide empty state
        if (visibleCount === 0) {
            emptyState.classList.remove('hidden');
            productGrid.classList.add('hidden');
        } else {
            emptyState.classList.add('hidden');
            productGrid.classList.remove('hidden');
        }
    }
    
    // Start date change handler
    startDateInput.addEventListener('change', function() {
        // End date must be after start date
        endDateInput.min = this.value;
        
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        
        updateDiscountPreview();
    });
    
    // End date change handler
    endDateInput.addEventListener('change', updateDiscountPreview);
    
    // Discount rate change handler
    discountRateInput.addEventListener('input', updateDiscountPreview);
    
    // Function to update the selected products display
    function updateSelectedProducts() {
        // Update count in the badge
        if (selectedProducts.length === 0) {
            selectedCountElement.classList.remove('hidden');
            selectedListElement.classList.add('hidden');
            selectedCountElement.textContent = 'No products selected';
            submitButton.disabled = true;
        } else {
            selectedCountElement.classList.add('hidden');
            selectedListElement.classList.remove('hidden');
            submitButton.disabled = false;
            
            // Create product list HTML
            let listHTML = '';
            selectedProducts.forEach(product => {
                listHTML += `
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <div class="flex items-center">
                            <img src="../../img/${product.image}" alt="${product.name}" class="w-10 h-10 object-cover rounded mr-3">
                            <div class="text-sm">
                                <div class="font-medium text-gray-800 truncate" style="max-width: 200px;" title="${product.name}">${product.name}</div>
                                <div class="text-gray-500">${product.id}</div>
                            </div>
                        </div>
                        <div class="text-sm font-medium">
                            RM ${product.price.toFixed(2)}
                        </div>
                    </div>
                `;
            });
            
            selectedListElement.innerHTML = listHTML;
        }
        
        // Update hidden input fields for form submission
        selectedProductsInput.innerHTML = '';
        selectedProducts.forEach(product => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = product.id;
            selectedProductsInput.appendChild(input);
        });
    }
    
    // Function to update discount preview
    function updateDiscountPreview() {
        const discountRate = parseFloat(discountRateInput.value) || 0;
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (selectedProducts.length === 0) {
            previewContent.innerHTML = '<p class="text-center text-gray-500 py-4">Select products and set discount details to see preview</p>';
            return;
        }
        
        // Determine status based on dates
        let statusText = '';
        let statusClass = '';
        
        if (!startDate || !endDate) {
            statusText = 'Dates not set';
            statusClass = 'bg-gray-100 text-gray-600';
        } else if (today >= startDate && today <= endDate) {
            statusText = 'Will be Active';
            statusClass = 'bg-green-100 text-green-800';
        } else if (today < startDate) {
            statusText = 'Will be Upcoming';
            statusClass = 'bg-blue-100 text-blue-800';
        } else {
            statusText = 'Will be Expired';
            statusClass = 'bg-red-100 text-red-800';
        }
        
        // Format dates nicely
        let formattedStartDate = startDate ? new Date(startDate).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not set';
        let formattedEndDate = endDate ? new Date(endDate).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not set';
        
        // Create preview HTML
        let previewHTML = `
            <div class="mb-4">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-500">Validity:</span>
                        <span class="ml-2 text-sm font-medium">${formattedStartDate} to ${formattedEndDate}</span>
                    </div>
                    <div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-100 pt-3">
                <div class="text-sm text-gray-500 mb-2">Discount Preview (${selectedProducts.length} products):</div>
        `;
        
        // Show up to 3 products in preview
        const displayProducts = selectedProducts.slice(0, 3);
        
        displayProducts.forEach(product => {
            const discountAmount = product.price * (discountRate / 100);
            const finalPrice = product.price - discountAmount;
            
            previewHTML += `
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <div class="flex items-center">
                        <img src="../../img/${product.image}" alt="${product.name}" class="w-10 h-10 object-cover rounded mr-3">
                        <div class="text-sm">
                            <div class="font-medium text-gray-800 truncate" style="max-width: 150px;">${product.name}</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end">
                        <div class="text-xs text-gray-500 line-through">RM ${product.price.toFixed(2)}</div>
                        <div class="text-sm font-bold text-green-600">RM ${finalPrice.toFixed(2)}</div>
                        <div class="text-xs text-red-600">-${discountRate}%</div>
                    </div>
                </div>
            `;
        });
        
        // If there are more products, show a message
        if (selectedProducts.length > 3) {
            const remaining = selectedProducts.length - 3;
            previewHTML += `
                <div class="text-center text-sm text-gray-500 mt-3">
                    + ${remaining} more product${remaining > 1 ? 's' : ''}
                </div>
            `;
        }
        
        previewHTML += '</div>';
        previewContent.innerHTML = previewHTML;
    }
    
    // Initial updates
    updateSelectedProducts();
    updateDiscountPreview();
});