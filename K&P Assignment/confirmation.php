

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
<?php
  include 'header.php';

if (!isset($_SESSION['booking_confirmed']) || !$_SESSION['booking_confirmed']) {
    header('Location: bookingTicket.php');
    exit();
}

$event_id = $_SESSION['event_id'];
$stmt = $connection->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("s", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Event not found.');
}

$event = $result->fetch_assoc();
$quantity = $_SESSION['quantity'];
$total_price = $_SESSION['total_price'];

unset($_SESSION['booking_confirmed']);
unset($_SESSION['event_id']);
unset($_SESSION['quantity']);
unset($_SESSION['total_price']);
?>

    <div class="confirmation-container">
        <h1>Booking Confirmation</h1>
        <p>Thank you for your purchase, <?php echo htmlspecialchars($event['event_name']); ?>!</p>
        <div class="booking-details">
            <h2>Event Details</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($event['event_name']); ?></p>
            <p><strong>Date:</strong> <?php echo date("F d, Y", strtotime($event['event_date'])); ?></p>
            <p><strong>Time:</strong> <?php echo htmlspecialchars($event['event_time']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
            <p><strong>Quantity:</strong> <?php echo $quantity; ?></p>
            <p><strong>Total Price Paid:</strong> $<?php echo number_format($total_price, 2); ?></p>
        </div>
        <a href="print.php?event_id=<?php echo urlencode($event_id); ?>" class="print-button">Print Ticket</a>
        <a href="events.php" class="more-events-button">View More Events</a>
    </div>
</body>

</html>