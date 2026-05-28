<?php
session_start();
include '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;

if ($activity_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid activity ID']);
    exit();
}

// Get participant count for the activity
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM participants WHERE activity_id = ?");
$stmt->bind_param('i', $activity_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['count' => intval($row['count'])]);
?>