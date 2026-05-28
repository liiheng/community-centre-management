<?php
session_start();
require_once "includes/db.php"; // Fixed path (same directory as feedback.php)
require_once "includes/header.php";

// Ensure only logged-in users (organizers/members) can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['organizer', 'member'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $feedback_type = $_POST['feedback_type'];
    $feedback_text = trim($_POST['feedback']);

    if (!empty($subject) && !empty($feedback_text) && !empty($feedback_type)) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, subject, feedback_type, feedback_text, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("isss", $user_id, $subject, $feedback_type, $feedback_text);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Feedback submitted successfully!</div>";
        } else {
            $message = "<div class='alert error'>Error: Could not submit feedback.</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert error'>Please fill in all fields.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f7;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 70%;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        label {
            font-weight: bold;
            color: #444;
        }
        input[type="text"], textarea, select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        .feedback-type {
            display: flex;
            gap: 20px;
            margin: 10px 0;
        }
        .type-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .type-option input {
            margin: 0;
        }
        button {
            padding: 12px;
            background: #007bff;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #0056b3;
        }
        .alert {
            padding: 12px;
            border-radius: 5px;
            text-align: center;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fa-solid fa-comment-dots"></i> Submit Feedback to Admin</h2>
        <?php echo $message; ?>
        <form method="post" action="">
            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject" required>

            <label>Type of Feedback</label>
            <div class="feedback-type">
                <label class="type-option">
                    <input type="radio" name="feedback_type" value="Suggestion" required>
                    <i class="fa-solid fa-lightbulb"></i> Suggestion
                </label>
                <label class="type-option">
                    <input type="radio" name="feedback_type" value="Complaint" required>
                    <i class="fa-solid fa-triangle-exclamation"></i> Complaint
                </label>
                <label class="type-option">
                    <input type="radio" name="feedback_type" value="Question" required>
                    <i class="fa-solid fa-circle-question"></i> Question
                </label>
            </div>

            <label for="feedback">Feedback</label>
            <textarea name="feedback" id="feedback" required></textarea>

            <button type="submit"><i class="fa-solid fa-paper-plane"></i> Submit Feedback</button>
        </form>
    </div>
</body>
</html>
