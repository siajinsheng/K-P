document.addEventListener('DOMContentLoaded', function() {
    const discountRateInput = document.getElementById('discount_rate');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const previewContent = document.getElementById('previewContent');
    
    // These variables will be provided by the PHP file
    // We'll use data attributes or a global variable to pass this data
    const productDetails = window.productDetails || {};
    const productPrice = productDetails.price;
    const productImage = productDetails.image;
    const productName = productDetails.name;
    const productId = productDetails.id;
    
    // Start date change handler
    startDateInput.addEventListener('change', function() {
        // End date must be after start date
        if (this.value && endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        
        updateDiscountPreview();
    });
    
    // End date change handler
    endDateInput.addEventListener('change', updateDiscountPreview);
    
    // Discount rate change handler
    discountRateInput.addEventListener('input', updateDiscountPreview);
    
    // Function to update discount preview
    function updateDiscountPreview() {
        const discountRate = parseFloat(discountRateInput.value) || 0;
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        // Calculate discounted price
        const price = parseFloat(productPrice) || 0;
        const discountAmount = price * (discountRate / 100);
        const finalPrice = price - discountAmount;
        
        // Determine status based on dates
        let statusText = '';
        let statusClass = '';
        const today = new Date().toISOString().split('T')[0];
        
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
        previewContent.innerHTML = `
            <div class="flex flex-col md:flex-row items-start gap-6">
                <div class="flex-1">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Original Price</div>
                            <div class="font-bold text-gray-700 line-through">RM ${price.toFixed(2)}</div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500">Discount Rate</div>
                            <div class="font-bold text-red-600">${discountRate.toFixed(1)}% OFF</div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500">Discounted Price</div>
                            <div class="font-bold text-green-600">RM ${finalPrice.toFixed(2)}</div>
                        </div>
                        
                        <div>
                            <div class="text-sm text-gray-500">You Save</div>
                            <div class="font-bold text-indigo-600">RM ${discountAmount.toFixed(2)}</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:justify-between gap-2">
                            <div>
                                <span class="text-sm text-gray-500">New Validity:</span>
                                <span class="ml-2 text-sm font-medium">${formattedStartDate} to ${formattedEndDate}</span>
                            </div>
                            <div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Initial update of discount preview
    updateDiscountPreview();
});