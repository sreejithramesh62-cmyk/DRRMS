<?php
// connect.php
$servername = "127.0.0.1";
$username = "root";
$password = ""; // XAMPP default
$database = "drrms2";
$port = 3306;

$conn = mysqli_connect($servername, $username, $password, $database, $port);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
