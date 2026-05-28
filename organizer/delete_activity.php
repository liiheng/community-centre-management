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

// After deletion, redirect back to the referring page if it's the same host to preserve context.
$fallback = 'create_activity.php';
$redirect = $fallback;
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $refHost = parse_url($ref, PHP_URL_HOST);
    if ($refHost && $refHost === $_SERVER['HTTP_HOST']) {
        $redirect = $ref;
    }
}

header('Location: ' . $redirect);
exit();
?>
