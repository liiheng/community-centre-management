<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - Community Centre</title>
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

<!-- Forgot Password Box -->
<div class="login-container">
    <div class="login-box">
        <h1>Forgot Password</h1>
        <p>Enter your registered email address and we'll send you a link to reset your password.</p>

        <form method="post" action="">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit">Send Reset Link</button>
        </form>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

<script src="js/background.js"></script>
</body>
</html>
