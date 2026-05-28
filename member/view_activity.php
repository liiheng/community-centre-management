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

// Get all approved activities this month with room names and organizer names
$activities = [];
$stmt = $conn->prepare("
    SELECT ta.*, u.name as organizer_name, r.name as room_name 
    FROM temp_activities ta 
    JOIN users u ON ta.organizer_id = u.id 
    LEFT JOIN rooms r ON ta.room = r.id 
    WHERE ta.status = 'approved' AND MONTH(ta.start_date) = ? AND YEAR(ta.start_date) = ?
");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { text-align:center; margin-bottom:20px; color:#333; }
        
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
        .back-button:hover { background: #0056b3; }
        
        .calendar-nav { display:flex; justify-content:center; align-items:center; gap:12px; margin:20px 0; }
        .calendar-nav button { background:#007bff; color:#fff; border:none; padding:8px 12px; border-radius:5px; cursor:pointer; font-size:14px; font-weight:bold; transition:0.2s; }
        .calendar-nav button:hover { background:#0056b3; }
        .calendar-nav h3 { margin:0; color:#333; font-size:22px; }
        
        .calendar-header { display:grid; grid-template-columns:repeat(7,1fr); text-align:center; background:#007bff; color:#fff; font-weight:bold; padding:10px 0; border-radius:8px 8px 0 0; margin-bottom:0; }
        .calendar-header div { padding:8px; }
        
        .calendar { 
            display:grid; 
            grid-template-columns:repeat(7,1fr); 
            gap:5px; 
            background:#fff; 
            padding:15px; 
            border-radius:0 0 10px 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .calendar-day { 
            border:1px solid #d0d6db; 
            min-height:150px; 
            padding:8px; 
            box-sizing: border-box;
            background: #fff;
            border-radius:8px;
            display:flex;
            flex-direction:column;
            overflow: hidden;
        }
        .calendar-day strong { 
            margin:0 0 6px; 
            font-size:18px; 
            font-weight:bold; 
            height:24px; 
            line-height:24px; 
            display:block; 
            color:#333;
        }
        
        .activity-item { 
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 13px;
            margin-bottom: 4px;
            cursor: pointer;
            border: 1px solid #d8dde0;
            background:#fafafa;
            display:block;
            text-decoration:none;
            color:#333;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            transition: all 0.2s;
        }
        .activity-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .activity-item.registered { 
            background:#d4edda; 
            color:#155724; 
            border-color:#c3e6cb; 
        }
        .activity-item.unregistered { 
            background:#e3f2fd; 
            color:#1565c0; 
            border-color:#bbdefb; 
        }

        /* Modal */
        .modal { display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { 
            background:#fff; 
            margin:50px auto; 
            padding:0;
            display:flex; 
            width:70%; 
            max-width:900px;
            border-radius:10px; 
            position:relative;
            max-height:calc(100vh - 100px);
            overflow:hidden;
        }
        .modal-left { 
            width:40%; 
            padding:20px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f8f9fa;
        } 
        .modal-left img { 
            width:100%; 
            max-width:100%;
            height:auto;
            border-radius:10px; 
            object-fit:cover;
        }
        .modal-right { 
            width:60%; 
            padding:20px;
            position:relative;
            display:flex;
            flex-direction:column;
            overflow-y:auto;
        }
        .modal-content-area {
            flex:1;
            padding-right:10px;
        }
        .close { 
            position:absolute; 
            top:10px; 
            right:15px; 
            font-size:28px; 
            cursor:pointer; 
            color:#666; 
            z-index:10;
            width:30px;
            height:30px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:50%;
            transition:all 0.2s;
        }
        .close:hover { 
            color:#000; 
            background:#f0f0f0;
        }
        
        .modal-actions { 
            display:flex; 
            justify-content:flex-end; 
            gap:10px; 
            margin-top:20px;
            padding-top:15px;
            border-top:1px solid #eee;
            position:sticky;
            bottom:0;
            background:#fff;
        }
        .register-btn { 
            text-decoration:none; 
            padding:12px 20px; 
            border-radius:6px; 
            color:#fff; 
            font-weight:bold; 
            display:flex; 
            align-items:center; 
            gap:8px;
            transition:all 0.2s;
            font-size:14px;
        }
        .register-btn.register { background:#28a745; }
        .register-btn.unregister { background:#dc3545; }
        .register-btn:hover { opacity:0.9; transform:translateY(-1px); }
        
        .participant-count {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
        .participant-count i {
            color: #007bff;
            margin-right: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                flex-direction: column;
                margin: 20px auto;
                max-height: calc(100vh - 40px);
            }
            .modal-left, .modal-right {
                width: 100%;
            }
            .modal-left {
                max-height: 300px;
            }
            .calendar {
                gap: 2px;
                padding: 10px;
            }
            .calendar-day {
                min-height: 120px;
                padding: 6px;
            }
            .calendar-day strong {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <a class="back-button" href="../index.php"><i class="fas fa-arrow-left"></i> Back</a>

    <h2><i class="fas fa-calendar-alt"></i> Activity Calendar</h2>

    <div class="calendar-nav">
        <form method="get" style="display:inline;">
            <input type="hidden" name="month" value="<?=($currentMonth==1)?12:$currentMonth-1?>">
            <input type="hidden" name="year" value="<?=($currentMonth==1)?$currentYear-1:$currentYear?>">
            <button type="submit"><i class="fas fa-chevron-left"></i> Previous</button>
        </form>
        <h3><?=date('F Y', strtotime("$currentYear-$currentMonth-01"))?></h3>
        <form method="get" style="display:inline;">
            <input type="hidden" name="month" value="<?=($currentMonth==12)?1:$currentMonth+1?>">
            <input type="hidden" name="year" value="<?=($currentMonth==12)?$currentYear+1:$currentYear?>">
            <button type="submit">Next <i class="fas fa-chevron-right"></i></button>
        </form>
    </div>

    <div class="calendar-header">
        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div>
        <div>Fri</div><div>Sat</div><div>Sun</div>
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
                    $activityJson = htmlspecialchars(json_encode($activity), ENT_QUOTES);
                    echo "<div class='activity-item $class' onclick='showDetails($activityJson, $isRegistered)'>
                            <i class='fas fa-" . ($isRegistered ? "check-circle" : "calendar-plus") . "' style='margin-right:5px;'></i>
                            " . htmlspecialchars($activity['title']) . "
                          </div>";
                }
            }
            echo "</div>";
        }
        ?>
    </div>

    <!-- Modal -->
    <div id="activityModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-left">
                <img id="modalPoster" src="" alt="Activity Poster">
            </div>
            <div class="modal-right">
                <div class="modal-content-area">
                    <h3 id="modalTitle"></h3>
                    <p><strong>Description:</strong></p>
                    <p id="modalDescription" style="margin-left:15px; color:#666; line-height:1.5;"></p>
                    <p><strong>Organizer:</strong> <span id="modalOrganizer"></span></p>
                    <p><strong>Room:</strong> <span id="modalRoom"></span></p>
                    <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                    <p><strong>Start Date & Time:</strong> <span id="modalStart"></span></p>
                    <p><strong>End Date & Time:</strong> <span id="modalEnd"></span></p>
                    
                    <div class="participant-count">
                        <i class="fas fa-users"></i>
                        <strong>Participants:</strong> <span id="participantCount">0</span> registered
                    </div>
                </div>
                <div class="modal-actions">
                    <a id="registerBtn" class="register-btn" href="#"></a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetails(data, isRegistered) {
            document.getElementById('modalTitle').textContent = data.title;
            document.getElementById('modalDescription').textContent = data.description || 'No description provided';
            document.getElementById('modalOrganizer').textContent = data.organizer_name || 'Unknown';
            document.getElementById('modalRoom').textContent = data.room_name || 'Not assigned';
            document.getElementById('modalAudience').textContent = data.target_audience || 'Not specified';
            
            // Format dates nicely
            var startDate = new Date(data.start_date);
            var endDate = new Date(data.end_date);
            document.getElementById('modalStart').textContent = startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('modalEnd').textContent = endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            document.getElementById('modalPoster').src = data.poster ? '../' + data.poster : '../uploads/default_poster.png';
            
            // Get participant count via AJAX
            fetch('get_participant_count.php?activity_id=' + data.id)
                .then(response => response.json())
                .then(result => {
                    document.getElementById('participantCount').textContent = result.count || 0;
                })
                .catch(error => {
                    document.getElementById('participantCount').textContent = '0';
                });

            const btn = document.getElementById('registerBtn');
            if (isRegistered) {
                btn.innerHTML = '<i class="fas fa-user-minus"></i> Unregister';
                btn.className = 'register-btn unregister';
                btn.href = 'register_activity.php?id=' + data.id + '&action=unregister';
            } else {
                btn.innerHTML = '<i class="fas fa-user-plus"></i> Register';
                btn.className = 'register-btn register';
                btn.href = 'register_activity.php?id=' + data.id + '&action=register';
            }

            document.getElementById('activityModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('activityModal').style.display = 'none';
        }
        
        window.onclick = function(e) {
            if (e.target == document.getElementById('activityModal')) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>