<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />

  <link type="text/css" rel="stylesheet" href="CSS/Home.css" />
  <link type="text/css" rel="stylesheet" href="CSS/app.css" />
  <title>TARUMT Theatre Society | Home</title>
</head>

<body>

  <?php

  include ('header.php');
  //echo print_r($_SESSION);
  ?>


  <header>
    <div class="section_container">
      <div class="header_content">
        <h1>Theatre</h1>
        <p>The Theatre Society at TARUMT is a vibrant community
          of passionate individuals dedicated to the art of theatre. Founded with the aim of
          fostering creativity, collaboration, and artistic expression, our society provides
          an inclusive platform for students from all backgrounds to explore and engage in the
          world of performing arts. nnvdsdsnidsvnmvndvdsvdsvisdvdisvduvdsivhduvhdsivhudsvhvj
        </p>
        <button><a href="AboutUs.php">Read More</a></button>
      </div>
    </div>
  </header>
  <div class="box">
    <h2>Join Our Events</h2>
    <h3>Latest Events</h3>
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
  </div>

  <section class="about_container">
    <div class="section_container">
      <div class="about_content">
        <h2>Why Join Us</h2>
        <p>Joining our Theatre Society isn't just about being part of a club; it's about
          immersing yourself in a world where every scene is a story waiting to unfold and every
          member is a vital piece of the ensemble. Here, you'll discover the joy of exploring
          different roles - whether it's stepping into the spotlight as a lead actor, designing
          mesmerizing sets, or orchestrating the perfect lighting cue.
        </p>
        <button><a href="event.php">Join Us</a></button>
      </div>
    </div>
  </section>

  <?php
  include ('footer.php');
  ?>

</body>

</html>