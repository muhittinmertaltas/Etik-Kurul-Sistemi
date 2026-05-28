<?php
// logout.php (Ana dizinde olacak)
session_start();
session_destroy();
header("Location: index.php");
exit();