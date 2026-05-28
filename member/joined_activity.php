<?php
session_start();
include '../includes/db.php';

// Only member can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// View mode
$view = $_GET['view'] ?? 'list';

// Calendar month/year persistence
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Default filter for list view (keeps same param names for UI similarity)
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$conditions = "1=1 AND t.status = 'approved'"; // only approved activities shown for joined members
$params = [];
$types = '';

// If user filters by status (keeps UI same though probably not needed for member view)
if ($status_filter) {
    $conditions .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($search) {
    $conditions .= " AND (t.title LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Sorting
switch ($sort) {
    case 'title_asc':  $order_by = "t.title ASC"; break;
    case 'title_desc': $order_by = "t.title DESC"; break;
    case 'date_asc':   $order_by = "t.start_date ASC"; break;
    default:           $order_by = "t.start_date DESC";
}

// Handle unregister (via GET to keep behavior consistent with approve_activity.php style)
if (isset($_GET['unregister_id'])) {
    $unregister_id = intval($_GET['unregister_id']);

    // Ensure the participant belongs to this user before deleting
    $check = $conn->prepare("SELECT id FROM participants WHERE id = ? AND user_id = ?");
    $check->bind_param('ii', $unregister_id, $user_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        $del = $conn->prepare("DELETE FROM participants WHERE id = ?");
        $del->bind_param('i', $unregister_id);
        $del->execute();
        $del->close();
    } else {
        $check->close();
    }

    // Build redirect URL to maintain current filters
    $redirectParams = [];
    $redirectParams['view'] = $_GET['view'] ?? 'list';
    if (isset($_GET['month'])) $redirectParams['month'] = $_GET['month'];
    if (isset($_GET['year'])) $redirectParams['year'] = $_GET['year'];
    if (isset($_GET['status']) && $_GET['status'] !== '') $redirectParams['status'] = $_GET['status'];
    if (isset($_GET['search']) && $_GET['search'] !== '') $redirectParams['search'] = $_GET['search'];
    if (isset($_GET['sort'])) $redirectParams['sort'] = $_GET['sort'];
    if (isset($_GET['page'])) $redirectParams['page'] = $_GET['page'];

    $redirectUrl = 'joined_activity.php?' . http_build_query($redirectParams);
    header("Location: $redirectUrl");
    exit();
}

// Count total rows for list view pagination (only activities this member joined)
$countSql = "SELECT COUNT(*) 
    FROM temp_activities t 
    JOIN users u ON t.organizer_id = u.id 
    LEFT JOIN rooms r ON t.room = r.id
    JOIN participants p ON p.activity_id = t.id
    WHERE p.user_id = ? AND $conditions";

$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    // bind dynamic types plus leading 'i' for user_id
    $bind_types = 'i' . $types;
    $bind_vals = array_merge([$user_id], $params);
    $stmt->bind_param($bind_types, ...$bind_vals);
} else {
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$stmt->bind_result($total_rows);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_rows / $limit);

// Fetch for list view
$query = "SELECT t.*, u.name AS organizer_name, r.name AS room_name, p.id AS participant_id
          FROM temp_activities t 
          JOIN users u ON t.organizer_id = u.id 
          LEFT JOIN rooms r ON t.room = r.id 
          JOIN participants p ON p.activity_id = t.id
          WHERE p.user_id = ? AND $conditions
          ORDER BY $order_by 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $bind_types = 'i' . $types . 'ii'; // i for user_id, params types, ii for limit offset
    $bind_vals = array_merge([$user_id], $params, [$limit, $offset]);
    $stmt->bind_param($bind_types, ...$bind_vals);
} else {
    $stmt->bind_param('iii', $user_id, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch joined activities for calendar view (all joined, no pagination)
$all = $conn->prepare("SELECT t.*, u.name AS organizer_name, r.name AS room_name, p.id AS participant_id
                       FROM temp_activities t
                       JOIN users u ON t.organizer_id = u.id
                       LEFT JOIN rooms r ON t.room = r.id
                       JOIN participants p ON p.activity_id = t.id
                       WHERE p.user_id = ?");
$all->bind_param('i', $user_id);
$all->execute();
$allRes = $all->get_result();
$calendarEvents = [];
while ($row = $allRes->fetch_assoc()) {
    $calendarEvents[] = $row;
}
$all->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Joined Activities</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { text-align:center; margin-bottom:20px; }
        .view-toggle { text-align:center; margin-bottom:20px; display:flex; justify-content:center; gap:10px; }
        .view-toggle a { padding:10px 18px; border-radius:5px; text-decoration:none; font-weight:bold; color:#fff; }
        .list-btn { background:#007bff; } .calendar-btn { background:#28a745; }
        #filterForm { display:flex; justify-content:center; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
        #filterForm input, #filterForm select { padding:10px 14px; border:1px solid #ccc; border-radius:8px; font-size:14px; outline:none; transition:0.2s; }
        #filterForm input:focus, #filterForm select:focus { border-color:#007bff; box-shadow:0 0 5px rgba(0,123,255,0.3); }
        table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; }
        th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
        th { background:#007bff; color:#fff; }
        tr:hover { background:#f1f1f1; cursor:pointer; }
        .status-label { padding:6px 12px; border-radius:20px; font-weight:bold; font-size:13px; display:inline-block; }
        .status-approved { background:#d4edda; color:#155724; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-rejected { background:#f8d7da; color:#721c24; }
        .activity-actions a { margin-right:8px; padding:6px 10px; border-radius:5px; font-size:13px; color:#fff; text-decoration:none; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
        .activity-actions a.unreg-btn { background:#dc3545; }
        .activity-actions a.addcal-btn { background:#007bff; }
        .calendar-nav { display:flex; justify-content:center; align-items:center; gap:12px; margin:20px 0; }
        .calendar-nav button { background:#007bff; color:#fff; border:none; padding:8px 12px; border-radius:5px; cursor:pointer; font-size:14px; font-weight:bold; transition:0.2s; }
        .calendar-nav button:hover { background:#0056b3; }
        .calendar-header { display:grid; grid-template-columns:repeat(7,1fr); text-align:center; background:#007bff; color:#fff; font-weight:bold; padding:5px 0; border-radius:5px 5px 0 0; }
        .calendar-header div { padding:5px; }
        .calendar { display:grid; grid-template-columns:repeat(7,1fr); gap:5px; background:#fff; padding:15px; border-radius:10px; }
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
        .calendar-day h4 { 
            margin:0 0 6px; 
            font-size:18px; 
            font-weight:bold; 
            height:24px; 
            line-height:24px; 
            display:block; 
        }
        .calendar-activity { 
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
            height:34px;
            min-height:34px; 
            flex: 0 0 34px;
            box-sizing:border-box;
            white-space:nowrap;
        }
        .calendar-activity.status-pending { background:#fff3cd; color:#856404; border-color:#ffeaa7; }
        .calendar-activity.status-approved { background:#d4edda; color:#155724; border-color:#c3e6cb; }
        .calendar-activity.status-rejected { background:#f8d7da; color:#721c24; border-color:#f5c6cb; }

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
            gap:8px; 
            margin-top:20px;
            padding-top:15px;
            border-top:1px solid #eee;
            position:sticky;
            bottom:0;
            background:#fff;
        }
        .modal-actions a, .modal-actions button { 
            text-decoration:none; 
            padding:6px 10px; 
            border-radius:5px; 
            color:#fff; 
            font-weight:bold; 
            display:flex; 
            align-items:center; 
            gap:5px;
            transition:all 0.2s;
            font-size:13px;
            border:none;
            cursor:pointer;
        }
        .modal-actions .unregister-btn { background:#dc3545; }
        .modal-actions .addcal-btn { background:#007bff; }
        .modal-actions a:hover, .modal-actions button:hover { opacity:0.85; }

        /* Pagination same as approve */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination a:hover {
            background: #f8f9fa;
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
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<h2>My Joined Activities</h2>

<div class="view-toggle">
    <a href="?view=list" class="list-btn">List View</a>
    <a href="?view=calendar" class="calendar-btn">Calendar View</a>
</div>

<?php if ($view=='list'): ?>
<form id="filterForm" method="get">
    <input type="hidden" name="view" value="list">
    <select name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="pending" <?=($status_filter=='pending')?'selected':''?>>Pending</option>
        <option value="approved" <?=($status_filter=='approved')?'selected':''?>>Approved</option>
        <option value="rejected" <?=($status_filter=='rejected')?'selected':''?>>Rejected</option>
    </select>
    <input type="text" name="search" placeholder="Search title/organizer" value="<?=htmlspecialchars($search)?>">
    <select name="sort" onchange="this.form.submit()">
        <option value="date_desc" <?=($sort=='date_desc')?'selected':''?>>Date Desc</option>
        <option value="date_asc" <?=($sort=='date_asc')?'selected':''?>>Date Asc</option>
        <option value="title_asc" <?=($sort=='title_asc')?'selected':''?>>Title A-Z</option>
        <option value="title_desc" <?=($sort=='title_desc')?'selected':''?>>Title Z-A</option>
    </select>
    <button type="submit" style="padding:10px 14px; border:1px solid #007bff; background:#007bff; color:white; border-radius:8px; cursor:pointer;">Search</button>
</form>

<table>
    <tr>
        <th>Title</th>
        <th>Organizer</th>
        <th>Room</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): 
        $displayRoom = $row['room_name'] ?? 'Not Assigned';
        // Include participant id to allow unregister
        $participant_id = $row['participant_id'];
        $json = json_encode($row, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    ?>
    <tr onclick='showModalFromEvent(<?php echo $json; ?>)'>
        <td><?=htmlspecialchars($row['title'])?></td>
        <td><?=htmlspecialchars($row['organizer_name'])?></td>
        <td><?=htmlspecialchars($displayRoom)?></td>
        <td><?=date('Y-m-d H:i', strtotime($row['start_date']))?></td>
        <td><?=date('Y-m-d H:i', strtotime($row['end_date']))?></td>
        <td>
            <span class="status-label status-<?=htmlspecialchars($row['status'])?>"><?=ucfirst(htmlspecialchars($row['status']))?></span>
        </td>
        <td class="activity-actions">
            <!-- Unregister: uses unregister_id (participant id) -->
            <a href="?unregister_id=<?=$participant_id?>&view=list&status=<?=$status_filter?>&search=<?=urlencode($search)?>&sort=<?=$sort?>&page=<?=$page?>" class="unreg-btn" onclick="event.stopPropagation(); return confirm('Unregister from this activity?');"><i class="fas fa-sign-out-alt"></i></a>
            <!-- Add to Google Calendar -->
            <a href="#" class="addcal-btn" onclick="event.stopPropagation(); openGoogleCalendar(<?php echo $json; ?>); return false;"><i class="fas fa-calendar-plus"></i></a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?view=list&status=<?=$status_filter?>&search=<?=urlencode($search)?>&sort=<?=$sort?>&page=<?=$page-1?>">« Previous</a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?=$i?></span>
        <?php else: ?>
            <a href="?view=list&status=<?=$status_filter?>&search=<?=urlencode($search)?>&sort=<?=$sort?>&page=<?=$i?>"><?=$i?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $total_pages): ?>
        <a href="?view=list&status=<?=$status_filter?>&search=<?=urlencode($search)?>&sort=<?=$sort?>&page=<?=$page+1?>">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: 
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfWeek = date('w', strtotime("$year-$month-01"));
$weeks = ceil(($daysInMonth + $firstDayOfWeek)/7);
?>

<div class="calendar-nav">
    <form method="get" style="display:inline;">
        <input type="hidden" name="view" value="calendar">
        <input type="hidden" name="month" value="<?=($month==1)?12:$month-1?>">
        <input type="hidden" name="year" value="<?=($month==1)?$year-1:$year?>">
        <button type="submit">&lt; Previous</button>
    </form>
    <strong><?=date('F Y', strtotime("$year-$month-01"))?></strong>
    <form method="get" style="display:inline;">
        <input type="hidden" name="view" value="calendar">
        <input type="hidden" name="month" value="<?=($month==12)?1:$month+1?>">
        <input type="hidden" name="year" value="<?=($month==12)?$year+1:$year?>">
        <button type="submit">Next &gt;</button>
    </form>
</div>

<div class="calendar-header">
    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div>
    <div>Thu</div><div>Fri</div><div>Sat</div>
</div>
<div class="calendar">
<?php
$dayCounter = 1;
for($i=0;$i<$weeks*7;$i++) {
    if($i<$firstDayOfWeek || $dayCounter>$daysInMonth) {
        echo '<div class="calendar-day"></div>';
    } else {
        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $dayCounter);
        echo '<div class="calendar-day"><h4>'.$dayCounter.'</h4>';
        foreach($calendarEvents as $e) {
            if (substr($e['start_date'],0,10) == $currentDate) {
                $evJson = json_encode($e, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
                echo '<div class="calendar-activity status-'.htmlspecialchars($e['status']).'" onclick=\'showModalFromEvent('.$evJson.')\'>' .
                      htmlspecialchars($e['title']) . '</div>';
            }
        }
        echo '</div>';
        $dayCounter++;
    }
}
?>
</div>
<?php endif; ?>

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
                <p><strong>Status:</strong> <span id="modalStatus" class="status-label"></span></p>
            </div>
            <div class="modal-actions">
                <!-- Unregister button will use participant id (set in JS) -->
                <a href="#" id="unregisterBtn" class="unregister-btn"><i class="fas fa-sign-out-alt"></i> Unregister</a>
                <a href="#" id="addCalBtn" class="addcal-btn"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
            </div>
        </div>
    </div>
</div>

<script>
function pad(n){return n<10? '0'+n:''+n;}

function toGoogleDateString(dtString){
    var d = new Date(dtString);
    // Use UTC time and format YYYYMMDDTHHMMSSZ
    var yyyy = d.getUTCFullYear();
    var mm = pad(d.getUTCMonth()+1);
    var dd = pad(d.getUTCDate());
    var hh = pad(d.getUTCHours());
    var min = pad(d.getUTCMinutes());
    var sec = pad(d.getUTCSeconds());
    return ''+yyyy+mm+dd+'T'+hh+min+sec+'Z';
}

function openGoogleCalendar(ev){
    if (!ev || !ev.start_date) return;
    var start = toGoogleDateString(ev.start_date);
    var end = toGoogleDateString(ev.end_date);
    var title = encodeURIComponent(ev.title || '');
    var details = encodeURIComponent(ev.description || '');
    var location = encodeURIComponent(ev.room_name || ev.room || '');
    var url = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        + '&text=' + title
        + '&details=' + details
        + '&location=' + location
        + '&dates=' + start + '/' + end;
    window.open(url, '_blank');
}

function showModalFromEvent(evt) {
    var event = evt; // keep name consistent
    document.getElementById("activityModal").style.display = "block";
    document.getElementById("modalPoster").src = event.poster ? "../" + event.poster : "../uploads/default_poster.png";
    document.getElementById("modalTitle").textContent = event.title;
    document.getElementById("modalDescription").textContent = event.description || 'No description provided';
    document.getElementById("modalOrganizer").textContent = event.organizer_name;
    document.getElementById("modalRoom").textContent = event.room_name || "Not Assigned";
    document.getElementById("modalAudience").textContent = event.target_audience || 'Not specified';

    var startDate = new Date(event.start_date);
    var endDate = new Date(event.end_date);
    document.getElementById("modalStart").textContent = startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    document.getElementById("modalEnd").textContent = endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

    var statusSpan = document.getElementById("modalStatus");
    statusSpan.textContent = event.status ? (event.status.charAt(0).toUpperCase() + event.status.slice(1)) : 'N/A';
    statusSpan.className = "status-label status-" + (event.status || 'pending');

    // Build unregister URL using participant_id
    var currentParams = new URLSearchParams(window.location.search);
    var baseUnregUrl = "?unregister_id=" + (event.participant_id ? event.participant_id : '');
    for (let [key, value] of currentParams) {
        if (key !== 'unregister_id') {
            baseUnregUrl += "&" + key + "=" + encodeURIComponent(value);
        }
    }
    var unreg = document.getElementById('unregisterBtn');
    unreg.href = baseUnregUrl;
    unreg.onclick = function(e){
        if (!confirm('Unregister from this activity?')){ e.preventDefault(); return false; }
        // proceed to GET link
    };

    var addBtn = document.getElementById('addCalBtn');
    addBtn.onclick = function(e){ e.preventDefault(); openGoogleCalendar(event); };
}

function closeModal() {
    document.getElementById("activityModal").style.display = "none";
}

window.onclick = function(e) {
    if (e.target == document.getElementById("activityModal")) {
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