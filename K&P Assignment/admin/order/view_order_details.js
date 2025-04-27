function updateOrderStatus(orderId, newStatus) {
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

function updateStatusFromForm() {
    const orderId = document.querySelector('input[name="orderId"]').value;
    const newStatus = document.querySelector('select[name="newStatus"]').value;
    
    if (!newStatus) {
        alert('Please select a status');
        return;
    }
    
    updateOrderStatus(orderId, newStatus);
}

function printOrderDetails() {
    window.print();
}