<?php
if (isset($_GET['id']) && !empty($_GET['id'])) {
    include('connectDatabase.php');

    $booking_id = $_GET['id'];

    // Begin transaction
    if (!mysqli_begin_transaction($connection)) {
        die("Transaction initiation failed: " . mysqli_error($connection));
    }

    // Retrieve ticket quantity
    $ticket_quantity_query = mysqli_query($connection, "SELECT ticket_id, quantity FROM booking WHERE booking_id = '$booking_id'");
    if (!$ticket_quantity_query) {
        echo "Error retrieving ticket quantity: " . mysqli_error($connection);
        mysqli_rollback($connection);
        exit;
    }

    while ($row = mysqli_fetch_assoc($ticket_quantity_query)) {
        $ticket_id = $row['ticket_id'];
        $quantity = $row['quantity'];

        // Update ticket availability
        $update_ticket_query = mysqli_query($connection, "UPDATE ticket SET ticketAvailable = ticketAvailable + $quantity WHERE ticket_id = '$ticket_id'");
        if (!$update_ticket_query) {
            echo "Error updating ticket availability: " . mysqli_error($connection);
            mysqli_rollback($connection);
            exit;
        }
    }

    // Delete associated payment
    $delete_payment_query = mysqli_query($connection, "DELETE FROM payment WHERE booking_id = '$booking_id'");
    if (!$delete_payment_query) {
        echo "Deletion error: " . mysqli_error($connection);
        mysqli_rollback($connection);
        exit;
    }

    // Delete booking
    $delete_booking_query = mysqli_query($connection, "DELETE FROM booking WHERE booking_id = '$booking_id'");
    if (!$delete_booking_query) {
        echo "Deletion error: " . mysqli_error($connection);
        mysqli_rollback($connection);
        exit;
    }

    // Update event state
    $update_event_state_query = "UPDATE events SET event_status = 0 WHERE event_id IN (SELECT event_id FROM ticket WHERE ticketAvailable = 0)";
    if (!mysqli_query($connection, $update_event_state_query)) {
        echo "Error updating event status: " . mysqli_error($connection);
        mysqli_rollback($connection);
        exit;
    }

    // Commit transaction
    if (!mysqli_commit($connection)) {
        echo "Transaction commit failed: " . mysqli_error($connection);
        exit;
    }

    echo "<script>
            alert('Booking and associated payment successfully deleted!');
            window.location.href = 'viewBooking.php';
          </script>";
    exit;
} else {
    header("Location: viewBooking.php");
    exit;
}
?>
