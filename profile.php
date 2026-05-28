<?php
session_start();
include 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$message = '';

// fetch current user
$stmt = $conn->prepare("SELECT id, name, email, role, profile_pic, dob, gender, phone, address FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    // handle profile picture upload
    $profile_path = $user['profile_pic'];
    if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . '/uploads/profile_pics';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // save old profile for possible deletion
        $oldProfile = $user['profile_pic'];

        $origName = basename($_FILES['profile_pic']['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $safeName = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $target = $uploadsDir . DIRECTORY_SEPARATOR . $safeName;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
            // resolve real paths to avoid mixed-slash and traversal issues
            $profilePicsDirReal = realpath($uploadsDir);

            if (!empty($oldProfile) && $profilePicsDirReal !== false) {
                // make old profile path relative to project and normalize separators
                $oldRel = ltrim($oldProfile, '/\\');
                $oldFileCandidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldRel);
                $oldFileReal = realpath($oldFileCandidate);

                // only unlink if the resolved old file exists and is inside the profile_pics directory
                if ($oldFileReal && strpos($oldFileReal, $profilePicsDirReal) === 0 && is_file($oldFileReal)) {
                    @unlink($oldFileReal);
                }
            }

            $profile_path = 'uploads/profile_pics/' . $safeName;
        }
    }

    // handle password change
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $params = [];
    $types = '';
    $sql = "UPDATE users SET name = ?, email = ?, profile_pic = ?, dob = ?, gender = ?, phone = ?, address = ?";
    $params[] = $name;
    $params[] = $email;
    $params[] = $profile_path;
    $params[] = $dob;
    $params[] = $gender;
    $params[] = $phone;
    $params[] = $address;
    $types = 'sssssss';

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password = ?";
        $params[] = $hashed;
        $types .= 's';
    }

    $sql .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // build references for bind_param
        $bind_names[] = $types;
        for ($i=0; $i<count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);

        if ($stmt->execute()) {
            $message = 'Profile updated.';
            $_SESSION['name'] = $name;
            // refresh user data
            $user['name'] = $name;
            $user['email'] = $email;
            $user['profile_pic'] = $profile_path;
            $user['dob'] = $dob;
            $user['gender'] = $gender;
            $user['phone'] = $phone;
            $user['address'] = $address;
        } else {
            $message = 'Update failed: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = 'Failed to prepare statement.';
    }
}
?>
<!doctype html>
<html>
<head>
    <title>Profile Settings</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f6f7f9; margin:0; }
        .profile-container { max-width:900px; margin:90px auto; padding:26px; background:#fff; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
        .profile-grid { display:grid; grid-template-columns: 240px 1fr; gap:24px; align-items:start }
        .left-card { text-align:center }
        label { display:block; margin-top:12px; font-weight:600 }
        input[type=text], input[type=email], input[type=password], input[type=date], textarea, select { width:100%; padding:10px; margin-top:8px; border:1px solid #ddd; border-radius:6px; font-size:14px }
        input[type=submit] { margin-top:18px; padding:10px 16px; background:#007bff; color:#fff; border:none; border-radius:6px; cursor:pointer }
        .profile-preview { display:flex; gap:16px; align-items:center; justify-content:center }
        img.profile-big { width:140px; height:140px; border-radius:50%; object-fit:cover; }
        .section { margin-bottom:12px }
        .hint { font-size:13px; color:#666; margin-top:6px }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="profile-container">
    <h2>Profile Settings</h2>
    <?php if ($message): ?><p style="color:green"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="profile-grid">
            <div class="left-card">
                <div class="profile-preview">
                    <?php if (!empty($user['profile_pic'])): ?>
                        <img class="profile-big" src="/community_management/<?php echo ltrim($user['profile_pic'], '/'); ?>" alt="Profile">
                    <?php else: ?>
                        <img class="profile-big" src="/community_management/uploads/default_profile.png" alt="Profile">
                    <?php endif; ?>
                </div>
                <div class="section">
                    <label>Change Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*">
                    <div class="hint">Recommended: square image for best display.</div>
                </div>
            </div>
            <div>
                <label>Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="password">

                <label>Date of Birth</label>
                <input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob']); ?>">

                <label>Gender</label>
                <select name="gender">
                    <option value="">Select</option>
                    <option value="Male" <?php echo ($user['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($user['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>

                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">

                <label>Address</label>
                <textarea name="address"><?php echo htmlspecialchars($user['address']); ?></textarea>

                <input type="submit" value="Save Changes">
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>