<?php
include('connectDatabase.php');

$errors = [];

$eventQuery = "SELECT event_id, event_name, event_date, event_time, price FROM events WHERE event_status = 1";
$eventResult = mysqli_query($connection, $eventQuery);
$events = [];
$ticketPrice = [];
$ticketAvailable = [];
while ($row = mysqli_fetch_assoc($eventResult)) {
    $events[$row['event_id']] = $row['event_name'] . " - " . $row['event_date'] . " " . $row['event_time'];
    $ticketPrice[$row['event_id']] = $row['price'];
    $ticketAvailable[$row['event_id']] = getAvailableTickets($connection, $row['event_id']);
}

$studentQuery = "SELECT student_id, studName FROM student";
$studentResult = mysqli_query($connection, $studentQuery);
$students = [];
while ($row = mysqli_fetch_assoc($studentResult)) {
    $students[$row['student_id']] = $row['studName'] . " - " . $row['student_id'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventID = $_POST['eventID'];
    $studentID = $_POST['studentID'];
    $bookingDate = date("Y-m-d");
    $quantity = isset($_POST['quantity']) ? $_POST['quantity'] : '';
    $tax = '0.06';

    if (empty($eventID)) {
        $errors['eventID'] = "Event ID is required.";
    }

    if (empty($studentID)) {
        $errors['studentID'] = "Student ID is required.";
    }

    if (empty($quantity) || $quantity <= 0) {
        $errors['quantity'] = "Quantity must be a positive number.";
    }

    if (empty($tax) || $tax < 0 || $tax > 1) {
        $errors['tax'] = "Tax must be a decimal value between 0 and 1.";
    }

    if ($quantity > $ticketAvailable[$eventID]) {
        $errors['quantity'] = "Quantity exceeds available tickets.";
    }

    if (empty($errors)) {
        $bookingID = generateBookingID();

        $ticketQuery = "SELECT ticket_id FROM ticket WHERE event_id = ?";
        $ticketStmt = mysqli_prepare($connection, $ticketQuery);
        mysqli_stmt_bind_param($ticketStmt, "s", $eventID);
        mysqli_stmt_execute($ticketStmt);
        mysqli_stmt_bind_result($ticketStmt, $ticketID);
        mysqli_stmt_fetch($ticketStmt);
        mysqli_stmt_close($ticketStmt);

        $query = "INSERT INTO booking (booking_id, student_id, ticket_id, date, quantity) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ssssi", $bookingID, $studentID, $ticketID, $bookingDate, $quantity);

        if (mysqli_stmt_execute($stmt)) {
            $updateQuery = "UPDATE ticket SET ticketAvailable = ticketAvailable - ? WHERE event_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "is", $quantity, $eventID);
            mysqli_stmt_execute($updateStmt);

            $ticketAvailable[$eventID] -= $quantity;
            if ($ticketAvailable[$eventID] == 0) {
                $updateEventStateQuery = "UPDATE events SET event_state = 0 WHERE event_id = ?";
                $updateEventStateStmt = mysqli_prepare($connection, $updateEventStateQuery);
                mysqli_stmt_bind_param($updateEventStateStmt, "s", $eventID);
                mysqli_stmt_execute($updateEventStateStmt);
            }

            $totalAmount = $quantity * $ticketPrice[$eventID] * (1 + $tax);
            $paymentID = generatePaymentID();
            $paymentStatus = "1";

            $paymentQuery = "INSERT INTO payment (payment_id, booking_id, tax, total_amount, status, payment_date) VALUES (?, ?, ?, ?, ?, ?)";
            $paymentStmt = mysqli_prepare($connection, $paymentQuery);
            mysqli_stmt_bind_param($paymentStmt, "ssddss", $paymentID, $bookingID, $tax, $totalAmount, $paymentStatus, $bookingDate);
            mysqli_stmt_execute($paymentStmt);

            echo "<script>
            alert('Booking added successfully! Total price is RM$totalAmount');
            window.location.href = 'viewBooking.php';
            </script>";
            exit;
        } else {
            $errors['database'] = "Error adding booking: " . mysqli_error($connection);
        }
    }
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

function getAvailableTickets($connection, $eventID)
{
    $query = "SELECT ticketAvailable FROM ticket WHERE event_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $eventID);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $availableTickets);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $availableTickets;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Add New Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <link rel="stylesheet" href="CSS/adminForm.css" />
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include('header(admin).php'); ?>
    <div class="container">
        <h1>Add New Booking</h1>
        <form name="addNewBookingForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
            <div class="inputForm">
                <div class="form-group">
                    <label for="eventID">Event:</label>
                    <select id="eventID" name="eventID">
                        <option value="">Select Event</option>
                        <?php foreach ($events as $event_id => $event_details) { ?>
                            <option value="<?php echo $event_id; ?>"><?php echo $event_details . " (Available Tickets: " . $ticketAvailable[$event_id] . ")"; ?></option>
                        <?php } ?>
                    </select>
                    <?php if (isset($errors['eventID'])) echo "<span class='error'>$errors[eventID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label for="studentID">Student:</label>
                    <select id="studentID" name="studentID">
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student_id => $student_name) { ?>
                            <option value="<?php echo $student_id; ?>"><?php echo $student_name; ?></option>
                        <?php } ?>
                    </select>
                    <?php if (isset($errors['studentID'])) echo "<span class='error'>$errors[studentID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" value="<?php echo isset($quantity) ? htmlspecialchars($quantity) : ''; ?>">
                    <?php if (isset($errors['quantity'])) echo "<span class='error'>$errors[quantity]</span>"; ?>
                </div>
            </div>
            <div class="formButton">
                <button type="button" name="cancel" onclick="window.location.href='viewBooking.php'" class="box">Cancel</button>
                <button type="submit" name="submit" class="box">Add New Booking</button>
            </div>
        </form>
    </div>
    <?php include('footer(admin).php'); ?>
</body>

</html>
