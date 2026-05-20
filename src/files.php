<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH'])) {
    $upload_err = "File exceeds the maximum allowed upload size limit.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $name = preg_replace('/^.*[\\\\\\/]/', '', $_FILES['file']['name']);
        $uuid = bin2hex(random_bytes(16));
        $disk_uuid = bin2hex(random_bytes(16));
        $upload_dir = __DIR__ . '/storage/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','xls','xlsx','csv','zip','txt','rar','7z'];
        
        if (!in_array($ext, $allowed)) {
            $upload_err = "Upload Failed. Only documents and images are allowed.";
        } else {
            $real_path = '/storage/' . $uuid . '_' . $disk_uuid . '_' . $name;
            $loc_path = __DIR__ . $real_path;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $loc_path)) {
                $stmt = $pdo->prepare('INSERT INTO files (user_id, uuid, filename, real_path, size) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$user_id, $uuid, $name, $real_path, filesize($loc_path)]);
                $upload_msg = "File uploaded via Dropzone.";
            }
        }
    } else {
        $upload_err = "Upload Failed. Code: " . $_FILES['file']['error'];
        if ($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE) { $upload_err = "The uploaded file exceeds the upload_max_filesize directive in php.ini."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_FORM_SIZE) { $upload_err = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_PARTIAL) { $upload_err = "The uploaded file was only partially uploaded."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) { $upload_err = "No file was uploaded."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_NO_TMP_DIR) { $upload_err = "Missing a temporary folder."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_CANT_WRITE) { $upload_err = "Failed to write file to disk."; }
        elseif ($_FILES['file']['error'] == UPLOAD_ERR_EXTENSION) { $upload_err = "A PHP extension stopped the file upload."; }
    }
}


// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $file_id = $_POST['file_id'];
    $stmt = $pdo->prepare('SELECT id, real_path FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$file_id, $user_id]);
    $file_data = $stmt->fetch();
    if ($file_data) {
        $db_path = $file_data['real_path'];
        $full_path = (strpos($db_path, '/var/www/html') === 0) ? $db_path : __DIR__ . $db_path;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $pdo->prepare('DELETE FROM shares WHERE file_id = ?')->execute([$file_id]);
        $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$file_id]);
        $msg = "File deleted successfully.";
    }
}


// Handle share creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share') {
    $file_id = $_POST['file_id'];

    // IDOR Fix: Verify file belongs to user
    $stmt = $pdo->prepare('SELECT id FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$file_id, $user_id]);
    if (!$stmt->fetch()) { die("Access Denied"); }

    $uuid = bin2hex(random_bytes(16));
    $pass = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $max_dls = !empty($_POST['max_downloads']) ? (int)$_POST['max_downloads'] : 0;
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    $stmt = $pdo->prepare('INSERT INTO shares (file_id, share_uuid, password, max_downloads, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$file_id, $uuid, $pass, $max_dls, $expires]);
    $share_msg = "Link created: /s.php?u=" . $uuid;
}

// Handle secure file download and preview
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['file_id']) && in_array($_GET['action'], ['download', 'preview'])) {
    $file_id = $_GET['file_id'];
    $stmt = $pdo->prepare('SELECT filename, real_path FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$file_id, $user_id]); // User check (IDOR protection)
    $file_data = $stmt->fetch();

    if ($file_data) {
        $db_path = $file_data['real_path'];
        $full_path = (strpos($db_path, '/var/www/html') === 0) ? $db_path : __DIR__ . $db_path;

        if (file_exists($full_path)) {
            $filename = preg_replace('/^.*[\\\\\\/]/', '', $file_data['filename']);
            $filesize = filesize($full_path);

            if ($_GET['action'] === 'download') {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . $filesize);
                readfile($full_path);
                exit;
            } elseif ($_GET['action'] === 'preview') {
                $mime_type = mime_content_type($full_path) ?: 'application/octet-stream';
                
                if (preg_match('/html|javascript|xml|svg/i', $mime_type)) {
                    $mime_type = 'text/plain';
                }

                header('Content-Type: ' . $mime_type);
                header('X-Content-Type-Options: nosniff'); // 防禦 MIME Sniffing
                header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox'); // 最高級別的預覽沙盒
                header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
                header('Content-Length: ' . $filesize);
                readfile($full_path);
                exit;
            }
        } else {
            $upload_err = "File not found on disk.";
        }
    } else {
        $upload_err = "Access Denied or File not found.";
    }
}

