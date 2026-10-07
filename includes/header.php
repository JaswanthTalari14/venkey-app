<?php 
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedicalAk - Smart Healthcare Solutions</title>
    <!-- DNS Preconnect & Font Optimization -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <!-- PWA Manifest & Icons -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="images/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0F172A">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="MedicalAk">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MedicalAk">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();

        // Register PWA Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js', { scope: './' })
                    .then(function(reg) {
                        console.log('PWA ServiceWorker registered with scope:', reg.scope);
                    })
                    .catch(function(err) {
                        console.warn('PWA ServiceWorker registration failed:', err);
                    });
            });
        }

        // PWA Install Prompt Handler & State Management
        let deferredInstallPrompt = null;

        function showPWAInstalledToast() {
            if (document.getElementById('pwaToast')) return;
            const toast = document.createElement('div');
            toast.id = 'pwaToast';
            toast.style.cssText = 'position: fixed; bottom: 25px; right: 25px; z-index: 999999; background: rgba(18, 18, 18, 0.92); color: #50e3c2; padding: 0.85rem 1.4rem; border-radius: 12px; border: 1px solid rgba(80, 227, 194, 0.4); font-weight: 600; font-size: 0.9rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px); display: flex; align-items: center; gap: 0.6rem; transition: opacity 0.4s ease;';
            toast.innerHTML = '<i class="fas fa-check-circle" style="font-size: 1.1rem; color: #2ed573;"></i> App Installed Successfully ✅';
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 400);
            }, 4000);
        }

        function markPWAInstalledState() {
            localStorage.setItem('pwa_installed', 'true');
            document.querySelectorAll('.pwaInstallBtn').forEach(function(btn) {
                btn.innerHTML = '<i class="fas fa-check-circle" style="color: #2ed573; margin-right: 0.5rem;"></i> App Installed';
                btn.style.opacity = '0.8';
                btn.style.cursor = 'default';
            });
        }

        function resetPWAInstallState() {
            localStorage.removeItem('pwa_installed');
            document.querySelectorAll('.pwaInstallBtn').forEach(function(btn) {
                btn.innerHTML = '<i class="fas fa-download" style="margin-right: 0.5rem;"></i> Install App';
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        }

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredInstallPrompt = e;
            if (!window.matchMedia('(display-mode: standalone)').matches && window.navigator.standalone !== true) {
                resetPWAInstallState();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const isInstalled = window.matchMedia('(display-mode: standalone)').matches || 
                                window.navigator.standalone === true || 
                                localStorage.getItem('pwa_installed') === 'true';

            if (isInstalled) {
                markPWAInstalledState();
            }

            document.addEventListener('click', function(e) {
                const installBtn = e.target.closest('.pwaInstallBtn');
                if (installBtn) {
                    e.preventDefault();
                    if (localStorage.getItem('pwa_installed') === 'true' || window.matchMedia('(display-mode: standalone)').matches) {
                        showPWAInstalledToast();
                        return;
                    }
                    if (deferredInstallPrompt) {
                        deferredInstallPrompt.prompt();
                        deferredInstallPrompt.userChoice.then(function(choiceResult) {
                            if (choiceResult.outcome === 'accepted') {
                                markPWAInstalledState();
                                showPWAInstalledToast();
                            }
                            deferredInstallPrompt = null;
                        });
                    } else {
                        alert('To install MedicalAk:\n\n1. Tap your browser menu (3 dots or Share icon)\n2. Select "Add to Home screen" or "Install App".');
                    }
                }
            });
        });

        window.addEventListener('appinstalled', function() {
            markPWAInstalledState();
            showPWAInstalledToast();
        });
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
                    <div id="notifPanel" class="notif-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.6rem; margin-bottom: 0.8rem; gap: 0.5rem; flex-wrap: wrap;">
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem; color: var(--text-primary);"><i class="fas fa-bell" style="color: var(--primary-color);"></i> Notifications</h4>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <button id="markAllReadBtn" style="background: none; border: none; color: var(--primary-color); font-size: 0.75rem; cursor: pointer; font-weight: 600; padding: 0.2rem 0.4rem; border-radius: 4px;" title="Mark all as read">Mark all as read</button>
                                <button id="clearNotifsBtn" style="background: rgba(255, 71, 87, 0.12); border: 1px solid rgba(255, 71, 87, 0.3); color: #ff4757; font-size: 0.75rem; cursor: pointer; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.25rem; transition: all 0.2s;" title="Clear all notifications">
                                    <i class="fas fa-trash-alt" style="font-size: 0.7rem;"></i> Clear
                                </button>
                            </div>
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
                        <li><a href="customer_support.php"><i class="fas fa-headset"></i> Support Center</a></li>
                        <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> Create Ticket</a></li>
                    <?php elseif($_SESSION['role'] == 'doctor'): ?>
                        <li><a href="doctor_dashboard.php">Dashboard</a></li>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                        <li><a href="admin_support.php"><i class="fas fa-headset"></i> Support Center</a></li>
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
    <div id="notifToastContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999; pointer-events: none; display: flex; flex-direction: column; gap: 10px; max-width: 350px;"></div>

    <!-- Clear Notifications Confirmation Modal -->
    <div id="clearNotifModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); border-radius: 16px; padding: 1.5rem; max-width: 360px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.6); color: var(--text-primary); text-align: center;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 style="margin: 0 0 0.5rem; font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">Clear all notifications?</h3>
            <p style="margin: 0 0 1.25rem; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">This will permanently remove all notifications from your notification list.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <button id="cancelClearNotifBtn" type="button" style="flex: 1; padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid var(--glass-border, rgba(255,255,255,0.15)); background: rgba(255, 255, 255, 0.08); color: var(--text-primary); font-size: 0.85rem; font-weight: 600; cursor: pointer;">Cancel</button>
                <button id="confirmClearNotifBtn" type="button" style="flex: 1; padding: 0.6rem 1rem; border-radius: 10px; border: none; background: #ff4757; color: white; font-size: 0.85rem; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);">Clear</button>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
    (function() {
        const bellBtn = document.getElementById('notifBellBtn');
        const panel = document.getElementById('notifPanel');
        const list = document.getElementById('notifList');
        const badge = document.getElementById('notifBadge');
        const markAllBtn = document.getElementById('markAllReadBtn');
        const clearBtn = document.getElementById('clearNotifsBtn');
        const clearModal = document.getElementById('clearNotifModal');
        const cancelClearBtn = document.getElementById('cancelClearNotifBtn');
        const confirmClearBtn = document.getElementById('confirmClearNotifBtn');

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
                if (panel && !panel.contains(e.target) && !bellBtn.contains(e.target) && (!clearModal || !clearModal.contains(e.target))) {
                    panel.style.display = 'none';
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.stopPropagation();
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

        // Clear Notifications Handlers
        if (clearBtn && clearModal) {
            clearBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearModal.style.display = 'flex';
            });
        }

        if (cancelClearBtn && clearModal) {
            cancelClearBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearModal.style.display = 'none';
            });
        }

        if (clearModal) {
            clearModal.addEventListener('click', function(e) {
                if (e.target === clearModal) {
                    clearModal.style.display = 'none';
                }
            });
        }

        if (confirmClearBtn && clearModal) {
            confirmClearBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                confirmClearBtn.disabled = true;
                confirmClearBtn.innerText = 'Clearing...';

                fetch('api_notifications.php?action=clear_all', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    confirmClearBtn.disabled = false;
                    confirmClearBtn.innerText = 'Clear';
                    clearModal.style.display = 'none';

                    if (data.success) {
                        updateBadge(0);
                        renderList([]);
                    } else {
                        showToast('Error', data.message || 'Failed to clear notifications.');
                    }
                })
                .catch(err => {
                    confirmClearBtn.disabled = false;
                    confirmClearBtn.innerText = 'Clear';
                    clearModal.style.display = 'none';
                    showToast('Error', 'Failed to clear notifications. Please try again.');
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
                list.innerHTML = '<div style="text-align: center; color: var(--text-secondary); padding: 1.5rem 1rem; font-size: 0.85rem;"><i class="fas fa-bell-slash" style="font-size: 1.5rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>No notifications found.</div>';
                return;
            }
            let html = '';
            items.forEach(function(item) {
                let iconClass = 'fa-bell';
                if (item.type === 'wallet' || item.type === 'payment') iconClass = 'fa-wallet';
                else if (item.type === 'order') iconClass = 'fa-box';
                else if (item.type === 'referral') iconClass = 'fa-gift';

                let isUnread = item.is_read == 0;
                let itemClass = isUnread ? 'notif-item unread' : 'notif-item read';

                html += `
                    <div class="${itemClass}" onclick="markSingleRead(${item.id}, '${item.related_entity_type || ''}')">
                        <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
                            <div style="flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; background: ${isUnread ? 'rgba(74, 144, 226, 0.2)' : 'rgba(255, 255, 255, 0.08)'}; display: flex; align-items: center; justify-content: center; margin-top: 2px;">
                                <i class="fas ${iconClass}" style="color: var(--primary-color); font-size: 0.8rem;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="notif-title">${escapeHtml(item.title)}</div>
                                <div class="notif-message">${escapeHtml(item.message)}</div>
                                <div class="notif-time">${formatTime(item.created_at)}</div>
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
                      if (entityType === 'medical_card') {
                          window.location.href = 'digital_medical_card.php';
                      } else if (entityType === 'wallet') {
                          window.location.href = 'my_wallet.php';
                      } else if (entityType === 'order') {
                          window.location.href = 'your_orders.php';
                      } else if (entityType === 'prescription') {
                          window.location.href = 'health_vault.php';
                      } else if (entityType === 'appointment') {
                          window.location.href = 'book_consult.php';
                      } else if (entityType === 'referral') {
                          window.location.href = 'refer_earn.php';
                      }
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
            if (container) {
                const toast = document.createElement('div');
                toast.style.cssText = 'background: rgba(20, 25, 40, 0.95); border: 1px solid var(--primary-color); border-radius: 12px; padding: 1rem; color: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.5); backdrop-filter: blur(10px); animation: fadeIn 0.3s; overflow-wrap: anywhere; word-break: break-word;';
                toast.innerHTML = `<div style="font-weight: bold; font-size: 0.9rem; color: var(--primary-color);"><i class="fas fa-bell"></i> ${escapeHtml(title)}</div><div style="font-size: 0.8rem; margin-top: 0.3rem;">${escapeHtml(msg)}</div>`;
                container.appendChild(toast);
                setTimeout(function() {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 5000);
            }
            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                try {
                    new Notification(title, { body: msg });
                } catch(e) {}
            }
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

