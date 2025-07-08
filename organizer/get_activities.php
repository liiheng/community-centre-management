<?php
require_once "../includes/db.php";

$result = $conn->query("SELECT title, start_date AS start, end_date AS end FROM temp_activities WHERE status='pending'");
$activities = [];

while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

header('Content-Type: application/json');
echo json_encode($activities);
