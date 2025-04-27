// Delete modal functions
function confirmDelete(categoryId, categoryName) {
    document.getElementById('deleteCategoryId').value = categoryId;
    document.getElementById('deleteCategoryName').textContent = categoryName;
    document.getElementById('deleteModal').classList.remove('hidden');
}

// Generate colors for chart
function generateColors(count, alpha) {
    const colors = [];
    const hueStep = 360 / count;
    
    for (let i = 0; i < count; i++) {
        const hue = i * hueStep;
        colors.push(`hsla(${hue}, 70%, 60%, ${alpha})`);
    }
    
    return colors;
}

// Initialize chart and set up event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Set up delete modal cancel button
    const cancelDeleteBtn = document.getElementById('cancelDelete');
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            document.getElementById('deleteModal').classList.add('hidden');
        });
    }
    
    // Initialize chart if the element exists
    if (document.getElementById('categoryChart')) {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        
        // Check if categoryData is defined (should be set in the PHP file)
        if (typeof categoryData !== 'undefined') {
            // Prepare data for chart
            const labels = categoryData.map(item => item.name);
            const counts = categoryData.map(item => item.count);
            
            // Generate colors
            const backgroundColors = generateColors(categoryData.length, 0.7);
            const borderColors = generateColors(categoryData.length, 1);
            
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Number of Products',
                        data: counts,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
});