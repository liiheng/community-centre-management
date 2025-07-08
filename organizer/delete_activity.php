<?php
session_start();
require '../includes/db.php';

if (isset($_GET['id'])) {
    $activity_id = $_GET['id'];

    $delete = "DELETE FROM temp_activities WHERE id = ?";
    $stmt = $conn->prepare($delete);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
}

header("Location: create_activity.php");
exit();
?>
