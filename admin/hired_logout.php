<?php
session_start();
unset($_SESSION['hired_user_id'], $_SESSION['hired_user_name'], $_SESSION['hired_user_email'], $_SESSION['hired_role']);
session_destroy();
header("Location: hired_login.php");
exit;