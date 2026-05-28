<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("Activity ID not provided.");
}

$activity_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify organizer owns this activity and get title
$stmt = $conn->prepare("SELECT title FROM temp_activities WHERE id = ? AND organizer_id = ?");
$stmt->bind_param("ii", $activity_id, $user_id);
$stmt->execute();
$stmt->bind_result($title);
if (!$stmt->fetch()) {
    die("Unauthorized access.");
}
$stmt->close();

// Fetch participants
$sql = "SELECT u.name, u.email, u.dob, u.gender, u.phone, u.address, p.registered_at
        FROM participants p
        JOIN users u ON p.user_id = u.id
        WHERE p.activity_id = ?
        ORDER BY p.registered_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();

// Clean title for filename
$filename = "participants(" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title) . ").csv";

// Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Name', 'Email', 'DOB', 'Gender', 'Phone', 'Address', 'Registered At']);

// Rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['name'],
        $row['email'],
        $row['dob'],
        $row['gender'],
        $row['phone'],
        $row['address'],
        $row['registered_at']
    ]);
}
fclose($output);
exit();
?>
