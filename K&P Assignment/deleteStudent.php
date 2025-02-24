<?php
if (isset($_GET['id']) && !empty($_GET['id'])) {
    include('connectDatabase.php');

    $student_id = $_GET['id'];

    $query = mysqli_query($connection, "SELECT * FROM student WHERE student_id = '$student_id'");

    if ($query) {
        $student = mysqli_fetch_assoc($query);

        if ($student) {
            echo "<script>
                    var confirmation = confirm('Are you sure you want to delete student with ID: {$student['student_id']} and Name: {$student['studName']}?');
                    if (confirmation) {
                        window.location.href = 'deleteStudentConfirmed.php?id={$student['student_id']}'; // Redirect to deleteStudentConfirmed.php if user confirms
                    } else {
                        window.location.href = 'viewStudent.php'; // Redirect back to viewStudent.php if user cancels
                    }
                  </script>";
        } else {
            header("Location: viewStudent.php");
            exit;
        }
    } else {
        echo "Query error: " . mysqli_error($connection);
        header("Location: viewStudent.php");
        exit;
    }
} else {
    header("Location: viewStudent.php");
    exit;
}
?>
