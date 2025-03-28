<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Page</title>
    <link rel="stylesheet" type="text/css" href="css/checkout.css">
</head>

<body>
    <?php
    include 'header.php';

    $payment_id = $_SESSION['payment_id'] ?? '';
    $booking_id = $_SESSION['booking_id'] ?? '';
    $student_id = $_SESSION['studID'] ?? '';
    $event_id = $_SESSION['event_id'] ?? '';

    if (!$student_id) {
        die('Please log in to book tickets.');
    }

    $eventQuery = "SELECT event_pic, event_name, event_date, event_time, location, price, event_status FROM events WHERE event_id = ?";
    if ($stmt = mysqli_prepare($connection, $eventQuery)) {
        mysqli_stmt_bind_param($stmt, "s", $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $event_pic, $event_name, $event_date, $event_time, $location, $price, $event_status);

        if (!mysqli_stmt_fetch($stmt)) {
            die('No event found for the given ID.');
        }
        mysqli_stmt_close($stmt);
    } else {
        die("Error preparing statement: " . mysqli_error($connection));
    }
    if (!$booking_id) {
        die('Booking ID is missing!');
    }

    $bookingQuery = "SELECT booking_id, student_id, ticket_id, date, quantity FROM booking WHERE booking_id = ?";
    if ($stmt = mysqli_prepare($connection, $bookingQuery)) {
        mysqli_stmt_bind_param($stmt, "s", $booking_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $booking_id, $student_id, $ticket_id, $date, $quantity);

        if (!mysqli_stmt_fetch($stmt)) {
            die('No Booking found for the given ID.');
        }
        mysqli_stmt_close($stmt);
    } else {
        die("Error preparing statement: " . mysqli_error($connection));
    }
    if (!$booking_id) {
        die('Booking ID is missing!');
    }

    $paymentQuery = "SELECT tax, total_amount, status FROM payment WHERE booking_id = ?";
    if ($stmt = mysqli_prepare($connection, $paymentQuery)) {
        mysqli_stmt_bind_param($stmt, "s", $booking_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $tax, $total_amount, $status);

        if (!mysqli_stmt_fetch($stmt)) {
            die('No payment details found for the given payment ID.');
        }
        mysqli_stmt_close($stmt);
    } else {
        die("Error preparing statement: " . mysqli_error($connection));
    }

    $tax1 = $price * $quantity * $tax ;
    ?>
    <div class="container">
        <div class="event-details">
            <h1><?php echo htmlspecialchars($event_name); ?></h1>
            <img src="pic/<?php echo htmlspecialchars($event_pic); ?>" alt="Event Image"
                style="width:200px; height:auto;">
            <p>Date: <?php echo date("F d, Y", strtotime($event_date)); ?></p>
            <p>Time: <?php echo htmlspecialchars($event_time); ?></p>
            <p>Location: <?php echo htmlspecialchars($location); ?></p>

        </div>
        <div class="summary">
            <h1>Summary</h1>
            <p>Price: $<?php echo number_format($price, 2); ?></p>
            <p>Quantity:<?php echo $quantity; ?></p>
            <p>Tax: $<?php echo number_format($tax1, 2); ?></p>
            <h2>Total Amount: $<?php echo number_format($total_amount, 2); ?></h2>
            <a href="payment.php">Proceed to Payment</a>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>

</html>