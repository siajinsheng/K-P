<?php
include('connectDatabase.php');

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentID = $_POST['studentID'];
    $studName = $_POST['studName'];
    $studEmail = $_POST['studEmail'];
    $studPassword = $_POST['studPassword'];
    $constudpassword = $_POST['constudpassword'];
    $contact = $_POST['contact'];
    $birthday = $_POST['birthday'];
    $gender = $_POST['gender'];

    if (empty($studentID)) {
        $errors['studentID'] = "Student ID is required.";
    } elseif (!preg_match("/^S\w{4,11}$/", $studentID)) {
        $errors['studentID'] = "Student ID must start with 'S' and be between 5 to 12 characters.";
    } else {
        $query = "SELECT * FROM student WHERE student_id=?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "s", $studentID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existingStudent = mysqli_fetch_assoc($result);
        if ($existingStudent) {
            $errors['studentID'] = "Student ID already exists. Please choose a different one.";
        }
    }

    if (empty($_FILES['image']['name'])) {
        $errors['image'] = "Student Picture is required.";
    } else {
        $maxFileSize = 5 * 1024 * 1024;
        if ($_FILES['image']['size'] > $maxFileSize) {
            $errors['image'] = "Maximum file size for the student picture is 5MB.";
        }

        $allowedExtensions = array('jpeg', 'jpg', 'png');
        $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
            $errors['image'] = "Only JPEG, JPG, and PNG files are allowed.";
        }
    }

    if (empty($studName)) {
        $errors['studName'] = "Student Name is required.";
    } elseif (strlen($studName) > 30) {
        $errors['studName'] = "Student Name cannot be more than 30 characters.";
    }

    if (empty($studEmail)) {
        $errors['studEmail'] = "Student Email is required.";
    } elseif (!filter_var($studEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['studEmail'] = "Invalid email format.";
    } elseif (strlen($studEmail) > 30) {
        $errors['studEmail'] = "Student Email cannot be more than 30 characters.";
    }

    if (empty($studPassword)) {
        $errors['studPassword'] = "Password is required.";
    } elseif (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z\d]).{8,}$/', $studPassword)) {
        $errors['studPassword'] = "Password must contain at least one uppercase letter, one lowercase letter, one symbol, one number, and at least 8 characters.";
    }

    if (empty($constudpassword)) {
        $errors['constudpassword'] = "Please confirm your password.";
    } elseif ($studPassword !== $constudpassword) {
        $errors['constudpassword'] = "Passwords do not match.";
    }

    if (empty($contact)) {
        $errors['contact'] = "Contact Number is required.";
    } elseif (!preg_match("/^\d{10,11}$/", $contact)) {
        $errors['contact'] = "Contact Number must be in the format 0121234567.";
    }

    if (empty($birthday)) {
        $errors['birthday'] = "Birthday is required.";
    } elseif (!strtotime($birthday)) {
        $errors['birthday'] = "Invalid date format.";
    } else {
        $minDate = strtotime('1830-01-01');
        $maxDate = strtotime('2030-12-31');
        $selectedDate = strtotime($birthday);
        if ($selectedDate < $minDate || $selectedDate > $maxDate) {
            $errors['birthday'] = "Date must be between 1830/01/01 and 2030/12/31.";
        }
    }

    if (empty($gender)) {
        $errors['gender'] = "Gender is required.";
    }

    if (empty($errors)) {
        $targetDirectory = "pic/";
        $file = $_FILES['image']['name'];
        $targetFilePath = $targetDirectory . $file;

        if (!empty($file)) {
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $errors['image'] = "Failed to upload file.";
            }
        } else {
            $targetFilePath = "";
        }

        if (empty($errors)) {
            $query = "INSERT INTO student (student_id, studName, studEmail, studPassword, constudpassword, contact, birthday, gender, pic, created_time) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "sssssssss", $studentID, $studName, $studEmail, $studPassword, $constudpassword, $contact, $birthday, $gender, $file);
            if (mysqli_stmt_execute($stmt)) {

                echo "<script>
            alert('Student added successfully!');
            window.location.href = 'viewStudent.php';
            </script>";
                exit;
            } else {
                $errors['database'] = "Failed to insert data into database.";
            }
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
    <title>TARUMT Theatre Society | Add New Student</title>

</head>

<body>
    <?php include('header(admin).php'); ?>
    <div class="container">
        <h1>Add New Student</h1>
        <form name="addNewEventForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
            <div class="inputForm">
                <div class="form-group">
                    <label>Student ID:</label>
                    <input type="text" name="studentID" value="<?php echo isset($_POST['studentID']) ? htmlspecialchars($_POST['studentID']) : ''; ?>">
                    <?php if (isset($errors['studentID'])) echo "<span class='error'>$errors[studentID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Student Picture:</label>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $maxFileSize; ?>">
                    <input type="file" name="image" accept=".jpeg, .jpg, .png" onchange="validateFileSize(this)">
                    <?php if (isset($errors['image'])) echo "<span class='error'>$errors[image]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Student Name:</label>
                    <input type="text" name="studName" value="<?php echo isset($_POST['studName']) ? htmlspecialchars($_POST['studName']) : ''; ?>">
                    <?php if (isset($errors['studName'])) echo "<span class='error'>$errors[studName]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Student Email:</label>
                    <input type="email" name="studEmail" value="<?php echo isset($_POST['studEmail']) ? htmlspecialchars($_POST['studEmail']) : ''; ?>">
                    <?php if (isset($errors['studEmail'])) echo "<span class='error'>$errors[studEmail]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="studPassword" value="<?php echo isset($_POST['studPassword']) ? htmlspecialchars($_POST['studPassword']) : ''; ?>">
                    <?php if (isset($errors['studPassword'])) echo "<span class='error'>$errors[studPassword]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Confirm Password:</label>
                    <input type="password" name="constudpassword" value="<?php echo isset($_POST['constudpassword']) ? htmlspecialchars($_POST['constudpassword']) : ''; ?>">
                    <?php if (isset($errors['constudpassword'])) echo "<span class='error'>$errors[constudpassword]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Contact Number:</label>
                    <input type="text" name="contact" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                    <?php if (isset($errors['contact'])) echo "<span class='error'>$errors[contact]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Birthday:</label>
                    <input type="date" name="birthday" value="<?php echo isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : ''; ?>">
                    <?php if (isset($errors['birthday'])) echo "<span class='error'>$errors[birthday]</span>"; ?>
                </div>
                <div class="form-group">
                    <label>Gender:</label>
                    <select name="gender" id="gender">
                        <option value="">Choose an option</option>
                        <option value="Male" <?php if (isset($_POST['gender']) && $_POST['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if (isset($_POST['gender']) && $_POST['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                    </select>
                    <?php if (isset($errors['gender'])) echo "<span class='error'>$errors[gender]</span>"; ?>
                </div>
            </div>
            <div class="formButton">
                <button type="button" name="cancel" onclick="window.location.href='viewStudent.php'" class="box">Cancel</button>
                <button type="submit" name="submit" class="box">Add New Student</button>
            </div>
        </form>
    </div>

    <?php include('footer(admin).php'); ?>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</body>

</html>