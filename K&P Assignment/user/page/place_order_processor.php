<?php
require_once '../../_base.php';

// Start session and ensure user is logged in
safe_session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to proceed with checkout');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$current_timestamp = date('Y-m-d H:i:s'); // Current timestamp: 2025-04-25 13:42:12

// Handle checkout form submission
if (is_post() && isset($_POST['place_order'])) {
    $address_id = $_POST['address_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($address_id)) {
        $errors[] = 'Please select a delivery address';
    }
    
    if (empty($payment_method) || !in_array($payment_method, ['Credit Card', 'PayPal'])) {
        $errors[] = 'Please select a valid payment method';
    }
    
    // If validation passes, process order
    if (empty($errors)) {
        try {
            // Begin transaction
            $_db->beginTransaction();
            
            // 1. Generate order ID - ORXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(order_id, 3)) as max_id 
                FROM orders 
                WHERE order_id LIKE 'OR%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_order_id = intval($result->max_id) + 1;
            } else {
                $next_order_id = 1; // Start from 1 if no previous orders
            }
            $order_id = 'OR' . str_pad($next_order_id, 3, '0', STR_PAD_LEFT);
            
            // 2. Generate payment ID - PMXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(payment_id, 3)) as max_id 
                FROM payment 
                WHERE payment_id LIKE 'PM%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_payment_id = intval($result->max_id) + 1;
            } else {
                $next_payment_id = 1; // Start from 1 if no previous payments
            }
            $payment_id = 'PM' . str_pad($next_payment_id, 3, '0', STR_PAD_LEFT);
            
            // 3. Generate delivery ID - DVXXX format
            $stm = $_db->prepare("
                SELECT MAX(SUBSTRING(delivery_id, 3)) as max_id 
                FROM delivery 
                WHERE delivery_id LIKE 'DV%'
            ");
            $stm->execute();
            $result = $stm->fetch();
            
            if ($result && !empty($result->max_id)) {
                $next_delivery_id = intval($result->max_id) + 1;
            } else {
                $next_delivery_id = 1; // Start from 1 if no previous deliveries
            }
            $delivery_id = 'DV' . str_pad($next_delivery_id, 3, '0', STR_PAD_LEFT);
            
            // 4. Get cart items with product details
            $stm = $_db->prepare("
                SELECT c.cart_id, c.product_id, c.quantity, c.size, c.added_time,
                       p.product_name, p.product_price, p.product_pic1, p.product_status,
                       q.product_stock
                FROM cart c
                JOIN product p ON c.product_id = p.product_id
                JOIN quantity q ON c.product_id = q.product_id AND c.size = q.size
                WHERE c.user_id = ?
                ORDER BY c.added_time DESC
            ");
            $stm->execute([$user_id]);
            $cart_items = $stm->fetchAll();
            
            // 5. Calculate totals
            $subtotal = 0;
            $total_items = 0;
            
            foreach ($cart_items as $item) {
                $item_total = $item->product_price * $item->quantity;
                $subtotal += $item_total;
                $total_items += $item->quantity;
            }
            
            // Determine shipping fee
            $shipping_fee = $subtotal >= 100 ? 0 : 10;
            
            // Calculate tax (6% GST)
            $tax_rate = 0.06;
            $tax = round($subtotal * $tax_rate, 2);
            
            // Calculate total
            $total = $subtotal + $shipping_fee + $tax;
            
            // 6. Create delivery record
            $estimated_delivery_date = date('Y-m-d', strtotime('+7 days')); // 7 days from now
            
            $stm = $_db->prepare("
                INSERT INTO delivery (delivery_id, address_id, delivery_fee, delivery_status, estimated_date)
                VALUES (?, ?, ?, 'Processing', ?)
            ");
            $stm->execute([$delivery_id, $address_id, $shipping_fee, $estimated_delivery_date]);
            
            // 7. Create order record
            $stm = $_db->prepare("
                INSERT INTO orders (order_id, user_id, delivery_id, order_date, orders_status, order_subtotal, order_total)
                VALUES (?, ?, ?, ?, 'Pending', ?, ?)
            ");
            $stm->execute([$order_id, $user_id, $delivery_id, $current_timestamp, $subtotal, $total]);
            
            // 8. Insert order details
            $stm = $_db->prepare("
                INSERT INTO order_details (order_id, product_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $stm->execute([
                    $order_id,
                    $item->product_id,
                    $item->quantity,
                    $item->product_price
                ]);
                
                // Update product stock
                $update_stock = $_db->prepare("
                    UPDATE quantity 
                    SET product_stock = product_stock - ?, 
                        product_sold = product_sold + ? 
                    WHERE product_id = ? AND size = ?
                ");
                $update_stock->execute([$item->quantity, $item->quantity, $item->product_id, $item->size]);
            }
            
            // 9. Create payment record (with Pending status as we haven't processed payment yet)
            $stm = $_db->prepare("
                INSERT INTO payment (payment_id, order_id, tax, total_amount, payment_method, payment_status, payment_date)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stm->execute([$payment_id, $order_id, $tax, $total, $payment_method, $current_timestamp]);
            
            // 10. Clear user cart
            $stm = $_db->prepare("
                DELETE FROM cart 
                WHERE user_id = ?
            ");
            $stm->execute([$user_id]);
            
            // 11. Commit transaction
            $_db->commit();
            
            // 12. Redirect to payment page
            redirect('payment.php?order_id=' . $order_id);
            
        } catch (PDOException $e) {
            // Rollback on error
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Order processing error: " . $e->getMessage());
            $errors[] = 'An error occurred while processing your order. Please try again.';
        }
    }
    
    // If there were errors, the script will continue execution and display them
    // in the checkout page
}
?>