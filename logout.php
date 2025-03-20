<?php
session_start();
session_destroy();
header("Location: espace-personnel.php");
exit();
?>