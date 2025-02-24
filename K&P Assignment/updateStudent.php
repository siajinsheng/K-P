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
    }

    if (empty($_FILES['image']['name'])) {
        if (!isset($_POST['keepOldPicture']) || $_POST['keepOldPicture'] != '1') {
            $errors['image'] = "Student Picture is required.";
        }
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

    if (!empty($studPassword)) {
        if (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z\d]).{8,}$/', $studPassword)) {
            $errors['studPassword'] = "Password must contain at least one uppercase letter, one lowercase letter, one symbol, one number, and at least 8 characters.";
        }
    } else {
        $studPassword = $_POST['studPassword'];
    }

    if ($studPassword !== $constudpassword) {
        $errors['constudpassword'] = "Passwords do not match.";
    } else {
        $constudpassword = $_POST['constudpassword'];
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
        if (isset($_POST['keepOldPicture']) && $_POST['keepOldPicture'] == '1' && !empty($_POST['oldPicturePath'])) {
            $file = $_POST['oldPicturePath'];
        } else {
            $targetDirectory = "pic/";
            $file = $_FILES['image']['name'];
            $targetFilePath = $targetDirectory . $file;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $errors['image'] = "Failed to upload file.";
            }
        }

        if (empty($errors)) {
            $query = "UPDATE student SET studName=?, studEmail=?, studpassword=?, constudpassword=?, contact=?, birthday=?, gender=?, pic=? WHERE student_id=?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "sssssssss", $studName, $studEmail, $studPassword, $constudpassword, $contact, $birthday, $gender, $file, $studentID);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
            alert('Student information updated successfully!');
            window.location.href = 'viewStudent.php';
            </script>";
                exit;
            } else {
                $errors['database'] = "Failed to update student information: " . mysqli_error($connection);
            }
        }
    }
}

if (isset($_GET['id'])) {
    $studentID = $_GET['id'];
    $query = "SELECT * FROM student WHERE student_id=?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $studentID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
} else {
    header("Location: viewStudent.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Update Student</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link rel="stylesheet" href="CSS/adminForm.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include('header(admin).php'); ?>
    <div class="container">
        <h1>Update Student Information</h1>
        <form name="updateStudentForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $student['student_id']; ?>" enctype="multipart/form-data">
            <div class="inputForm">
                <div class="form-group">
                    <label>Student ID:</label>
                    <input type="text" name="studentID" value="<?php echo $student['student_id']; ?>" readonly>
                    <?php if (isset($errors['studentID'])) echo "<span class='error'>$errors[studentID]</span>"; ?>
                </div>
                <div class="form-group">
                    <label for="studName">Student Name:</label><br>
                    <input type="text" id="studName" name="studName" value="<?php echo $student['studName']; ?>"><br>
                    <?php if (isset($errors['studName'])) echo "<span class='error'>$errors[studName]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="image">Student Picture:</label><br>
                    <?php if (!empty($student['pic'])) { ?>
                        <img class="event" src="/theatre/<?php echo $student['pic']; ?>">
                    <?php } else { ?>
                        <h1>No Image Uploaded</h1>
                    <?php } ?>
                    <input type="file" id="image" name="image" accept=".jpeg, .jpg, .png"><br>
                    <label>
                        <input type="checkbox" name="keepOldPicture" value="1"> Keep old picture
                    </label>
                    <input type="hidden" name="oldPicturePath" value="<?php echo $student['pic']; ?>">
                    <?php if (isset($errors['image'])) echo "<span class='error'>$errors[image]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="studPassword">New Password:</label><br>
                    <input type="password" id="studPassword" name="studPassword" value="<?php echo $student['studpassword']; ?>"><br>
                    <?php if (isset($errors['studPassword'])) echo "<span class='error'>$errors[studPassword]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="constudpassword">Confirm New Password:</label><br>
                    <input type="password" id="constudpassword" name="constudpassword" value="<?php echo $student['constudpassword']; ?>"><br>
                    <?php if (isset($errors['constudpassword'])) echo "<span class='error'>$errors[constudpassword]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="studEmail">Student Email:</label><br>
                    <input type="email" id="studEmail" name="studEmail" value="<?php echo $student['studEmail']; ?>"><br>
                    <?php if (isset($errors['studEmail'])) echo "<span class='error'>$errors[studEmail]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number:</label><br>
                    <input type="text" id="contact" name="contact" value="<?php echo $student['contact']; ?>"><br>
                    <?php if (isset($errors['contact'])) echo "<span class='error'>$errors[contact]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="birthday">Birthday:</label><br>
                    <input type="date" id="birthday" name="birthday" value="<?php echo $student['birthday']; ?>"><br>
                    <?php if (isset($errors['birthday'])) echo "<span class='error'>$errors[birthday]</span>"; ?><br>
                </div>
                <div class="form-group">
                    <label for="gender">Gender:</label><br>
                    <select name="gender" id="gender">
                        <option value="Male" <?php if ($student['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if ($student['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                    </select><br>
                    <?php if (isset($errors['gender'])) echo "<span class='error'>$errors[gender]</span>"; ?><br>
                </div>
            </div>
            <div class="formButton">
                <button type="button" name="cancel" onclick="window.location.href='viewStudent.php'" class="box">Cancel</button>
                <button type="submit" name="submit" class="box">Update Student</button>
            </div>
        </form>
    </div>
    <?php include('footer(admin).php'); ?>
</body>

</html>