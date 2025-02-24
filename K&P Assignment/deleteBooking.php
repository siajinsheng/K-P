<?php
if (isset($_GET['id']) && !empty($_GET['id'])) {
    include('connectDatabase.php');

    $booking_id = $_GET['id'];

    $query = mysqli_query($connection, "SELECT * FROM booking WHERE booking_id = '$booking_id'");

    if ($query) {
        $booking = mysqli_fetch_assoc($query);

        if ($booking) {
            echo "<script>
                    var confirmation = confirm('Are you sure you want to delete booking with ID: {$booking['booking_id']}?');
                    if (confirmation) {
                        window.location.href = 'deleteBookingConfirmed.php?id={$booking['booking_id']}';
                    } else {
                        window.location.href = 'viewBooking.php';
                    }
                  </script>";
        } else {
            header("Location: viewBooking.php");
            exit;
        }
    } else {
        echo "Query error: " . mysqli_error($connection);
        header("Location: viewBooking.php");
        exit;
    }
} else {
    header("Location: viewBooking.php");
    exit;
}
?>
