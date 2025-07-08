<?php
session_start();
require_once "includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info from DB
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $role);
$stmt->fetch();
$stmt->close();
?>


<!DOCTYPE html>
<html>

<head>
    <title>Main Menu - Community Centre</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f2f2f2;
        }

        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            background-color: #ffffff;
            padding: 10px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .top-bar span {
            margin-right: 15px;
            font-weight: bold;
        }

        .top-bar a.logout-btn {
            text-decoration: none;
            background-color: #cc3333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .top-bar a.logout-btn:hover {
            background-color: #a30000;
        }

        .main-content {
            padding: 40px 20px;
            text-align: center;
        }

        .main-content h1 {
            margin-bottom: 10px;
        }

        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
        }

        .card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 220px;
            height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: transform 0.2s ease;
            text-align: center;
        }

        .card:hover {
            transform: scale(1.05);
            cursor: pointer;
            background-color: #f0f0f0;
        }

        .card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #007BFF;
        }

        .card p {
            font-size: 16px;
            margin: 0;
        }
    </style>
</head>

<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <span>Hello, <?php echo htmlspecialchars($name); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <h1>Welcome to the Community Activities Centre</h1>
        <p>You are logged in as <strong><?php echo ucfirst($role); ?></strong></p>

        <div class="card-container">
            <?php if ($role == 'member'): ?>
                <a href="member/view_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-calendar-check"></i>
                        <p>Browse & Register Activities</p>
                    </div>
                </a>
                <a href="feedback.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <p>Give Feedback</p>
                    </div>
                </a>

            <?php elseif ($role == 'organizer'): ?>
                <a href="organizer/create_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-plus-circle"></i>
                        <p>Create Activity</p>
                    </div>
                </a>
                <a href="reserve_place.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-door-open"></i>
                        <p>Reserve Place</p>
                    </div>
                </a>
                <a href="announcement.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-bullhorn"></i>
                        <p>Post Announcement</p>
                    </div>
                </a>

            <?php elseif ($role == 'admin'): ?>
                <a href="admin/approve_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-check-circle"></i>
                        <p>Approve Activities</p>
                    </div>
                </a>
                <a href="manage_schedule.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-calendar-alt"></i>
                        <p>Manage Schedule</p>
                    </div>
                </a>
                <a href="handle_conflicts.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Handle Conflicts</p>
                    </div>
                </a>
                <a href="view_feedback.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-chart-line"></i>
                        <p>View Feedback & Reports</p>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>


</body>

</html>