<?php
/**
 * JailTrak Probation Officer Logout
 */

session_start();
session_destroy();
header('Location: login.php');
exit;
?>