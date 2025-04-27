document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('csv_file');
    const fileUploadBtn = document.getElementById('file-upload-btn');
    const fileInfo = document.getElementById('file-info');
    const fileNameText = document.getElementById('file-name-text');
    
    fileInput.addEventListener('change', function() {
        if (fileInput.files.length > 0) {
            const fileName = fileInput.files[0].name;
            fileNameText.textContent = fileName;
            fileInfo.classList.remove('hidden');
            fileUploadBtn.style.borderColor = '#4f46e5';
            fileUploadBtn.style.backgroundColor = 'rgba(79, 70, 229, 0.05)';
        } else {
            fileInfo.classList.add('hidden');
            fileUploadBtn.style.borderColor = '#cbd5e1';
            fileUploadBtn.style.backgroundColor = '#f8fafc';
        }
    });
    
    fileUploadBtn.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Handle drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadBtn.addEventListener(eventName, preventDefaults, false);
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadBtn.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadBtn.addEventListener(eventName, unhighlight, false);
    });
    
    fileUploadBtn.addEventListener('drop', handleDrop, false);
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        fileUploadBtn.style.borderColor = '#4f46e5';
        fileUploadBtn.style.backgroundColor = 'rgba(79, 70, 229, 0.1)';
    }
    
    function unhighlight() {
        fileUploadBtn.style.borderColor = '#cbd5e1';
        fileUploadBtn.style.backgroundColor = '#f8fafc';
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        
        if (files.length > 0) {
            const fileName = files[0].name;
            fileNameText.textContent = fileName;
            fileInfo.classList.remove('hidden');
        }
    }
});