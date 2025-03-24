<?php
include('connectDatabase.php');

$errors = [];

if (isset($_GET['id'])) {
    $eventId = $_GET['id'];

    $query = "SELECT * FROM events WHERE event_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $eventId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);

    $query2 = "SELECT * FROM ticket WHERE event_id = ?";
    $stmt2 = mysqli_prepare($connection, $query2);
    mysqli_stmt_bind_param($stmt2, "s", $eventId);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $ticket = mysqli_fetch_assoc($result2);

    if (!$event || !$ticket) {
        echo "Event not found!";
        exit;
    }
} else {
    echo "Event ID not provided!";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventName = $_POST['eventName'];
    $eventDate = $_POST['eventDate'];
    $eventTime = $_POST['eventTime'];
    $location = $_POST['eventLocation'];
    $description = $_POST['eventDesc'];
    $price = $_POST['eventPrice'];
    $eventStatus = $_POST['eventStatus'];
    $totalTicket = $_POST['totalTicket'];
    $ticketAvailable = $_POST['ticketAvailable'];

    if (empty($eventName)) {
        $errors['eventName'] = "Event Name is required.";
    } elseif (strlen($eventName) > 30) {
        $errors['eventName'] = "Event Name cannot be more than 30 characters.";
    }

    if (!isset($_FILES['image']['name']) && !isset($_POST['keepOldPicture'])) {
        $errors['image'] = "Event Picture is required.";
    } elseif (!empty($_FILES['image']['name'])) {
        $maxFileSize = 5 * 1024 * 1024;
        if ($_FILES['image']['size'] > $maxFileSize) {
            $errors['image'] = "Maximum file size for the event picture is 5MB.";
        }

        $allowedExtensions = array('jpeg', 'jpg', 'png');
        $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
            $errors['image'] = "Only JPEG, JPG, and PNG files are allowed.";
        }

        if (empty($errors['image'])) {
            $targetDirectory = "pic/";
            $fileName = uniqid() . '.' . $fileExtension;
            $targetFilePath = $targetDirectory . $fileName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $errors['image'] = "Failed to move uploaded file.";
            }
        }
    }

    if (empty($eventDate)) {
        $errors['eventDate'] = "Event Date is required.";
    } elseif (!strtotime($eventDate)) {
        $errors['eventDate'] = "Invalid date format.";
    } else {
        $minDate = strtotime('2024-01-01');
        $maxDate = strtotime('2030-12-31');
        $selectedDate = strtotime($eventDate);
        if ($selectedDate < $minDate || $selectedDate > $maxDate) {
            $errors['eventDate'] = "Event Date must be between 2024/01/01 and 2030/12/31.";
        }
    }

    if (empty($eventTime)) {
        $errors['eventTime'] = "Event Time is required.";
    }

    if (empty($location)) {
        $errors['eventLocation'] = "Event Location is required.";
    } elseif (strlen($location) > 30) {
        $errors['eventLocation'] = "Event Location cannot be more than 30 characters.";
    }

    if (empty($description)) {
        $errors['eventDesc'] = "Event Description is required.";
    } elseif (strlen($description) > 999) {
        $errors['eventDesc'] = "Description cannot be more than 999 characters.";
    }

    if (empty($price)) {
        $errors['eventPrice'] = "Event Price is required.";
    } elseif (!preg_match("/^\d{1,10}(\.\d{0,2})?$/", $price)) {
        $errors['eventPrice'] = "Price must be a number with up to 10 digits and 2 decimal places.";
    }

    if (empty($totalTicket)) {
        $errors['totalTicket'] = "Total Ticket is required.";
    } elseif ($totalTicket < 0) {
        $errors['totalTicket'] = "Total Ticket must be a non-negative number.";
    }

    if (empty($ticketAvailable)) {
        $errors['ticketAvailable'] = "Ticket Available is required.";
    } elseif ($ticketAvailable < 0) {
        $errors['ticketAvailable'] = "Ticket Available must be a non-negative number.";
    }

    if ($eventStatus == "999") {
        $errors['eventStatus'] = "Please choose an Event Status.";
    }

    if (empty($errors)) {
        $query = "UPDATE events SET event_pic=?, event_name=?, event_date=?, event_time=?, location=?, description=?, price=?, event_status=? WHERE event_id=?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ssssssdss", $fileName, $eventName, $eventDate, $eventTime, $location, $description, $price, $eventStatus, $eventId);

        if (mysqli_stmt_execute($stmt)) {
            $ticketQuery = "UPDATE ticket SET maxTicket=?, ticketAvailable=? WHERE event_id=?";
            $stmt2 = mysqli_prepare($connection, $ticketQuery);
            mysqli_stmt_bind_param($stmt2, "dds", $totalTicket, $ticketAvailable, $eventId);
            mysqli_stmt_execute($stmt2);

            echo "<script>
            alert('Event update successfully!');
            window.location.href = 'viewEvent.php';
            </script>";
            exit;
        } else {
            $errors['database'] = "Error updating event details: " . mysqli_error($connection);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link rel="stylesheet" href="CSS/adminForm.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <title>TARUMT Theatre Society | Update Event</title>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include('header(admin).php'); ?>
    <div class="container">
        <h1>Update Event</h1>
        <form name="updateEventForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $eventId; ?>" enctype="multipart/form-data">
            <div class="inputForm">
                <div class="form-group">
                    <label>Event ID:</label>
                    <input type="text" name="eventID" value="<?php echo $event['event_id']; ?>" readonly>
                    <?php if (isset($errors['eventID'])) echo "<span class='error'>$errors[eventID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Picture:</label>
                    <?php if (!empty($event['event_pic'])) { ?>
                        <img class="event" src="/theatre/<?php echo $event['event_pic']; ?>">
                    <?php } else { ?>
                        <h1>No Image Uploaded</h1>
                    <?php } ?>
                    <input type="file" name="image" accept=".jpeg, .jpg, .png">
                    <label>
                        <input type="checkbox" name="keepOldPicture" value="1"> Keep old picture
                    </label>
                    <?php if (isset($errors['image'])) echo "<span class='error'>$errors[image]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Name:</label>
                    <input type="text" name="eventName" value="<?php echo $event['event_name']; ?>">
                    <?php if (isset($errors['eventName'])) echo "<span class='error'>$errors[eventName]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Date:</label>
                    <input type="date" name="eventDate" value="<?php echo $event['event_date']; ?>">
                    <?php if (isset($errors['eventDate'])) echo "<span class='error'>$errors[eventDate]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Time:</label>
                    <input type="time" name="eventTime" value="<?php echo $event['event_time']; ?>">
                    <?php if (isset($errors['eventTime'])) echo "<span class='error'>$errors[eventTime]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Location:</label>
                    <input type="text" name="eventLocation" value="<?php echo $event['location']; ?>">
                    <?php if (isset($errors['eventLocation'])) echo "<span class='error'>$errors[eventLocation]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea rows="6" name="eventDesc"><?php echo $event['description']; ?></textarea>
                    <?php if (isset($errors['eventDesc'])) echo "<span class='error'>$errors[eventDesc]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Price (RM):</label>
                    <input type="text" name="eventPrice" placeholder="RM" value="<?php echo $event['price']; ?>">
                    <?php if (isset($errors['eventPrice'])) echo "<span class='error'>$errors[eventPrice]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Total Ticket:</label>
                    <input type="number" name="totalTicket" value="<?php echo $ticket['maxTicket']; ?>">
                    <?php if (isset($errors['totalTicket'])) echo "<span class='error'>$errors[totalTicket]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Ticket Available:</label>
                    <input type="number" name="ticketAvailable" value="<?php echo $ticket['ticketAvailable']; ?>">
                    <?php if (isset($errors['ticketAvailable'])) echo "<span class='error'>$errors[ticketAvailable]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Status:</label>
                    <select name="eventStatus" id="eventStatus">
                        <option value="1" <?php if ($event['event_status'] == '1') echo 'selected'; ?>>Event Availables</option>
                        <option value="0" <?php if ($event['event_status'] == '0') echo 'selected'; ?>>Event Not Available</option>
                    </select>
                    <?php if (isset($errors['eventStatus'])) echo "<span class='error'>$errors[eventStatus]</span>"; ?>
                </div>
            </div>
            <div class="formButton">
                <button type="button" name="cancel" onclick="window.location.href='viewEvent.php'" class="box">Cancel</button>
                <button type="submit" name="submit" class="box">Update Event</button>
            </div>
        </form>
    </div>
    <?php include('footer(admin).php'); ?>
</body>

</html>