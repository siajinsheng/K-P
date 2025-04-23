<?php
$_title = 'K&P - Register';
require '_base.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>K&P - Home</title>
  <link rel="stylesheet" href="css/index.css">
  <?php include 'user/header.php'; ?>
</head>

<body>
  <div class="fullpage-container">
    <section class="fullpage-section clickable-section" id="section1" data-target="products.php">
      <!-- Content for section 1 -->
    </section>

    <section class="fullpage-section clickable-section" id="section2" data-target="products.php">
      <!-- Content for section 2 -->
    </section>

    <section class="fullpage-section clickable-section" id="section3" data-target="products.php">
      <!-- Content for section 3 -->
    </section>
  </div>
  <?php include 'user/footer.php'; ?>

  <script src="js/index.js"></script>
</body>

</html>