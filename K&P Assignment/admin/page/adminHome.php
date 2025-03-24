<?php
include ('header(admin).php');
echo print_r($_SESSION);
?>
<!DOCTYPE html>
<html lang="en">

<style>
    .wrapper,
    .wrapper2 {
        display: inline-flex;
        list-style: none;
        height: 120px;
        width: 100%;
        padding: 5%;
        font-family: "Poppins", sans-serif;
        justify-content: center;
    }

    #btn-message {
        --text-color: #000;
        --bg-color-sup: #d2d2d2;
        --bg-color: #f4f4f4;
        --bg-hover-color: #ffffff;
        --online-status: #00da00;
        --font-size: 16px;
        --btn-transition: all 0.2s ease-out;
    }

    .button-message {
        display: flex;
        justify-content: center;
        align-items: center;
        font: 400 var(--font-size) Helvetica Neue, sans-serif;
        box-shadow: 0 0 2.17382px rgba(0, 0, 0, .049), 0 1.75px 6.01034px rgba(0, 0, 0, .07), 0 3.63px 14.4706px rgba(0, 0, 0, .091), 0 22px 48px rgba(0, 0, 0, .14);
        background-color: var(--bg-color);
        border-radius: 68px;
        cursor: pointer;
        padding: 6px 10px 6px 6px;
        width: fit-content;
        height: 40px;
        border: 0;
        overflow: hidden;
        position: relative;
        transition: var(--btn-transition);
        margin: 15px;
    }

    .button-message:hover {
        height: 56px;
        padding: 8px 20px 8px 8px;
        background-color: var(--bg-hover-color);
        transition: var(--btn-transition);
    }

    .button-message:active {
        transform: scale(0.99);
    }

    .button-message:hover .status-user {
        width: 10px;
        height: 10px;
        right: 1px;
        bottom: 1px;
        outline: solid 3px var(--bg-hover-color);
    }

    .notice-content {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        padding-left: 8px;
        text-align: initial;
        color: var(--text-color);
    }

    .content {
        letter-spacing: -6px;
        height: 0;
        opacity: 0;
        transform: translateY(-20px);
        transition: var(--btn-transition);
    }

    .dec {
        font-size: 12px;
        letter-spacing: -6px;
        height: 0;
        opacity: 0;
        transform: translateY(10px);
        transition: var(--btn-transition);
    }

    .lable-message {
        margin-right: 30px;
    }

    .lable-message,
    .lable-message2 {
        display: flex;
        align-items: center;
        opacity: 1;
        transform: scaleY(1);
        transition: var(--btn-transition);
    }

    .button-message:hover .content {
        height: auto;
        letter-spacing: normal;
        opacity: 1;
        transform: translateY(0);
        transition: var(--btn-transition);
    }

    .button-message:hover .dec {
        height: auto;
        letter-spacing: normal;
        opacity: 1;
        transform: translateY(0);
        transition: var(--btn-transition);
    }

    .button-message:hover .lable-message {
        height: 0;
        transform: scaleY(0);
        transition: var(--btn-transition);
    }

    .lable-message,
    .lable-message2,
    .content {
        font-weight: 600;
    }


    .msg-count {
        position: absolute;
        top: -5px;
        right: -28px;
        background-color: red;
        border-radius: 50%;
        font-size: 0.7em;
        color: rgb(0, 0, 0);
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;

        text-align: center;
    }

    /*==============================================*/
    @keyframes active-status {
        0% {
            background-color: var(--online-status);
        }

        33.33% {
            background-color: #93e200;
        }

        66.33% {
            background-color: #93e200;
        }

        100% {
            background-color: var(--online-status);
        }
    }
</style>

<head>
    <meta charset="UTF-8">
    <script src="https://kit.fontawesome.com/yourcode.js" crossorigin="anonymous"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <link type="text/css" rel="stylesheet" href="CSS/event.css" />
    <title>TARUMT Theatre Society | Admin Home</title>
</head>

<body>
    <?php
    $query1 = "SELECT * FROM events";
    $result1 = mysqli_query($connection, $query1);
    $total_events = mysqli_num_rows($result1);

    $query2 = "SELECT * FROM booking";
    $result2 = mysqli_query($connection, $query2);
    $total_bookings = mysqli_num_rows($result2);

    $query3 = "SELECT * FROM student";
    $result3 = mysqli_query($connection, $query3);
    $total_students = mysqli_num_rows($result3);
    ?>
    <ul class="wrapper">
        <a id="btn-message" class="button-message" href="viewEvent.php">
            <div class="notice-content">
                <div class="content">View Event Details</div>
                <div class="lable-message">Event<span class="msg-count"><?php echo $total_events; ?></span></div>
                <div class="dec">all the event details</div>
            </div>
        </a>
        <a id="btn-message" class="button-message" href="viewBooking.php">
            <div class="notice-content">
                <div class="content">View Booking Details</div>
                <div class="lable-message">Booking<span class="msg-count"><?php echo $total_bookings; ?></span></div>
                <div class="dec">all the booking details</div>
            </div>
        </a>
        <a id="btn-message" class="button-message" href="viewStudent.php">
            <div class="notice-content">
                <div class="content">View Student Details</div>
                <div class="lable-message">Student<span class="msg-count"><?php echo $total_students; ?></span></div>
                <div class="dec">all the student details</div>
            </div>
        </a>
    </ul>

    <ul class="wrapper2">
        <a id="btn-message" class="button-message" href="addNewEvent.php">
            <div class="notice-content">
                <div class="lable-message2">Add Event</div>
                <div class="dec">add the new event</div>
            </div>
        </a>
        <a id="btn-message" class="button-message" href="addNewBooking.php">
            <div class="notice-content">
                <div class="lable-message2">Add Booking</div>
                <div class="dec">add the new booking </div>
            </div>
        </a>
        <a id="btn-message" class="button-message" href="addNewStudent.php">
            <div class="notice-content">
                <div class="lable-message2">Add Student</div>
                <div class="dec">add the new student</div>
            </div>
        </a>
    </ul>
    <ul class="wrapper2"></ul>
    <?php
    include ('footer(admin).php');
    ?>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</body>

</html>