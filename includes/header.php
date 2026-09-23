<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedicalAk - Smart Healthcare Solutions</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
<?php 
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/notification_functions.php';
    $header_unread = get_unread_notification_count($_SESSION['user_id']);
}
?>
    <header>
        <a href="index.php" class="logo">MedicalAk</a>
        <div class="header-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="notif-wrapper" style="position: relative; display: inline-block;">
                    <button id="notifBellBtn" class="theme-toggle" aria-label="Notifications" title="Notifications" style="position: relative; margin-right: 0.4rem;">
                        <i class="fas fa-bell"></i>
                        <span id="notifBadge" style="display: <?php echo $header_unread > 0 ? 'inline-flex' : 'none'; ?>; position: absolute; top: -5px; right: -5px; background: #ff4757; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7rem; font-weight: bold; align-items: center; justify-content: center;">
                            <?php echo $header_unread; ?>
                        </span>
                    </button>

                    <!-- Dropdown Panel -->
                    <div id="notifPanel" class="glass-panel" style="display: none; position: absolute; right: 0; top: 45px; width: 330px; max-height: 420px; z-index: 10000; padding: 1rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid var(--glass-border); overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; margin-bottom: 0.8rem;">
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700;"><i class="fas fa-bell" style="color: var(--primary-color);"></i> Notifications</h4>
                            <button id="markAllReadBtn" style="background: none; border: none; color: var(--primary-color); font-size: 0.75rem; cursor: pointer; font-weight: 600;">Mark all as read</button>
                        </div>
                        <div id="notifList" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <div style="text-align: center; color: var(--text-secondary); padding: 1rem; font-size: 0.85rem;">Loading...</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <button class="theme-toggle" id="themeToggle" aria-label="Toggle Dark/Light Mode" title="Toggle Dark/Light Mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav id="navMenu">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#about">About</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'patient'): ?>
                        <li><a href="patient_dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'doctor'): ?>
                        <li><a href="doctor_dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                    <?php elseif($_SESSION['role'] == 'rmp'): ?>
                        <li><a href="rmp_dashboard.php">RMP Panel</a></li>
                    <?php endif; ?>
                    <li><a href="profile.php" style="color: var(--secondary-color);"><i class="fas fa-user-circle"></i> Profile</a></li>
                    <li><a href="logout.php" class="btn btn-outline" style="padding: 0.4rem 1rem;">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline">Login</a></li>
                    <li><a href="register.php" class="btn btn-primary">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Notification Toast Container -->
    <div id="notifToastContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; max-width: 350px;"></div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
    (function() {
        const bellBtn = document.getElementById('notifBellBtn');
        const panel = document.getElementById('notifPanel');
        const list = document.getElementById('notifList');
        const badge = document.getElementById('notifBadge');
        const markAllBtn = document.getElementById('markAllReadBtn');
        let currentUnread = <?php echo $header_unread; ?>;
        let knownNotifIds = new Set();

        if (bellBtn && panel) {
            bellBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                panel.style.display = (panel.style.display === 'none' || !panel.style.display) ? 'block' : 'none';
                if (panel.style.display === 'block') {
                    fetchNotifications();
                }
            });

            document.addEventListener('click', function(e) {
                if (panel && !panel.contains(e.target) && !bellBtn.contains(e.target)) {
                    panel.style.display = 'none';
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                fetch('api_notifications.php?action=mark_all_read')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            updateBadge(0);
                            fetchNotifications();
                        }
                    });
            });
        }

        function updateBadge(count) {
            currentUnread = count;
            if (badge) {
                badge.innerText = count;
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
            }
        }

        function fetchNotifications() {
            fetch('api_notifications.php?action=fetch')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        updateBadge(data.unread_count);
                        renderList(data.notifications);
                    }
                });
        }

        function renderList(items) {
            if (!list) return;
            if (!items || items.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-secondary); padding: 1rem; font-size: 0.85rem;">No notifications found.</div>';
                return;
            }
            let html = '';
            items.forEach(function(item) {
                let iconClass = 'fa-bell';
                if (item.type === 'wallet' || item.type === 'payment') iconClass = 'fa-wallet';
                else if (item.type === 'order') iconClass = 'fa-box';
                else if (item.type === 'referral') iconClass = 'fa-gift';

                let bgStyle = item.is_read == 0 ? 'background: rgba(255,255,255,0.08); border-left: 3px solid var(--primary-color);' : 'background: rgba(0,0,0,0.2);';
                
                html += `
                    <div style="padding: 0.75rem; border-radius: 8px; ${bgStyle} cursor: pointer;" onclick="markSingleRead(${item.id}, '${item.related_entity_type || ''}')">
                        <div style="display: flex; gap: 0.6rem; align-items: flex-start;">
                            <i class="fas ${iconClass}" style="color: var(--primary-color); margin-top: 3px;"></i>
                            <div style="flex: 1;">
                                <div style="font-size: 0.85rem; font-weight: bold; color: var(--text-primary);">${escapeHtml(item.title)}</div>
                                <div style="font-size: 0.78rem; color: var(--text-secondary); margin: 0.2rem 0;">${escapeHtml(item.message)}</div>
                                <div style="font-size: 0.7rem; color: var(--text-secondary);">${formatTime(item.created_at)}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        window.markSingleRead = function(id, entityType) {
            fetch('api_notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            }).then(res => res.json())
              .then(data => {
                  if (data.success) {
                      updateBadge(data.unread_count);
                      fetchNotifications();
                  }
              });
        };

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function formatTime(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ', ' + d.toLocaleDateString();
        }

        function showToast(title, msg) {
            const container = document.getElementById('notifToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.style.cssText = 'background: rgba(20, 25, 40, 0.95); border: 1px solid var(--primary-color); border-radius: 12px; padding: 1rem; color: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.5); backdrop-filter: blur(10px); animation: fadeIn 0.3s;';
            toast.innerHTML = `<div style="font-weight: bold; font-size: 0.9rem; color: var(--primary-color);"><i class="fas fa-bell"></i> ${escapeHtml(title)}</div><div style="font-size: 0.8rem; margin-top: 0.3rem;">${escapeHtml(msg)}</div>`;
            container.appendChild(toast);
            setTimeout(function() {
                toast.remove();
            }, 5000);
        }

        // Real-time EventSource Listener (Server-Sent Events)
        if (window.EventSource) {
            const source = new EventSource('api_notifications.php?action=stream');
            source.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.unread_count !== undefined) {
                        if (data.unread_count > currentUnread && data.notifications && data.notifications.length > 0) {
                            const latest = data.notifications[0];
                            if (!knownNotifIds.has(latest.id)) {
                                knownNotifIds.add(latest.id);
                                showToast(latest.title, latest.message);
                            }
                        }
                        updateBadge(data.unread_count);
                        if (panel && panel.style.display === 'block') {
                            renderList(data.notifications);
                        }
                    }
                } catch(err) {}
            };
        }
    })();
    </script>
    <?php endif; ?>
    <main>
