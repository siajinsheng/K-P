<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <link rel="stylesheet" href="CSS/adminTable.css" />

    <title>TARUMT Theatre Society | View Student</title>
</head>

<body>
    <?php include('header(admin).php'); ?>

    <div class="container">
        <h1>View Student</h1>
        <input type="text" id="myInput" onkeyup="filterByFields()" placeholder="Search by Student ID, Name, Email, or Gender..">

        <a href="addNewStudent.php" class="add-event-button">Add New Student</a>

        <div class="ticketTable">
            <table border="1">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Student Picture</th>
                        <th>Student Email</th>
                        <th>Password</th>
                        <th>Contact Number</th>
                        <th>Birthday</th>
                        <th>Gender</th>
                        <th>Created Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    include('connectDatabase.php');
                    $query = mysqli_query($connection, "SELECT * FROM student");
                    if (!$query) {
                        die('Query error: ' . mysqli_error($connection));
                    }
                    while ($row = mysqli_fetch_array($query)) {
                    ?>
                        <tr>
                            <td><?php echo $row['student_id']; ?></td>
                            <td><?php echo $row['studName']; ?></td>
                            <td>
                                <?php if (!empty($row['pic'])) { ?>
                                    <img class="student-pic" src="/theatre/pic/<?php echo $row['pic']; ?>" alt="Student Picture">
                                <?php } else { ?>
                                    <h1>No Image Uploaded</h1>
                                <?php } ?>
                            </td>
                            <td><?php echo $row['studEmail']; ?></td>
                            <td class="password"><?php echo $row['studpassword']; ?></td>
                            <td><?php echo $row['contact']; ?></td>
                            <td><?php echo $row['birthday']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['created_time']; ?></td>
                            <td>
                                <a href="updateStudent.php?id=<?php echo $row['student_id']; ?>">Edit</a> |
                                <a href="deleteStudent.php?id=<?php echo $row['student_id']; ?>">Delete</a>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
        <?php include('footer(admin).php'); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.password').each(function() {
                var fullPassword = $(this).text();
                var passShow = fullPassword.slice(0, 4);
                var passNotShow = '*'.repeat(fullPassword.length - 4);
                $(this).text(passShow + passNotShow);
            });
        });

        function filterByFields() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("myInput");
            filter = input.value.toUpperCase();
            table = document.querySelector(".ticketTable table");
            tr = table.getElementsByTagName("tr");

            var foundCount = 0;

            for (i = 1; i < tr.length; i++) {
                var found = false;
                for (var j = 0; j < tr[i].cells.length; j++) {
                    td = tr[i].cells[j];
                    if (td) {
                        txtValue = td.textContent || td.innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                if (found) {
                    tr[i].style.display = "";
                    foundCount++;
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    </script>

    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>

</body>

</html>