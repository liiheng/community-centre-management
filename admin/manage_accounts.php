<?php
session_start();
include '../includes/db.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $action = $_POST['action'];
    $user_id = intval($_POST['user_id']);

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='approved' WHERE id=? AND role='organizer'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status='rejected' WHERE id=? AND role='organizer'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Toggle role view
$view_role = $_GET['role'] ?? 'member';

// Fetch users
$stmt = $conn->prepare("SELECT * FROM users WHERE role=? ORDER BY id DESC");
$stmt->bind_param("s", $view_role);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Accounts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { text-align:center; margin-bottom:20px; }

        .role-toggle { text-align:center; margin-bottom:20px; }
        .role-toggle a {
            margin:0 10px; text-decoration:none; padding:8px 16px; border-radius:5px; color:#fff;
        }
        .role-toggle .active { background:#007bff; }
        .role-toggle a.inactive { background:#6c757d; }

        .controls { display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .controls input, .controls select { padding:8px; border-radius:6px; border:1px solid #ccc; font-size:14px; }

        table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; }
        th, td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#007bff; color:#fff; }
        tr:hover { background:#f1f1f1; cursor:pointer; }

        .status-label { padding:6px 12px; border-radius:20px; font-weight:bold; font-size:13px; display:inline-block; }
        .status-approved { background:#d4edda; color:#155724; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-rejected { background:#f8d7da; color:#721c24; }

        .action-btn { border:none; padding:6px 10px; border-radius:5px; color:#fff; cursor:pointer; margin-right:5px; font-size:14px; }
        .approve-btn { background:#007bff; }
        .reject-btn { background:#6c757d; }
        .delete-btn { background:#dc3545; }

        /* Modal */
        .modal { display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content {
            background:#fff; margin:50px auto; padding:20px; width:400px; border-radius:10px; position:relative;
            text-align:center;
        }
        .modal-content img { width:100px; height:100px; border-radius:50%; margin-bottom:20px; object-fit:cover; }
        .modal-content table { width:100%; text-align:left; }
        .modal-content table td { padding:5px 10px; }
        .close { position:absolute; top:10px; right:15px; font-size:24px; cursor:pointer; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<h2>Manage Accounts</h2>

<div class="role-toggle">
    <a href="?role=member" class="<?= $view_role === 'member' ? 'active' : 'inactive' ?>">Members</a>
    <a href="?role=organizer" class="<?= $view_role === 'organizer' ? 'active' : 'inactive' ?>">Organizers</a>
</div>

<div class="controls">
    <input type="text" id="searchBox" placeholder="Search by name or email...">
    <?php if($view_role==='organizer'): ?>
        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
        </select>
    <?php endif; ?>
    <select id="sortBy">
        <option value="id_desc">Sort: Newest</option>
        <option value="id_asc">Sort: Oldest</option>
        <option value="name_asc">Name (A-Z)</option>
        <option value="name_desc">Name (Z-A)</option>
    </select>
</div>

<?php if(empty($users)): ?>
    <p style="text-align:center;">No users found.</p>
<?php else: ?>
<table id="userTable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($users as $user): 
        $dob = $user['dob'] ? new DateTime($user['dob']) : null;
        $age = $dob ? $dob->diff(new DateTime())->y : '-';
    ?>
        <tr onclick="showModal(this)" data-details='<?= json_encode($user, JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['gender']) ?></td>
            <td><?= $age ?></td>
            <td>
                <?php $statusClass = 'status-' . strtolower($user['status']); ?>
                <span class="status-label <?= $statusClass ?>"><?= ucfirst($user['status']) ?></span>
            </td>
            <td onclick="event.stopPropagation();">
                <?php if($view_role==='organizer'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="action" value="approve" class="action-btn approve-btn" title="Approve"><i class="fas fa-check"></i></button>
                        <button type="submit" name="action" value="reject" class="action-btn reject-btn" title="Reject"><i class="fas fa-times"></i></button>
                        <button type="submit" name="action" value="delete" class="action-btn delete-btn" title="Delete" onclick="return confirm('Delete this user?');"><i class="fas fa-trash"></i></button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="action" value="delete" class="action-btn delete-btn" title="Delete" onclick="return confirm('Delete this user?');"><i class="fas fa-trash"></i></button>
                    </form>
                <?php endif; ?>
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
        <img id="modalPic" src="" alt="Profile Picture">
        <table>
            <tr><td><strong>Name:</strong></td><td id="modalName"></td></tr>
            <tr><td><strong>Email:</strong></td><td id="modalEmail"></td></tr>
            <tr><td><strong>Gender:</strong></td><td id="modalGender"></td></tr>
            <tr><td><strong>Age:</strong></td><td id="modalAge"></td></tr>
            <tr><td><strong>Phone:</strong></td><td id="modalPhone"></td></tr>
            <tr><td><strong>Address:</strong></td><td id="modalAddress"></td></tr>
            <tr><td><strong>Role:</strong></td><td id="modalRole"></td></tr>
            <tr><td><strong>Status:</strong></td><td id="modalStatus"></td></tr>
        </table>
    </div>
</div>

<script>
function showModal(row){
    const data = JSON.parse(row.dataset.details);
    document.getElementById('modalPic').src = "../" + (data.profile_pic || 'images/default_profile.png');
    document.getElementById('modalName').innerText = data.name;
    document.getElementById('modalEmail').innerText = data.email;
    document.getElementById('modalGender').innerText = data.gender;
    if(data.dob){
        const dob = new Date(data.dob);
        const age = new Date().getFullYear() - dob.getFullYear();
        document.getElementById('modalAge').innerText = age;
    } else { document.getElementById('modalAge').innerText = '-'; }
    document.getElementById('modalPhone').innerText = data.phone || '-';
    document.getElementById('modalAddress').innerText = data.address || '-';
    document.getElementById('modalRole').innerText = data.role;
    document.getElementById('modalStatus').innerText = data.status;
    document.getElementById('modal').style.display='block';
}

window.onclick = function(event){
    if(event.target == document.getElementById('modal')){
        document.getElementById('modal').style.display = "none";
    }
}

// Search, Filter, Sort
const searchBox = document.getElementById('searchBox');
const statusFilter = document.getElementById('statusFilter');
const sortBy = document.getElementById('sortBy');
const tableBody = document.querySelector('#userTable tbody');

function applyFilters() {
    let rows = Array.from(tableBody.rows);
    let search = searchBox.value.toLowerCase();
    let filter = statusFilter ? statusFilter.value.toLowerCase() : '';
    let sort = sortBy.value;

    rows.forEach(row => {
        let name = row.cells[0].innerText.toLowerCase();
        let email = row.cells[1].innerText.toLowerCase();
        let status = row.cells[4].querySelector('.status-label') ? 
                     row.cells[4].querySelector('.status-label').innerText.toLowerCase() : '';
        let match = (!search || name.includes(search) || email.includes(search)) && (!filter || status === filter);
        row.style.display = match ? '' : 'none';
    });

    rows.sort((a,b) => {
        if(sort==='name_asc'){ return a.cells[0].innerText.localeCompare(b.cells[0].innerText); }
        else if(sort==='name_desc'){ return b.cells[0].innerText.localeCompare(a.cells[0].innerText); }
        else if(sort==='id_asc'){ return a.rowIndex - b.rowIndex; }
        else if(sort==='id_desc'){ return b.rowIndex - a.rowIndex; }
        return 0;
    });

    rows.forEach(r=>tableBody.appendChild(r));
}

searchBox.addEventListener('input', applyFilters);
if(statusFilter) statusFilter.addEventListener('change', applyFilters);
sortBy.addEventListener('change', applyFilters);
</script>
</body>
</html>
