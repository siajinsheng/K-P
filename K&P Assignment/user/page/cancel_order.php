<?php
require_once '../../_base.php';

// Ensure user is authenticated
safe_session_start();
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to manage orders');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$order_id = req('id');

if (empty($order_id)) {
    temp('error', 'No order specified');
    redirect('orders.php');
}

try {
    // Check if order belongs to user and is in a cancellable state
    $stm = $_db->prepare("
        SELECT * FROM orders
        WHERE order_id = ? AND user_id = ?
    ");
    $stm->execute([$order_id, $user_id]);
    $order = $stm->fetch();
    
    if (!$order) {
        temp('error', 'Order not found or does not belong to your account');
        redirect('orders.php');
    }
    
    // Check if order is in a cancellable state
    if ($order->orders_status !== 'Pending') {
        temp('error', 'This order cannot be cancelled because it is already ' . strtolower($order->orders_status));
        redirect('order-details.php?id=' . $order_id);
    }
    
    // Process the cancellation if confirmation received
    if (isset($_POST['confirm_cancel']) && $_POST['confirm_cancel'] === 'yes') {
        $_db->beginTransaction();
        
        // Update order status to Cancelled
        $stm = $_db->prepare("
            UPDATE orders
            SET orders_status = 'Cancelled'
            WHERE order_id = ?
        ");
        $stm->execute([$order_id]);
        
        // Add cancellation reason if provided
        if (!empty($_POST['cancel_reason'])) {
            $reason = trim($_POST['cancel_reason']);
            
            // Here you could store the cancellation reason in a cancellation_history table
            // For now, we'll just log it
            error_log("Order $order_id cancelled by user $user_id. Reason: $reason");
        }
        
        // Update any related payment records
        $stm = $_db->prepare("
            UPDATE payment
            SET payment_status = 'Refunded'
            WHERE order_id = ? AND payment_status = 'Completed'
        ");
        $stm->execute([$order_id]);
        
        // Return inventory - adjust product stocks
        $stm = $_db->prepare("
            SELECT od.product_id, od.quantity
            FROM order_details od
            WHERE od.order_id = ?
        ");
        $stm->execute([$order_id]);
        $items = $stm->fetchAll();
        
        foreach ($items as $item) {
            // Update product quantity (simplified - in real system, you'd need to handle size-specific inventory)
            $update_stock = $_db->prepare("
                UPDATE quantity
                SET product_stock = product_stock + ?, product_sold = product_sold - ?
                WHERE product_id = ?
                LIMIT 1
            ");
            $update_stock->execute([$item->quantity, $item->quantity, $item->product_id]);
        }
        
        $_db->commit();
        
        temp('success', 'Your order has been cancelled successfully');
        redirect('orders.php');
    }
    
} catch (PDOException $e) {
    if ($_db->inTransaction()) {
        $_db->rollBack();
    }
    
    error_log("Error cancelling order: " . $e->getMessage());
    temp('error', 'An error occurred while processing your request. Please try again.');
    redirect('orders.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Cancel Order</title>
    <link rel="stylesheet" href="../css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cancel-order-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            font-size: 1.8rem;
            color: var(--danger-color);
        }
        
        .form-header .warning-icon {
            font-size: 3rem;
            color: var(--warning-color);
            margin-bottom: 20px;
        }
        
        .cancel-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .order-info {
            background-color: var(--light-gray);
            padding: 15px;
            border-radius: var(--border-radius);
        }
        
        .order-info p {
            margin: 5px 0;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="cancel-order-container">
        <div class="form-header">
            <i class="fas fa-exclamation-triangle warning-icon"></i>
            <h1>Cancel Order</h1>
            <p>Please confirm that you want to cancel this order</p>
        </div>
        
        <div class="order-info">
            <p><strong>Order #:</strong> <?= htmlspecialchars($order->order_id) ?></p>
            <p><strong>Date Placed:</strong> <?= date('F d, Y', strtotime($order->order_date)) ?></p>
            <p><strong>Total Amount:</strong> RM <?= number_format($order->order_total, 2) ?></p>
        </div>
        
        <form method="post" class="cancel-form">
            <div class="form-group">
                <label for="cancel_reason">Reason for cancellation (optional)</label>
                <select id="cancel_reason_select" name="cancel_reason_select" onchange="handleReasonChange()">
                    <option value="">Select a reason</option>
                    <option value="Changed my mind">Changed my mind</option>
                    <option value="Found a better price elsewhere">Found a better price elsewhere</option>
                    <option value="Ordered the wrong item/size">Ordered the wrong item/size</option>
                    <option value="Delivery time too long">Delivery time too long</option>
                    <option value="Other">Other (please specify)</option>
                </select>
            </div>
            
            <div class="form-group" id="other_reason_container" style="display: none;">
                <label for="cancel_reason">Please specify your reason</label>
                <textarea id="cancel_reason" name="cancel_reason" placeholder="Please provide details about why you're cancelling this order"></textarea>
            </div>
            
            <div class="form-actions">
                <a href="order-details.php?id=<?= $order_id ?>" class="btn outline-btn">Go Back</a>
                <button type="submit" name="confirm_cancel" value="yes" class="btn danger-btn" onclick="return confirmCancel()">
                    Cancel Order
                </button>
            </div>
        </form>
    </div>
    
    <?php include('../footer.php'); ?>
    
    <script>
        function handleReasonChange() {
            const reasonSelect = document.getElementById('cancel_reason_select');
            const otherReasonContainer = document.getElementById('other_reason_container');
            const reasonTextarea = document.getElementById('cancel_reason');
            
            if (reasonSelect.value === 'Other') {
                otherReasonContainer.style.display = 'block';
                reasonTextarea.value = '';
            } else {
                otherReasonContainer.style.display = 'none';
                reasonTextarea.value = reasonSelect.value;
            }
        }
        
        function confirmCancel() {
            return confirm('Are you sure you want to cancel this order? This action cannot be undone.');
        }
    </script>
</body>
</html>