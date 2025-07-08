<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    echo "No activity ID specified.";
    exit();
}

$activity_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch the activity data
$stmt = $conn->prepare("SELECT * FROM temp_activities WHERE id = ? AND organizer_id = ?");
$stmt->bind_param("ii", $activity_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Activity not found or you are not authorized.";
    exit();
}

$activity = $result->fetch_assoc();
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $resources = $_POST['resources'];
    $target_audience = $_POST['target_audience'];

    $poster_path = $activity['poster']; // Default to existing
    if (!empty($_FILES["poster"]["name"])) {
        $poster_path = "uploads/" . basename($_FILES["poster"]["name"]);
        move_uploaded_file($_FILES["poster"]["tmp_name"], "../" . $poster_path);
    }

    $stmt = $conn->prepare("UPDATE temp_activities SET title=?, description=?, start_date=?, end_date=?, resources=?, target_audience=?, poster=? WHERE id=? AND organizer_id=?");
    $stmt->bind_param("sssssssii", $title, $description, $start_date, $end_date, $resources, $target_audience, $poster_path, $activity_id, $user_id);

    if ($stmt->execute()) {
        $message = "Activity updated successfully!";
        // Refresh data
        $activity = [
            'title' => $title,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'resources' => $resources,
            'target_audience' => $target_audience,
            'poster' => $poster_path
        ];
    } else {
        $message = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Activity</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .edit-container {
            background: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            width: 500px;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        input[type="text"],
        input[type="datetime-local"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 3px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        textarea {
            resize: vertical;
        }
        .message {
            color: green;
            text-align: center;
            margin-bottom: 10px;
        }
        input[type="submit"] {
            background-color: #0d6efd;
            color: white;
            padding: 10px;
            width: 100%;
            margin-top: 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .back-link {
            display: inline-block;
            margin-top: 15px;
            text-align: center;
            text-decoration: none;
            color: #0d6efd;
            display: block;
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <h2>Edit Activity</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <label>Title:</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($activity['title']); ?>" required>

            <label>Description:</label>
            <textarea name="description" required><?php echo htmlspecialchars($activity['description']); ?></textarea>

            <label>Start Date & Time:</label>
            <input type="datetime-local" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($activity['start_date'])); ?>" required>

            <label>End Date & Time:</label>
            <input type="datetime-local" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($activity['end_date'])); ?>" required>

            <label>Resources:</label>
            <textarea name="resources" required><?php echo htmlspecialchars($activity['resources']); ?></textarea>

            <label>Target Audience:</label>
            <input type="text" name="target_audience" value="<?php echo htmlspecialchars($activity['target_audience']); ?>" required>

            <label>Update Poster (optional):</label>
            <input type="file" name="poster">

            <input type="submit" value="Update Activity">
        </form>
        <a class="back-link" href="create_activity.php">← Back to Calendar</a>
    </div>
</body>
</html>
