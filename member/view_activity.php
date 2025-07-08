<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all approved activities this month
$activities = [];
$stmt = $conn->prepare("SELECT * FROM temp_activities WHERE status = 'approved' AND MONTH(start_date) = ? AND YEAR(start_date) = ?");
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $day = date('j', strtotime($row['start_date']));
    $activities[$day][] = $row;
}
$stmt->close();

// Get registered activities for this user
$registered = [];
$result = $conn->query("SELECT activity_id FROM participants WHERE user_id = $userId");
while ($row = $result->fetch_assoc()) {
    $registered[] = $row['activity_id'];
}

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonth-01"));
$firstDayOfMonth = ($firstDayOfMonth == 0) ? 7 : $firstDayOfMonth;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Activity Calendar</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            padding: 8px 14px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .page-header {
            text-align: center;
            margin-top: 60px;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 10px;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            margin-bottom: 5px;
            font-weight: bold;
            text-align: center;
        }
        .calendar-day {
            border: 1px solid #ccc;
            background: white;
            padding: 6px;
            height: 120px;
            overflow-y: auto;
            border-radius: 4px;
        }
        .calendar-day strong {
            display: block;
            margin-bottom: 5px;
        }
        .activity-link {
            display: block;
            padding: 4px 6px;
            margin-bottom: 4px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .registered {
            background-color: #28a745;
        }
        .unregistered {
            background-color: #007BFF;
        }
        .nav-buttons a {
            margin-right: 10px;
            text-decoration: none;
            background: #007BFF;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 10;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #fff;
            display: flex;
            max-width: 800px;
            width: 90%;
            padding: 20px;
            border-radius: 8px;
        }
        .modal-content img {
            max-width: 300px;
            max-height: 400px;
            margin-right: 20px;
            border-radius: 6px;
        }
        .modal-details {
            flex: 1;
        }
        .close {
            position: absolute;
            top: 30px;
            right: 40px;
            font-size: 28px;
            color: #fff;
            cursor: pointer;
        }
        .register-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
            display: inline-block;
        }
        .unregister-btn {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <a class="back-button" href="../index.php">&larr; Back</a>

    <div class="page-header">
        <h2>Activity Calendar</h2>
    </div>

    <div class="calendar-header">
        <div class="nav-buttons">
            <?php
            $prevMonth = $currentMonth - 1;
            $prevYear = $currentYear;
            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear--;
            }
            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            ?>
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Previous</a>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next &raquo;</a>
        </div>
        <h3 style="text-align: center;"><?= date('F Y', strtotime("$currentYear-$currentMonth-01")) ?></h3>
    </div>

    <div class="calendar-weekdays">
        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
    </div>

    <div class="calendar">
        <?php
        for ($blank = 1; $blank < $firstDayOfMonth; $blank++) {
            echo "<div class='calendar-day'></div>";
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            echo "<div class='calendar-day'><strong>$day</strong>";
            if (isset($activities[$day])) {
                foreach ($activities[$day] as $activity) {
                    $isRegistered = in_array($activity['id'], $registered);
                    $class = $isRegistered ? 'registered' : 'unregistered';
                    echo "<span class='activity-link $class' onclick='showDetails(" . json_encode($activity) . ", $isRegistered)'>{$activity['title']}</span>";
                }
            }
            echo "</div>";
        }
        ?>
    </div>

    <div id="activityModal" class="modal">
        <span class="close" onclick="document.getElementById('activityModal').style.display='none'">&times;</span>
        <div class="modal-content">
            <img id="modalPoster" src="" alt="Poster">
            <div class="modal-details">
                <h3 id="modalTitle"></h3>
                <p><strong>Description:</strong> <span id="modalDesc"></span></p>
                <p><strong>Start:</strong> <span id="modalStart"></span></p>
                <p><strong>End:</strong> <span id="modalEnd"></span></p>
                <p><strong>Resources:</strong> <span id="modalResources"></span></p>
                <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                <a id="registerBtn" class="register-btn" href="#">Register</a>
            </div>
        </div>
    </div>

    <script>
        function showDetails(data, isRegistered) {
            document.getElementById('modalTitle').innerText = data.title;
            document.getElementById('modalDesc').innerText = data.description;
            document.getElementById('modalStart').innerText = data.start_date;
            document.getElementById('modalEnd').innerText = data.end_date;
            document.getElementById('modalResources').innerText = data.resources;
            document.getElementById('modalAudience').innerText = data.target_audience;
            document.getElementById('modalPoster').src = '../' + data.poster;

            const btn = document.getElementById('registerBtn');
            if (isRegistered) {
                btn.innerText = 'Unregister';
                btn.className = 'register-btn unregister-btn';
                btn.href = 'register_activity.php?id=' + data.id + '&action=unregister';
            } else {
                btn.innerText = 'Register';
                btn.className = 'register-btn';
                btn.href = 'register_activity.php?id=' + data.id + '&action=register';
            }

            document.getElementById('activityModal').style.display = 'flex';
        }
    </script>
</body>
</html>
