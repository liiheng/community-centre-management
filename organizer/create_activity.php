<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle month navigation
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $resources = $_POST['resources'];
    $target_audience = $_POST['target_audience'];

    $poster_path = "";
    if (!empty($_FILES["poster"]["name"])) {
        $poster_path = "uploads/" . basename($_FILES["poster"]["name"]);
        move_uploaded_file($_FILES["poster"]["tmp_name"], "../" . $poster_path);
    }

    $stmt = $conn->prepare("INSERT INTO temp_activities (title, description, start_date, end_date, resources, target_audience, poster, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $title, $description, $start_date, $end_date, $resources, $target_audience, $poster_path, $user_id);

    if ($stmt->execute()) {
        $message = "Activity created successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Get all activities this month
$activities = [];
$stmt = $conn->prepare("SELECT temp_activities.*, users.name FROM temp_activities JOIN users ON temp_activities.organizer_id = users.id WHERE MONTH(start_date) = ? AND YEAR(start_date) = ?");
$stmt->bind_param("ii", $current_month, $current_year);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $day = date('j', strtotime($row['start_date']));
    $activities[$day][] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Activity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            margin: 0;
        }

        .sidebar {
            width: 65%;
            padding: 20px;
        }

        .form-container {
            width: 35%;
            padding: 30px;
            background: #f7f7f7;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-container h3 {
            margin-top: 0;
        }

        form input,
        form textarea,
        form select {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        form input[type="submit"] {
            background-color: #28a745;
            color: white;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        form input[type="submit"]:hover {
            background-color: #218838;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day {
            min-height: 100px;
            border: 1px solid #ccc;
            padding: 5px;
            box-sizing: border-box;
        }

        .weekday-labels {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        .activity-box {
            border-radius: 4px;
            padding: 5px;
            font-size: 12px;
            margin-top: 5px;
            cursor: pointer;
            border: 1px solid #ccc;
        }

        .approved {
            background-color: #c3f4d3;
        }

        .pending {
            background-color: #fff3cd;
        }

        .rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            padding: 20px;
            display: flex;
            width: 70%;
            border-radius: 10px;
            position: relative;
        }

        .modal-left {
            width: 40%;
        }

        .modal-left img {
            width: 100%;
            border-radius: 10px;
        }

        .modal-right {
            width: 60%;
            padding-left: 20px;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 26px;
            font-weight: bold;
            color: #555;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }
    </style>
</head>

<body>
    <a class="back-button" href="../index.php"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="sidebar">
        <h2 style="text-align: center;">Activity Calendar</h2>
        <h3 style="text-align: center;"><?php echo date("F Y", strtotime("$current_year-$current_month-01")); ?></h3>
        <div class="nav-buttons">
            <a href="?month=<?php echo $current_month - 1 <= 0 ? 12 : $current_month - 1; ?>&year=<?php echo $current_month - 1 <= 0 ? $current_year - 1 : $current_year; ?>">« Previous</a>
            <a href="?month=<?php echo $current_month + 1 > 12 ? 1 : $current_month + 1; ?>&year=<?php echo $current_month + 1 > 12 ? $current_year + 1 : $current_year; ?>">Next »</a>
        </div>
        <div class="weekday-labels">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) echo "<div>$day</div>"; ?>
        </div>
        <div class="calendar">
            <?php
            $first_day = date('w', strtotime("$current_year-$current_month-01"));
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
            for ($i = 0; $i < $first_day; $i++) echo "<div></div>";

            for ($i = 1; $i <= $days_in_month; $i++) {
                echo "<div class='calendar-day'><strong>$i</strong>";
                if (isset($activities[$i])) {
                    foreach ($activities[$i] as $act) {
                        $status_class = $act['status'];
                        $is_creator = $act['organizer_id'] == $user_id;
                        $details = htmlspecialchars(json_encode($act), ENT_QUOTES);
                        echo "<div class='activity-box $status_class' onclick='showModal(this)' data-details='$details'>
                                <strong>{$act['title']}</strong>
                              </div>";
                        if ($is_creator) {
                            echo "<small><a href='edit_activity.php?id={$act['id']}'>Edit</a> |
                                  <a href='delete_activity.php?id={$act['id']}' onclick='return confirm(\"Delete this activity?\")'>Delete</a></small>";
                        }
                    }
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <div class="form-container">
        <h3 style="text-align: center;">Create New Activity</h3>
        <?php if ($message): ?>
            <p style="color: green;"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <label>Title:</label>
            <input type="text" name="title" required>
            <label>Description:</label>
            <textarea name="description" required></textarea>
            <label>Start Date & Time:</label>
            <input type="datetime-local" name="start_date" required>
            <label>End Date & Time:</label>
            <input type="datetime-local" name="end_date" required>
            <label>Resources:</label>
            <textarea name="resources" required></textarea>
            <label>Target Audience:</label>
            <input type="text" name="target_audience" required>
            <label>Upload Poster:</label>
            <input type="file" name="poster">
            <input type="submit" value="Create Activity">
        </form>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('modal').style.display='none'">&times;</span>
            <div class="modal-left">
                <img id="modalPoster" src="" alt="Poster">
            </div>
            <div class="modal-right">
                <h3 id="modalTitle"></h3>
                <p><strong>Description:</strong> <span id="modalDescription"></span></p>
                <p><strong>Time:</strong> <span id="modalTime"></span></p>
                <p><strong>Resources:</strong> <span id="modalResources"></span></p>
                <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                <p><strong>Organizer:</strong> <span id="modalOrganizer"></span></p>
            </div>
        </div>
    </div>

    <script>
        function showModal(element) {
            const data = JSON.parse(element.dataset.details);
            document.getElementById('modalPoster').src = "../" + data.poster;
            document.getElementById('modalTitle').innerText = data.title;
            document.getElementById('modalDescription').innerText = data.description;
            document.getElementById('modalTime').innerText = new Date(data.start_date).toLocaleString() + " - " + new Date(data.end_date).toLocaleString();
            document.getElementById('modalResources').innerText = data.resources;
            document.getElementById('modalAudience').innerText = data.target_audience;
            document.getElementById('modalOrganizer').innerText = data.name;
            document.getElementById('modal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('modal')) {
                document.getElementById('modal').style.display = "none";
            }
        }
    </script>
</body>

</html>