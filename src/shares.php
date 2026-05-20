<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];

if (isset($_GET['revoke'])) {
    $stmt = $pdo->prepare('DELETE s FROM shares s JOIN files f ON s.file_id = f.id WHERE s.id = ? AND f.user_id = ?');
    $stmt->execute([$_GET['revoke'], $user_id]);
    header('Location: shares.php'); exit;
}

$stmt = $pdo->prepare('
    SELECT s.*, f.filename 
    FROM shares s 
    JOIN files f ON s.file_id = f.id 
    WHERE f.user_id = ?
    ORDER BY s.created_at DESC
');
$stmt->execute([$user_id]);
$shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Shares</title>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            <div class="topbar">
                <div class="breadcrumbs">
                    <i class="fas fa-share-alt theme-text" style="font-size:20px; margin-right:10px;"></i> / Shares
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Link</th>
                            <th>Protected</th>
                            <th>Downloads</th>
                            <th>State / Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($shares as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['filename']); ?></td>
                                <td><a href="/s.php?u=<?php echo $s['share_uuid']; ?>" style="color:var(--theme-color);" target="_blank">/s.php?u=<?php echo substr($s['share_uuid'], 0, 8); ?>...</a></td>
                                <td>
                                    <?php if($s['password']): ?>
                                        <i class="fas fa-lock" style="color:#f59e0b;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-lock-open" style="color:var(--success-text);"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        if($s['max_downloads'] > 0) {
                                            $percent = round(($s['downloads'] / $s['max_downloads']) * 100);
                                            $col = $percent >= 100 ? 'var(--danger-text)' : 'var(--theme-color)';
                                            echo "<span style='color:$col; font-weight:600;'>{$s['downloads']}</span> / {$s['max_downloads']}";
                                        } else {
                                            echo "<span style='font-weight:600;'>{$s['downloads']}</span> <span style='color:var(--text-muted); font-size:12px;'>(Unlimited)</span>";
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                    <?php 
                                        $expired = false;
                                        if(!empty($s['expires_at']) && strtotime($s['expires_at']) < time()) {
                                            $expired = true;
                                        }
                                        $full = ($s['max_downloads'] > 0 && $s['downloads'] >= $s['max_downloads']);
                                        
                                        if($expired || $full): 
                                    ?>
                                        <span class="badge" style="background:var(--danger-bg); color:var(--danger-text); align-self:flex-start;">Disabled</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-bg); color:var(--success-text); align-self:flex-start;">Active</span>
                                    <?php endif; ?>
                                    
                                    <span style="font-size:11px; color:var(--text-muted); font-weight:600;">
                                        <?php if (!empty($s['expires_at'])): ?>
                                            Exp: <span class="local-time" data-time="<?php echo $s['expires_at']; ?>"><?php echo date('Y/m/d H:i', strtotime($s['expires_at'])); ?></span>
                                        <?php else: ?>
                                            Never
                                        <?php endif; ?>
                                    </span>
                                    </div>
                                </td>
                                <td>
                                    <a href="shares.php?revoke=<?php echo $s['id']; ?>" class="btn-sm" style="color:var(--danger-text); border-color:var(--danger-border); text-decoration:none;" onclick="customConfirm(event, 'Revoke this share link?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($shares) === 0): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No active shares.</td></tr>
                        <?php endif; ?>
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
