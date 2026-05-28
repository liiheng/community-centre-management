<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
// flash message from PRG
$flash = '';
if (isset($_SESSION['flash_message'])) { $flash = $_SESSION['flash_message']; unset($_SESSION['flash_message']); }

// Handle month navigation
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $target_audience = $_POST['target_audience'];
    // support either combined datetime-local or date + time inputs
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $activity_date = isset($_POST['activity_date']) ? $_POST['activity_date'] : '';
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
    $room = isset($_POST['room']) ? $_POST['room'] : null;

    // if start_date not provided, build from activity_date + start_time
    if (empty($start_date) && $activity_date && $start_time) {
        // expected format: YYYY-MM-DD and HH:MM (24h)
        $start_date = $activity_date . ' ' . $start_time . ':00';
    }
    if (empty($end_date) && $activity_date && $end_time) {
        $end_date = $activity_date . ' ' . $end_time . ':00';
    }

    $poster_path = "";
    if (!empty($_FILES["poster"]["name"])) {
        $poster_path = "uploads/" . basename($_FILES["poster"]["name"]);
        move_uploaded_file($_FILES["poster"]["tmp_name"], "../" . $poster_path);
    }

    // check if temp_activities has a 'room' column; if so include it in insert
    $hasRoom = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM temp_activities LIKE 'room'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasRoom = true;
    }

    if ($hasRoom) {
        $stmt = $conn->prepare("INSERT INTO temp_activities (title, description, start_date, end_date, room, target_audience, poster, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $title, $description, $start_date, $end_date, $room, $target_audience, $poster_path, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO temp_activities (title, description, start_date, end_date, target_audience, poster, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $title, $description, $start_date, $end_date, $target_audience, $poster_path, $user_id);
    }

    if ($stmt->execute()) {
        // set flash and redirect (PRG) to clear form inputs
        $_SESSION['flash_message'] = "Activity created successfully! Your application is pending approval.";
        $stmt->close();
        header('Location: create_activity.php');
        exit();
    } else {
        $message = "Error: " . $stmt->error;
        $stmt->close();
    }
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

// Sort events in each day by start_date (ascending) so they display in time order
foreach ($activities as $day => &$dayEvents) {
    usort($dayEvents, function($a, $b) {
        return strcmp($a['start_date'], $b['start_date']);
    });
}
unset($dayEvents);

// load rooms into array for select and JS use
$rooms = array();
$roomsRes = $conn->query("SELECT id, name, description FROM rooms ORDER BY name");
if ($roomsRes) {
    while ($r = $roomsRes->fetch_assoc()) {
        $rooms[intval($r['id'])] = $r;
    }
}
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
            gap: 8px;
        }

        /* simple fixed-size day box */
        .calendar-day {
            height: 150px;
            border: 1px solid #d0d6db;
            padding: 8px;
            box-sizing: border-box;
            background: #fff;
            border-radius:6px;
            display:flex;
            flex-direction:column;
            overflow: hidden; /* ensure internal overflow doesn't expand outer box */
        }

        /* day number/header occupies fixed space */
        .calendar-day > strong { display:block; height:22px; line-height:22px; margin:0 0 6px; font-size:14px }

        .weekday-labels {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: 700;
            margin-top: 10px;
            margin-bottom: 8px;
            color:#333;
        }

        .activity-box {
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 13px;
            margin-top: 6px;
            cursor: pointer;
            border: 1px solid #d8dde0;
            background:#fafafa;
            display:flex;
            align-items:center;
            gap:8px;
            overflow:hidden;
            height:34px; /* fixed wedge height */
            min-height:34px; flex: 0 0 34px; /* don't grow or shrink overall height */
            box-sizing:border-box;
        }

    /* allow title to shrink inside flex container (min-width:0 is important) */
    .activity-box .title { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:700; flex:1 1 auto; min-width:0; max-width:100% }
    .activity-box .actions { display:flex; gap:6px }
    .activity-box .actions a { text-decoration:none; padding:5px 8px; border-radius:5px; font-weight:700; color:#fff; font-size:12px }
    .activity-box .actions a.edit { background:#007bff }
    .activity-box .actions a.delete { background:#dc3545 }

    /* modal action buttons */
    #modalActions { margin-top:12px; display:flex; gap:8px }
    #modalActions a { text-decoration:none; padding:8px 12px; border-radius:6px; color:#fff; font-weight:700 }
    #modalActions a.edit { background:#007bff }
    #modalActions a.delete { background:#dc3545 }

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

        .nav-buttons a {
            display:inline-block;
            padding:6px 10px;
            background:#007bff;
            color:#fff;
            border-radius:5px;
            text-decoration:none;
            font-weight:700;
        }

        /* time slot buttons */
        .slot-btn { border:1px solid #d0d6db; padding:6px 10px; background:#fff; border-radius:6px; cursor:pointer }
        .slot-btn.active { background:#007bff; color:#fff; border-color:#0069d9 }
        .slot-btn.disabled { background:#f1f3f5; color:#999; border-color:#e6e9ec; cursor:not-allowed }

        /* modal room buttons */
        .room-btn { border:1px solid #d0d6db; padding:8px 10px; background:#fff; border-radius:6px; cursor:pointer; margin-right:8px }
        .room-btn:hover { box-shadow:0 4px 12px rgba(0,0,0,0.06) }
        .room-btn.selected { background:#007bff; color:#fff; border-color:#0069d9 }
        .room-desc { margin-top:8px; color:#444; font-size:13px; display:none }

    /* events area: scroll when content overflows day box */
    .calendar-day .events { overflow-y:auto; margin-top:6px; flex:1 1 auto; max-height: calc(100% - 32px); }
    /* ensure long titles don't wrap and day box stays compact */
    .activity-box .title, .activity-box { white-space:nowrap }

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
<?php include '../includes/header.php'; ?>

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
                        echo "<div class='events'>";
                        foreach ($activities[$i] as $act) {
                            $status_class = $act['status'];
                            $is_creator = $act['organizer_id'] == $user_id;
                            // include room name for modal display
                            $roomName = '';
                            if (isset($act['room']) && $act['room'] !== '') {
                                $rStmt = $conn->prepare("SELECT name FROM rooms WHERE id = ? LIMIT 1");
                                if ($rStmt) { $rStmt->bind_param('i', $act['room']); $rStmt->execute(); $rRes = $rStmt->get_result(); if ($rr = $rRes->fetch_assoc()) $roomName = $rr['name']; $rStmt->close(); }
                            }
                            $act['room_name'] = $roomName;
                            $details = htmlspecialchars(json_encode($act), ENT_QUOTES);
                            $titleEsc = htmlspecialchars($act['title']);
                echo "<div class='activity-box $status_class' onclick='showModal(this)' data-details='$details'>
                    <span class='title'>{$titleEsc}</span>
                    <span class='actions'></span>
                      </div>";
                        }
                        echo "</div>";
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
        <!-- popup notification -->
        <div id="flashPopup" style="position:fixed; right:20px; top:20px; background:#28a745; color:#fff; padding:12px 16px; border-radius:8px; display:none; z-index:20000; box-shadow:0 6px 20px rgba(0,0,0,0.15)"></div>
        <form action="" method="POST" enctype="multipart/form-data">
            <label>Date:</label>
            <input type="date" name="activity_date" required>
            <label>Room:</label>
            <div id="roomButtons" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px"></div>
            <input type="hidden" name="room" id="roomSelect">
            <div style="margin-bottom:10px;">
                <label>Start Time (pick one):</label>
                <div id="startSlots" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px"></div>
            </div>
            <div style="margin-bottom:10px;">
                <label>End Time (pick one):</label>
                <div id="endSlots" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px"></div>
            </div>
            <input type="hidden" name="start_time" id="start_time_input">
            <input type="hidden" name="end_time" id="end_time_input">
            <label>Upload Poster:</label>
            <input type="file" name="poster">
            <label>Title:</label>
            <input type="text" name="title" required>
            <label>Description:</label>
            <textarea name="description" required></textarea>
            <label>Target Audience:</label>
            <input type="text" name="target_audience" required>
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
                <!-- reordered per user request: poster(left), title, description, target audience, room, date, start time, end time, organizer name -->
                <h3 id="modalTitle"></h3>
                <p><strong>Description:</strong> <span id="modalDescription"></span></p>
                <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
                <p><strong>Room:</strong> <span id="modalRoomName"></span></p>
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <p><strong>Start Time:</strong> <span id="modalStart"></span></p>
                <p><strong>End Time:</strong> <span id="modalEnd"></span></p>
                <p><strong>Organizer:</strong> <span id="modalOrganizer"></span></p>
                <div id="modalActions" role="group" aria-label="Event actions"></div>
            </div>
        </div>
    </div>

    <!-- custom tooltip for blocked slot details -->
    <div id="slotTooltip" style="position:fixed; pointer-events:none; background:#333; color:#fff; padding:8px 10px; border-radius:6px; font-size:13px; display:none; z-index:10000; max-width:320px; white-space:pre-wrap;"></div>

    <script>
        // show flash popup if server provided a flash message
        (function(){
            var flash = <?php echo json_encode($flash); ?>;
            if (flash) {
                var p = document.getElementById('flashPopup');
                p.innerText = flash;
                p.style.display = 'block';
                setTimeout(function(){ p.style.opacity = '1'; }, 10);
                setTimeout(function(){ p.style.transition = 'opacity 0.4s'; p.style.opacity = '0'; setTimeout(function(){ p.style.display='none'; }, 450); }, 4500);
            }
        })();
        function showModal(element) {
            const data = JSON.parse(element.dataset.details);
            document.getElementById('modalPoster').src = "../" + data.poster;
            document.getElementById('modalTitle').innerText = data.title;
            document.getElementById('modalDescription').innerText = data.description;
            document.getElementById('modalAudience').innerText = data.target_audience;
            document.getElementById('modalRoomName').innerText = data.room_name || '';
            var start = new Date(data.start_date);
            var end = new Date(data.end_date);
            document.getElementById('modalDate').innerText = start.toLocaleDateString();
            document.getElementById('modalStart').innerText = start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('modalEnd').innerText = end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('modalOrganizer').innerText = data.name;
            // populate modal action buttons (Edit/Delete) for organizer owner
            var actions = document.getElementById('modalActions');
            actions.innerHTML = '';
            <?php if (isset($user_id)): ?>
                if (data.organizer_id == <?php echo intval($user_id); ?>) {
                    var edit = document.createElement('a');
                    edit.href = 'edit_activity.php?id=' + data.id;
                    edit.className = 'edit';
                    edit.innerText = 'Edit';
                    var del = document.createElement('a');
                    del.href = 'delete_activity.php?id=' + data.id;
                    del.className = 'delete';
                    del.innerText = 'Delete';
                    del.onclick = function(e) { if(!confirm('Delete this activity?')) { e.preventDefault(); return false; } };
                    actions.appendChild(edit);
                    actions.appendChild(del);
                }
            <?php endif; ?>
            document.getElementById('modal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('modal')) {
                document.getElementById('modal').style.display = "none";
            }
        }
    </script>
    <script>
        // build time slots dynamically from 9:00 to 20:00 (1-hour gaps)
        function buildSlots(containerId) {
            var container = document.getElementById(containerId);
            container.innerHTML = '';
            for (var h = 9; h <= 20; h++) {
                var hh = (h < 10 ? '0' + h : h) + ':00';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'slot-btn';
                btn.dataset.time = hh;
                btn.innerText = hh;
                btn.onclick = function(e){ selectSlot(e.target); };
                container.appendChild(btn);
            }
        }

        function selectSlot(btn) {
            if (btn.disabled) return;
            var containerId = btn.parentElement.id;
            // Toggle behavior: if clicking an already active button, unselect it
            var wasActive = btn.classList.contains('active');
            if (containerId === 'startSlots') {
                // if was active, clear selection
                if (wasActive) {
                    btn.classList.remove('active');
                    document.getElementById('start_time_input').value = '';
                    enforceStartEndExclusion();
                    return;
                }
                // otherwise select this and clear others
                document.querySelectorAll('#startSlots .slot-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('start_time_input').value = btn.dataset.time;
                // enforce that the same time cannot be selected as end
                enforceStartEndExclusion();
            } else {
                if (wasActive) {
                    btn.classList.remove('active');
                    document.getElementById('end_time_input').value = '';
                    enforceEndStartExclusion();
                    return;
                }
                document.querySelectorAll('#endSlots .slot-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('end_time_input').value = btn.dataset.time;
                // enforce that the same time cannot be selected as start
                enforceEndStartExclusion();
            }
        }

        // disable booked slots returned from availability endpoint
        function refreshAvailability() {
            var roomId = document.getElementById('roomSelect').value;
            var date = document.querySelector('input[name="activity_date"]').value;
            // reset all
            document.querySelectorAll('.slot-btn').forEach(function(b){ b.disabled=false; b.classList.remove('disabled'); b.title = ''; });
            if (!roomId || !date) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'check_room_availability.php?room_id=' + encodeURIComponent(roomId) + '&date=' + encodeURIComponent(date));
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        // res.bookings: list of {start, end, title, organizer}
                        if (res.bookings && Array.isArray(res.bookings)) {
                            // build array of blocked hour slots (format HH:MM)
                            var blocked = [];
                            res.bookings.forEach(function(ev){
                                var s = new Date(ev.start);
                                var e = new Date(ev.end);
                                // round down start to hour and iterate by 1-hour steps
                                var cur = new Date(s);
                                cur.setMinutes(0,0,0);
                                while (cur < e) {
                                    var hh = (cur.getHours() < 10 ? '0' + cur.getHours() : cur.getHours()) + ':00';
                                    // avoid duplicates
                                    if (blocked.indexOf(hh) === -1) blocked.push(hh);
                                    cur.setHours(cur.getHours() + 1);
                                }
                            });

                            // disable any start or end slot that would overlap
                            document.querySelectorAll('.slot-btn').forEach(function(b){
                                if (blocked.indexOf(b.dataset.time) !== -1) {
                                    b.disabled = true; b.classList.add('disabled');
                                    // attach tooltip showing which events block this slot
                                    var titles = res.bookings.filter(function(ev){
                                        var s = new Date(ev.start), e = new Date(ev.end);
                                        var slotStart = new Date(s);
                                        var parts = b.dataset.time.split(':'); slotStart.setHours(parseInt(parts[0]), parseInt(parts[1]),0,0);
                                        return slotStart >= s && slotStart < e;
                                    }).map(function(ev){ return ev.title + ' (' + ev.organizer + ') ' + new Date(ev.start).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '-' + new Date(ev.end).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); });
                                    b.title = titles.join('\n');
                                }
                            });
                            // after applying server-side blocked slots, re-apply start/end exclusion rules
                            enforceStartEndExclusion();
                            enforceEndStartExclusion();
                        }
                    } catch(e) { console.error(e); }
                }
            };
            xhr.send();
        }

        // ensure a selected start time disables same end slot, and vice versa
        function enforceStartEndExclusion() {
            var s = document.getElementById('start_time_input').value;
            if (!s) {
                // clear any enforced flags on end slots
                document.querySelectorAll('#endSlots .slot-btn').forEach(function(b){ if (b.dataset.enforced) { b.disabled = false; b.classList.remove('disabled'); delete b.dataset.enforced; } });
                return;
            }
            document.querySelectorAll('#endSlots .slot-btn').forEach(function(b){
                if (b.dataset.time === s) {
                    // mark as enforced-disabled
                    b.disabled = true; b.classList.add('disabled'); b.dataset.enforced = '1';
                    if (b.classList.contains('active')) { b.classList.remove('active'); document.getElementById('end_time_input').value = ''; }
                } else {
                    if (b.dataset.enforced) { b.disabled = false; b.classList.remove('disabled'); delete b.dataset.enforced; }
                }
            });
        }

        function enforceEndStartExclusion() {
            var e = document.getElementById('end_time_input').value;
            if (!e) {
                document.querySelectorAll('#startSlots .slot-btn').forEach(function(b){ if (b.dataset.enforced) { b.disabled = false; b.classList.remove('disabled'); delete b.dataset.enforced; } });
                return;
            }
            document.querySelectorAll('#startSlots .slot-btn').forEach(function(b){
                if (b.dataset.time === e) {
                    b.disabled = true; b.classList.add('disabled'); b.dataset.enforced = '1';
                    if (b.classList.contains('active')) { b.classList.remove('active'); document.getElementById('start_time_input').value = ''; }
                } else {
                    if (b.dataset.enforced) { b.disabled = false; b.classList.remove('disabled'); delete b.dataset.enforced; }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function(){
            console.log('init create_activity JS');
            // build slots on page load
            buildSlots('startSlots');
            buildSlots('endSlots');

            // wire up room/date changes
            var roomSelectEl = document.getElementById('roomSelect');
            if (roomSelectEl) roomSelectEl.addEventListener('change', refreshAvailability);
            var dateEl = document.querySelector('input[name="activity_date"]');
            if (dateEl) dateEl.addEventListener('change', refreshAvailability);

            // render room buttons
            try { renderRoomButtons(); } catch(e){ console.error('renderRoomButtons failed', e); }

            // if there is a flash but popup failed to render, ensure it's visible briefly
            try {
                var flash = <?php echo json_encode($flash); ?>;
                if (flash) {
                    var p = document.getElementById('flashPopup');
                    if (p) { p.innerText = flash; p.style.display = 'block'; setTimeout(function(){ p.style.opacity = '1'; }, 10); setTimeout(function(){ p.style.transition = 'opacity 0.4s'; p.style.opacity = '0'; setTimeout(function(){ p.style.display='none'; }, 450); }, 4500); }
                    else { alert(flash); }
                }
            } catch(e){ console.error(e); }
        });
    </script>
    <script>
        // pass PHP rooms to JS
        var ROOMS = <?php echo json_encode($rooms); ?>;

        // render room buttons in the create form and wire hover description
        function renderRoomButtons() {
            var container = document.getElementById('roomButtons');
            container.innerHTML = '';
            for (var id in ROOMS) {
                var r = ROOMS[id];
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'room-btn';
                b.dataset.id = id;
                b.dataset.desc = r.description || '';
                b.innerText = r.name;
                b.onclick = function(e){ selectRoomButton(this); };
                b.onmouseover = function(e){ showRoomHover(this); };
                b.onmouseout = function(e){ hideRoomHover(); };
                container.appendChild(b);
            }
        }

        function selectRoomButton(btn) {
            var current = document.getElementById('roomSelect').value;
            // if clicking same selected room, toggle off
            if (current && current === btn.dataset.id) {
                btn.classList.remove('selected');
                document.getElementById('roomSelect').value = '';
                refreshAvailability();
                return;
            }
            // clear selection and select new
            document.querySelectorAll('#roomButtons .room-btn').forEach(function(bb){ bb.classList.remove('selected'); });
            btn.classList.add('selected');
            document.getElementById('roomSelect').value = btn.dataset.id;
            refreshAvailability();
        }

        // simple hover box to show description near the button
        var roomHoverBox = null;
        function showRoomHover(btn) {
            var desc = btn.dataset.desc || '';
            if (!desc) return;
            var rect = btn.getBoundingClientRect();
            var box = document.getElementById('slotTooltip');
            box.innerText = desc;
            box.style.display = 'block';
            box.style.left = (rect.right + 8) + 'px';
            box.style.top = (rect.top) + 'px';
        }
        function hideRoomHover() { document.getElementById('slotTooltip').style.display = 'none'; }

        renderRoomButtons();

        // show styled tooltip for blocked slots
        var tooltip = document.getElementById('slotTooltip');
        document.addEventListener('mouseover', function(e){
            var t = e.target;
            if (t && t.classList && t.classList.contains('slot-btn') && t.classList.contains('disabled')) {
                if (t.title) {
                    tooltip.innerText = t.title;
                    tooltip.style.display = 'block';
                    var rect = t.getBoundingClientRect();
                    tooltip.style.left = (rect.right + 8) + 'px';
                    tooltip.style.top = (rect.top) + 'px';
                }
            }
        });
        document.addEventListener('mouseout', function(e){
            var t = e.target;
            if (t && t.classList && t.classList.contains('slot-btn')) {
                tooltip.style.display = 'none';
            }
        });
    </script>
</body>

</html>