document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const dropZonePreviews = document.getElementById('dropZonePreviews');
    const uploadedImagesInput = document.getElementById('uploadedImagesInput');
    const productTypeInput = document.getElementById('product_type_input');
    const typeOptions = document.querySelectorAll('.type-option');
    let uploadedFiles = [];

    // Handle product type selection
    typeOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            typeOptions.forEach(el => el.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Update hidden input
            productTypeInput.value = this.getAttribute('data-value');
        });
    });

    // Set initial selection if value exists
    if (productTypeInput.value) {
        document.querySelector(`.type-option[data-value="${productTypeInput.value}"]`)?.classList.add('selected');
    }

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);

    // Handle click to select files
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    }, false);

    // Reset button handling
    document.querySelector('button[type="reset"]').addEventListener('click', function() {
        // Clear all previews
        dropZonePreviews.innerHTML = '';

        // Delete all uploaded files from server
        uploadedFiles.forEach(filename => {
            deleteUploadedFile(filename);
        });

        // Reset product type selection
        typeOptions.forEach(el => el.classList.remove('selected'));
        productTypeInput.value = '';

        uploadedFiles = [];
        uploadedImagesInput.value = JSON.stringify(uploadedFiles);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight() {
        dropZone.classList.add('drag-over');
    }

    function unhighlight() {
        dropZone.classList.remove('drag-over');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        // Limit to 3 files based on database schema
        if (uploadedFiles.length + files.length > 3) {
            alert('Maximum 3 images allowed');
            return;
        }

        // Process each file
        Array.from(files).forEach(file => {
            if (!validateImage(file)) {
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const uniqueFileName = Date.now() + '_' + file.name.replace(/\s+/g, '_');

                // Create preview
                const previewDiv = document.createElement('div');
                previewDiv.classList.add('drop-zone-preview');
                previewDiv.dataset.filename = uniqueFileName;

                const img = document.createElement('img');
                img.src = e.target.result;

                const removeBtn = document.createElement('div');
                removeBtn.classList.add('drop-zone-preview-remove');
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.addEventListener('click', () => {
                    // Remove from previews
                    dropZonePreviews.removeChild(previewDiv);

                    // Remove from uploaded files
                    uploadedFiles = uploadedFiles.filter(f => f !== uniqueFileName);

                    // Update hidden input
                    uploadedImagesInput.value = JSON.stringify(uploadedFiles);

                    deleteUploadedFile(uniqueFileName);
                });

                // NEW: Add image editing toolbar
                const toolbar = document.createElement('div');
                toolbar.classList.add('image-edit-toolbar');
                
                // Rotate left button
                const rotateLeftBtn = document.createElement('div');
                rotateLeftBtn.classList.add('edit-tool');
                rotateLeftBtn.innerHTML = '<i class="fas fa-undo"></i>';
                rotateLeftBtn.title = "Rotate Left";
                rotateLeftBtn.addEventListener('click', () => {
                    processImage(uniqueFileName, 'rotate_left', img, rotateLeftBtn);
                });
                
                // Rotate right button
                const rotateRightBtn = document.createElement('div');
                rotateRightBtn.classList.add('edit-tool');
                rotateRightBtn.innerHTML = '<i class="fas fa-redo"></i>';
                rotateRightBtn.title = "Rotate Right";
                rotateRightBtn.addEventListener('click', () => {
                    processImage(uniqueFileName, 'rotate_right', img, rotateRightBtn);
                });
                
                // Flip horizontal button
                const flipHBtn = document.createElement('div');
                flipHBtn.classList.add('edit-tool');
                flipHBtn.innerHTML = '<i class="fas fa-arrows-alt-h"></i>';
                flipHBtn.title = "Flip Horizontal";
                flipHBtn.addEventListener('click', () => {
                    processImage(uniqueFileName, 'flip_horizontal', img, flipHBtn);
                });
                
                // Flip vertical button
                const flipVBtn = document.createElement('div');
                flipVBtn.classList.add('edit-tool');
                flipVBtn.innerHTML = '<i class="fas fa-arrows-alt-v"></i>';
                flipVBtn.title = "Flip Vertical";
                flipVBtn.addEventListener('click', () => {
                    processImage(uniqueFileName, 'flip_vertical', img, flipVBtn);
                });
                
                // Add all buttons to the toolbar
                toolbar.appendChild(rotateLeftBtn);
                toolbar.appendChild(rotateRightBtn);
                toolbar.appendChild(flipHBtn);
                toolbar.appendChild(flipVBtn);

                previewDiv.appendChild(img);
                previewDiv.appendChild(removeBtn);
                previewDiv.appendChild(toolbar);
                dropZonePreviews.appendChild(previewDiv);

                // Upload file via AJAX
                uploadFile(file, uniqueFileName);
            };
            reader.readAsDataURL(file);
        });

        // Clear file input
        if (fileInput.value) fileInput.value = '';
    }

    function processImage(filename, operation, imgElement, buttonElement) {
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
                imgElement.src = `../../img/${result.updated_url}`;
                
                // Add a temporary highlight effect
                const preview = imgElement.closest('.drop-zone-preview');
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

    function uploadFile(file, uniqueFileName) {
        const formData = new FormData();
        formData.append('image', file, uniqueFileName);

        fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    uploadedFiles.push(result.filename);
                    uploadedImagesInput.value = JSON.stringify(uploadedFiles);

                    // Add a nice animation to the preview
                    const preview = document.querySelector(`.drop-zone-preview[data-filename="${result.filename}"]`);
                    if (preview) {
                        preview.classList.add('animate-pulse');
                        setTimeout(() => {
                            preview.classList.remove('animate-pulse');
                        }, 1000);
                    }
                } else {
                    alert('Upload failed: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Upload failed. Please try again.');
            });
    }

    function deleteUploadedFile(filename) {
        fetch('delete_image.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    console.error('Error deleting file:', result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Validate form before submission
    document.getElementById('productForm').addEventListener('submit', function(e) {
        // Check product type
        if (!productTypeInput.value) {
            e.preventDefault();
            alert('Please select a product type (Men, Women, or Unisex)');
            return false;
        }
        
        let totalStock = 0;
        const sizes = ['S', 'M', 'L', 'XL', 'XXL'];

        sizes.forEach(size => {
            const qtyInput = document.getElementById('quantity_' + size);
            if (qtyInput) {
                totalStock += parseInt(qtyInput.value || 0);
            }
        });

        if (totalStock <= 0) {
            e.preventDefault();
            alert('Please add stock for at least one size');
            document.querySelector('.panel-section:nth-child(2)').scrollIntoView({
                behavior: 'smooth'
            });
            return false;
        }

        if (uploadedFiles.length === 0) {
            e.preventDefault();
            alert('Please upload at least one product image');
            document.querySelector('.panel-section:nth-child(3)').scrollIntoView({
                behavior: 'smooth'
            });
            return false;
        }

        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        submitBtn.disabled = true;

        return true;
    });

    // Cancel button event
    document.querySelector('a[href="product.php"]').addEventListener('click', function(e) {
        // Prevent default navigation
        e.preventDefault();

        // Delete all uploaded files from server
        if (uploadedFiles.length > 0) {
            uploadedFiles.forEach(filename => {
                deleteUploadedFile(filename);
            });

            // Clear uploaded files array
            uploadedFiles = [];
        }

        // Navigate to product.php after cleanup
        window.location.href = "product.php";
    });

    function validateImage(file) {
        // Check file type
        if (!file.type.startsWith('image/')) {
            alert('Only image files are allowed');
            return false;
        }

        // Check file size (limit to 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image size should be less than 5MB');
            return false;
        }

        return true;
    }
});