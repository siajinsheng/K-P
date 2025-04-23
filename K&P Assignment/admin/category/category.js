document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('categorySearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll("#categoryTable tbody tr");
            
            tableRows.forEach(row => {
                const categoryId = row.cells[0].textContent.toLowerCase();
                const categoryName = row.cells[1].textContent.toLowerCase();
                
                if (categoryId.includes(searchTerm) || categoryName.includes(searchTerm)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    }
    
    // Category distribution chart
    if (document.getElementById('categoryChart') && typeof categoryData !== 'undefined') {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed.y + ' products';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Delete confirmation modal
    const modal = document.getElementById('deleteModal');
    const cancelBtn = document.getElementById('cancelDelete');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.classList.add('hidden');
        });
    }
});

// Helper function to confirm delete
function confirmDelete(categoryId, categoryName) {
    const modal = document.getElementById('deleteModal');
    const nameElement = document.getElementById('deleteCategoryName');
    const idInput = document.getElementById('deleteCategoryId');
    
    nameElement.textContent = categoryName;
    idInput.value = categoryId;
    modal.classList.remove('hidden');
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