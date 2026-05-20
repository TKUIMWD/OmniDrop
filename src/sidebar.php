<?php
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$avatar_url = isset($_SESSION['avatar']) && $_SESSION['avatar'] ? $_SESSION['avatar'] : 'images/default_avatar.png';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" style="padding: 30px; display: flex; flex-direction: column; width: 260px; min-width: 260px; flex-shrink: 0; height: 100vh; overflow-y: auto; box-sizing: border-box; top:0; position:relative; box-shadow: 2px 0 20px rgba(0,0,0,0.1);">
    
    <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
        <button id="themeToggle" class="theme-toggle-btn" title="Toggle Theme">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <div style="text-align: center; margin-bottom: 40px;">
        <img src="<?php echo htmlspecialchars($avatar_url ?? ''); ?>" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid var(--theme-color); margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); object-fit: cover;">
        <h3 style="font-weight: 700; font-size: 18px; margin: 0; letter-spacing: -0.2px;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'OmniDrop'); ?></h3>
        <p style="color: var(--theme-color); font-size: 11px; font-weight: 700; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px;">
            <?php echo $is_admin ? '<i class="fas fa-shield-alt"></i> ADMIN' : 'USER'; ?>
        </p>
    </div>

    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; font-size: 14px; font-weight: 500;">
        <li>
            <a href="files.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; text-decoration: none; transition: all 0.3s; background: <?php echo $current_page == 'files.php' ? 'var(--input-bg)' : 'transparent'; ?>; color: <?php echo $current_page == 'files.php' ? 'var(--text-main) !important' : 'inherit'; ?>;">
                <i class="fas fa-folder-open <?php echo $current_page == 'files.php' ? 'theme-text' : ''; ?>"></i> 
                <span class="<?php echo $current_page == 'files.php' ? 'theme-text' : ''; ?>">My Drive</span>
            </a>
        </li>
        <li>
            <a href="shares.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; text-decoration: none; transition: all 0.3s; background: <?php echo $current_page == 'shares.php' ? 'var(--input-bg)' : 'transparent'; ?>; color: <?php echo $current_page == 'shares.php' ? 'var(--text-main) !important' : 'inherit'; ?>;">
                <i class="fas fa-share-nodes <?php echo $current_page == 'shares.php' ? 'theme-text' : ''; ?>"></i> 
                <span class="<?php echo $current_page == 'shares.php' ? 'theme-text' : ''; ?>">Shared Links</span>
            </a>
        </li>
        <li>
            <a href="reverse_shares.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; text-decoration: none; transition: all 0.3s; background: <?php echo $current_page == 'reverse_shares.php' ? 'var(--input-bg)' : 'transparent'; ?>; color: <?php echo $current_page == 'reverse_shares.php' ? 'var(--text-main) !important' : 'inherit'; ?>;">
                <i class="fas fa-inbox <?php echo $current_page == 'reverse_shares.php' ? 'theme-text' : ''; ?>"></i> 
                <span class="<?php echo $current_page == 'reverse_shares.php' ? 'theme-text' : ''; ?>">Dropzones</span>
            </a>
        </li>
        <li style="margin-top: 15px;">
            <a href="settings.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; text-decoration: none; transition: all 0.3s; background: <?php echo $current_page == 'settings.php' ? 'var(--input-bg)' : 'transparent'; ?>; color: <?php echo $current_page == 'settings.php' ? 'var(--text-main) !important' : 'inherit'; ?>;">
                <i class="fas fa-cog <?php echo $current_page == 'settings.php' ? 'theme-text' : ''; ?>"></i> 
                <span class="<?php echo $current_page == 'settings.php' ? 'theme-text' : ''; ?>">Settings</span>
            </a>
        </li>
        <?php if($is_admin): ?>
        <li>
            <a href="admin.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; text-decoration: none; transition: all 0.3s; background: <?php echo $current_page == 'admin.php' ? 'var(--input-bg)' : 'transparent'; ?>; color: <?php echo $current_page == 'admin.php' ? 'var(--text-main) !important' : 'inherit'; ?>;">
                <i class="fas fa-cogs <?php echo $current_page == 'admin.php' ? 'theme-text' : ''; ?>"></i> 
                <span class="<?php echo $current_page == 'admin.php' ? 'theme-text' : ''; ?>">Admin Setup</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li style="margin-top: auto; padding-top: 30px;">
            <a href="logout.php" style="display: flex; align-items: center; gap: 15px; padding: 12px 18px; border-radius: 8px; color: var(--danger-text) !important; text-decoration: none; transition: all 0.3s; background: var(--danger-bg); font-weight: 600; border: 1px solid var(--danger-border);">
                <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
            </a>
        </li>
        <li style="margin-top: 20px; text-align: center; color: var(--text-muted); font-size: 13px; font-weight: 600; opacity: 0.7;">
            OmniDrop v1.0.0
        </li>
    </ul>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.local-time').forEach(el => {
                const timeStr = el.getAttribute('data-time');
                const type = el.getAttribute('data-type') || 'utc';
                
                if (timeStr === 'Never' || !timeStr) return;
                
                let dtStr = timeStr;
                if (type === 'utc') {
                    // Append 'Z' to treat the DB time as UTC
                    if (!timeStr.includes('Z') && !timeStr.includes('+')) {
                        dtStr = timeStr.replace(' ', 'T') + 'Z';
                    }
                } else {
                    // Treat as local time, just replace space with T
                    if (!timeStr.includes('T')) {
                        dtStr = timeStr.replace(' ', 'T');
                    }
                }
                
                const dt = new Date(dtStr);
                if (!isNaN(dt.getTime())) {
                    el.textContent = dt.toLocaleString();
                }
            });
        });
    </script>
    <script>
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = themeBtn.querySelector('i');
        
        function updateIcon(theme) {
            if(theme === 'light') {
                themeIcon.className = 'fas fa-sun';
                themeBtn.style.color = '#eab308';
            } else {
                themeIcon.className = 'fas fa-moon';
                themeBtn.style.color = 'var(--text-muted)';
            }
        }
        
        // Initial setup from inline script in pages
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        updateIcon(currentTheme);

        themeBtn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('omni_theme', target);
            updateIcon(target);
        });
    

        // Custom Confirm Modal
        window.customConfirm = function(event, message) {
            event.preventDefault();
            const target = event.currentTarget || event.target;
            
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s;';
            
            const modal = document.createElement('div');
            modal.style.cssText = 'background:var(--input-bg); border:1px solid var(--glass-border); padding:30px; border-radius:12px; width:100%; max-width:400px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.5); transform:translateY(-20px); transition:transform 0.2s; font-family:sans-serif;';
            
            modal.innerHTML = `
                <div style="font-size:40px; color:var(--danger-text); margin-bottom:15px;">&#9888;</div>
                <h3 style="margin:0 0 15px 0; color:var(--text-main); font-size:22px;">Are you sure?</h3>
                <p style="color:var(--text-muted); margin:0 0 25px 0; line-height:1.5;">${message}</p>
                <div style="display:flex; justify-content:center; gap:15px;">
                    <button class="btn-cancel" style="padding:10px 20px; border:1px solid var(--glass-border); background:var(--glass-bg); color:var(--text-main); border-radius:6px; cursor:pointer; font-weight:600; transition:all 0.2s;">Cancel</button>
                    <button class="btn-confirm" style="padding:10px 20px; border:1px solid var(--danger-border); background:var(--danger-bg); color:var(--danger-text); border-radius:6px; cursor:pointer; font-weight:600; transition:all 0.2s;">Confirm</button>
                </div>
            `;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Animation
            requestAnimationFrame(() => {
                overlay.style.opacity = '1';
                modal.style.transform = 'translateY(0)';
            });
            
            const close = () => {
                overlay.style.opacity = '0';
                modal.style.transform = 'translateY(-20px)';
                setTimeout(() => overlay.remove(), 200);
            };
            
            modal.querySelector('.btn-cancel').onclick = close;
            modal.querySelector('.btn-confirm').onclick = () => {
                close();
                if (target.tagName === 'FORM') {
                    // We need to bypass the onsubmit handler to prevent an infinite loop
                    target.removeAttribute('onsubmit');
                    target.submit();
                } else if (target.tagName === 'a' || target.tagName === 'A') {
                    window.location.href = target.href;
                } else {
                    // if it's a button inside a form
                    const form = target.closest('form');
                    if (form) {
                        form.removeAttribute('onsubmit');
                        form.submit();
                    }
                }
            };
        };
</script>
</div>
