<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="CSS/booking_ticket.css" />
    <title>TARUMT Theatre Society | Booking Ticket</title>
</head>

<body>
    <?php
    include 'header.php';
    $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';
    $student_id = $_SESSION['studID'] ?? '';

    if (!$event_id) {
        die('Event ID is missing!');
    }

    if (!$student_id) {
        echo '<script>alert("Please log in to book tickets.");</script>';
        echo '<script>window.location.href = "login.php";</script>';
        exit();
    }


    $query = "SELECT event_pic, event_name, event_date, event_time, location, price, event_status FROM events WHERE event_id = ?";
    if ($stmt = mysqli_prepare($connection, $query)) {
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

    function generateBookingID()
    {
        $characters = '0123456789';
        $bookingID = 'B';
        for ($i = 0; $i < 4; $i++) {
            $bookingID .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $bookingID;
    }

    function generatePaymentID()
    {
        $characters = '0123456789';
        $paymentID = 'P';
        for ($i = 0; $i < 4; $i++) {
            $paymentID .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $paymentID;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
        $quantity = intval($_POST['quantity']);
        $total_price = $quantity * $price;
        $booking_date = date('Y-m-d');
        $tax = '0.06';

        mysqli_begin_transaction($connection);

        $ticketQuery = "SELECT ticket_id, ticketAvailable FROM ticket WHERE event_id = ? FOR UPDATE";
        if ($ticketStmt = mysqli_prepare($connection, $ticketQuery)) {
            mysqli_stmt_bind_param($ticketStmt, "s", $event_id);
            mysqli_stmt_execute($ticketStmt);
            mysqli_stmt_bind_result($ticketStmt, $ticket_id, $ticketAvailable);
            if (mysqli_stmt_fetch($ticketStmt)) {
                mysqli_stmt_close($ticketStmt);

                if ($ticketAvailable >= $quantity) {
                    $bookingID = generateBookingID();
                    $insertQuery = "INSERT INTO booking (booking_id, student_id, ticket_id, date, quantity) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($connection, $insertQuery)) {
                        mysqli_stmt_bind_param($stmt, "ssssi", $bookingID, $student_id, $ticket_id, $booking_date, $quantity);
                        if (mysqli_stmt_execute($stmt)) {
                            $updateTicketQuery = "UPDATE ticket SET ticketAvailable = ticketAvailable - ? WHERE ticket_id = ?";
                            if ($updateStmt = mysqli_prepare($connection, $updateTicketQuery)) {
                                mysqli_stmt_bind_param($updateStmt, "is", $quantity, $ticket_id);
                                if (mysqli_stmt_execute($updateStmt)) {
                                    mysqli_commit($connection);
                                    $totalAmount = $total_price * (1 + $tax);
                                    $paymentID = generatePaymentID();
                                    $paymentStatus = "pending";

                                    $paymentQuery = "INSERT INTO payment (payment_id, booking_id, tax, total_amount, status, payment_date) VALUES (?, ?, ?, ?, ?, ?)";
                                    $paymentStmt = mysqli_prepare($connection, $paymentQuery);
                                    mysqli_stmt_bind_param($paymentStmt, "ssddss", $paymentID, $bookingID, $tax, $totalAmount, $paymentStatus, $bookingDate);
                                    mysqli_stmt_execute($paymentStmt);

                                    $_SESSION['booking_id'] = $bookingID;
                                    $_SESSION['payment_id'] = $paymentID;
                                    $_SESSION['event_id'] = $event_id;

                                    echo "<script>
                                    alert('Booking successful! You have booked $quantity tickets.');
                                    window.location.href = 'checkout.php';
                                    </script>";
                                } else {
                                    mysqli_rollback($connection);
                                    echo "<p>Error updating tickets availability.</p>";
                                }
                                mysqli_stmt_close($updateStmt);
                            } else {
                                mysqli_rollback($connection);
                                echo "<p>Error preparing update statement: " . mysqli_error($connection) . "</p>";
                            }
                        } else {
                            mysqli_rollback($connection);
                            echo "<p>Error booking tickets: " . mysqli_error($connection) . "</p>";
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        mysqli_rollback($connection);
                        echo "Error preparing booking statement: " . mysqli_error($connection);
                    }
                } else {
                    mysqli_rollback($connection);
                    echo "<p>Not enough tickets available.</p>";
                }
            } else {
                mysqli_rollback($connection);
                echo "<p>No ticket information found for this event.</p>";
            }
        } else {
            mysqli_rollback($connection);
            echo "<p>Error preparing ticket query: " . mysqli_error($connection) . "</p>";
        }
    }
    ?>

    <div class="events">
        <div class="box">
            <div class="">
                <img src="pic/<?php echo htmlspecialchars($event_pic); ?>" alt="Event Image"
                    style="width:300px; height:auto;">
                <h1><?php echo htmlspecialchars($event_name); ?></h1>
                <p>Date: <?php echo date("F d, Y", strtotime($event_date)); ?></p>
                <p>Time: <?php echo htmlspecialchars($event_time); ?></p>
                <p>Location: <?php echo htmlspecialchars($location); ?></p>
                <p>Price: $<?php echo number_format($price, 2); ?></p>
            </div>

            <div class="booking-form">
                <form action="?event_id=<?php echo htmlspecialchars($event_id); ?>" method="post">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                    <button type="submit" name="submit">Book Tickets</button>
                </form>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>

</body>

</html>