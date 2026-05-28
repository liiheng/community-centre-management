<?php
session_start();
include '../includes/db.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = $_GET['status'];
    $view = $_GET['view'] ?? 'list'; // Preserve view mode

    if (in_array($status, ['approved', 'rejected', 'pending'])) {
        $stmt = $conn->prepare("UPDATE temp_activities SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            // Always redirect back with view
            header("Location: approve_activity.php?view=" . urlencode($view) . "&message=Status+updated");
            exit();
        } else {
            echo "Error updating status: " . $stmt->error;
        }
    } else {
        echo "Invalid status value.";
    }
} else {
    echo "Missing parameters.";
}
