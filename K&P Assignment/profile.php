<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/profile.css">
    <title>Profile</title>
</head>

<?php
include 'header.php';

$studentID = $_SESSION['studID'];

if ($studentID != '') {
    $query = "SELECT student_id, studName, studEmail, contact, birthday, gender, pic FROM student WHERE student_id = ?";
    $stmt = mysqli_prepare($connection, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $studentID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $row = mysqli_fetch_assoc($result);

        if ($row) {
            ?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Student Profile</title>
            </head>

            <body>

                <div class="user_info">
                    <div class="box">
                        <img src="pic/<?php echo htmlspecialchars($row['pic']); ?>" alt="Profile Picture" style="width: 270px; height: auto;">
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($row['student_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($row['studName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($row['studEmail']); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($row['contact']); ?></p>
                        <p><strong>Birthday:</strong> <?php echo htmlspecialchars($row['birthday']); ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($row['gender']); ?></p>
                        
                        <a href="profile_edit.php?student_id=<?php echo htmlspecialchars($row['student_id']); ?>" class="btn">Edit Profile</a>
                        <a href="view_ticket.php">View Tickets</a>
                    </div>
                </div>
            </body>

            </html>
            <?php
        } else {
            echo "No student found with that ID.";
        }

        mysqli_stmt_free_result($stmt);

        mysqli_stmt_close($stmt);
    } else {
        echo "SQL error: " . htmlspecialchars(mysqli_error($connection));
    }
} else {
    echo "Invalid student ID.";
}


include 'footer.php';
?>

</html>