$stmt = $pdo->prepare('SELECT * FROM files WHERE user_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$user_id]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Files</title>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            <div class="topbar">
                <div class="breadcrumbs">
                    <i class="fas fa-folder-open theme-text" style="font-size:20px; margin-right:10px;"></i> / Home
                </div>
            </div>

            <?php if(isset($share_msg)): ?>
                <div style="background: var(--success-bg); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--success-border); font-family: monospace;">
                    <i class="fas fa-check-circle" style="color: var(--success-text);"></i> <?php echo $share_msg; ?>
                </div>
            <?php endif; ?>

            <div class="form-container" style="background: var(--input-bg); margin-bottom: 30px; border: 1px dashed var(--glass-border);">
                <form action="files.php" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; justify-content: center; padding: 20px; flex-direction: column;">
                    <i class="fas fa-cloud-upload-alt theme-text" style="font-size: 40px; margin-bottom: 15px;"></i>
                    <h3 style="margin: 0 0 15px 0; color: var(--text-main);">Upload a File</h3>
                    <input type="file" name="file" onchange="this.form.submit()" style="border: none; background: transparent; color: var(--text-main); cursor: pointer;">
                </form>
            <?php if(isset($upload_err)) echo "<p style='color: var(--danger-text); background: var(--danger-bg); padding:10px; border-radius:8px; border:1px solid var(--danger-border); margin-top:20px; font-weight:500; text-align:center;'>$upload_err</p>"; ?>
            <?php if(isset($upload_msg)) echo "<p style='color: var(--success-text); background: var(--success-bg); padding:10px; border-radius:8px; border:1px solid var(--success-border); margin-top:20px; font-weight:500; text-align:center;'>$upload_msg</p>"; ?>
            </div>

            <h3>Your Files</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($files as $f): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($f['filename']); ?></td>
                                <td><?php echo round($f['size'] / 1024, 2); ?> KB</td>
                                <td><span class="local-time" data-time="<?php echo $f['uploaded_at']; ?>"><?php echo $f['uploaded_at']; ?></span></td>
                                <td>
                                    <div style="display:flex; gap:5px; align-items:center;">
                                        <button class="theme-bg btn-sm" style="margin:0; padding:0 10px; height:28px; display:inline-flex; align-items:center; font-size:12px; border:1px solid transparent; cursor:pointer; box-sizing:border-box;" onclick="openShareModal(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars(addslashes($f['filename']), ENT_QUOTES); ?>')">
                                            <i class="fas fa-share-alt" style="margin-right:4px;"></i> Share
                                        </button>
                                        <a href="?action=preview&file_id=<?php echo $f['id']; ?>" target="_blank" class="theme-bg btn-sm" style="margin:0; padding:0 10px; height:28px; display:inline-flex; align-items:center; font-size:12px; text-decoration:none; border:1px solid transparent; box-sizing:border-box;">
                                            <i class="fas fa-eye" style="margin-right:4px;"></i> View
                                        </a>
                                        <a href="?action=download&file_id=<?php echo $f['id']; ?>" class="btn-sm" style="margin:0; padding:0 10px; height:28px; display:inline-flex; align-items:center; font-size:12px; text-decoration:none; background:var(--success-bg); color:var(--success-text); border:1px solid var(--success-border); box-sizing:border-box;">
                                            <i class="fas fa-download" style="margin-right:4px;"></i> Download
                                        </a>
                                        <form method="POST" style="margin:0; display:inline-flex; align-items:center;" onsubmit="customConfirm(event, 'Delete this file permanently?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="file_id" value="<?php echo $f['id']; ?>">
                                            <button type="submit" class="btn-sm" style="margin:0; padding:0 10px; height:28px; display:inline-flex; align-items:center; font-size:12px; color:var(--danger-text); border:1px solid var(--danger-border); background:var(--danger-bg); cursor:pointer; box-sizing:border-box;"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($files) === 0): ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No files uploaded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div class="card" style="width: 400px; position: relative; margin: 0;">
            <button onclick="document.getElementById('shareModal').style.display='none'" style="position:absolute; top:10px; right:15px; background:none; border:none; color: var(--text-main); font-size:20px; cursor:pointer;"><i class="fas fa-times"></i></button>
            <h2 style="margin-top:0;">Create Share Link</h2>
            <p id="shareFileName" style="color: var(--theme-color); font-weight: bold; margin-bottom: 20px; word-break: break-all;"></p>
            <form action="files.php" method="POST" onsubmit="if(document.getElementById('expires_display').value){document.getElementById('real_expires').value = new Date(document.getElementById('expires_display').value).toISOString().slice(0,19).replace('T',' ');}">
                <input type="hidden" name="action" value="share">
                <input type="hidden" name="file_id" id="shareFileId">
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password (Optional)">
                </div>
                <div class="form-group">
                    <input type="number" name="max_downloads" placeholder="Max Downloads (0 = unlimited)" value="0">
                </div>
                <div class="form-group">
                    <label style="color:var(--text-muted); font-size:12px; margin-bottom:5px; display:block;">Expires (Optional)</label>
                    <input type="datetime-local" id="expires_display">
                    <input type="hidden" name="expires_at" id="real_expires">
                </div>
                <button type="submit" class="theme-bg"><i class="fas fa-link"></i> Generate Link</button>
            </form>
        </div>
    </div>

    <script>
        function openShareModal(id, name) {
            document.getElementById('shareFileId').value = id;
            document.getElementById('shareFileName').innerText = name;
            document.getElementById('shareModal').style.display = 'flex';
        }
    </script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
</body>
</html>
