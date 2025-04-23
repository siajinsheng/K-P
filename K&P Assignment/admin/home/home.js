// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
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
                    label: 'Revenue (RM)',
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
    
    // Orders Status Chart
    const ordersPendingElement = document.getElementById('ordersPending');
    const ordersProcessingElement = document.getElementById('ordersProcessing');
    const ordersCompletedElement = document.getElementById('ordersCompleted');
    const totalOrdersElement = document.getElementById('totalOrders');
    
    const ordersPending = ordersPendingElement ? parseInt(ordersPendingElement.value) : 0;
    const ordersProcessing = ordersProcessingElement ? parseInt(ordersProcessingElement.value) : 0;
    const ordersCompleted = ordersCompletedElement ? parseInt(ordersCompletedElement.value) : 0;
    const totalOrders = totalOrdersElement ? parseInt(totalOrdersElement.value) : 0;
    const otherOrders = totalOrders - (ordersPending + ordersProcessing + ordersCompleted);
    
    if(document.getElementById('ordersChart')) {
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Delivered', 'Other'],
                datasets: [{
                    data: [ordersPending, ordersProcessing, ordersCompleted, otherOrders],
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(156, 163, 175, 0.8)'
                    ],
                    borderColor: [
                        'rgba(251, 191, 36, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(156, 163, 175, 1)'
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
                    }
                }
            }
        });
    }
});