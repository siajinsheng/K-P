<?php
include('connectDatabase.php');

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventID = $_POST['eventID'];
    $eventName = $_POST['eventName'];
    $eventDate = $_POST['eventDate'];
    $eventTime = $_POST['eventTime'];
    $location = $_POST['eventLocation'];
    $description = $_POST['eventDesc'];
    $price = $_POST['eventPrice'];
    $eventStatus = $_POST['eventStatus'];
    $totalTicket = $_POST['totalTicket'];

    if (empty($eventID)) {
        $errors['eventID'] = "Event ID is required.";
    } elseif (!preg_match("/^E\w{1,11}$/", $eventID)) {
        $errors['eventID'] = "Event ID must start with 'E' and contain alphanumeric characters only.";
    } elseif (strlen($eventID) > 11) {
        $errors['eventID'] = "Event ID must be less than or equal to 11 characters.";
    } elseif (strlen($eventID) < 5) {
        $errors['eventID'] = "Event ID must be at least 5 characters long.";
    } else {
        $query = "SELECT * FROM events WHERE event_id=?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "s", $eventID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existingEvent = mysqli_fetch_assoc($result);
        if ($existingEvent) {
            $errors['eventID'] = "Event ID already exists. Please choose a different one.";
        }
    }

    if ((!empty($_FILES['image']['name']) && !isset($_POST['keepOldPicture'])) || (isset($_FILES['image']['name']) && isset($_POST['keepOldPicture']))) {
        if (!empty($_FILES['image']['name'])) {
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
        } elseif (isset($_POST['keepOldPicture'])) {
            $query = "SELECT * FROM events WHERE event_id=?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "s", $eventID);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $existingEvent = mysqli_fetch_assoc($result);
            if ($existingEvent) {
                $targetFilePath = $existingEvent['event_pic'];
            }
        } else {
            $errors['image'] = "Event Picture is required.";
        }
    }

    if (empty($eventName)) {
        $errors['eventName'] = "Event Name is required.";
    } elseif (strlen($eventName) > 30) {
        $errors['eventName'] = "Event Name cannot be more than 30 characters.";
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

    if ($eventStatus == "999") {
        $errors['eventStatus'] = "Please choose an Event status.";
    }

    if (empty($errors)) {
        $query = "INSERT INTO events (event_pic, event_name, event_date, event_time, location, description, price, event_status, event_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ssssssdss", $fileName, $eventName, $eventDate, $eventTime, $location, $description, $price, $eventStatus, $eventID);

        if (mysqli_stmt_execute($stmt)) {
            $ticketID = generateTicketID();
            $ticketQuery = "INSERT INTO ticket (maxTicket, ticketAvailable, event_id, ticket_id) VALUES (?, ?, ?, ?)";
            $stmt2 = mysqli_prepare($connection, $ticketQuery);
            mysqli_stmt_bind_param($stmt2, "ddss", $totalTicket, $totalTicket, $eventID, $ticketID);
            mysqli_stmt_execute($stmt2);

            echo "<script>
            alert('Event added successfully!');
            window.location.href = 'viewEvent.php';
            </script>";
            exit;
        } else {
            $errors['database'] = "Error updating event details: " . mysqli_error($connection);
        }
    }
}

function generateTicketID()
{
    $characters = '0123456789';
    $ticketID = 'T';
    for ($i = 0; $i < 4; $i++) {
        $ticketID .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $ticketID;
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
    <title>TARUMT Theatre Society | Add New Event</title>
</head>

<body>
    <?php include('header(admin).php'); ?>
    <div class="container">
        <h1>Add New Event</h1>
        <form name="addNewEventForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
            <div class="inputForm">
                <div class="form-group">
                    <label>Event ID:</label>
                    <input type="text" name="eventID" value="<?php echo isset($eventID) ? htmlspecialchars($eventID) : ''; ?>">
                    <?php if (isset($errors['eventID'])) echo "<span class='error'>$errors[eventID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Picture:</label>
                    <input type="file" name="image" accept=".jpeg, .jpg, .png">
                    <?php if (isset($errors['image'])) echo "<span class='error'>$errors[image]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Name:</label>
                    <input type="text" name="eventName" value="<?php echo isset($eventName) ? htmlspecialchars($eventName) : ''; ?>">
                    <?php if (isset($errors['eventName'])) echo "<span class='error'>$errors[eventName]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Date:</label>
                    <input type="date" name="eventDate" value="<?php echo isset($eventDate) ? htmlspecialchars($eventDate) : ''; ?>">
                    <?php if (isset($errors['eventDate'])) echo "<span class='error'>$errors[eventDate]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Time:</label>
                    <input type="time" name="eventTime" value="<?php echo isset($eventTime) ? htmlspecialchars($eventTime) : ''; ?>">
                    <?php if (isset($errors['eventTime'])) echo "<span class='error'>$errors[eventTime]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event Location:</label>
                    <input type="text" name="eventLocation" value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>">
                    <?php if (isset($errors['eventLocation'])) echo "<span class='error'>$errors[eventLocation]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea rows="6" name="eventDesc"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    <?php if (isset($errors['eventDesc'])) echo "<span class='error'>$errors[eventDesc]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Price (RM):</label>
                    <input type="text" name="eventPrice" placeholder="RM" value="<?php echo isset($price) ? htmlspecialchars($price) : ''; ?>">
                    <?php if (isset($errors['eventPrice'])) echo "<span class='error'>$errors[eventPrice]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Total Ticket:</label>
                    <input type="number" name="totalTicket" value="<?php echo isset($totalTicket) ? htmlspecialchars($totalTicket) : ''; ?>">
                    <?php if (isset($errors['totalTicket'])) echo "<span class='error'>$errors[totalTicket]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Event status:</label>
                    <select name="eventStatus">
                        <option value="999" <?php if (isset($eventStatus) && $eventStatus == '999') echo 'selected'; ?>>Choose an option</option>
                        <option value="1" <?php if (isset($eventStatus) && $eventStatus == '1') echo 'selected'; ?>>Event Available</option>
                        <option value="0" <?php if (isset($eventStatus) && $eventStatus == '0') echo 'selected'; ?>>Event Not Available</option>
                    </select>
                    <?php if (isset($errors['eventStatus'])) echo "<span class='error'>$errors[eventStatus]</span>"; ?>
                </div>
            </div>
            <div class="formButton">
                <button type="button" name="cancel" onclick="window.location.href='viewEvent.php'" class="box">Cancel</button>
                <button type="submit" name="submit" class="box">Add New Event</button>
            </div>
        </form>
    </div>

    <?php include('footer(admin).php'); ?>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</body>

</html>