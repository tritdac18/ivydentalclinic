<?php
require_once 'config/security.php';

// Perform secure logout
performLogout();

// Redirect to login page
header("Location: login.php");
exit();
?>