<?php
session_start();
session_destroy();
header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
exit();
?>

