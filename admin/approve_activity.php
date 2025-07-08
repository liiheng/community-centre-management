<?php
session_start();
include '../includes/db.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle filters and search
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$conditions = "1=1";
$params = [];
if ($status_filter) {
    $conditions .= " AND t.status = ?";
    $params[] = $status_filter;
}
if ($search) {
    $conditions .= " AND (t.title LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total rows for pagination
$stmt = $conn->prepare("SELECT COUNT(*) FROM temp_activities t JOIN users u ON t.organizer_id = u.id WHERE $conditions");
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($total_rows);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_rows / $limit);

// Fetch filtered and paginated data
$query = "SELECT t.*, u.name AS organizer_name FROM temp_activities t JOIN users u ON t.organizer_id = u.id WHERE $conditions ORDER BY t.start_date DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Handle deletion of activity including the poster file
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Get poster path from database
    $poster_query = $conn->prepare("SELECT poster FROM temp_activities WHERE id = ?");
    $poster_query->bind_param('i', $delete_id);
    $poster_query->execute();
    $poster_query->bind_result($poster_path);
    $poster_query->fetch();
    $poster_query->close();

    // Delete the poster file if it exists
    if (!empty($poster_path)) {
        $full_path = "../" . $poster_path; // Assuming poster_path is like 'uploads/image.jpg'
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }

    // Delete the activity from database
    $delete_stmt = $conn->prepare("DELETE FROM temp_activities WHERE id = ?");
    $delete_stmt->bind_param('i', $delete_id);
    if ($delete_stmt->execute()) {
        header("Location: approve_activity.php?message=Activity+deleted+successfully");
        exit();
    } else {
        $error_message = "Error deleting activity.";
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Approve Activities</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
        }
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: white;
            background: #007bff;
            padding: 6px 12px;
            border-radius: 4px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            margin-bottom: 20px;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f4f4f4;
        }
        .pending { background-color: #fff3cd; }
        .approved { background-color: #d4edda; }
        .rejected { background-color: #f8d7da; }
        .pagination a {
            margin: 0 5px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a.current {
            font-weight: bold;
            color: black;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-button">← Back</a>
    <h2>Approve Activities</h2>

    <form method="GET">
        <input type="text" name="search" placeholder="Search by title or organizer" value="<?php echo htmlspecialchars($search); ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
            <option value="approved" <?php if ($status_filter == 'approved') echo 'selected'; ?>>Approved</option>
            <option value="rejected" <?php if ($status_filter == 'rejected') echo 'selected'; ?>>Rejected</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <table>
        <tr>
            <th>Title</th>
            <th>Organizer</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="<?php echo $row['status']; ?>">
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['organizer_name']); ?></td>
                <td><?php echo $row['start_date']; ?></td>
                <td><?php echo $row['end_date']; ?></td>
                <td><?php echo ucfirst($row['status']); ?></td>
                <td>
                    <a href="set_status.php?id=<?php echo $row['id']; ?>&status=approved">Approve</a> | 
                    <a href="set_status.php?id=<?php echo $row['id']; ?>&status=rejected">Reject</a> |
                    <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this activity?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="<?php if ($i == $page) echo 'current'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
</body>
</html>
