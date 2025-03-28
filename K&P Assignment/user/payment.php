<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />

    <link type="text/css" rel="stylesheet" href="CSS/payment.css" />
    <title>TARUMT Theatre Society | Payment</title>
</head>

<body>

    <?php
    include ('header.php');

    $payment_id = $_SESSION['payment_id'] ?? '';

    if (!$payment_id) {
        die('Payment session expired or invalid. Please try again.');
    }

    function sanitize_input($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $paymentProcessed = false;
    $cardNumber = $cardHolder = $expiryMonth = $expiryYear = $cvv = "";
    $errors = [];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $cardNumber = sanitize_input($_POST['cardNumber']);
        $cardHolder = sanitize_input($_POST['cardHolder']);
        $expiryMonth = sanitize_input($_POST['expiryMonth']);
        $expiryYear = sanitize_input($_POST['expiryYear']);
        $cvv = sanitize_input($_POST['cvv']);

        if (empty($cardNumber) || empty($cardHolder) || empty($expiryMonth) || empty($expiryYear) || empty($cvv)) {
            $errors['all'] = 'All fields are required.';
        }

        if (!preg_match("/^[0-9]{16}$/", $cardNumber)) {
            $errors['cardNumber'] = 'Invalid card number. Card number should be 16 digits.';
            $cardNumber = "";
        }

        if (!preg_match("/^[a-zA-Z ]*$/", $cardHolder)) {
            $errors['cardHolder'] = 'Card holder name can only contain letters and spaces.';
            $cardHolder = "";
        }

        if (!preg_match("/^(0[1-9]|1[0-2])$/", $expiryMonth)) {
            $errors['expiryMonth'] = 'Invalid month. Must be MM format (01-12).';
            $expiryMonth = "";
        }

        $currentYear = date('y');
        if (!preg_match("/^\d{2}$/", $expiryYear) || $expiryYear <= $currentYear) {
            $errors['expiryYear'] = 'Invalid year. Must be YY format and greater than the current year.';
            $expiryYear = "";
        }

        if (!preg_match("/^[0-9]{3}$/", $cvv)) {
            $errors['cvv'] = 'Invalid CVV. CVV should be 3 or 4 digits.';
            $cvv = "";
        }

        if (empty($errors)) {
            $updateQuery = "UPDATE payment SET status = 'Paid', payment_date = NOW() WHERE payment_id = ?";
            if ($stmt = mysqli_prepare($connection, $updateQuery)) {
                mysqli_stmt_bind_param($stmt, "s", $payment_id);
                if (mysqli_stmt_execute($stmt)) {
                    $paymentProcessed = true;
                } else {
                    die("Error updating payment: " . mysqli_error($connection));
                }
                mysqli_stmt_close($stmt);
            } else {
                die("Error preparing statement: " . mysqli_error($connection));
            }
        }
    }
    ?>

    <div class="container">
        <div class="box">
            <h2>Payment Details</h2>
            <?php
            if (!empty($errors['all'])) {
                echo "<p style='color:red;'>{$errors['all']}</p>";
            }
            ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="cardNumber">Card Number:</label>
                    <input type="text" class="form-control" id="cardNumber" name="cardNumber"
                        value="<?php echo $cardNumber; ?>" required placeholder="xxxxxxxxxxxxxxxx">
                    <?php if (!empty($errors['cardNumber'])) {
                        echo "<p style='color:red;'>{$errors['cardNumber']}</p>";
                    } ?>
                </div>
                <div class="form-group">
                    <label for="cardHolder">Card Holder:</label>
                    <input type="text" class="form-control" id="cardHolder" name="cardHolder"
                        value="<?php echo $cardHolder; ?>" required placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label for="expiryMonth">Expiry Month:</label>
                    <input type="number" class="form-control" id="expiryMonth" name="expiryMonth"
                        value="<?php echo $expiryMonth; ?>" required placeholder="MM (e.g., 05)">
                </div>
                <div class="form-group">
                    <label for="expiryYear">Expiry Year:</label>
                    <input type="number" class="form-control" id="expiryYear" name="expiryYear"
                        value="<?php echo $expiryYear; ?>" required placeholder="YY (e.g., 23)">
                </div>
                <div class="form-group">
                    <label for="cvv">CVV:</label>
                    <input type="text" class="form-control" id="cvv" name="cvv" value="<?php echo $cvv; ?>" required
                        placeholder="123">
                    <?php if (!empty($errors['cvv'])) {
                        echo "<p style='color:red;'>{$errors['cvv']}</p>";
                    } ?>
                </div>
                <button type="submit" class="btn btn-success">Submit Payment</button>
            </form>
            <a href="event.php" class="btn btn-info">Return to Event</a>
        </div>
    </div>

    <?php
    include ('footer.php');
    ?>
    <?php if ($paymentProcessed): ?>
        <script>
            alert("Payment processed successfully!");
            setTimeout(function () {
                window.location.href = "event.php";
            }, 1000);
        </script>
    <?php endif; ?>
</body>

</html>