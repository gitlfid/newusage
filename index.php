<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard"); // Tanpa .php karena .htaccess
} else {
    header("Location: login");
}
exit();
?>