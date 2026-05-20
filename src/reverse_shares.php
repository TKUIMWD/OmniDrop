<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $uuid = bin2hex(random_bytes(16));
    $note = !empty($_POST['note']) ? $_POST['note'] : 'Reverse Drop';
    $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : 0;
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    $stmt = $pdo->prepare('INSERT INTO reverse_shares (user_id, token, note, max_uses, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$user_id, $uuid, $note, $max_uses, $expires]);
    $msg = "Upload Link: /r.php?token=" . $uuid;
}

if (isset($_GET['del'])) {
    $stmt = $pdo->prepare('DELETE FROM reverse_shares WHERE id = ? AND user_id = ?');
    $stmt->execute([$_GET['del'], $user_id]);
    header('Location: reverse_shares.php'); exit;
}

$stmt = $pdo->prepare('SELECT * FROM reverse_shares WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user_id]);
$r_shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Reverse Shares</title>
    <style><?php include "css/style.css"; ?></style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            <div class="topbar">
                <div class="breadcrumbs">
                    <i class="fas fa-inbox theme-text" style="font-size:20px; margin-right:10px;"></i> / Reverse Shares
                </div>
            </div>

            <?php if(isset($msg)): ?>
                <div style="background: var(--success-bg); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--success-border); font-family: monospace;">
                    <i class="fas fa-check-circle" style="color: var(--success-text);"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom: 30px;">
                <h3 style="margin-top:0; border-bottom:1px solid var(--glass-border); padding-bottom:15px; font-size:16px;"><i class="fas fa-link theme-text" style="margin-right:8px;"></i> Create Upload Token</h3>
                <p style="color:var(--text-muted); font-size:14px; margin-top:20px; margin-bottom:20px;">Generate a secure, reusable link to allow external clients or guests to upload files directly into your account without logging in.</p>
                <form action="reverse_shares.php" method="POST" onsubmit="if(document.getElementById('default_expiry').value){document.getElementById('real_expiry').value = new Date(document.getElementById('default_expiry').value).toISOString().slice(0,19).replace('T',' ');}" style="display:flex; flex-direction:column; gap:15px; max-width: 600px;">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group" style="margin:0;">
                        <label>Token Reference Note <span style="font-weight:normal; color:var(--text-muted);">(Required)</span></label>
                        <input type="text" name="note" placeholder="e.g., Public Upload" style="width:100%; border-radius:8px;" required>
                    </div>

                    <div style="display:flex; gap:15px; align-items:flex-end;">
                        <div class="form-group" style="margin:0; flex:1;">
                            <label>Maximum Uses <span style="font-weight:normal; color:var(--text-muted);">(Optional)</span></label>
                            <input type="number" name="max_uses" placeholder="0 for unlimited" min="0" style="width:100%; border-radius:8px;">
                        </div>
                        <div class="form-group" style="margin:0; flex:1;">
                            <label>Expiry Date <span style="font-weight:normal; color:var(--text-muted);">(Optional)</span></label>
                            <input type="datetime-local" id="default_expiry" style="width:100%; border-radius:8px;">
                            <input type="hidden" name="expires_at" id="real_expiry">
                            <script>
                                const dt = new Date();
                                dt.setHours(dt.getHours() + 24);
                                const tzOffset = dt.getTimezoneOffset() * 60000;
                                const localISOTime = (new Date(dt - tzOffset)).toISOString().slice(0, 16);
                                document.getElementById('default_expiry').value = localISOTime;
                            </script>
                        </div>
                    </div>

                    <button type="submit" class="theme-bg" style="width:100%;"><i class="fas fa-plus"></i> Generate Secure Link</button>
                </form>
            </div>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Note</th>
                            <th>Link</th>
                            <th>Usage</th>
                            <th>State / Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($r_shares as $r): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($r['note']); ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <input type="text" readonly value="<?php echo 'http://'.$_SERVER['HTTP_HOST'].'/r.php?token='.$r['token']; ?>" style="margin:0; padding:6px 10px; font-size:12px; width:220px; background:var(--input-bg); border:1px solid var(--input-border); color:var(--text-muted);">
                                        <a href="/r.php?token=<?php echo $r['token']; ?>" target="_blank" style="color:var(--theme-color); font-size:14px;"><i class="fas fa-external-link-alt"></i></a>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        if($r['max_uses'] > 0) {
                                            $percent = round(($r['used_count'] / $r['max_uses']) * 100);
                                            $col = $percent >= 100 ? 'var(--danger-text)' : 'var(--theme-color)';
                                            echo "<span style='color:$col; font-weight:600;'>{$r['used_count']}</span> / {$r['max_uses']}";
                                        } else {
                                            echo "<span style='font-weight:600;'>{$r['used_count']}</span> <span style='color:var(--text-muted); font-size:12px;'>(Unlimited)</span>";
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:5px;">
                                    <?php 
                                        $expired = false;
                                        if(!empty($r['expires_at']) && strtotime($r['expires_at']) < time()) {
                                            $expired = true;
                                        }
                                        $full = ($r['max_uses'] > 0 && $r['used_count'] >= $r['max_uses']);
                                        
                                        if(!$r['is_active'] || $expired || $full): 
                                    ?>
                                        <span class="badge" style="background:var(--danger-bg); color:var(--danger-text); align-self:flex-start;">Disabled</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--success-bg); color:var(--success-text); align-self:flex-start;">Active</span>
                                    <?php endif; ?>
                                    
                                    <span style="font-size:11px; color:var(--text-muted); font-weight:600;">
                                        <?php if (!empty($r['expires_at'])): ?>
                                            Exp: <span class="local-time" data-time="<?php echo $r['expires_at']; ?>"><?php echo date('Y/m/d H:i', strtotime($r['expires_at'])); ?></span>
                                        <?php else: ?>
                                            Never Expires
                                        <?php endif; ?>
                                    </span>
                                    </div>
                                </td>
                                <td>
                                    <a href="reverse_shares.php?del=<?php echo $r['id']; ?>" class="btn-sm" style="color:var(--danger-text); border-color:var(--danger-border); text-decoration:none;" onclick="customConfirm(event, 'Delete this link permanently?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($r_shares) === 0): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No reverse shares created.</td></tr>
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
