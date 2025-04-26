// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const currentYear = document.getElementById('currentYear').value;
    
    // Monthly Revenue Chart
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthlyDataElement = document.getElementById('monthlyRevenueData');
    const monthlyData = monthlyDataElement ? JSON.parse(monthlyDataElement.value) : [];
    
    if(document.getElementById('revenueChart')) {
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthNames,
                datasets: [{
                    label: `Revenue (RM) - ${currentYear}`,
                    data: monthlyData,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: RM' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Quarterly Revenue Bar Chart
    const quarterlyDataElement = document.getElementById('quarterlyRevenueData');
    const quarterlyData = quarterlyDataElement ? JSON.parse(quarterlyDataElement.value) : [];
    
    if(document.getElementById('quarterlyChart')) {
        const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
        const quarterlyChart = new Chart(quarterlyCtx, {
            type: 'bar',
            data: {
                labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                datasets: [{
                    label: `Quarterly Revenue (RM) - ${currentYear}`,
                    data: quarterlyData,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
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
                            callback: function(value) {
                                return 'RM' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: RM' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Orders Status Chart
    const ordersPendingElement = document.getElementById('ordersPending');
    const ordersProcessingElement = document.getElementById('ordersProcessing');
    const ordersShippedElement = document.getElementById('ordersShipped');
    const ordersCompletedElement = document.getElementById('ordersCompleted');
    const ordersCancelledElement = document.getElementById('ordersCancelled');
    
    const ordersPending = ordersPendingElement ? parseInt(ordersPendingElement.value) : 0;
    const ordersProcessing = ordersProcessingElement ? parseInt(ordersProcessingElement.value) : 0;
    const ordersShipped = ordersShippedElement ? parseInt(ordersShippedElement.value) : 0;
    const ordersCompleted = ordersCompletedElement ? parseInt(ordersCompletedElement.value) : 0;
    const ordersCancelled = ordersCancelledElement ? parseInt(ordersCancelledElement.value) : 0;
    
    if(document.getElementById('ordersChart')) {
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
                datasets: [{
                    data: [ordersPending, ordersProcessing, ordersShipped, ordersCompleted, ordersCancelled],
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(251, 191, 36, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Category Sales Chart
    const categorySalesLabelsElement = document.getElementById('categorySalesLabels');
    const categorySalesDataElement = document.getElementById('categorySalesData');
    
    const categorySalesLabels = categorySalesLabelsElement ? JSON.parse(categorySalesLabelsElement.value) : [];
    const categorySalesData = categorySalesDataElement ? JSON.parse(categorySalesDataElement.value) : [];
    
    if(document.getElementById('categoryChart')) {
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: categorySalesLabels,
                datasets: [{
                    data: categorySalesData,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(107, 114, 128, 0.8)',
                        'rgba(167, 139, 250, 0.8)'
                    ],
                    borderColor: [
                        'rgba(99, 102, 241, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(107, 114, 128, 1)',
                        'rgba(167, 139, 250, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return `${label}: RM${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
    }
});