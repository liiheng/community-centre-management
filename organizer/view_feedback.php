<?php
session_start();
include '../includes/db.php';

// Only organizers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get organizer's activities with feedback stats
$activities_query = $conn->prepare("
    SELECT ta.id, ta.title, ta.start_date, ta.end_date, ta.status,
           COUNT(DISTINCT p.id) as total_participants,
           COUNT(DISTINCT f.id) as feedback_count,
           COALESCE(AVG(f.rating), 0) as avg_rating
    FROM temp_activities ta
    LEFT JOIN participants p ON ta.id = p.activity_id
    LEFT JOIN feedback f ON ta.id = f.activity_id
    WHERE ta.organizer_id = ?
    GROUP BY ta.id
    ORDER BY ta.start_date DESC
");
$activities_query->bind_param('i', $user_id);
$activities_query->execute();
$activities = $activities_query->get_result();

// Get specific activity feedback if requested
$selected_activity = null;
$feedbacks = null;
if (isset($_GET['activity_id'])) {
    $activity_id = intval($_GET['activity_id']);
    
    // Verify this activity belongs to the organizer
    $verify_query = $conn->prepare("SELECT * FROM temp_activities WHERE id = ? AND organizer_id = ?");
    $verify_query->bind_param('ii', $activity_id, $user_id);
    $verify_query->execute();
    $selected_activity = $verify_query->get_result()->fetch_assoc();
    $verify_query->close();
    
    if ($selected_activity) {
        // Get all feedback for this activity
        $feedback_query = $conn->prepare("
            SELECT f.*, u.name as user_name, f.created_at
            FROM feedback f
            JOIN users u ON f.user_id = u.id
            WHERE f.activity_id = ?
            ORDER BY f.created_at DESC
        ");
        $feedback_query->bind_param('i', $activity_id);
        $feedback_query->execute();
        $feedbacks = $feedback_query->get_result();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Activity Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .back-btn:hover { background: #0056b3; }
        
        .page-header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center; }
        .page-header h2 { margin: 0; color: #333; }
        
        .activities-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .activity-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .activity-card:hover { transform: translateY(-2px); }
        .activity-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .activity-meta { color: #666; font-size: 14px; margin-bottom: 15px; }
        .activity-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
        .stat-item { text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; display: block; }
        .stat-number.rating { color: #ffc107; }
        .stat-number.registrations { color: #28a745; }
        .stat-number.feedback { color: #007bff; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .view-feedback-btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: bold; }
        .view-feedback-btn:hover { background: #0056b3; }
        .no-feedback { background: #6c757d; }
        
        .feedback-detail { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .feedback-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .feedback-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        
        .feedback-list { space-y: 15px; }
        .feedback-item { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #007bff; }
        .feedback-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .reviewer-name { font-weight: bold; color: #333; }
        .review-date { color: #666; font-size: 14px; }
        .star-display { color: #ffc107; margin-bottom: 10px; }
        .feedback-comments { color: #555; line-height: 1.6; }
        
        .empty-state { text-align: center; padding: 40px; color: #666; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #ddd; }
        
        .rating-breakdown { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .rating-bar { display: flex; align-items: center; margin-bottom: 10px; }
        .rating-label { width: 60px; font-size: 14px; }
        .rating-progress { flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; margin: 0 15px; overflow: hidden; }
        .rating-fill { height: 100%; background: #ffc107; transition: width 0.3s; }
        .rating-count { font-size: 14px; color: #666; width: 40px; text-align: right; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="page-header">
            <h2><i class="fas fa-comments"></i> Activity Feedback</h2>
            <p>View and analyze feedback from participants in your activities</p>
        </div>
        
        <?php if (!$selected_activity): ?>
            <div class="activities-grid">
                <?php while ($activity = $activities->fetch_assoc()): ?>
                    <div class="activity-card">
                        <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                        <div class="activity-meta">
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($activity['start_date'])); ?> |
                            <i class="fas fa-flag"></i> <?php echo ucfirst($activity['status']); ?>
                        </div>
                        
                        <div class="activity-stats">
                            <div class="stat-item">
                                <span class="stat-number rating">
                                    <?php echo $activity['avg_rating'] > 0 ? number_format($activity['avg_rating'], 1) : 'N/A'; ?>
                                </span>
                                <span class="stat-label">Avg Rating</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number registrations"><?php echo $activity['total_participants']; ?></span>
                                <span class="stat-label">Participants</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number feedback"><?php echo $activity['feedback_count']; ?></span>
                                <span class="stat-label">Feedback</span>
                            </div>
                        </div>
                        
                        <?php if ($activity['feedback_count'] > 0): ?>
                            <a href="view_feedback.php?activity_id=<?php echo $activity['id']; ?>" class="view-feedback-btn">
                                <i class="fas fa-eye"></i> View Feedback (<?php echo $activity['feedback_count']; ?>)
                            </a>
                        <?php else: ?>
                            <span class="view-feedback-btn no-feedback">
                                <i class="fas fa-comment-slash"></i> No Feedback Yet
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($activities->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Activities Found</h3>
                    <p>You haven't created any activities yet. <a href="create_activity.php">Create your first activity</a> to start receiving feedback!</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="feedback-detail">
                <div class="feedback-header">
                    <div>
                        <h3><?php echo htmlspecialchars($selected_activity['title']); ?></h3>
                        <p class="activity-meta">
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y g:i A', strtotime($selected_activity['start_date'])); ?>
                        </p>
                    </div>
                    <a href="view_feedback.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Activities
                    </a>
                </div>
                
                <?php if ($feedbacks->num_rows > 0): ?>
                    <?php
                    // Calculate rating breakdown
                    $rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
                    $total_feedback = 0;
                    $total_rating = 0;
                    $feedback_array = [];
                    
                    while ($feedback = $feedbacks->fetch_assoc()) {
                        $feedback_array[] = $feedback;
                        $rating_counts[$feedback['rating']]++;
                        $total_feedback++;
                        $total_rating += $feedback['rating'];
                    }
                    $avg_rating = $total_feedback > 0 ? $total_rating / $total_feedback : 0;
                    ?>
                    
                    <div class="feedback-summary">
                        <div class="stat-item">
                            <span class="stat-number rating"><?php echo number_format($avg_rating, 1); ?></span>
                            <span class="stat-label">Average Rating</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number feedback"><?php echo $total_feedback; ?></span>
                            <span class="stat-label">Total Reviews</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number rating">
                                <?php echo $rating_counts[5] + $rating_counts[4]; ?>
                            </span>
                            <span class="stat-label">Positive (4-5★)</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #dc3545;">
                                <?php echo $rating_counts[1] + $rating_counts[2]; ?>
                            </span>
                            <span class="stat-label">Critical (1-2★)</span>
                        </div>
                    </div>
                    
                    <div class="rating-breakdown">
                        <h4>Rating Breakdown</h4>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="rating-bar">
                                <div class="rating-label"><?php echo $i; ?> ★</div>
                                <div class="rating-progress">
                                    <div class="rating-fill" style="width: <?php echo $total_feedback > 0 ? ($rating_counts[$i] / $total_feedback) * 100 : 0; ?>%"></div>
                                </div>
                                <div class="rating-count"><?php echo $rating_counts[$i]; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="feedback-list">
                        <h4>Individual Reviews</h4>
                        <?php foreach ($feedback_array as $feedback): ?>
                            <div class="feedback-item">
                                <div class="feedback-item-header">
                                    <span class="reviewer-name">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['user_name']); ?>
                                    </span>
                                    <span class="review-date">
                                        <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="star-display">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $feedback['rating'] ? '' : ' text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; font-weight: bold;"><?php echo $feedback['rating']; ?>/5</span>
                                </div>
                                <?php if (!empty($feedback['comments'])): ?>
                                    <div class="feedback-comments">
                                        <?php echo nl2br(htmlspecialchars($feedback['comments'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No Feedback Yet</h3>
                        <p>This activity hasn't received any feedback from participants yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .text-muted { color: #ddd !important; }
    </style>
</body>
</html>