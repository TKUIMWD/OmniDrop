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
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','xls','xlsx','csv','zip','txt','rar','7z'];
        
        if (!in_array($ext, $allowed)) {
            $upload_err = "Upload Failed. Only documents and images are allowed.";
        } else {
            // Generate S3 key path
            $s3_key = "files/" . $uuid . "/" . $disk_uuid . "_" . $name;
            $tmp_file = $_FILES['file']['tmp_name'];
            $file_size = $_FILES['file']['size'];
            
            // Upload to S3
            if ($s3Manager->uploadFile($tmp_file, $s3_key)) {
                $stmt = $pdo->prepare('INSERT INTO files (user_id, uuid, filename, real_path, size) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$user_id, $uuid, $name, $s3_key, $file_size]);
                $upload_msg = "File uploaded successfully to S3.";
            } else {
                $upload_err = "Failed to upload file to S3. Please try again.";
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
        $s3_key = $file_data['real_path'];
        // Delete from S3
        $s3Manager->deleteFile($s3_key);
        // Delete from database
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
        $s3_key = $file_data['real_path'];
        $filename = preg_replace('/^.*[\\\\\\/]/', '', $file_data['filename']);

        if ($_GET['action'] === 'download') {
            // Redirect to presigned URL for download
            $presigned_url = $s3Manager->getPresignedUrl($s3_key, 3600); // 1 hour expiry
            if ($presigned_url) {
                header('Location: ' . $presigned_url);
                exit;
            } else {
                $upload_err = "Failed to generate download link.";
            }
        } elseif ($_GET['action'] === 'preview') {
            // Redirect to presigned URL for preview
            $presigned_url = $s3Manager->getPresignedUrl($s3_key, 3600); // 1 hour expiry
            if ($presigned_url) {
                // Use iframe for preview
                header('Content-Type: text/html');
                echo '<iframe src="' . htmlspecialchars($presigned_url) . '" style="width:100%; height:100vh; border:none;"></iframe>';
                exit;
            } else {
                $upload_err = "Failed to generate preview link.";
            }
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
