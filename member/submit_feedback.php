<?php
session_start();
include '../includes/db.php';

// Only members can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;

// Check if activity exists and user is registered
if ($activity_id > 0) {
    $check_query = $conn->prepare("
        SELECT ta.*, p.user_id as is_participant, f.id as feedback_id
        FROM temp_activities ta
        LEFT JOIN participants p ON ta.id = p.activity_id AND p.user_id = ?
        LEFT JOIN feedback f ON ta.id = f.activity_id AND f.user_id = ?
        WHERE ta.id = ? AND ta.status = 'approved'
    ");
    $check_query->bind_param('iii', $user_id, $user_id, $activity_id);
    $check_query->execute();
    $activity = $check_query->get_result()->fetch_assoc();
    $check_query->close();

    if (!$activity) {
        header('Location: ../index.php');
        exit();
    }

    // Check if user is registered as participant
    if (!$activity['is_participant']) {
        $error = "You must be registered as a participant for this activity to submit feedback.";
    }

    // Check if feedback already submitted
    if ($activity['feedback_id']) {
        $error = "You have already submitted feedback for this activity.";
    }
}

$message = "";
$success = false;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($error)) {
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments']);

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $message = "Please provide a valid rating between 1 and 5 stars.";
    } else {
        // Insert feedback
        $stmt = $conn->prepare("INSERT INTO feedback (activity_id, user_id, rating, comments) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiis', $activity_id, $user_id, $rating, $comments);
        
        if ($stmt->execute()) {
            $success = true;
            $message = "Thank you for your feedback! Your review has been submitted successfully.";
        } else {
            $message = "Error submitting feedback. Please try again.";
        }
        $stmt->close();
    }
}

// Get user's participated activities that can be reviewed
$activities_query = $conn->prepare("
    SELECT ta.id, ta.title, ta.start_date, ta.end_date, 
           p.user_id as is_participant, f.id as feedback_id,
           u.name as organizer_name
    FROM temp_activities ta
    JOIN participants p ON ta.id = p.activity_id
    JOIN users u ON ta.organizer_id = u.id
    LEFT JOIN feedback f ON ta.id = f.activity_id AND f.user_id = p.user_id
    WHERE p.user_id = ? AND ta.status = 'approved' AND ta.end_date < NOW()
    ORDER BY ta.end_date DESC
");
$activities_query->bind_param('i', $user_id);
$activities_query->execute();
$activities = $activities_query->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Submit Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .back-btn:hover { background: #0056b3; }
        
        .feedback-form { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .feedback-form h2 { margin-top: 0; color: #333; text-align: center; }
        
        .activity-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        .activity-info h3 { margin-top: 0; color: #007bff; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        
        .star-rating { display: flex; gap: 5px; margin-bottom: 10px; }
        .star { font-size: 30px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .star:hover, .star.active { color: #ffc107; }
        
        textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; resize: vertical; min-height: 100px; font-family: Arial, sans-serif; }
        .submit-btn { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .submit-btn:hover { background: #218838; }
        .submit-btn:disabled { background: #6c757d; cursor: not-allowed; }
        
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .activities-list { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .activities-list h3 { margin-top: 0; color: #333; }
        .activity-item { padding: 15px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: between; align-items: center; }
        .activity-item:hover { background: #f8f9fa; }
        .activity-details { flex: 1; }
        .activity-title { font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .activity-meta { font-size: 14px; color: #666; }
        .feedback-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .feedback-submitted { background: #d4edda; color: #155724; }
        .feedback-pending { background: #fff3cd; color: #856404; }
        .feedback-btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; }
        .feedback-btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Activities</a>
        
        <?php if ($activity_id > 0): ?>
            <div class="feedback-form">
                <h2><i class="fas fa-star"></i> Submit Feedback</h2>
                
                <?php if (isset($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <a href="submit_feedback.php" class="feedback-btn">View All Activities</a>
                <?php elseif ($message): ?>
                    <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php if ($success): ?>
                        <a href="submit_feedback.php" class="feedback-btn">View All Activities</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="activity-info">
                        <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                        <p><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($activity['start_date'])); ?> - <?php echo date('g:i A', strtotime($activity['end_date'])); ?></p>
                    </div>
                    
                    <form method="POST" id="feedbackForm">
                        <div class="form-group">
                            <label>Rating *</label>
                            <div class="star-rating" id="starRating">
                                <span class="star" data-rating="1"><i class="fas fa-star"></i></span>
                                <span class="star" data-rating="2"><i class="fas fa-star"></i></span>
                                <span class="star" data-rating="3"><i class="fas fa-star"></i></span>
                                <span class="star" data-rating="4"><i class="fas fa-star"></i></span>
                                <span class="star" data-rating="5"><i class="fas fa-star"></i></span>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="comments">Comments (Optional)</label>
                            <textarea name="comments" id="comments" placeholder="Share your thoughts about this activity..."></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn" id="submitBtn" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="activities-list">
                <h3><i class="fas fa-clipboard-list"></i> Your Completed Activities</h3>
                
                <?php if ($activities->num_rows > 0): ?>
                    <?php while ($activity = $activities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-details">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                <div class="activity-meta">
                                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($activity['start_date'])); ?> |
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['organizer_name']); ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($activity['feedback_id']): ?>
                                    <span class="feedback-status feedback-submitted">Feedback Submitted</span>
                                <?php else: ?>
                                    <span class="feedback-status feedback-pending">Feedback Pending</span>
                                    <a href="submit_feedback.php?activity_id=<?php echo $activity['id']; ?>" class="feedback-btn">
                                        <i class="fas fa-star"></i> Submit Feedback
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="message">
                        <i class="fas fa-info-circle"></i> You haven't participated in any activities yet. Join activities to leave feedback!
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('ratingValue');
        const submitBtn = document.getElementById('submitBtn');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                ratingValue.value = rating;
                updateStars(rating);
                submitBtn.disabled = false;
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = this.dataset.rating;
                updateStars(rating);
            });
        });
        
        document.getElementById('starRating').addEventListener('mouseleave', function() {
            updateStars(ratingValue.value || 0);
        });
        
        function updateStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>