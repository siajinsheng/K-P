<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TARUMT Theatre Society | Manege Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <link rel="stylesheet" href="CSS/adminTable.css" />
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    include 'header(admin).php'; ?>
    <div class="container">
        <h1>Manege Booking</h1>
        <input type="text" id="myInput" onkeyup="filterByFields()"
            placeholder="Search by Event ID, Date, Time, Location, Description, or Price..">

        <a href="addNewBooking.php" class="add-event-button">Add New Booking</a>

        <div class="eventTable">
            <table border="1">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Student ID</th>
                        <th>Event ID</th>
                        <th>Booking Date</th>
                        <th>Quantity Booking</th>
                        <th>Total Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT booking.*, ticket.event_id, payment.total_amount 
                                  FROM booking 
                                  LEFT JOIN ticket ON booking.ticket_id = ticket.ticket_id 
                                  LEFT JOIN payment ON booking.booking_id = payment.booking_id";
                    $result = mysqli_query($connection, $query);
                    if (!$result) {
                        die('Query error: ' . mysqli_error($connection));
                    }
                    while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <tr>
                            <td><?php echo $row['booking_id']; ?></td>
                            <td><?php echo $row['student_id']; ?></td>
                            <td><?php echo $row['event_id']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><?php echo $row['total_amount']; ?></td>
                            <td>
                                <a href="deleteBooking.php?id=<?php echo $row['booking_id']; ?>">Delete</a>
                            </td>
                        </tr>
                    <?php }
                    mysqli_free_result($result);
                    mysqli_close($connection);
                    ?>
                </tbody>
            </table>
            <p id="noDataMessage">No matching data found.</p>
        </div>

        <script>
            function filterByFields() {
                var input, filter, table, tr, td, i, txtValue;
                input = document.getElementById("myInput");
                filter = input.value.toUpperCase();
                table = document.querySelector(".eventTable table");
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

                var noDataMessage = document.getElementById("noDataMessage");
                if (foundCount === 0) {
                    noDataMessage.style.display = "block";
                } else {
                    noDataMessage.style.display = "none";
                }
            }
        </script>

    </div>
    <?php
    include 'footer(admin).php'; ?>
</body>

</html>