<?php
session_start();
include("includes/db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_or_username = $_POST['email_or_username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR name = ?");
    $stmt->bind_param("ss", $email_or_username, $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Community Centre</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="background-slideshow">
    <div class="bg-slide active" style="background-image: url('images/bg1.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg2.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg3.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg4.jpg');"></div>
</div>
<div class="login-container">
    <div class="login-box">
        <h1>Welcome to the Community Activities Centre</h1>
        <p>Your place to connect, create, and participate in community life.</p>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label>Email or Username:</label>
                <input type="text" name="email_or_username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>

        <div class="links">
            <a href="register.php">Register Account</a> |
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
</div>

<script src="js/background.js"></script>
</body>
</html>
