// Auto-hide success message after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success message after 5 seconds
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 1s';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.style.display = 'none', 1000);
        }, 5000);
    }

    // Auto-hide error message after 8 seconds
    const errorAlert = document.querySelector('.alert-error');
    if (errorAlert) {
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 1s';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.style.display = 'none', 1000);
        }, 8000);
    }

    // Tabs functionality
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(this.getAttribute('data-tab')).classList.add('active');
        });
    });

    // Add Size Row
    document.getElementById('addSizeBtn').addEventListener('click', function() {
        const tableBody = document.getElementById('sizeTableBody');
        const newRow = document.createElement('tr');
        newRow.className = 'size-row';

        newRow.innerHTML = `
            <td class="py-2 px-4 border-b">
                <select name="size[]" class="w-full">
                    <option value="S">S</option>
                    <option value="M">M</option>
                    <option value="L">L</option>
                    <option value="XL">XL</option>
                    <option value="XXL">XXL</option>
                </select>
            </td>
            <td class="py-2 px-4 border-b">
                <input type="number" name="stock[]" min="0" class="w-full" value="0">
            </td>
            <td class="py-2 px-4 border-b text-gray-500">0</td>
            <td class="py-2 px-4 border-b">
                <div class="action-buttons">
                    <button type="button" class="text-red-500 hover:text-red-700" onclick="removeSize(this)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </td>
        `;

        tableBody.appendChild(newRow);
    });

    // Form submission - validate main image presence
    document.getElementById('updateProductForm').addEventListener('submit', function(event) {
        const mainImageField = document.getElementById('product_pic1');
        const mainImageRemove = document.getElementById('remove_product_pic1');

        // Check if main image would be removed without replacement
        if (mainImageRemove.value === '1' && (!mainImageField.files || !mainImageField.files[0])) {
            event.preventDefault();
            alert('Main product image (Image 1) is required. Please upload an image before saving.');

            // Switch to the Images tab
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelector('[data-tab="images"]').classList.add('active');
            document.getElementById('images').classList.add('active');

            return false;
        }

        document.getElementById('submitSpinner').style.display = 'inline-block';
        document.getElementById('submitBtn').disabled = true;
    });

    // Special handling for main image remove buttons
    document.querySelectorAll('.main-img-remove-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const fieldId = this.getAttribute('data-field');
            const input = document.getElementById(fieldId);

            // If it's the main image and no replacement is selected, show warning
            if (fieldId === 'product_pic1' && (!input.files || !input.files[0])) {
                e.preventDefault();
                alert('Main product image cannot be removed without a replacement image. Please select a new image first.');
                return false;
            }
        });
    });

    // Set up image input event listeners
    const imageInputs = ['product_pic1', 'product_pic2', 'product_pic3'];
    imageInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('change', function() {
                if (input.files && input.files[0]) {
                    if (!validateImage(input.files[0], inputId)) {
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    const previewContainer = document.querySelector(`#dropZone_${inputId} .img-preview`);
                    const removeField = document.getElementById(`remove_${inputId}`);
                    const removeBtn = document.getElementById(`removeBtn_${inputId}`);

                    reader.onload = function(e) {
                        // Clear any previous image or placeholder
                        previewContainer.innerHTML = '';

                        // Create and append the new image
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.id = `preview_${inputId}`;

                        // Create image toolbar for the new image
                        const toolbar = document.createElement('div');
                        toolbar.className = 'img-toolbar';
                        toolbar.innerHTML = `
                            <div class="img-tool" title="Rotate Left" onclick="processImage('${input.files[0].name}', 'rotate_left', '${inputId}', this)">
                                <i class="fas fa-undo"></i>
                            </div>
                            <div class="img-tool" title="Rotate Right" onclick="processImage('${input.files[0].name}', 'rotate_right', '${inputId}', this)">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="img-tool" title="Flip Horizontal" onclick="processImage('${input.files[0].name}', 'flip_horizontal', '${inputId}', this)">
                                <i class="fas fa-arrows-alt-h"></i>
                            </div>
                            <div class="img-tool" title="Flip Vertical" onclick="processImage('${input.files[0].name}', 'flip_vertical', '${inputId}', this)">
                                <i class="fas fa-arrows-alt-v"></i>
                            </div>
                        `;

                        previewContainer.appendChild(img);
                        previewContainer.appendChild(toolbar);

                        // Reset the remove flag and show remove button
                        removeField.value = "0";
                        removeBtn.classList.remove('hidden');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            });
        }
    });

    // Show last updated info when page loads
    const currentDateTime = getCurrentDateTime();
    const userInfo = document.createElement('div');
    userInfo.className = 'text-xs text-gray-500 text-center mt-4';
    userInfo.innerHTML = `Last updated: ${currentDateTime}`;
    document.querySelector('.sticky-actions').before(userInfo);
});

