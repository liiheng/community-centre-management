<?php
include("includes/db.php");
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm  = $_POST["confirm_password"];
    $role     = $_POST["role"];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashed, $role);
        
        if ($stmt->execute()) {
            $success = "Account registered successfully! <a href='login.php'>Login now</a>";
        } else {
            $error = "Registration failed. Email may already be in use.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Community Centre</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<!-- Background Slideshow -->
<div class="background-slideshow">
    <div class="bg-slide active" style="background-image: url('images/bg1.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg2.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg3.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg4.jpg');"></div>
</div>

<!-- Registration Form -->
<div class="login-container">
    <div class="login-box">
        <h1>Register</h1>
        <p>Create your account to join our community events!</p>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <label>Role:</label>
                <select name="role" required>
                    <option value="member">Member</option>
                    <option value="organizer">Organizer</option>
                </select>
            </div>
            <button type="submit">Register</button>
        </form>

        <div class="links">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<script src="js/background.js"></script>
</body>
</html>
