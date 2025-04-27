// Handle alert dismissal for error alerts
setTimeout(function() {
    const alert = document.getElementById('errorAlert');
    if (alert) {
        alert.classList.add('translate-x-full');
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);

// Handle alert dismissal for success alerts
setTimeout(function() {
    const alert = document.getElementById('successAlert');
    if (alert) {
        alert.classList.add('translate-x-full');
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);

// Add script to position alerts dynamically when page loads
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.navbar');
    const headerHeight = header ? header.offsetHeight + 10 : 85;

    const errorAlert = document.getElementById('errorAlert');
    if (errorAlert) errorAlert.style.top = `${headerHeight}px`;

    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        successAlert.style.top = errorAlert ?
            `${headerHeight + errorAlert.offsetHeight + 10}px` :
            `${headerHeight}px`;
    }
    
    // Get low stock count from data attribute or element content
    const lowStockCard = document.getElementById('lowStockCard');
    const lowStockCount = lowStockCard ? parseInt(lowStockCard.getAttribute('data-count') || '0') : 0;
    
    if (lowStockCount > 0) {
        showNotification('Low Stock Alert', `${lowStockCount} products are running low on stock.`, 'warning');
    }

    // Low stock modal functionality
    if (lowStockCard) {
        const lowStockModal = document.getElementById('lowStockModal');
        const closeLowStockModal = document.getElementById('closeLowStockModal');
        const closeLowStockBtn = document.getElementById('closeLowStockBtn');

        lowStockCard.addEventListener('click', function() {
            lowStockModal.classList.remove('hidden');
        });

        if (closeLowStockModal) {
            closeLowStockModal.addEventListener('click', function() {
                lowStockModal.classList.add('hidden');
            });
        }

        if (closeLowStockBtn) {
            closeLowStockBtn.addEventListener('click', function() {
                lowStockModal.classList.add('hidden');
            });
        }
    }

    // Batch update modal functionality
    const batchUpdateBtn = document.getElementById('batchUpdateBtn');
    if (batchUpdateBtn) {
        const batchUpdateModal = document.getElementById('batchUpdateModal');
        const closeBatchUpdateModal = document.getElementById('closeBatchUpdateModal');
        const cancelBatchUpdate = document.getElementById('cancelBatchUpdate');
        const applyBatchUpdate = document.getElementById('applyBatchUpdate');
        const updateType = document.getElementById('updateType');
        const selectedProductsCount = document.getElementById('selectedProductsCount');
        const percentageFields = document.getElementById('percentageFields');
        const exactPriceField = document.getElementById('exactPriceField');
        const statusField = document.getElementById('statusField');
        const categoryField = document.getElementById('categoryField');

        batchUpdateBtn.addEventListener('click', function() {
            const selectedProducts = getSelectedProducts();
            if (selectedProducts.length === 0) {
                showNotification('No Products Selected', 'Please select products to update.', 'error');
                return;
            }

            selectedProductsCount.textContent = `${selectedProducts.length} products selected.`;
            updateType.value = '';
            hideAllUpdateFields();
            applyBatchUpdate.disabled = true;

            batchUpdateModal.classList.remove('hidden');
        });

        updateType.addEventListener('change', function() {
            hideAllUpdateFields();
            applyBatchUpdate.disabled = false;

            switch (this.value) {
                case 'increase_price':
                case 'decrease_price':
                    percentageFields.classList.remove('hidden');
                    break;
                case 'set_price':
                    exactPriceField.classList.remove('hidden');
                    break;
                case 'change_status':
                    statusField.classList.remove('hidden');
                    break;
                case 'change_category':
                    categoryField.classList.remove('hidden');
                    break;
                default:
                    applyBatchUpdate.disabled = true;
                    break;
            }
        });

        if (closeBatchUpdateModal) {
            closeBatchUpdateModal.addEventListener('click', function() {
                batchUpdateModal.classList.add('hidden');
            });
        }

        if (cancelBatchUpdate) {
            cancelBatchUpdate.addEventListener('click', function() {
                batchUpdateModal.classList.add('hidden');
            });
        }

        if (applyBatchUpdate) {
            applyBatchUpdate.addEventListener('click', function() {
                const selectedProducts = getSelectedProducts();
                const updateTypeValue = updateType.value;

                if (selectedProducts.length === 0) {
                    showNotification('No Products Selected', 'Please select products to update.', 'error');
                    return;
                }

                if (!updateTypeValue) {
                    showNotification('No Update Type', 'Please select an update type.', 'error');
                    return;
                }

                let updateValue = '';
                let statusValue = '';
                let categoryValue = '';

                switch (updateTypeValue) {
                    case 'increase_price':
                    case 'decrease_price':
                        updateValue = document.getElementById('percentValue').value;
                        if (!updateValue || isNaN(updateValue) || updateValue <= 0 || updateValue > 100) {
                            showNotification('Invalid Value', 'Please enter a valid percentage between 0.01 and 100.', 'error');
                            return;
                        }
                        break;
                    case 'set_price':
                        updateValue = document.getElementById('exactPrice').value;
                        if (!updateValue || isNaN(updateValue) || updateValue <= 0) {
                            showNotification('Invalid Value', 'Please enter a valid price greater than 0.', 'error');
                            return;
                        }
                        break;
                    case 'change_status':
                        statusValue = document.getElementById('newStatus').value;
                        if (!statusValue) {
                            showNotification('Invalid Status', 'Please select a valid status.', 'error');
                            return;
                        }
                        break;
                    case 'change_category':
                        categoryValue = document.getElementById('newCategory').value;
                        if (!categoryValue) {
                            showNotification('Invalid Category', 'Please select a valid category.', 'error');
                            return;
                        }
                        break;
                }

                // Confirmation dialog
                let confirmMessage = '';
                switch (updateTypeValue) {
                    case 'increase_price':
                        confirmMessage = `Are you sure you want to increase the price of ${selectedProducts.length} products by ${updateValue}%?`;
                        break;
                    case 'decrease_price':
                        confirmMessage = `Are you sure you want to decrease the price of ${selectedProducts.length} products by ${updateValue}%?`;
                        break;
                    case 'set_price':
                        confirmMessage = `Are you sure you want to set the price of ${selectedProducts.length} products to RM ${updateValue}?`;
                        break;
                    case 'change_status':
                        confirmMessage = `Are you sure you want to change the status of ${selectedProducts.length} products to ${statusValue}?`;
                        break;
                    case 'change_category':
                        const categoryName = document.getElementById('newCategory').options[document.getElementById('newCategory').selectedIndex].text;
                        confirmMessage = `Are you sure you want to change the category of ${selectedProducts.length} products to ${categoryName}?`;
                        break;
                }

                if (confirm(confirmMessage)) {
                    processBatchUpdate(selectedProducts, updateTypeValue, updateValue, statusValue, categoryValue);
                }
            });
        }
        
        // Batch update processing function
        function processBatchUpdate(productIds, updateType, updateValue = '', statusValue = '', categoryValue = '') {
            // Show loading indicator
            showNotification('Processing...', 'Updating products, please wait.', 'info');

            // Create form data to send
            const formData = new FormData();
            formData.append('batch_update', '1');
            formData.append('update_type', updateType);
            formData.append('update_value', updateValue);
            formData.append('status_value', statusValue);
            formData.append('category_value', categoryValue);

            // Append all product IDs
            productIds.forEach(productId => {
                formData.append('product_ids[]', productId);
            });

            // Send AJAX request
            fetch('product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    batchUpdateModal.classList.add('hidden');

                    if (data.success) {
                        showNotification('Success', data.message, 'success');
                        // Reload page after a short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error', 'An error occurred while processing the update.', 'error');
                });
        }
    }

    // Helper function to hide all update fields
    function hideAllUpdateFields() {
        const percentageFields = document.getElementById('percentageFields');
        const exactPriceField = document.getElementById('exactPriceField');
        const statusField = document.getElementById('statusField');
        const categoryField = document.getElementById('categoryField');
        
        if (percentageFields) percentageFields.classList.add('hidden');
        if (exactPriceField) exactPriceField.classList.add('hidden');
        if (statusField) statusField.classList.add('hidden');
        if (categoryField) categoryField.classList.add('hidden');
    }

    // Helper function to get selected products
    function getSelectedProducts() {
        const checkboxes = document.querySelectorAll('.product-select:checked');
        return Array.from(checkboxes).map(checkbox => checkbox.value);
    }

    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update selectAll checkbox state when individual checkboxes change
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('product-select')) {
                const allCheckboxes = document.querySelectorAll('.product-select');
                const checkedCheckboxes = document.querySelectorAll('.product-select:checked');
                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            }
        });
    }

    // Limit select change handler
    const limitSelect = document.getElementById('limitSelect');
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        });
    }

    // Sort headers click handler
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.addEventListener('click', function() {
            const sort = this.getAttribute('data-sort');
            // Get current sort and direction from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort') || 'product_id';
            const currentDir = urlParams.get('dir') || 'asc';

            let dir = 'asc';
            if (sort === currentSort) {
                dir = currentDir === 'asc' ? 'desc' : 'asc';
            }

            const url = new URL(window.location.href);
            url.searchParams.set('sort', sort);
            url.searchParams.set('dir', dir);
            window.location.href = url.toString();
        });
    });

    // Status select change handler
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            const newStatus = this.value;

            // Apply visual styling based on status
            if (newStatus === 'Available') {
                this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-green-100 text-green-800';
            } else if (newStatus === 'Out of Stock') {
                this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-yellow-100 text-yellow-800';
            } else if (newStatus === 'Discontinued') {
                this.className = 'status-select rounded-full text-xs py-1 px-2 border bg-red-100 text-red-800';
            }

            // Send AJAX request to update status
            const formData = new FormData();
            formData.append('update_status', '1');
            formData.append('product_id', productId);
            formData.append('status', newStatus);

            fetch('product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Success', 'Product status updated successfully.', 'success');
                    } else {
                        showNotification('Error', 'Error updating product status: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error', 'An error occurred while updating status.', 'error');
                });
        });
    });

    // Stock modal functionality
    const stockModal = document.getElementById('stockModal');
    if (stockModal) {
        const closeStockModal = document.getElementById('closeStockModal');
        const closeStockBtn = document.getElementById('closeStockBtn');
        const stockModalTitle = document.getElementById('stockModalTitle');
        const stockContent = document.getElementById('stockContent');

        document.querySelectorAll('.view-stock-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');

                stockModalTitle.textContent = 'Stock Levels: ' + productName;
                stockModal.classList.remove('hidden');

                // Fetch stock information
                fetch(`get_stock.php?product_id=${productId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.stock.length > 0) {
                            let stockHtml = `
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sold</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                        `;

                            data.stock.forEach(item => {
                                // Set color class based on stock level
                                let stockColorClass = 'text-green-600';
                                if (item.product_stock < 10) {
                                    stockColorClass = 'text-yellow-600';
                                }
                                if (item.product_stock <= 5) {
                                    stockColorClass = 'text-red-600';
                                }

                                stockHtml += `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${item.size}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium ${stockColorClass}">${item.product_stock}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.product_sold}</td>
                                </tr>
                            `;
                            });

                            stockHtml += `
                                </tbody>
                            </table>
                            <div class="mt-4 text-sm text-gray-500">
                                <div class="flex items-center mb-1">
                                    <span class="h-3 w-3 rounded-full bg-green-600 mr-2"></span>
                                    <span>Good stock level (10+)</span>
                                </div>
                                <div class="flex items-center mb-1">
                                    <span class="h-3 w-3 rounded-full bg-yellow-600 mr-2"></span>
                                    <span>Low stock level (6-9)</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-red-600 mr-2"></span>
                                    <span>Critical stock level (0-5)</span>
                                </div>
                            </div>
                        `;

                            stockContent.innerHTML = stockHtml;
                        } else {
                            stockContent.innerHTML = `
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-exclamation-circle text-3xl mb-3"></i>
                                <p>No stock information available for this product.</p>
                            </div>
                        `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching stock data:', error);
                        stockContent.innerHTML = `
                        <div class="p-6 text-center text-red-500">
                            <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                            <p>Error loading stock information. Please try again.</p>
                        </div>
                    `;
                    });
            });
        });

        if (closeStockModal) {
            closeStockModal.addEventListener('click', () => {
                stockModal.classList.add('hidden');
            });
        }

        if (closeStockBtn) {
            closeStockBtn.addEventListener('click', () => {
                stockModal.classList.add('hidden');
            });
        }
    }

    // Close modals when clicking outside
    window.addEventListener('click', (event) => {
        const stockModal = document.getElementById('stockModal');
        const lowStockModal = document.getElementById('lowStockModal');
        const batchUploadModal = document.getElementById('batchUploadModal');
        const batchUpdateModal = document.getElementById('batchUpdateModal');
        
        if (event.target === stockModal) {
            stockModal.classList.add('hidden');
        }
        if (event.target === lowStockModal) {
            lowStockModal.classList.add('hidden');
        }
        if (event.target === batchUploadModal) {
            batchUploadModal.classList.add('hidden');
        }
        if (event.target === batchUpdateModal) {
            batchUpdateModal.classList.add('hidden');
        }
    });
});

// Helper function to show notifications
function showNotification(title, message, type = 'info') {
    const id = 'notification-' + Date.now();

    let bgColor, textColor, borderColor, icon;
    switch (type) {
        case 'success':
            bgColor = 'bg-green-100';
            textColor = 'text-green-700';
            borderColor = 'border-green-500';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-100';
            textColor = 'text-red-700';
            borderColor = 'border-red-500';
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            bgColor = 'bg-yellow-100';
            textColor = 'text-yellow-700';
            borderColor = 'border-yellow-500';
            icon = 'fa-exclamation-triangle';
            break;
        default: // info
            bgColor = 'bg-blue-100';
            textColor = 'text-blue-700';
            borderColor = 'border-blue-500';
            icon = 'fa-info-circle';
    }

    const notification = document.createElement('div');
    notification.id = id;

    // Calculate header height to position notifications below it
    const header = document.querySelector('.navbar');
    const headerHeight = header ? header.offsetHeight + 10 : 90; // 10px extra padding

    notification.className = `fixed right-5 ${bgColor} border-l-4 ${borderColor} ${textColor} p-4 rounded shadow-md z-1001 transform transition-transform duration-500 translate-x-0`;

    // Set top position dynamically based on header height
    notification.style.top = `${headerHeight}px`;

    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-3"></i>
            <div>
                <div class="font-bold">${title}</div>
                <div>${message}</div>
            </div>
        </div>
        <button class="absolute top-1 right-1 ${textColor} hover:${textColor}" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    document.body.appendChild(notification);

    // Stack notifications if multiple are present
    stackNotifications();

    setTimeout(() => {
        const alert = document.getElementById(id);
        if (alert) {
            alert.classList.add('translate-x-full');
            setTimeout(() => {
                alert.remove();
                stackNotifications(); // Re-stack remaining notifications
            }, 500);
        }
    }, 5000);
}

// Function to stack notifications vertically
function stackNotifications() {
    const notifications = document.querySelectorAll('[id^="notification-"]');
    const header = document.querySelector('.navbar');
    const headerHeight = header ? header.offsetHeight + 10 : 90;

    let currentTop = headerHeight;

    notifications.forEach((notification) => {
        notification.style.top = `${currentTop}px`;
        currentTop += notification.offsetHeight + 10; // 10px spacing between notifications
    });
}