<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

$response = ['registered' => false];

if (isset($_SESSION['user_id'], $_GET['activity_id'])) {
    $stmt = $conn->prepare("SELECT * FROM participants WHERE activity_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_GET['activity_id'], $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['registered'] = true;
    }

    $stmt->close();
}

echo json_encode($response);
?>