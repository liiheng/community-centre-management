<?php
$conn = new mysqli("localhost", "root", "", "community_center");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>