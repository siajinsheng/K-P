<?php
require '_base.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet" />

  <link type="text/css" rel="stylesheet" href="CSS/Home.css" />
  <link type="text/css" rel="stylesheet" href="user/css/app.css" />
  <title>K&P | Index</title>
</head>

<body>

  <?php

  require 'user/header.php';
  //echo print_r($_SESSION);
  ?>


  <header>
    <div class="section_container">
      <div class="header_content">
        <h1>K&P</h1>
        <p>Welcome to K&P – your go-to destination for stylish, high-quality fashion. We offer a curated selection of trendy and timeless clothing to elevate your wardrobe. Shop with us for the perfect blend of comfort, elegance, and modern style. Stay fashionable with K&P!
        </p>
      </div>
    </div>
  </header>
  <div class="box">
    <img src="user/image/index1.avif" alt="">
  </div>

  <?php
  require 'user/footer.php';
  ?>

</body>

</html>


<?php
$_title = 'Index';
?>