<?php

include '_base.php';

// ----------------------------------------------------------------------------

temp('info', 'Logout successfully');
logout(); //unset the session user

header("location:login.php");

?>