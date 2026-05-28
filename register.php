<?php
include("includes/db.php");
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name      = trim($_POST["name"]);
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $confirm   = $_POST["confirm_password"];
    $role      = $_POST["role"];
    $dob       = $_POST["dob"] ?? null;
    $gender    = $_POST["gender"] ?? null;
    $phone     = $_POST["phone"] ?? null;
    $address   = $_POST["address"] ?? null;
    $profile_pic = "uploads/default_profile.png"; // default profile picture

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $error = "Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $status = $role === 'organizer' ? 'pending' : 'approved';

            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, dob, gender, phone, address, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $name, $email, $hashed, $role, $status, $dob, $gender, $phone, $address, $profile_pic);

            if ($stmt->execute()) {
                if ($role === 'member') {
                    $success = "Member account created successfully! <a href='login.php'>Login now</a>";
                } else {
                    $success = "Organizer account registered! Your account is pending admin approval.";
                }
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Community Centre</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin:0; font-family: Arial, sans-serif; }
        .background-slideshow { position: fixed; top:0; left:0; width:100%; height:100%; overflow:hidden; z-index:-1; }
        .bg-slide { position:absolute; width:100%; height:100%; background-size:cover; background-position:center; opacity:0; transition: opacity 1s ease; }
        .bg-slide.active { opacity:1; }

        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .register-box {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 10px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }

        .register-box h1 { text-align:center; margin-bottom:10px; }
        .register-box p { text-align:center; margin-bottom:20px; color:#555; }

        .form-group { margin-bottom:15px; }
        .form-group label { display:block; margin-bottom:5px; font-weight:bold; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; font-size:14px;
        }

        .form-group textarea { resize:none; }

        button {
            width:100%; padding:12px; background:#007bff; color:#fff; border:none; border-radius:5px;
            font-size:16px; cursor:pointer;
            transition: background 0.3s ease;
        }
        button:hover { background:#0056b3; }

        .success { background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:5px; }
        .error { background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:5px; }

        .links { text-align:center; margin-top:15px; }
        .links a { color:#007bff; text-decoration:none; }
        .links a:hover { text-decoration:underline; }

        @media(max-width:500px){
            .register-box { padding:20px; }
        }
    </style>
</head>
<body>

<div class="background-slideshow">
    <div class="bg-slide active" style="background-image: url('images/bg1.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg2.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg3.jpg');"></div>
    <div class="bg-slide" style="background-image: url('images/bg4.jpg');"></div>
</div>

<div class="register-container">
    <div class="register-box">
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
                <label>Date of Birth:</label>
                <input type="date" name="dob" required>
            </div>
            <div class="form-group">
                <label>Gender:</label>
                <select name="gender" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" name="phone" required>
            </div>
            <div class="form-group">
                <label>Address:</label>
                <textarea name="address" rows="3" required></textarea>
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

<script>
    // Simple background slideshow
    let slides = document.querySelectorAll('.bg-slide');
    let index = 0;
    setInterval(() => {
        slides[index].classList.remove('active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('active');
    }, 5000);
</script>

</body>
</html>