// Help sidebar toggle
function toggleHelpSidebar() {
    document.getElementById('helpSidebar').classList.toggle('open');
}

// Remove Size Row
function removeSize(button) {
    if (document.querySelectorAll('.size-row').length > 1) {
        button.closest('tr').remove();
    } else {
        alert('At least one size is required.');
    }
}

// Process image function for rotate/flip operations
function processImage(filename, operation, fieldId, buttonElement) {
    // Show loading state
    buttonElement.classList.add('loading');

    // Prepare request data
    const requestData = {
        filename: filename,
        operation: operation
    };

    // Send request to process image
    fetch('process_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(result => {
            // Remove loading state
            buttonElement.classList.remove('loading');

            if (result.success) {
                // Update image with new version (add timestamp to prevent caching)
                const imgElement = document.getElementById(`preview_${fieldId}`);
                imgElement.src = `../../img/${result.updated_url}`;

                // Add a temporary highlight effect
                const preview = buttonElement.closest('.img-preview');
                preview.style.boxShadow = '0 0 0 3px rgba(79, 70, 229, 0.6)';
                setTimeout(() => {
                    preview.style.boxShadow = '';
                }, 1000);
            } else {
                console.error('Error processing image:', result.message);
                alert('Error processing image: ' + result.message);
            }
        })
        .catch(error => {
            // Remove loading state
            buttonElement.classList.remove('loading');
            console.error('Error:', error);
            alert('An error occurred while processing the image');
        });
}

// Remove image (sets hidden flag and clears preview)
function removeImage(fieldId) {
    // Get references to elements
    const input = document.getElementById(fieldId);
    const previewContainer = document.querySelector(`#dropZone_${fieldId} .img-preview`);
    const removeField = document.getElementById(`remove_${fieldId}`);
    const removeBtn = document.getElementById(`removeBtn_${fieldId}`);

    // Special handling for the main image (product_pic1)
    if (fieldId === 'product_pic1') {
        // Check if there is a new image selected to replace it
        if (!input.files || !input.files[0]) {
            alert('Main product image (Image 1) cannot be removed without a replacement. Please select a new image first.');
            return; // Don't proceed with removal
        }
    }

    // Clear file input value
    input.value = '';

    // Set the remove flag to 1 (true) to tell the backend to remove the image
    removeField.value = "1";

    // Clear the preview and add the placeholder
    const isMainImage = fieldId === 'product_pic1';
    previewContainer.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full text-gray-400" id="placeholder_${fieldId}">
            <i class="fas fa-image text-4xl mb-2"></i>
            <span class="text-sm">${isMainImage ? 'Main image required' : 'Drag and drop or click to upload'}</span>
        </div>
    `;

    // Hide the remove button since there's no longer an image to remove
    removeBtn.classList.add('hidden');
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Product ID copied to clipboard');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}

// Drag and drop handlers
function handleDragOver(e, fieldId) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById(`dropZone_${fieldId}`).classList.add('highlight');
}

function handleDragLeave(e, fieldId) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById(`dropZone_${fieldId}`).classList.remove('highlight');
}

function handleDrop(e, fieldId) {
    e.preventDefault();
    e.stopPropagation();
    const dropZone = document.getElementById(`dropZone_${fieldId}`);
    dropZone.classList.remove('highlight');

    const dt = e.dataTransfer;
    const files = dt.files;
    const input = document.getElementById(fieldId);

    if (files.length > 0) {
        const file = files[0];
        if (validateImage(file, fieldId)) {
            // Create a FileList-like object
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);

            // Set the file input's files property
            input.files = dataTransfer.files;

            // Trigger the change event manually
            const event = new Event('change', {
                bubbles: true
            });
            input.dispatchEvent(event);
        }
    }
}

function validateImage(file, fieldId) {
    const dropZone = document.getElementById(`dropZone_${fieldId}`);

    // Check file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        dropZone.classList.add('error');
        setTimeout(() => dropZone.classList.remove('error'), 2000);
        alert('Only JPG, PNG, and GIF files are allowed!');
        return false;
    }

    // Check file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        dropZone.classList.add('error');
        setTimeout(() => dropZone.classList.remove('error'), 2000);
        alert('File size exceeds 5MB limit!');
        return false;
    }

    return true;
}

// Display current date and time in user's timezone
function getCurrentDateTime() {
    const now = new Date();
    const options = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    return now.toLocaleString('en-US', options);
}