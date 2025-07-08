<?php
require_once "../includes/db.php";

$title = $_POST['title'];
$description = $_POST['description'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$resources = $_POST['resources'];
$target_audience = $_POST['target_audience'];
$organizer_id = $_POST['organizer_id'];
$poster = $_FILES['poster']['name'];
$poster_tmp = $_FILES['poster']['tmp_name'];

$poster_path = 'posters/' . time() . '_' . $poster;
move_uploaded_file($poster_tmp, $poster_path);

$sql = "INSERT INTO temp_activities (title, description, start_date, end_date, resources, target_audience, poster, organizer_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssi", $title, $description, $start_date, $end_date, $resources, $target_audience, $poster_path, $organizer_id);
$stmt->execute();
$stmt->close();

header("Location: create_activity.php");
exit();
