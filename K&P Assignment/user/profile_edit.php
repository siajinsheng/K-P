<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/profile_edit.css" />
    <title>TARUMT Theatre Society | Profile</title>
</head>

<body>

    <?php
    include 'header.php';
    $studentID = $_SESSION['studID'] ?? '';
    $message = '';

    if ($studentID == '') {
        die('Invalid access. No student ID provided.');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $studName = mysqli_real_escape_string($connection, $_POST['studName']);
        $studEmail = mysqli_real_escape_string($connection, $_POST['studEmail']);
        $contact = mysqli_real_escape_string($connection, $_POST['contact']);
        $birthday = mysqli_real_escape_string($connection, $_POST['birthday']);
        $gender = mysqli_real_escape_string($connection, $_POST['gender']);

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
                $query = "SELECT * FROM student WHERE student_id=?";
                $stmt = mysqli_prepare($connection, $query);
                mysqli_stmt_bind_param($stmt, "s", $studentID);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $existingEvent = mysqli_fetch_assoc($result);
                if ($existingEvent) {
                    $targetFilePath = $existingEvent['event_pic'];
                }
            } else {
                $errors['image'] = "Profile picture is required.";
            }
        }

        if (!filter_var($studEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format.';
        } else if (!preg_match('/^[0-9]{10,11}$/', $contact)) {
            $message = 'Invalid contact number. Please use the format 1234567890.';
        } else {
            $query = "UPDATE student SET studName=?, studEmail=?, contact=?, birthday=?, gender=?, pic=? WHERE student_id=?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "sssssss", $studName, $studEmail, $contact, $birthday, $gender, $fileName, $studentID);

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Profile updated successfully.';
            } else {
                $message = 'Error updating profile: ' . mysqli_error($connection);
            }
            mysqli_stmt_close($stmt);
        }
    }

    $query = "SELECT studName, studEmail, contact, birthday, gender, pic FROM student WHERE student_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $studentID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    ?>
    <div class="edit_form">
        <div class="form">
            <h1>Edit Profile</h1>
            <?php if (!empty($message)): ?>
                <p><?php echo $message; ?></p>
            <?php endif; ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <label for="studName">Name:</label>
                <input type="text" id="studName" name="studName"
                    value="<?php echo htmlspecialchars($row['studName']); ?>" required><br>

                <label for="studEmail">Email:</label>
                <input type="email" id="studEmail" name="studEmail"
                    value="<?php echo htmlspecialchars($row['studEmail']); ?>" required><br>

                <label for="contact">Contact:</label>
                <input type="text" id="contact" name="contact"
                    value="<?php echo htmlspecialchars($row['contact']); ?>"><br>

                <label for="birthday">Birthday:</label>
                <input type="date" id="birthday" name="birthday"
                    value="<?php echo htmlspecialchars($row['birthday']); ?>"><br>

                <label for="gender">Gender:</label>
                <select id="gender" name="gender">
                    <option value="Male" <?php echo ($row['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($row['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($row['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select><br>

                <label>Profile Picture:</label>
                <input type="file" name="image" accept=".jpeg, .jpg, .png">
                <?php if (isset($errors['image']))
                    echo "<span class='error'>$errors[image]</span>"; ?><br>

                <button type="submit">Update Profile</button>
                <a href="profile.php?student_id=<?php echo htmlspecialchars($studentID); ?>" class="btn">Back to
                    Profile</a>

            </form>
        </div>
    </div>
    <?php
    include 'footer.php';
    ?>
</body>

</html>