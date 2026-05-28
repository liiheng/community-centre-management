<?php
if (session_status() == PHP_SESSION_NONE) session_start();
// include DB using absolute path relative to this file
include __DIR__ . '/db.php';

$profile_pic = '';
$display_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $u_stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ? LIMIT 1");
    $u_stmt->bind_param('i', $uid);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    if ($u_row = $u_res->fetch_assoc()) {
        if (!empty($u_row['name'])) $display_name = $u_row['name'];
        if (!empty($u_row['profile_pic'])) $profile_pic = $u_row['profile_pic'];
    }
    $u_stmt->close();
}
?>
<style>
/* Strongly-scoped header and side menu styles to avoid being overridden by page CSS */
#cm-header-top { position: fixed; top: 0; left: 0; right: 0; height: 56px; background: #ffffff; border-bottom: 1px solid #e6e6e6; display: flex; align-items: center; padding: 0 12px; z-index: 2000; box-sizing: border-box; }
#cm-header-top * { box-sizing: border-box; }
#cm-header-top .cm-left-group { display: flex; align-items: center; gap: 8px; flex: 0 1 auto; }
#cm-menu-toggle { display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 44px !important; height: 38px !important; line-height: 1 !important; font-size: 20px !important; color: #222 !important; background: transparent !important; border: none !important; cursor: pointer !important; padding: 6px !important; border-radius: 6px !important; text-decoration: none !important; }
#cm-menu-toggle:hover { background: rgba(0,0,0,0.04) !important; }
#cm-header-title { font-weight: 600 !important; color: #222 !important; font-size: 18px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 520px; }
#cm-header-user { font-size: 14px; color:#444; margin-left: auto; display:flex; align-items:center; gap:8px }
#cm-header-user img.profile-small { width:34px; height:34px; border-radius:50%; object-fit:cover; }
/* side menu */
#cm-side-menu { position: fixed; top: 56px; left: -320px; width: 320px; bottom: 0; background: #fff; border-right: 1px solid #e0e0e0; box-shadow: 2px 0 8px rgba(0,0,0,0.06); transition: left 0.22s ease; z-index: 1999; padding: 12px; box-sizing: border-box; overflow-y: auto; }
#cm-side-menu.open { left: 0; }
#cm-side-menu ul { list-style: none; padding: 0; margin: 0; }
#cm-side-menu li { margin: 6px 0; }
#cm-side-menu a { text-decoration: none; color: #333; display: block; padding: 10px 8px; border-radius: 6px; }
#cm-side-menu a:hover { background: #f6f6f6 }
/* unified big profile area for all roles */
#cm-side-profile { text-align: center; padding: 16px 6px 18px 6px; border-bottom: 1px solid #f0f0f0; margin-bottom: 12px; }
#cm-side-profile img.profile-big { width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
#cm-side-profile .name { margin-top:8px; font-weight:600; }
#cm-side-profile .role { font-size:13px; color:#666; margin-top:4px }
/* push page content slightly to avoid header overlap */
.cm-body-offset { padding-top: 66px !important; }
</style>

<div id="cm-header-top">
    <div class="cm-left-group">
        <button id="cm-menu-toggle" aria-label="Open menu">&#9776;</button>
        <div id="cm-header-title">Community Management</div>
    </div>
    <div id="cm-header-user">
        <?php if ($profile_pic): ?>
            <img class="profile-small" src="/community_management/<?php echo ltrim($profile_pic, '/'); ?>" alt="Profile">
        <?php endif; ?>
        <span><?php echo htmlspecialchars($display_name); ?></span>
    </div>
</div>

<nav id="cm-side-menu" aria-hidden="true">
    <div id="cm-side-profile">
        <?php if ($profile_pic): ?>
            <img class="profile-big" src="/community_management/<?php echo ltrim($profile_pic, '/'); ?>" alt="Profile">
        <?php else: ?>
            <img class="profile-big" src="/community_management/uploads/default_profile.png" alt="Profile">
        <?php endif; ?>
        <div class="name"><?php echo htmlspecialchars($display_name); ?></div>
        <div class="role"><?php echo htmlspecialchars(ucfirst($role)); ?></div>
    </div>
    <ul>
        <li><a href="/community_management/index.php">Main Menu</a></li>
        <?php if ($role === 'member'): ?>
            <li><a href="/community_management/member/view_activity.php">View and Register Activities</a></li>
            <li><a href="/community_management/member/joined_activity.php">View Joined Activities</a></li>
            <!--li><a href="/community_management/announcement.php">Announcements</a></li>-->
            <li><a href="/community_management/member/submit_feedback.php">Submit Feedback</a></li>
        <?php elseif ($role === 'admin'): ?>
            <li><a href="/community_management/admin/approve_activity.php">Manage Activities</a></li>
            <li><a href="/community_management/admin/manage_accounts.php">Manage Accounts</a></li>
            <li><a href="/community_management/admin/manage_feedback.php">Manage Feedback</a></li>
            <!--li><a href="/community_management/announcement.php">Announcements</a></li>-->
        <?php elseif ($role === 'organizer'): ?>
            <li><a href="/community_management/organizer/create_activity.php">Create Activities</a></li>
            <li><a href="/community_management/organizer/my_activity.php">My Activities</a></li>
            <li><a href="/community_management/organizer/view_feedback.php">View Feedback</a></li>
            <!--li><a href="/community_management/announcement.php">Announcements</a></li>-->
        <?php endif; ?>
        <li><a href="/community_management/profile.php">Profile Settings</a></li>
        <li><a href="/community_management/logout.php">Logout</a></li>
    </ul>
</nav>

<script>
(function(){
    var toggle = document.getElementById('cm-menu-toggle');
    var menu = document.getElementById('cm-side-menu');
    toggle.addEventListener('click', function(e){
        e.stopPropagation();
        var isOpen = menu.classList.toggle('open');
        menu.setAttribute('aria-hidden', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function(e){
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.remove('open');
            menu.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
    if (document.body && !document.body.classList.contains('cm-body-offset')) {
        document.body.classList.add('cm-body-offset');
    }
})();
</script>
