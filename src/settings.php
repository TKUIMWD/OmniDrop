<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_password') {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['current_password'], $user['password'])) {
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$new_hash, $user_id]);
            $msg = "Password updated successfully.";
        } else {
            $error = "Current password incorrect.";
        }
    } elseif ($_POST['action'] === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
            $av_name = preg_replace('/^.*[\\\\\\/]/', '', $_FILES['avatar']['name']);
            $ext = strtolower(pathinfo($av_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];
            
            if (!in_array($ext, $allowed)) {
                $error = "Avatar upload failed. Only images are allowed.";
            } else {
                $uuid = bin2hex(random_bytes(16));
                $disk_uuid = bin2hex(random_bytes(16));
                $av_path = '/storage/avatar_' . time() . '_' . $uuid . '_' . $disk_uuid . '_' . $av_name;
                $loc_path = __DIR__ . $av_path;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $loc_path)) {
                    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
                    $stmt->execute([$user_id]);
                    $old = $stmt->fetch();
                    if ($old && $old['avatar']) {
                        $old_path = __DIR__ . $old['avatar'];
                        if (file_exists($old_path)) @unlink($old_path);
                    }

                    $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt->execute([$av_path, $user_id]);
                    $_SESSION['avatar'] = $av_path;
                    $msg = "Avatar updated successfully.";
                } else {
                    $error = "Avatar upload failed.";
                }
            }
        }
    } elseif ($_POST['action'] === 'remove_avatar') {
        $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $old = $stmt->fetch();
        if ($old && $old['avatar']) {
            $old_path = __DIR__ . $old['avatar'];
            if (file_exists($old_path)) @unlink($old_path);
        }
        $stmt = $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = ?');
        $stmt->execute([$user_id]);
        $_SESSION['avatar'] = null;
        $msg = "Avatar removed successfully.";
    } elseif ($_POST['action'] === 'delete_account' && !$is_admin) { // Prevent admin from deleting own account
        $stmt = $pdo->prepare('SELECT password, avatar FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($_POST['del_password'], $user['password'])) {
            // Delete avatar from disk
            if ($user['avatar']) {
                $ava_path = __DIR__ . $user['avatar'];
                if (file_exists($ava_path)) @unlink($ava_path);
            }
            
            // Delete all associated files from disk
            $stmt = $pdo->prepare('SELECT real_path FROM files WHERE user_id = ?');
            $stmt->execute([$user_id]);
            $user_files = $stmt->fetchAll();
            foreach($user_files as $uf) {
                $db_path = $uf['real_path'];
                $full_path = (strpos($db_path, '/var/www/html') === 0) ? $db_path : __DIR__ . $db_path;
                if (file_exists($full_path)) @unlink($full_path);
            }

            // Remove related entries across tables before deleting user
            $pdo->prepare('DELETE FROM reverse_shares WHERE user_id = ?')->execute([$user_id]);
            $pdo->prepare('DELETE s FROM shares s JOIN files f ON s.file_id = f.id WHERE f.user_id = ?')->execute([$user_id]);
            $pdo->prepare('DELETE FROM files WHERE user_id = ?')->execute([$user_id]);

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            header('Location: logout.php');
            exit;
        } else {
            $error = "Incorrect password! Account deletion aborted.";
        }
    } elseif ($_POST['action'] === 'delete_account' && $is_admin) {
        $error = "Admin accounts cannot be deleted.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Settings</title>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            <div class="topbar">
                <div class="breadcrumbs">
                    <i class="fas fa-cog theme-text" style="font-size:20px; margin-right:10px;"></i> / Settings
                </div>
            </div>

            <?php if(isset($msg)) echo "<p style='color: var(--success-text); background: var(--success-bg); padding:12px 15px; border-radius:8px; border:1px solid var(--success-border); font-weight:500; font-size:14px;'><i class='fas fa-check-circle'></i> $msg</p>"; ?>
            <?php if(isset($error)) echo "<p style='color: var(--danger-text); background: var(--danger-bg); padding:12px 15px; border-radius:8px; border:1px solid var(--danger-border); font-weight:500; font-size:14px;'><i class='fas fa-exclamation-circle'></i> $error</p>"; ?>

            <div style="display:flex; gap:30px; align-items:flex-start; flex-wrap:wrap;">
                
                <div style="flex: 1 1 400px; display:flex; flex-direction:column; gap:30px;">
                    <div class="card">
                        <h3>User Profile</h3>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_avatar">
                                <div class="form-group">
                                    <label>Profile Picture (Avatar)</label>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <img id="avatarPreview" src="<?php echo htmlspecialchars($avatar_url ?? ''); ?>" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--theme-color);">
                                        <input type="file" name="avatar" accept="image/*" style="border:none; background:transparent; max-width: 250px; padding:0;" onchange="if(this.files && this.files[0]) document.getElementById('avatarPreview').src = window.URL.createObjectURL(this.files[0]);">
                                    </div>
                                </div>
                                <button type="submit" class="theme-bg" style="width:100%; padding:15px; border-radius:8px; font-weight:600; cursor:pointer; border:1px solid transparent;"><i class="fas fa-upload"></i> Upload Avatar</button>
                            </form>
                            <?php if (isset($_SESSION['avatar']) && $_SESSION['avatar']): ?>
                            <form method="POST" onsubmit="customConfirm(event, 'Are you sure you want to remove your avatar?');">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" style="width:100%; border:1px solid var(--danger-border); background:var(--danger-bg); color:var(--danger-text); padding:15px; border-radius:8px; font-weight:600; cursor:pointer;" onmouseover="this.style.filter='brightness(1.2)'" onmouseout="this.style.filter='none'"><i class="fas fa-trash-alt"></i> Remove Avatar</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Security / Replace Password</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                            <button type="submit" class="theme-bg"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                    </div>
                </div>

                <div style="flex: 1 1 400px; display:flex; flex-direction:column; gap:30px;">
                    <?php if(!$is_admin): ?>
                    <div class="card danger-card">
                        <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                        <p>Warning! Deleting your account is permanent. All your files, shares, and reverse shares associated with this account will become orphaned and invisible to you. This action cannot be magically undone.</p>
                        
                        <form method="POST" onsubmit="customConfirm(event, 'Are you absolutely sure? There is no going back.');">
                            <input type="hidden" name="action" value="delete_account">
                            <div class="form-group">
                                <label style="color:var(--danger-text);">Verify Password to Delete Account</label>
                                <input type="password" name="del_password" required class="danger-input">
                            </div>
                            <button type="submit" class="btn-danger-outline"><i class="fas fa-user-slash"></i> Delete Account</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <h3><i class="fas fa-shield-alt theme-text"></i> System Administrator</h3>
                        <p style="color: var(--text-muted); font-size:14px; line-height:1.6;">You are currently logged in as an ADMIN. The account deletion module is disabled to prevent accidental lockouts.</p>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Inline script to load theme before render -->
    <script>
        const savedTheme = localStorage.getItem('omni_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
</body>
</html>
