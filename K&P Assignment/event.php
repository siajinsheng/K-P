<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Events Page</title>
    <link rel="stylesheet" href="css/event.css">
</head>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var events = document.querySelectorAll('.event-item');

        events.forEach(function (event) {
            event.addEventListener('click', function () {
                window.location.href = event.getAttribute('data-href');
            });
        });
    });
</script>


<body>
    <?php
    include ('header.php');
    ?>

    <div class="events-container">
        <?php
        $query = "SELECT * FROM events WHERE event_status = 1";
        $result = mysqli_query($connection, $query);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<div class='event-item' data-href='eventDetails.php?event_id=" . urlencode($row['event_id']) . "' style='margin: 20px; padding: 10px; border: 1px solid #ccc; cursor: pointer;'>";
                echo "<img src='pic/" . htmlspecialchars($row["event_pic"]) . "' style='width:320px; height:200px;'><br>";
                echo "<h2>" . htmlspecialchars($row["event_name"]) . "</h2>";
                echo "<strong>Date:</strong> " . date("F d, Y", strtotime($row["event_date"])) . "<br>";
                echo "<strong>Time:</strong> " . htmlspecialchars($row["event_time"]) . "<br>";
                echo "<strong>Location:</strong> " . htmlspecialchars($row["location"]) . "<br>";
                echo "<strong>Price:</strong> RM" . number_format($row["price"], 2) . "<br>";
                echo "</div>";
            }
        } else {
            echo "No events found.";
        }

        mysqli_close($connection);
        ?>
    </div>
    <?php
    include ('footer.php');
    ?>

</body>

</html>