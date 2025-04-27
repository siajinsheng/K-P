$(document).ready(function() {
    // Handle thumbnail click to change main image
    $('.thumbnail').on('click', function() {
        const imgSrc = $(this).attr('src');
        $('#mainImage').attr('src', imgSrc);

        // Update active thumbnail
        $('.thumbnail').removeClass('active-thumbnail');
        $(this).addClass('active-thumbnail');
    });

    // Initialize with first image as active
    $('.thumbnail:first').addClass('active-thumbnail');

    // Size selection handling
    $('.size-btn').on('click', function() {
        $('.size-btn').removeClass('bg-indigo-600 text-white');
        $('.size-btn').addClass('bg-white text-gray-800');
        $(this).removeClass('bg-white text-gray-800');
        $(this).addClass('bg-indigo-600 text-white');
    });

    // Simple placeholder stock chart
    if ($('#stockChart').length) {
        const stockCtx = document.getElementById('stockChart').getContext('2d');
        const stockChart = new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: stockSizes,
                datasets: [{
                    label: 'In Stock',
                    data: stockQuantities,
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 1
                }, {
                    label: 'Sold',
                    data: stockSold,
                    backgroundColor: 'rgba(99, 102, 241, 0.4)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Print functionality
    $('#printBtn').on('click', function(e) {
        e.preventDefault();
        window.print();
    });
});