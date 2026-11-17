<?php
$host = "127.0.0.1";        
$port = 3307;               
$user = "root";             
$pass = "1234";
$dbname = "ev_charging_db";

$conn = new mysqli($host, $user, $pass, $dbname, $port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>