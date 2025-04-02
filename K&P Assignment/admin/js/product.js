// Wait for DOM to be loaded
document.addEventListener('DOMContentLoaded', function () {
    // Modal Elements
    const addProductModal = document.getElementById('addProductModal');
    const addProductBtn = document.getElementById('addProductBtn');
    const closeModalButtons = document.querySelectorAll('.closeModal');

    // Open Add Product Modal
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function () {
            addProductModal.classList.remove('hidden');
        });
    }

    // Close Modal Functionality
    closeModalButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            addProductModal.classList.add('hidden');
        });
    });

    // Close Modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === addProductModal) {
            addProductModal.classList.add('hidden');
        }
    });

    // Image preview functionality
    const imageInputs = document.querySelectorAll('input[type="file"]');
    imageInputs.forEach(function (input) {
        input.addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    // Find the closest preview element if it exists
                    const label = event.target.closest('div').querySelector('label');
                    const previewId = event.target.id + '_preview';
                    let preview = document.getElementById(previewId);

                    // Create preview element if it doesn't exist
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = previewId;
                        preview.classList.add('mt-2', 'rounded-md', 'h-32', 'w-auto');
                        event.target.closest('div').appendChild(preview);
                    }

                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Form validation for product ID format
    const productIdInput = document.getElementById('product_id');
    if (productIdInput) {
        productIdInput.addEventListener('blur', function () {
            const value = this.value.trim();
            if (value && !value.match(/^P\d{3}$/)) {
                alert('Product ID should follow the format P001, P002, etc.');
            }
        });
    }

    // Price input validation to ensure it's a positive number
    const priceInput = document.getElementById('product_price');
    if (priceInput) {
        priceInput.addEventListener('change', function () {
            if (parseFloat(this.value) <= 0) {
                alert('Price must be greater than 0');
                this.value = '';
            }
        });
    }

    // Confirm before closing edit form if changes were made
    const editForm = document.querySelector('form[action="edit"]');
    if (editForm) {
        const originalValues = {};
        const inputs = editForm.querySelectorAll('input, select, textarea');

        // Store original values
        inputs.forEach(input => {
            if (input.type !== 'file') {
                originalValues[input.name] = input.value;
            }
        });

        // Check for changes before leaving
        window.addEventListener('beforeunload', function (e) {
            let hasChanges = false;

            inputs.forEach(input => {
                if (input.type !== 'file' && input.type !== 'hidden') {
                    if (originalValues[input.name] !== input.value) {
                        hasChanges = true;
                    }
                } else if (input.type === 'file' && input.files.length > 0) {
                    hasChanges = true;
                }
            });

            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
});