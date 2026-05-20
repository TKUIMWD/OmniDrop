<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_user' && intval($_POST['user_id']) !== $_SESSION['user_id']) {
        $del_uid = intval($_POST['user_id']);
        
        // Delete avatar from disk
        $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
        $stmt->execute([$del_uid]);
        $del_usr = $stmt->fetch();
        if ($del_usr && $del_usr['avatar']) {
            $ava_path = __DIR__ . $del_usr['avatar'];
            if (file_exists($ava_path)) @unlink($ava_path);
        }
        
        // Delete user's files from disk
        $stmt = $pdo->prepare('SELECT real_path FROM files WHERE user_id = ?');
        $stmt->execute([$del_uid]);
        $user_files = $stmt->fetchAll();
        foreach($user_files as $uf) {
            $db_path = $uf['real_path'];
            $full_path = (strpos($db_path, '/var/www/html') === 0) ? $db_path : __DIR__ . $db_path;
            if (file_exists($full_path)) @unlink($full_path);
        }

        // Delete all related database records
        $pdo->prepare('DELETE FROM reverse_shares WHERE user_id = ?')->execute([$del_uid]);
        $pdo->prepare('DELETE s FROM shares s JOIN files f ON s.file_id = f.id WHERE f.user_id = ?')->execute([$del_uid]);
        $pdo->prepare('DELETE FROM files WHERE user_id = ?')->execute([$del_uid]);
        
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$del_uid]);
        $msg = "User completely removed.";
    } elseif ($_POST['action'] === 'update_theme') {
        $stmt = $pdo->prepare('UPDATE system_settings SET setting_value = ? WHERE setting_key = "theme_color"');
        $stmt->execute([$_POST['theme_color']]);
        $msg = "Theme color applied system-wide.";
        $theme_color = $_POST['theme_color'];
    } elseif ($_POST['action'] === 'delete_file') {
        $stmt = $pdo->prepare('SELECT real_path FROM files WHERE id = ?');
        $stmt->execute([$_POST['file_id']]);
        $del_file = $stmt->fetch();
        if ($del_file) {
            $db_path = $del_file['real_path'];
            $full_path = (strpos($db_path, '/var/www/html') === 0) ? $db_path : __DIR__ . $db_path;
            @unlink($full_path);
            $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
            $stmt->execute([$_POST['file_id']]);
            $msg = "File deleted successfully.";
        }
    } elseif ($_POST['action'] === 'update_upload_config') {
        $max_size = (int)$_POST['max_size'];
        if ($max_size >= 1 && $max_size <= 200) {
            $htaccess_content = "php_value upload_max_filesize {$max_size}M\nphp_value post_max_size {$max_size}M\n";
            file_put_contents(__DIR__ . '/.htaccess', $htaccess_content);
            $msg = "Global upload limit updated to {$max_size}MB successfully.";
        } else {
            $error = "Invalid size. Please choose between 1 and 200 MB.";
        }
    }
}

// Parse current .htaccess settings for max_size
$current_max_size = 8; // Default 8MB
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    $lines = file($htaccess_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/php_value\s+upload_max_filesize\s+([0-9]+)M/i', $line, $matches)) {
            $current_max_size = (int)$matches[1];
            break;
        }
    }
}

// Fetch stats
$users = $pdo->query('SELECT * FROM users')->fetchAll();
$files = $pdo->query('SELECT f.*, u.username FROM files f JOIN users u ON f.user_id = u.id ORDER BY f.uploaded_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Admin Panel</title>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-section { background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 30px; }
        .admin-section h3 { color: var(--text-main); margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            <div class="topbar">
                <div class="breadcrumbs">
                    <i class="fas fa-shield-alt theme-text" style="font-size:20px; margin-right:10px;"></i> / Administration
                </div>
            </div>

            <?php if(isset($msg)) echo "<p style='color: var(--success-text); margin-bottom:20px; font-weight:500;'>$msg</p>"; ?>
            <?php if(isset($error)) echo "<p style='color: var(--danger-text); margin-bottom:20px; font-weight:500;'>$error</p>"; ?>

            <div class="admin-section">
                <h3><i class="fas fa-server"></i> Environment Configuration</h3>
                <p style="color: var(--text-muted); font-size:14px; line-height:1.6; margin-bottom:15px;">Adjust global container variables such as PHP upload limitations. Requires dynamic parsing of .htaccess.</p>
                <form method="POST" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="action" value="update_upload_config">
                    <input type="number" name="max_size" min="1" max="200" value="<?php echo $current_max_size; ?>" placeholder="Limit (MB)" required style="width: 120px; padding: 10px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-main);">
                    <span style="color:var(--text-main); font-weight:600;">MB</span>
                    <button type="submit" class="theme-bg btn-sm" style="margin:0; padding:10px 20px; width:auto;"><i class="fas fa-save"></i> Save Rules</button>
                </form>
            </div>

            <div class="admin-section">
                <h3><i class="fas fa-palette"></i> Branding Settings</h3>
                <form method="POST" style="display:flex; align-items:center; gap:15px;">
                    <input type="hidden" name="action" value="update_theme">
                    <label style="color: var(--text-muted); font-size:14px;">Primary Brand Color:</label>
                    <input type="color" name="theme_color" value="<?php echo htmlspecialchars($theme_color); ?>" style="height:35px; width:60px; border:none; background:transparent; cursor:pointer;">
                    <button type="submit" class="btn-sm theme-bg" style="width: auto; margin:0;"><i class="fas fa-save"></i> Save Theme</button>
                </form>
            </div>

            <div class="admin-section">
                <h3><i class="fas fa-users"></i> Manage Users</h3>
                <table class="file-table">
                    <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo $u['is_admin'] ? '<span class="badge badge-blue">Admin</span>' : '<span class="badge" style="background:var(--input-bg); color:var(--text-main);">User</span>'; ?></td>
                            <td>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="customConfirm(event, 'Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-sm" style="background:var(--danger-bg); color:var(--danger-text); border:1px solid var(--danger-border); margin:0;"><i class="fas fa-user-times"></i> Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-section">
                <h3><i class="fas fa-file-alt"></i> Manage All Files</h3>
                <table class="file-table">
                    <thead><tr><th>File</th><th>Owner</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($files as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['filename']); ?></td>
                            <td style="color: var(--theme-color);"><?php echo htmlspecialchars($f['username']); ?></td>
                            <td style="font-size:13px; color:var(--text-muted);"><span class="local-time" data-time="<?php echo $f['uploaded_at']; ?>"><?php echo $f['uploaded_at']; ?></span></td>
                            <td>
                                <form method="POST" onsubmit="customConfirm(event, 'Are you sure you want to permanently delete this file?');">
                                    <input type="hidden" name="action" value="delete_file">
                                    <input type="hidden" name="file_id" value="<?php echo $f['id']; ?>">
                                    <button type="submit" class="btn-sm" style="background:var(--danger-bg); color:var(--danger-text); border:1px solid var(--danger-border); margin:0;"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
</body>
</html>
