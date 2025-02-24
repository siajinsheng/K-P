<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tickets</title>
    <link rel="stylesheet" href="css/view_ticket.css">
</head>

<body>
    <?php
    include 'header.php';
    ?>
    <div class="container">
        <?php
        $student_id = $_SESSION['studID'] ?? '';
        if (!$student_id) {
            die('Please log in to book tickets.');
        }

        $bookingQuery = "SELECT booking_id, student_id, ticket_id, date, quantity FROM booking WHERE student_id = ?";
        if ($stmt = mysqli_prepare($connection, $bookingQuery)) {
            mysqli_stmt_bind_param($stmt, "s", $student_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 0) {
                die('No Booking found for the given ID.');
            }

            mysqli_stmt_bind_result($stmt, $booking_id, $student_id, $ticket_id, $date, $quantity);
            while (mysqli_stmt_fetch($stmt)) {
                if (!$booking_id) {
                    die('Booking ID is missing!');
                }

                $paymentQuery = "SELECT tax, total_amount, status FROM payment WHERE booking_id = ?";
                if ($paymentStmt = mysqli_prepare($connection, $paymentQuery)) {
                    mysqli_stmt_bind_param($paymentStmt, "s", $booking_id);
                    mysqli_stmt_execute($paymentStmt);
                    mysqli_stmt_store_result($paymentStmt);
                    if (mysqli_stmt_num_rows($paymentStmt) == 0) {
                        die('No payment details found for the given booking ID.');
                    }
                    mysqli_stmt_bind_result($paymentStmt, $tax, $total_amount, $status);
                    mysqli_stmt_fetch($paymentStmt);
                    mysqli_stmt_close($paymentStmt);
                } else {
                    die("Error preparing payment statement: " . mysqli_error($connection));
                }

                $ticketQuery = "SELECT ticket_id, event_id, maxTicket, ticketAvailable FROM ticket WHERE ticket_id = ?";
                if ($ticketStmt = mysqli_prepare($connection, $ticketQuery)) {
                    mysqli_stmt_bind_param($ticketStmt, "s", $ticket_id);
                    mysqli_stmt_execute($ticketStmt);
                    mysqli_stmt_store_result($ticketStmt);
                    if (mysqli_stmt_num_rows($ticketStmt) == 0) {
                        die('No event id found for the given ticket ID.');
                    }
                    mysqli_stmt_bind_result($ticketStmt, $ticket_id, $event_id, $maxTicket, $ticketAvailable);
                    mysqli_stmt_fetch($ticketStmt);
                    mysqli_stmt_close($ticketStmt);
                } else {
                    die("Error preparing ticket statement: " . mysqli_error($connection));
                }

                $eventQuery = "SELECT event_pic, event_name, event_date, event_time, location, price, event_status FROM events WHERE event_id = ?";
                if ($eventStmt = mysqli_prepare($connection, $eventQuery)) {
                    mysqli_stmt_bind_param($eventStmt, "s", $event_id);
                    mysqli_stmt_execute($eventStmt);
                    mysqli_stmt_store_result($eventStmt);
                    if (mysqli_stmt_num_rows($eventStmt) == 0) {
                        die('No event found for the given ID.');
                    }
                    mysqli_stmt_bind_result($eventStmt, $event_pic, $event_name, $event_date, $event_time, $location, $price, $event_status);
                    mysqli_stmt_fetch($eventStmt);
                    mysqli_stmt_close($eventStmt);
                    ?>
                    <div class="ticket_details">
                        <img src="pic/<?php echo htmlspecialchars($event_pic); ?>" alt="Event Image">
                        <p><?php echo htmlspecialchars($event_name); ?></p>
                        <p>Date: <?php echo date("F d, Y", strtotime($event_date)); ?></p>
                        <p>Time: <?php echo htmlspecialchars($event_time); ?></p>
                        <p>Location: <?php echo htmlspecialchars($location); ?></p>
                        <p>Price: $<?php echo number_format($price, 2); ?></p>
                        <p>Quantity: <?php echo $quantity; ?></p>
                        <p>Total Amount: $<?php echo number_format($total_amount, 2); ?></p>
                    </div>
                    <?php
                } else {
                    die("Error preparing event statement: " . mysqli_error($connection));
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            die("Error preparing booking statement: " . mysqli_error($connection));
        }
        ?>
    </div>
    <div class="btn">
        <a href="profile.php" class="btn">Back to
            Profile</a>
    </div>
    <?php
    include 'footer.php';
    ?>
</body>

</html>