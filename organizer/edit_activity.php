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

// Save referrer (only first visit, not on POST/redirect)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['edit_redirect'])) {
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $_SESSION['edit_redirect'] = $_SERVER['HTTP_REFERER'];
    } else {
        $_SESSION['edit_redirect'] = 'my_activity.php'; // fallback
    }
}

$redirect = $_SESSION['edit_redirect'] ?? 'my_activity.php';

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

$flash = '';
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';

    $poster_path = $activity['poster'];
    if (!empty($_FILES["poster"]["name"])) {
        $poster_path = "uploads/" . basename($_FILES["poster"]["name"]);
        move_uploaded_file($_FILES["poster"]["tmp_name"], "../" . $poster_path);
    }

    $stmt = $conn->prepare("UPDATE temp_activities SET title=?, description=?, target_audience=?, poster=? WHERE id=? AND organizer_id=?");
    $stmt->bind_param("ssssii", $title, $description, $target_audience, $poster_path, $activity_id, $user_id);

    if ($stmt->execute()) {
        $stmt->close();

        // Get back target page
        $target = $_SESSION['edit_redirect'] ?? null;
        unset($_SESSION['edit_redirect']);

        if ($target) {
            echo "<script>
        alert('Activity updated successfully.');
        window.location.href = '" . htmlspecialchars($target, ENT_QUOTES) . "';
    </script>";
        } else {
            // no stored referer → fallback to browser history
            echo "<script>
        alert('Activity updated successfully.');
        history.back();
    </script>";
        }
        exit();
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
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
    <?php include '../includes/header.php'; ?>

    <div class="edit-container">
        <h2>Edit Activity</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <!-- Show date and times as non-editable text -->
            <label>Date:</label>
            <div style="padding:8px 10px; background:#f7f7f7; border-radius:6px;"><?php echo date('Y-m-d', strtotime($activity['start_date'])); ?></div>

            <label>Start Time:</label>
            <div style="padding:8px 10px; background:#f7f7f7; border-radius:6px;"><?php echo date('H:i', strtotime($activity['start_date'])); ?></div>

            <label>End Time:</label>
            <div style="padding:8px 10px; background:#f7f7f7; border-radius:6px;"><?php echo date('H:i', strtotime($activity['end_date'])); ?></div>

            <!-- Poster, Title, Description, Target Audience are editable -->
            <label>Upload Poster:</label>
            <input type="file" name="poster">
            <label>Title:</label>
            <input type="text" name="title" required value="<?php echo htmlspecialchars($activity['title']); ?>">
            <label>Description:</label>
            <textarea name="description" required><?php echo htmlspecialchars($activity['description']); ?></textarea>
            <label>Target Audience:</label>
            <input type="text" name="target_audience" required value="<?php echo htmlspecialchars($activity['target_audience']); ?>">
            <input type="submit" value="Update Activity">
        </form>
        <a class="back-link" href="javascript:history.back()">← Back</a>

    </div>

    <script>
        // show flash popup if server provided a flash message
        (function() {
            var flash = <?php echo json_encode($flash); ?>;
            if (flash) {
                alert(flash);
            }
        })();
    </script>
</body>

</html>