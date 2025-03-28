<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/event_details.css">
    <title>events</title>
</head>

<body>

    <?php
    include 'header.php';

    $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';
    if (!$event_id) {
        die('Event ID is missing!');
    }
    $event_id = mysqli_real_escape_string($connection, $event_id);

    $query = "SELECT * FROM events WHERE event_id = '$event_id'";
    $result = mysqli_query($connection, $query);

    if (!$result || mysqli_num_rows($result) == 0) {
        die('No event found for the given ID.');
    }
    $event = mysqli_fetch_assoc($result);
    ?>
    <div class="events">
        <div class="details">
            <div>
                <img src="pic/<?php echo htmlspecialchars($event['event_pic']); ?>" alt="Event Image">
            </div>
            <div>
                <h1><?php echo htmlspecialchars($event['event_name']); ?></h1>
                <p><strong>Date:</strong> <?php echo date("F d, Y", strtotime($event['event_date'])); ?></p>
                <p><strong>Time:</strong> <?php echo htmlspecialchars($event['event_time']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                <p><strong>Price:</strong> $<?php echo number_format($event['price'], 2); ?></p>
                <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                <a href="bookingTicket.php?event_id=<?php echo urlencode($event_id); ?>" class="btn">Book Tickets</a>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>

</html>