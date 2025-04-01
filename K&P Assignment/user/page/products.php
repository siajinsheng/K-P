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

<div class="main">
        <ul class="gender-list">
            <li class="list-item">
                <input class="gender" name="gender" type="radio" id="radio-men" value="men" onchange="setProducts();"
                    checked>
                <label for="radio-men">Men</label>
            </li>
            <li class="list-item">
                <input class="gender" name="gender" type="radio" id="radio-ladies" value="ladies"
                    onchange="setProducts();">
                <label for="radio-ladies">Ladies</label>
            </li>
        </ul>
        <div class="menu">
        </div>
    </div>
    <?php
    include ('footer.php');
    ?>

</body>

</html>