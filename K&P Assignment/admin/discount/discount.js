document.addEventListener('DOMContentLoaded', function() {
    // Limit select change handler
    document.getElementById('limitSelect').addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('page', 1); // Reset to first page
        window.location.href = url.toString();
    });
    
    // Sort headers click handler
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.addEventListener('click', function() {
            const sort = this.getAttribute('data-sort');
            const currentSort = '<?= $sort ?>';
            const currentDir = '<?= $dir ?>';
            
            let dir = 'asc';
            if (sort === currentSort) {
                dir = currentDir === 'asc' ? 'desc' : 'asc';
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sort);
            url.searchParams.set('dir', dir);
            window.location.href = url.toString();
        });
    });
    
    // Automatically hide alerts after 5 seconds
    const alerts = document.querySelectorAll('#successAlert, #errorAlert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    });
});