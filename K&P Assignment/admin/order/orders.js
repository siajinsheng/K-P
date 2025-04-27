// Date picker initialization
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
    });

    // Toggle between table and card views
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    const tableViewBtn = document.getElementById('tableViewBtn');
    const cardViewBtn = document.getElementById('cardViewBtn');

    tableViewBtn.addEventListener('click', function() {
        tableView.style.display = 'block';
        cardView.style.display = 'none';
        tableViewBtn.classList.add('active-view');
        cardViewBtn.classList.remove('active-view');
    });

    cardViewBtn.addEventListener('click', function() {
        tableView.style.display = 'none';
        cardView.style.display = 'grid';
        cardViewBtn.classList.add('active-view');
        tableViewBtn.classList.remove('active-view');
    });

    // Remember view preference in local storage
    const savedView = localStorage.getItem('orderViewPreference');
    if (savedView === 'card') {
        cardViewBtn.click();
    }

    // Save preference when changed
    tableViewBtn.addEventListener('click', () => localStorage.setItem('orderViewPreference', 'table'));
    cardViewBtn.addEventListener('click', () => localStorage.setItem('orderViewPreference', 'card'));
});

function updateStatus(orderId, newStatus) {
    if(confirm(`Are you sure you want to update this order to "${newStatus}" status?`)) {
        fetch('update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                new_status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Order status updated successfully');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the order status');
        });
    }
}

function filterByStatus(status) {
    document.getElementById('status').value = status;
    document.getElementById('filterForm').submit();
}