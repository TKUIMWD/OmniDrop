<?php
session_start();
require 'db.php';

if (!isset($_GET['u'])) {
    die("Invalid link.");
}

$uuid = $_GET['u'];

$stmt = $pdo->prepare('SELECT s.*, f.filename, f.uuid as file_uuid FROM shares s JOIN files f ON s.file_id = f.id WHERE s.share_uuid = ?');
$stmt->execute([$uuid]);
$share = $stmt->fetch();

if (!$share) {
    die("Share does not exist or has been revoked.");
}

// Check expiration
if (!empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
    die("This share link has expired.");
}

// Check download limits
if ($share['max_downloads'] > 0 && $share['downloads'] >= $share['max_downloads']) {
    die("This share link has reached its maximum download limit.");
}

// Check Auth if password exists
if ($share['password'] !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (!password_verify($_POST['password'], $share['password'])) {
            $error = "Incorrect password.";
        } else {
            $_SESSION['authenticated_share_' . $uuid] = true;
            // Prevent POST resubmission dialogue by redirecting to self over GET
            header("Location: s.php?u=" . urlencode($uuid));
            exit;
        }
    }
    
    if (!isset($_SESSION['authenticated_share_' . $uuid]) || $_SESSION['authenticated_share_' . $uuid] !== true) {
        ?>
        <!DOCTYPE html>
        <html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script><head><title>OmniDrop - Protected Share</title>
        <style><?php include "css/style.css"; ?></style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body style="height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="position: absolute; top: 20px; right: 30px;">
        <button id="themeToggle" class="theme-toggle-btn" title="Toggle Theme" style="background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); width: 44px; height: 44px; border-radius: 50%; color: var(--text-main); cursor: pointer; transition: all 0.3s; box-shadow: var(--shadow-main); display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-moon" style="font-size: 18px;"></i>
        </button>
    </div>

        <div class="login-box">
            <h2 style="margin-top:0;"><i class="fas fa-lock theme-text"></i> Protected File</h2>
            <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">This share link requires a password.</p>
            <form method="POST">
                <div class="form-group"><input type="password" name="password" placeholder="Enter password..." required style="width:100%; padding:15px; margin-bottom:20px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-main); font-size:16px;"></div>
                <button type="submit" class="theme-bg" style="width:100%"><i class="fas fa-unlock"></i> Unlock</button>
            </form>
            <?php if(isset($error)) echo "<p style='color: var(--danger-text); margin-top:15px; font-weight:bold; font-size:14px;'>$error</p>"; ?>
        </div><script>
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = themeBtn.querySelector('i');
    function updateIcon(theme) {
        if(theme === 'light') { themeIcon.className = 'fas fa-sun'; themeIcon.style.color = '#eab308'; } 
        else { themeIcon.className = 'fas fa-moon'; themeIcon.style.color = 'var(--text-muted)'; }
    }
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    updateIcon(currentTheme);
    themeBtn.addEventListener('click', () => {
        const target = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', target);
        localStorage.setItem('omni_theme', target);
        updateIcon(target);
    });
</script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
</body>
</html>
        <?php
        exit;
    }
}

// Validation passed. Counter increment is handled inside download.php
$download_link = "/download.php?file=" . urlencode($share['file_uuid']) . "&share=" . urlencode($share['share_uuid']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Shared File</title>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="position: absolute; top: 20px; right: 30px;">
        <button id="themeToggleOutput" class="theme-toggle-btn" title="Toggle Theme" style="background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); width: 44px; height: 44px; border-radius: 50%; color: var(--text-main); cursor: pointer; transition: all 0.3s; box-shadow: var(--shadow-main); display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-moon" style="font-size: 18px;"></i>
        </button>
    </div>

    <div class="login-box" style="text-align: center;">
        <i class="fas fa-file-download theme-text" style="font-size: 50px; margin-bottom: 20px;"></i>
        <h2 style="margin-top:0;">Shared File Ready</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:10px;">You are attempting to download:</p>
        <p style="color:var(--text-main); font-weight:bold; font-size:16px; word-break:break-all; margin-bottom:30px; background:var(--input-bg); padding:10px; border-radius:8px; border:1px solid var(--input-border);">
            <?php echo htmlspecialchars($share['filename']); ?>
        </p>

        <?php if ($share['password'] !== null): ?>
            <p style="color: var(--success-text); font-size: 13px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> File unlocked successfully.</p>
        <?php endif; ?>

        <a href="<?php echo $download_link; ?>" class="theme-bg" style="display:inline-block; width:100%; text-align:center; padding:15px; border-radius:8px; text-decoration:none; font-weight:600; box-sizing:border-box;">
            <i class="fas fa-download"></i> Download
        </a>
    </div>

<script>
    const outThemeBtn = document.getElementById('themeToggleOutput');
    const outThemeIcon = outThemeBtn.querySelector('i');
    function updateOutIcon(theme) {
        if(theme === 'light') { outThemeIcon.className = 'fas fa-sun'; outThemeIcon.style.color = '#eab308'; } 
        else { outThemeIcon.className = 'fas fa-moon'; outThemeIcon.style.color = 'var(--text-muted)'; }
    }
    const currentOutTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    updateOutIcon(currentOutTheme);
    outThemeBtn.addEventListener('click', () => {
        const target = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', target);
        localStorage.setItem('omni_theme', target);
        updateOutIcon(target);
    });
</script>
</body>
</html>
