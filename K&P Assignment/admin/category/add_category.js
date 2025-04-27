// Character counter for category name
document.getElementById('category_name').addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('charCount').textContent = count + '/255';
    
    // Change color based on length
    if (count > 200) {
        document.getElementById('charCount').className = 'text-orange-500 text-xs';
    } else {
        document.getElementById('charCount').className = 'text-gray-400 text-xs';
    }
});

// Trigger initial character count
document.addEventListener('DOMContentLoaded', function() {
    const inputElement = document.getElementById('category_name');
    if (inputElement.value) {
        const event = new Event('input');
        inputElement.dispatchEvent(event);
    }
});