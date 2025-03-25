<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/view_admin.css" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <link rel="stylesheet" href="CSS/adminTable.css" />

    <title>TARUMT Theatre Society | Edit Event</title>
</head>

<body>
    <?php
    $headerFile = 'header(admin).php';
    $connectFile = 'connectDatabase.php';
    $footerFile = 'footer(admin).php';

    if (file_exists($headerFile)) {
        include($headerFile);
    } else {
        echo "<p>Error: Header file not found.</p>";
    }
    ?>
    <div class="container">
        <h1>Edit Details</h1>
        <input type="text" id="myInput" onkeyup="filterByFields()" placeholder="Search by Event ID, Date, Time, Location, Description, or Price..">

        <a href="addNewEvent.php" class="add-event-button">Add New Event</a>

        <div class="eventTable">
            <table border="1">
                <thead>
                    <tr>
                        <th>Event ID</th>
                        <th>Event Picture</th>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Total Ticket</th>
                        <th>Ticket Available</th>
                        <th>State</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (file_exists($connectFile)) {
                        include($connectFile);
                        $query = mysqli_query($connection, "SELECT e.*, t.maxTicket, t.ticketAvailable FROM events e LEFT JOIN ticket t ON e.event_id = t.event_id");
                        if (!$query) {
                            die('Query error: ' . mysqli_error($connection));
                        }
                        while ($row = mysqli_fetch_array($query)) {
                    ?>
                            <tr>
                                <td><?php echo $row['event_id']; ?></td>
                                <td><?php
                                    if (!empty($row['event_pic'])) {
                                    ?>
                                        <img class="event" src="pic/<?php echo $row['event_pic']; ?>" alt="Event Image">
                                    <?php
                                    } else {
                                    ?>
                                        <h1>No Image Uploaded</h1>
                                    <?php
                                    }
                                    echo "<br>";
                                    echo $row['event_pic'];
                                    ?>
                                </td>
                                <td><?php echo $row['event_name']; ?></td>
                                <td><?php echo $row['event_date']; ?></td>
                                <td><?php echo $row['event_time']; ?></td>
                                <td><?php echo $row['location']; ?></td>
                                <td>
                                    <div class="description">
                                        <div class="short-description" id="shortDesc<?php echo $row['event_id']; ?>">
                                            <?php echo substr($row['description'], 0, 100); ?>...
                                            <span class="description-toggle" onclick="toggleDescription('<?php echo $row['event_id']; ?>')">Show More</span>
                                        </div>
                                        <div class="full-description" id="fullDesc<?php echo $row['event_id']; ?>" style="display:none;">
                                            <?php echo $row['description']; ?>
                                            <span class="description-toggle" onclick="toggleDescription('<?php echo $row['event_id']; ?>')">Show Less</span>
                                        </div>
                                    </div>
                                </td>
                                <td>RM <?php echo $row['price']; ?></td>
                                <td><?php echo $row['maxTicket']; ?></td>
                                <td><?php echo $row['ticketAvailable']; ?></td>
                                <td>
                                    <?php if ($row['event_status'] == 1) { ?>
                                        <span style="color: green;">Event Available</span>
                                    <?php } elseif ($row['event_status'] == 0) { ?>
                                        <span style="color: orange;">Event Not Available</span>
                                    <?php } else { ?>
                                        <span style="color: red;">Error</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <a href="updateEvent.php?id=<?php echo $row['event_id']; ?>">Edit</a>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='12'>Error: Database connection file not found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <p id="noDataMessage">No matching data found.</p>
        </div>


        <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>

        <script>
            function toggleDescription(eventId) {
                var shortDesc = document.getElementById('shortDesc' + eventId);
                var fullDesc = document.getElementById('fullDesc' + eventId);
                if (shortDesc.style.display === 'none') {
                    shortDesc.style.display = 'block';
                    fullDesc.style.display = 'none';
                } else {
                    shortDesc.style.display = 'none';
                    fullDesc.style.display = 'block';
                }
            }

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
    if (file_exists($footerFile)) {
        include($footerFile);
    } else {
        echo "<p>Error: Footer file not found.</p>";
    }
    ?>
</body>

</html>