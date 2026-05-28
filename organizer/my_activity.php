<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch activities created by this organizer
$stmt = $conn->prepare("SELECT * FROM temp_activities WHERE organizer_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all rooms into mapping array
$rooms = [];
$roomRes = $conn->query("SELECT id, name FROM rooms");
while ($r = $roomRes->fetch_assoc()) {
    $rooms[$r['id']] = $r['name'];
}

// Fetch participant counts
$participantCounts = [];
if (!empty($activities)) {
    $ids = array_column($activities, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "SELECT activity_id, COUNT(*) AS total FROM participants WHERE activity_id IN ($in) GROUP BY activity_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $participantCounts[$row['activity_id']] = $row['total'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Activities</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { text-align:center; margin-bottom:20px; }

        .controls { display:flex; justify-content:space-between; margin-bottom:20px; }
        .controls input, .controls select {
            padding:8px; border-radius:6px; border:1px solid #ccc; font-size:14px;
        }

        table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; }
        th, td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#007bff; color:#fff; }
        tr:hover { background:#f1f1f1; cursor:pointer; }

        .status-label {
            padding:6px 12px; border-radius:20px; font-weight:bold; font-size:13px;
            display:inline-block;
        }
        .status-approved { background:#d4edda; color:#155724; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-rejected { background:#f8d7da; color:#721c24; }

        .activity-actions a { 
            margin-right:8px; 
            text-decoration:none; 
            padding:6px 10px; 
            border-radius:5px; 
            font-size:13px; 
            color:#fff; 
        }
        .edit-btn { background:#007bff; }
        .delete-btn { background:#dc3545; }
        .download-btn { background:#28a745; }

        /* Modal */
        .modal { display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content {
            background:#fff; margin:50px auto; padding:20px; display:flex;
            width:70%; border-radius:10px; position:relative;
        }
        .modal-left { width:40%; }
        .modal-left img { width:100%; border-radius:10px; }
        .modal-right { width:60%; padding-left:20px; }
        .close { position:absolute; top:15px; right:20px; font-size:26px; cursor:pointer; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<h2>My Created Activities</h2>

<div class="controls">
    <input type="text" id="searchBox" placeholder="Search by title...">
    <select id="statusFilter">
        <option value="">All Status</option>
        <option value="approved">Approved</option>
        <option value="pending">Pending</option>
        <option value="rejected">Rejected</option>
    </select>
    <select id="sortBy">
        <option value="date_desc">Sort: Date (Newest)</option>
        <option value="date_asc">Sort: Date (Oldest)</option>
        <option value="title_asc">Sort: Title (A-Z)</option>
        <option value="title_desc">Sort: Title (Z-A)</option>
    </select>
</div>

<?php if (empty($activities)): ?>
    <p style="text-align:center;">No activities created yet.</p>
<?php else: ?>
<table id="activityTable">
    <thead>
        <tr>
            <th>Title</th>
            <th>Room</th>
            <th>Date</th>
            <th>Time</th>
            <th>Participants</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($activities as $act): 
        $count = $participantCounts[$act['id']] ?? 0;
        $details = htmlspecialchars(json_encode($act), ENT_QUOTES);

        $statusClass = "status-" . strtolower($act['status']);
        $roomName = isset($rooms[$act['room']]) ? $rooms[$act['room']] : "N/A";
    ?>
        <tr onclick="showModal(this)" data-details='<?php echo $details; ?>'>
            <td><?php echo htmlspecialchars($act['title']); ?></td>
            <td><?php echo htmlspecialchars($roomName); ?></td>
            <td><?php echo date('d/m/Y', strtotime($act['start_date'])); ?></td>
            <td><?php echo date('H:i', strtotime($act['start_date'])) . " - " . date('H:i', strtotime($act['end_date'])); ?></td>
            <td><?php echo $count; ?></td>
            <td><span class="status-label <?php echo $statusClass; ?>"><?php echo ucfirst($act['status']); ?></span></td>
            <td class="activity-actions" onclick="event.stopPropagation();">
                <a href="edit_activity.php?id=<?php echo $act['id']; ?>" class="edit-btn" title="Edit Activity"><i class="fas fa-edit"></i></a>
                <a href="delete_activity.php?id=<?php echo $act['id']; ?>" class="delete-btn" title="Delete Activity" onclick="return confirm('Delete this activity?');"><i class="fas fa-trash"></i></a>
                <a href="download_participants.php?id=<?php echo $act['id']; ?>" class="download-btn" title="Download Participants"><i class="fas fa-download"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Modal -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('modal').style.display='none'">&times;</span>
        <div class="modal-left">
            <img id="modalPoster" src="" alt="Poster">
        </div>
        <div class="modal-right">
            <h3 id="modalTitle"></h3>
            <p><strong>Description:</strong> <span id="modalDescription"></span></p>
            <p><strong>Date:</strong> <span id="modalDate"></span></p>
            <p><strong>Start Time:</strong> <span id="modalStart"></span></p>
            <p><strong>End Time:</strong> <span id="modalEnd"></span></p>
            <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
            <p><strong>Room:</strong> <span id="modalRoom"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        </div>
    </div>
</div>

<script>
function showModal(row) {
    const data = JSON.parse(row.dataset.details);
    document.getElementById('modalPoster').src = "../" + data.poster;
    document.getElementById('modalTitle').innerText = data.title;
    document.getElementById('modalDescription').innerText = data.description;

    var start = new Date(data.start_date);
    var end = new Date(data.end_date);

    // format dd/mm/yyyy
    let formattedDate = ("0" + start.getDate()).slice(-2) + "/" +
                        ("0" + (start.getMonth()+1)).slice(-2) + "/" +
                        start.getFullYear();
    document.getElementById('modalDate').innerText = formattedDate;

    document.getElementById('modalStart').innerText = start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    document.getElementById('modalEnd').innerText = end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    document.getElementById('modalAudience').innerText = data.target_audience || '';

    const rooms = <?php echo json_encode($rooms); ?>;
    document.getElementById('modalRoom').innerText = rooms[data.room] || 'N/A';

    document.getElementById('modalStatus').innerText = data.status;

    document.getElementById('modal').style.display = 'block';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('modal')) {
        document.getElementById('modal').style.display = "none";
    }
}

// Search, Filter, Sort
const searchBox = document.getElementById('searchBox');
const statusFilter = document.getElementById('statusFilter');
const sortBy = document.getElementById('sortBy');
const tableBody = document.querySelector('#activityTable tbody');

function applyFilters() {
    let rows = Array.from(tableBody.rows);
    let search = searchBox.value.toLowerCase();
    let filter = statusFilter.value;
    let sort = sortBy.value;

    rows.forEach(row => {
        let title = row.cells[0].innerText.toLowerCase();
        let status = row.cells[5].innerText.toLowerCase();
        let match = (!search || title.includes(search)) && (!filter || status === filter);
        row.style.display = match ? '' : 'none';
    });

    rows.sort((a, b) => {
        if (sort === 'date_asc') {
            return new Date(a.cells[2].innerText.split('/').reverse().join('-')) - new Date(b.cells[2].innerText.split('/').reverse().join('-'));
        } else if (sort === 'date_desc') {
            return new Date(b.cells[2].innerText.split('/').reverse().join('-')) - new Date(a.cells[2].innerText.split('/').reverse().join('-'));
        } else if (sort === 'title_asc') {
            return a.cells[0].innerText.localeCompare(b.cells[0].innerText);
        } else if (sort === 'title_desc') {
            return b.cells[0].innerText.localeCompare(a.cells[0].innerText);
        }
        return 0;
    });

    rows.forEach(r => tableBody.appendChild(r));
}

searchBox.addEventListener('input', applyFilters);
statusFilter.addEventListener('change', applyFilters);
sortBy.addEventListener('change', applyFilters);
</script>
</body>
</html>
