<?php
if (isset($_GET['id']) && !empty($_GET['id'])) {
    include('connectDatabase.php');

    $student_id = $_GET['id'];

    $delete_query = mysqli_query($connection, "DELETE FROM student WHERE student_id = '$student_id'");

    if ($delete_query) {
        echo "<script>
                alert('Student successfully deleted!');
                window.location.href = 'viewStudent.php'; // Redirect back to viewStudent.php
              </script>";
        exit;
    } else {
        echo "Deletion error: " . mysqli_error($connection);
        header("Location: viewStudent.php");
        exit;
    }
} else {
    header("Location: viewStudent.php");
    exit;
}
?>
