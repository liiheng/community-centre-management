<?php
session_start();
include '../includes/db.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle feedback deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $delete_stmt->bind_param('i', $delete_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['message'] = "Feedback deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting feedback.";
    }
    $delete_stmt->close();
    header('Location: manage_feedback.php');
    exit();
}

// Get filter parameters
$activity_filter = $_GET['activity'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$date_filter = $_GET['date_range'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($activity_filter) {
    $where_conditions[] = "ta.id = ?";
    $params[] = $activity_filter;
    $types .= 'i';
}

if ($rating_filter) {
    $where_conditions[] = "f.rating = ?";
    $params[] = $rating_filter;
    $types .= 'i';
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(f.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "f.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $where_conditions[] = "f.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
}

if ($search) {
    $where_conditions[] = "(ta.title LIKE ? OR u_member.name LIKE ? OR u_organizer.name LIKE ? OR f.comments LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sorting
$order_by = match($sort) {
    'oldest' => 'f.created_at ASC',
    'rating_high' => 'f.rating DESC, f.created_at DESC',
    'rating_low' => 'f.rating ASC, f.created_at DESC',
    'activity' => 'ta.title ASC, f.created_at DESC',
    default => 'f.created_at DESC'
};

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM feedback f
    JOIN temp_activities ta ON f.activity_id = ta.id
    JOIN users u_member ON f.user_id = u_member.id
    JOIN users u_organizer ON ta.organizer_id = u_organizer.id
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);
$count_stmt->close();

// Get feedback data
$feedback_query = "
    SELECT f.*, ta.title as activity_title, ta.start_date,
           u_member.name as member_name, u_organizer.name as organizer_name
    FROM feedback f
    JOIN temp_activities ta ON f.activity_id = ta.id
    JOIN users u_member ON f.user_id = u_member.id
    JOIN users u_organizer ON ta.organizer_id = u_organizer.id
    $where_clause
    ORDER BY $order_by
    LIMIT $limit OFFSET $offset
";

$feedback_stmt = $conn->prepare($feedback_query);
if ($params) {
    $feedback_stmt->bind_param($types, ...$params);
}
$feedback_stmt->execute();
$feedbacks = $feedback_stmt->get_result();

// Get activities for filter dropdown
$activities = $conn->query("
    SELECT DISTINCT ta.id, ta.title, COUNT(f.id) as feedback_count
    FROM temp_activities ta
    LEFT JOIN feedback f ON ta.id = f.activity_id
    GROUP BY ta.id, ta.title
    HAVING feedback_count > 0
    ORDER BY ta.title
");

// Get statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_feedback,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_feedback,
        COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_feedback,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_feedback
    FROM feedback
");
$stats = $stats_query->fetch_assoc();

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .page-header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .page-header h2 { margin: 0 0 10px; color: #333; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 28px; font-weight: bold; display: block; margin-bottom: 5px; }
        .stat-number.total { color: #007bff; }
        .stat-number.rating { color: #ffc107; }
        .stat-number.positive { color: #28a745; }
        .stat-number.negative { color: #dc3545; }
        .stat-number.recent { color: #6f42c1; }
        .stat-label { font-size: 14px; color: #666; text-transform: uppercase; }
        
        .filters { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filters-row { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; min-width: 150px; }
        .filter-group label { margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .filter-btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; height: fit-content; }
        .filter-btn:hover { background: #0056b3; }
        .clear-btn { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .clear-btn:hover { background: #5a6268; }
        
        .feedback-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; color: #333; }
        .feedback-row:hover { background: #f8f9fa; }
        
        .activity-title { font-weight: bold; color: #007bff; }
        .member-name { color: #333; }
        .organizer-name { color: #666; font-size: 14px; }
        .rating-display { color: #ffc107; }
        .feedback-text { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .feedback-text:hover { white-space: normal; overflow: visible; }
        .feedback-date { color: #666; font-size: 14px; }
        
        .action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 12px; }
        .delete-btn { background: #dc3545; color: white; }
        .delete-btn:hover { background: #c82333; }
        
        .pagination { display: flex; justify-content: center; gap: 10px; margin: 20px 0; }
        .pagination a, .pagination span { padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #007bff; }
        .pagination .current { background: #007bff; color: white; border-color: #007bff; }
        .pagination a:hover { background: #f8f9fa; }
        
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #ddd; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
        .modal-content { background: white; margin: 5% auto; padding: 20px; width: 80%; max-width: 600px; border-radius: 10px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close { font-size: 28px; cursor: pointer; color: #999; }
        .close:hover { color: #000; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-comments"></i> Feedback Management</h2>
            <p>Monitor and manage all activity feedback from community members</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number total"><?php echo number_format($stats['total_feedback']); ?></span>
                <span class="stat-label">Total Feedback</span>
            </div>
            <div class="stat-card">
                <span class="stat-number rating"><?php echo number_format($stats['avg_rating'], 1); ?></span>
                <span class="stat-label">Average Rating</span>
            </div>
            <div class="stat-card">
                <span class="stat-number positive"><?php echo number_format($stats['positive_feedback']); ?></span>
                <span class="stat-label">Positive (4-5★)</span>
            </div>
            <div class="stat-card">
                <span class="stat-number negative"><?php echo number_format($stats['negative_feedback']); ?></span>
                <span class="stat-label">Critical (1-2★)</span>
            </div>
            <div class="stat-card">
                <span class="stat-number recent"><?php echo number_format($stats['recent_feedback']); ?></span>
                <span class="stat-label">This Week</span>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Activity</label>
                    <select name="activity">
                        <option value="">All Activities</option>
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <option value="<?php echo $activity['id']; ?>" <?php echo $activity_filter == $activity['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($activity['title']); ?> (<?php echo $activity['feedback_count']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Rating</label>
                    <select name="rating">
                        <option value="">All Ratings</option>
                        <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date Range</label>
                    <select name="date_range">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Activity, member, organizer..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Sort By</label>
                    <select name="sort">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="rating_high" <?php echo $sort == 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                        <option value="rating_low" <?php echo $sort == 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                        <option value="activity" <?php echo $sort == 'activity' ? 'selected' : ''; ?>>Activity Name</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="manage_feedback.php" class="clear-btn">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>
        
        <div class="feedback-table">
            <?php if ($feedbacks->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Member</th>
                            <th>Organizer</th>
                            <th>Rating</th>
                            <th>Comments</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                            <tr class="feedback-row">
                                <td>
                                    <div class="activity-title"><?php echo htmlspecialchars($feedback['activity_title']); ?></div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo date('M j, Y', strtotime($feedback['start_date'])); ?>
                                    </div>
                                </td>
                                <td class="member-name"><?php echo htmlspecialchars($feedback['member_name']); ?></td>
                                <td class="organizer-name"><?php echo htmlspecialchars($feedback['organizer_name']); ?></td>
                                <td>
                                    <div class="rating-display">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $feedback['rating'] ? '' : ' text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;"><?php echo $feedback['rating']; ?>/5</div>
                                </td>
                                <td>
                                    <?php if ($feedback['comments']): ?>
                                        <div class="feedback-text" title="<?php echo htmlspecialchars($feedback['comments']); ?>">
                                            <?php echo htmlspecialchars($feedback['comments']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">No comments</span>
                                    <?php endif; ?>
                                </td>
                                <td class="feedback-date">
                                    <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="#" onclick="showFeedbackDetail(<?php echo htmlspecialchars(json_encode($feedback)); ?>)" class="action-btn" style="background: #007bff; color: white; margin-right: 5px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?delete_id=<?php echo $feedback['id']; ?>" 
                                       class="action-btn delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this feedback? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No Feedback Found</h3>
                    <p>No feedback matches your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Feedback Detail Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Feedback Details</h3>
                <span class="close" onclick="closeFeedbackModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        function showFeedbackDetail(feedback) {
            const modal = document.getElementById('feedbackModal');
            const modalBody = document.getElementById('modalBody');
            
            const stars = Array.from({length: 5}, (_, i) => 
                `<i class="fas fa-star${i < feedback.rating ? '' : ' text-muted'}"></i>`
            ).join('');
            
            modalBody.innerHTML = `
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #007bff; margin-bottom: 10px;">${feedback.activity_title}</h4>
                    <p style="color: #666; margin-bottom: 15px;">
                        <strong>Activity Date:</strong> ${new Date(feedback.start_date).toLocaleDateString('en-US', { 
                            year: 'numeric', month: 'long', day: 'numeric' 
                        })}
                    </p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <strong>Member:</strong><br>
                        <span style="color: #333;">${feedback.member_name}</span>
                    </div>
                    <div>
                        <strong>Organizer:</strong><br>
                        <span style="color: #666;">${feedback.organizer_name}</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <strong>Rating:</strong><br>
                    <div style="color: #ffc107; font-size: 20px; margin: 5px 0;">
                        ${stars}
                        <span style="margin-left: 10px; color: #333; font-size: 16px;">${feedback.rating}/5</span>
                    </div>
                </div>
                
                ${feedback.comments ? `
                    <div style="margin-bottom: 20px;">
                        <strong>Comments:</strong><br>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; line-height: 1.6;">
                            ${feedback.comments.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                ` : ''}
                
                <div style="color: #666; font-size: 14px; border-top: 1px solid #eee; padding-top: 15px;">
                    <strong>Submitted:</strong> ${new Date(feedback.created_at).toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit' 
                    })}
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function closeFeedbackModal() {
            document.getElementById('feedbackModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('feedbackModal');
            if (event.target == modal) {
                closeFeedbackModal();
            }
        }
        
        // Add muted text style
        const style = document.createElement('style');
        style.textContent = '.text-muted { color: #ddd !important; }';
        document.head.appendChild(style);
    </script>
</body>
</html>