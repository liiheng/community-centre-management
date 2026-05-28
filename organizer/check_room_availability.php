<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

$response = ['bookings' => []];

if (!isset($_GET['room_id']) || !isset($_GET['date'])) {
    echo json_encode($response);
    exit();
}

$room_id = intval($_GET['room_id']);
$date = $_GET['date']; // expected YYYY-MM-DD

// fetch bookings (start/end/title/organizer name) for the given room and date
$sql = "SELECT ta.id, ta.start_date, ta.end_date, ta.title, u.name AS organizer_name
        FROM temp_activities ta
        LEFT JOIN users u ON ta.organizer_id = u.id
        WHERE ta.room = ? AND DATE(ta.start_date) = ? AND ta.status != 'rejected'";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('is', $room_id, $date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $response['bookings'][] = [
            'id' => $row['id'],
            'start' => $row['start_date'], // full datetime
            'end' => $row['end_date'],
            'title' => $row['title'],
            'organizer' => $row['organizer_name']
        ];
    }
    $stmt->close();
}

echo json_encode($response);
exit();

?>
