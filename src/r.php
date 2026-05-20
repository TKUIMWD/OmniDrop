<?php
session_start();
require 'db.php';

if (!isset($_GET['token'])) { die("Access Denied: Missing Token"); }

$token = $_GET['token'];
$stmt = $pdo->prepare('SELECT * FROM reverse_shares WHERE token = ?');
$stmt->execute([$token]);
$rs = $stmt->fetch();

if (!$rs || !$rs['is_active']) { die("Invalid or disabled token."); }
if (!empty($rs['expires_at']) && strtotime($rs['expires_at']) < time()) { die("This upload link has expired."); }
if ($rs['max_uses'] > 0 && $rs['used_count'] >= $rs['max_uses']) { die("This upload link has reached its maximum usage limit."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH'])) {
    $upload_err = "File exceeds the maximum allowed upload size limit.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] != UPLOAD_ERR_OK) {
        if ($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE) { $error = "Upload Failed. The uploaded file exceeds the upload_max_filesize directive in php.ini."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_FORM_SIZE) { $error = "Upload Failed. The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_PARTIAL) { $error = "Upload Failed. The uploaded file was only partially uploaded."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) { $error = "Upload Failed. No file was uploaded."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_NO_TMP_DIR) { $error = "Upload Failed. Missing a temporary folder."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_CANT_WRITE) { $error = "Upload Failed. Failed to write file to disk."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_EXTENSION) { $error = "Upload Failed. A PHP extension stopped the file upload."; }
        else { $error = "Upload Failed. Unknown error code: " . $_FILES['file']['error']; }
    } else {
    $user_id = $rs['user_id'];
    $file_name = preg_replace('/^.*[\\\\\\/]/', '', $_FILES['file']['name']);
    $uuid = bin2hex(random_bytes(16));
    $disk_uuid = bin2hex(random_bytes(16)); // Use a separate UUID for the physical file

    // Extension whitelist: block executables
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','xls','xlsx','csv','zip','txt','rar','7z'];
    if (!in_array($ext, $allowed)) {
        $error = "Upload Failed. Only documents and images are allowed.";
    } else {
    // Add prefix to indicate guest upload
    $file_name = "[Guest]" . $file_name;

    $real_path = '/storage/' . $uuid . '_' . $disk_uuid . '_' . $file_name;
    $local_path = __DIR__ . $real_path;

if (move_uploaded_file($_FILES['file']['tmp_name'], $local_path)) {
        $stmt = $pdo->prepare('INSERT INTO files (user_id, uuid, filename, real_path, size) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user_id, $uuid, $file_name, $real_path, filesize($local_path)]);

        $pdo->prepare('UPDATE reverse_shares SET used_count = used_count + 1 WHERE id = ?')->execute([$rs['id']]);

        $msg = "File uploaded securely to target account.";
    } else {
        $error = "Upload failed.";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Guest Upload</title>
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
        <h2><i class="fas fa-cloud-upload-alt" style="margin-right: 10px; color: var(--theme-color);"></i> Guest Upload</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Use this portal to securely upload files directly into the requester's account.</p>
        
        <?php if(isset($msg)): ?>
            <p style='color: var(--success-text); margin-bottom:20px; font-weight:500;'><i class="fas fa-check-circle"></i> <?php echo $msg; ?></p>
        <?php else: ?>
            <form method="POST" action="r.php?token=<?php echo htmlspecialchars($token); ?>" enctype="multipart/form-data">
                <div class="form-group" style="text-align:center; border: 2px dashed var(--glass-border); padding:40px 20px; border-radius:12px; background:var(--input-bg);">
                    <i class="fas fa-file-upload" style="font-size:40px; color: var(--theme-color); margin-bottom:15px;"></i><br>
                    <input type="file" name="file" required style="width:100%; border:none; background:transparent; color: var(--text-main);">
                </div>
                <button type="submit" class="theme-bg" style="width:100%; border:none; padding:15px; font-size:16px; border-radius:8px; cursor:pointer;" onmouseover="this.style.filter='brightness(1.1)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.filter=''; this.style.transform='none'"><i class="fas fa-upload"></i> Upload</button>
            </form>
            <?php if(isset($error)) echo "<p style='color: var(--danger-text); margin-top:15px; font-weight:500;'>$error</p>"; ?>
            <?php if(isset($upload_err)) echo "<p style='color: var(--danger-text); margin-top:15px; font-weight:500;'>$upload_err</p>"; ?>
        <?php endif; ?>
    </div>
<script>
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
