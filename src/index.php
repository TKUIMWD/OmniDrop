<?php
session_start();
require 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: files.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    if (isset($_POST['register'])) {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        try {
            $stmt->execute([$user, $hashed]);
            $msg = "Registered! You can now login.";
        } catch(Exception $e) { 
            $error = "Registration failed."; 
        }
    } else {
        $stmt = $pdo->prepare('SELECT id, password, is_admin, avatar FROM users WHERE username = ?');
        $stmt->execute([$user]);
        $row = $stmt->fetch();
        if ($row && password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $user;
            $_SESSION['is_admin'] = $row['is_admin'];
            $_SESSION['avatar'] = $row['avatar'];
            header('Location: files.php'); exit;
        } else { 
            $error = "Invalid credentials."; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('omni_theme') || 'dark');</script>
<head>
    <meta charset="UTF-8">
    <title>OmniDrop - Login</title>
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
        <i class="fas fa-layer-group theme-text" style="font-size: 50px; margin-bottom: 20px;"></i>
        <h1>OmniDrop</h1>
        <?php if(isset($error)) echo "<p style='color: var(--danger-text); margin-bottom:20px; font-weight:500;'>$error</p>"; ?>
        <?php if(isset($msg)) echo "<p style='color: var(--success-text); margin-bottom:20px; font-weight:500;'>$msg</p>"; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login" class="theme-bg">Sign In</button>
            <button type="submit" name="register" class="alt-btn">Create Account</button>
        </form>
    </div>

<script>
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = themeBtn.querySelector('i');
    
    function updateIcon(theme) {
        if(theme === 'light') {
            themeIcon.className = 'fas fa-sun';
            themeIcon.style.color = '#eab308';
        } else {
            themeIcon.className = 'fas fa-moon';
            themeIcon.style.color = 'var(--text-muted)';
        }
    }
    
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    updateIcon(currentTheme);

    themeBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const target = current === 'dark' ? 'light' : 'dark';
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
