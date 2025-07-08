<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if already registered
$stmt = $conn->prepare("SELECT * FROM participants WHERE user_id = ? AND activity_id = ?");
$stmt->bind_param("ii", $user_id, $activity_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Already registered, so unregister
    $delStmt = $conn->prepare("DELETE FROM participants WHERE user_id = ? AND activity_id = ?");
    $delStmt->bind_param("ii", $user_id, $activity_id);
    $delStmt->execute();
    $message = "Unregistered successfully!";
    $icon = "❌";
    $color = "#dc3545"; // red
    $delStmt->close();
} else {
    // Register the user
    $insStmt = $conn->prepare("INSERT INTO participants (user_id, activity_id) VALUES (?, ?)");
    $insStmt->bind_param("ii", $user_id, $activity_id);
    $insStmt->execute();
    $message = "Registered successfully!";
    $icon = "✅";
    $color = "#28a745"; // green
    $insStmt->close();
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Status</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: rgba(0, 0, 0, 0.7);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-box {
            background-color: #fff;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            animation: popIn 0.4s ease;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .tick-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: <?= $color ?>;
        }

        .message {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        .back-btn {
            padding: 10px 20px;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        @keyframes popIn {
            0% {
                transform: scale(0.6);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="modal-box">
        <div class="tick-icon"><?= $icon ?></div>
        <div class="message"><?= $message ?></div>
        <a class="back-btn" href="view_activity.php">Back</a>
    </div>
</body>
</html>
