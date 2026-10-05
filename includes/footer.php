    </main>

<?php
// Mobile Bottom Navigation (Mobile Devices Only < 768px - LOGGED-IN USERS ONLY)
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['role']);
$mobile_user_role = $is_logged_in ? $_SESSION['role'] : '';
$current_page = basename($_SERVER['PHP_SELF']);

$mobile_nav_items = [];

if ($is_logged_in) {
    // Populate/Sync profile image from database for logged-in user
    if (isset($conn)) {
        $uid = (int)$_SESSION['user_id'];
        $u_res = @$conn->query("SELECT profile_image FROM users WHERE id = $uid");
        if ($u_res && $u_row = $u_res->fetch_assoc()) {
            $_SESSION['profile_image'] = $u_row['profile_image'] ?? '';
        }
    }

    if ($mobile_user_role === 'patient') {
        $mobile_nav_items = [
            ['label' => 'Home', 'icon' => 'fas fa-home', 'url' => 'patient_dashboard.php', 'active_pages' => ['patient_dashboard.php', 'index.php']],
            ['label' => 'Medicines', 'icon' => 'fas fa-pills', 'url' => 'medicines.php', 'active_pages' => ['medicines.php', 'your_orders.php']],
            ['label' => 'Doctors', 'icon' => 'fas fa-user-md', 'url' => 'nearby_doctors.php', 'active_pages' => ['nearby_doctors.php', 'book_consult.php']],
            ['label' => 'Notifs', 'icon' => 'fas fa-bell', 'url' => '#', 'is_notif' => true, 'active_pages' => []],
            ['label' => 'Profile', 'icon' => 'fas fa-user-circle', 'url' => 'profile.php', 'is_profile' => true, 'active_pages' => ['profile.php', 'prescription_vault.php', 'my_wallet.php']]
        ];
    } elseif ($mobile_user_role === 'doctor') {
        $mobile_nav_items = [
            ['label' => 'Dashboard', 'icon' => 'fas fa-chart-line', 'url' => 'doctor_dashboard.php', 'active_pages' => ['doctor_dashboard.php']],
            ['label' => 'Appointments', 'icon' => 'fas fa-calendar-alt', 'url' => 'doctor_appointments.php', 'active_pages' => ['doctor_appointments.php']],
            ['label' => 'Referrals', 'icon' => 'fas fa-exchange-alt', 'url' => 'doctor_referrals.php', 'active_pages' => ['doctor_referrals.php']],
            ['label' => 'Orders', 'icon' => 'fas fa-box', 'url' => 'doctor_orders.php', 'active_pages' => ['doctor_orders.php', 'doctor_medicines.php']],
            ['label' => 'Profile', 'icon' => 'fas fa-user-cog', 'url' => 'profile.php', 'is_profile' => true, 'active_pages' => ['profile.php']]
        ];
    } elseif ($mobile_user_role === 'rmp') {
        $mobile_nav_items = [
            ['label' => 'Dashboard', 'icon' => 'fas fa-flask', 'url' => 'rmp_dashboard.php', 'active_pages' => ['rmp_dashboard.php']],
            ['label' => 'Referrals', 'icon' => 'fas fa-user-md', 'url' => 'rmp_referral.php', 'active_pages' => ['rmp_referral.php']],
            ['label' => 'Upload', 'icon' => 'fas fa-file-upload', 'url' => 'rmp_upload.php', 'active_pages' => ['rmp_upload.php']],
            ['label' => 'Payments', 'icon' => 'fas fa-receipt', 'url' => 'payment_history.php', 'active_pages' => ['payment_history.php']],
            ['label' => 'Profile', 'icon' => 'fas fa-user-cog', 'url' => 'profile.php', 'is_profile' => true, 'active_pages' => ['profile.php']]
        ];
    } elseif ($mobile_user_role === 'admin') {
        $mobile_nav_items = [
            ['label' => 'Overview', 'icon' => 'fas fa-chart-pie', 'url' => 'admin_dashboard.php', 'active_pages' => ['admin_dashboard.php']],
            ['label' => 'Orders', 'icon' => 'fas fa-boxes', 'url' => 'admin_orders_management.php', 'active_pages' => ['admin_orders_management.php', 'admin_orders.php']],
            ['label' => 'Wallets', 'icon' => 'fas fa-wallet', 'url' => 'admin_wallets.php', 'active_pages' => ['admin_wallets.php']],
            ['label' => 'Users', 'icon' => 'fas fa-users-cog', 'url' => 'admin_users.php', 'active_pages' => ['admin_users.php', 'admin_verify.php']],
            ['label' => 'Profile', 'icon' => 'fas fa-user-cog', 'url' => 'profile.php', 'is_profile' => true, 'active_pages' => ['profile.php']]
        ];
    }
}

$mobile_unread_cnt = 0;
if (isset($header_unread)) {
    $mobile_unread_cnt = $header_unread;
} elseif ($is_logged_in && function_exists('get_unread_notification_count')) {
    $mobile_unread_cnt = get_unread_notification_count($_SESSION['user_id']);
}

// Check Profile Photo Availability via Centralized Resolver
$user_profile_img = $_SESSION['profile_image'] ?? '';
$user_profile_url = function_exists('get_profile_image_url') ? get_profile_image_url($user_profile_img) : '';
$has_profile_img = !empty($user_profile_url);
?>

<?php if ($is_logged_in && !empty($mobile_nav_items)): ?>
<style>
@media (max-width: 767px) {
  body {
    padding-bottom: calc(65px + env(safe-area-inset-bottom, 0px)) !important;
  }
}
</style>
<!-- Mobile Bottom Navigation Bar (Appears ONLY for Logged-in Users on Mobile screens < 768px) -->
<nav class="mobile-bottom-nav" aria-label="Mobile Navigation">
    <?php foreach ($mobile_nav_items as $item): 
        $is_active = in_array($current_page, $item['active_pages']);
        $is_notif = !empty($item['is_notif']);
        $is_profile = !empty($item['is_profile']);
    ?>
        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
           class="mobile-nav-item <?php echo $is_active ? 'active' : ''; ?>"
           <?php if ($is_notif): ?>onclick="handleMobileNotifToggle(event);"<?php endif; ?>>
            
            <?php if ($is_profile && $has_profile_img): ?>
                <img src="<?php echo $user_profile_url; ?>" 
                     alt="Profile" 
                     class="mobile-nav-profile-img" 
                     onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';" />
                <i class="<?php echo $item['icon']; ?>" style="display: none;"></i>
                <i class="<?php echo $item['icon']; ?>" style="display: none;"></i>
            <?php else: ?>
                <i class="<?php echo $item['icon']; ?>"></i>
            <?php endif; ?>

            <span><?php echo htmlspecialchars($item['label']); ?></span>
            <?php if ($is_notif): ?>
                <span id="mobileNotifBadge" class="mobile-nav-badge" style="display: <?php echo $mobile_unread_cnt > 0 ? 'inline-flex' : 'none'; ?>;">
                    <?php echo $mobile_unread_cnt; ?>
                </span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> MedicalAk. All Rights Reserved. Transforming Healthcare with Smart Innovation.</p>
    </footer>
    <script>
    function handleMobileNotifToggle(e) {
        e.preventDefault();
        const bellBtn = document.getElementById('notifBellBtn');
        if (bellBtn) {
            bellBtn.click();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Synchronize Mobile Bottom Nav Notification Badge with Header Bell Badge
        const headerBadge = document.getElementById('notifBadge');
        const mobileBadge = document.getElementById('mobileNotifBadge');
        if (headerBadge && mobileBadge) {
            const syncBadge = function() {
                mobileBadge.innerText = headerBadge.innerText;
                mobileBadge.style.display = (headerBadge.style.display !== 'none' && headerBadge.innerText.trim() !== '0') ? 'inline-flex' : 'none';
            };
            syncBadge();
            const observer = new MutationObserver(syncBadge);
            observer.observe(headerBadge, { childList: true, characterData: true, attributes: true, subtree: true });
        }

        // Dark / Light Mode Toggle Logic
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        function updateThemeIcon(theme) {
            if (themeIcon) {
                if (theme === 'light') {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                }
            }
        }

        const initialTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('theme') || 'dark';
        updateThemeIcon(initialTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
        }

        // Top Navigation Mobile Toggle
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                navMenu.classList.toggle('active');
                const icon = navToggle.querySelector('i');
                if (icon) {
                    if (navMenu.classList.contains('active')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });

            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (navMenu.classList.contains('active') && !navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                    navMenu.classList.remove('active');
                    const icon = navToggle.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });

            // Close menu when clicking navigation links
            navMenu.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    navMenu.classList.remove('active');
                    const icon = navToggle.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            });
        }

        // Patient / Dashboard Sidebar Menu Open/Close Toggle
        const sidebars = document.querySelectorAll('.sidebar');
        sidebars.forEach(function(sidebar) {
            const toggle = sidebar.querySelector('.sidebar-toggle');
            const title = sidebar.querySelector('.sidebar-title');
            const menu = sidebar.querySelector('.sidebar-menu');

            function toggleSidebarMenu(e) {
                if (window.innerWidth < 992 && menu) {
                    if (e) e.stopPropagation();
                    const isOpen = menu.classList.toggle('active');
                    if (toggle) {
                        toggle.classList.toggle('active', isOpen);
                    }
                }
            }

            if (toggle) {
                toggle.addEventListener('click', toggleSidebarMenu);
            }
            if (title) {
                title.addEventListener('click', function(e) {
                    if (window.innerWidth < 992) {
                        toggleSidebarMenu(e);
                    }
                });
            }
        });

        // Close sidebar menu when clicking outside (Mobile/Tablet)
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                sidebars.forEach(function(sidebar) {
                    const toggle = sidebar.querySelector('.sidebar-toggle');
                    const menu = sidebar.querySelector('.sidebar-menu');
                    if (menu && menu.classList.contains('active') && !sidebar.contains(e.target)) {
                        menu.classList.remove('active');
                        if (toggle) toggle.classList.remove('active');
                    }
                });
            }
        });

        // Close sidebar menu on ESC key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth < 992) {
                sidebars.forEach(function(sidebar) {
                    const toggle = sidebar.querySelector('.sidebar-toggle');
                    const menu = sidebar.querySelector('.sidebar-menu');
                    if (menu && menu.classList.contains('active')) {
                        menu.classList.remove('active');
                        if (toggle) toggle.classList.remove('active');
                    }
                });
            }
        });

        // Auto close mobile sidebar when a menu item link is clicked
        document.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    const sidebar = link.closest('.sidebar');
                    if (sidebar) {
                        const toggle = sidebar.querySelector('.sidebar-toggle');
                        const menu = sidebar.querySelector('.sidebar-menu');
                        if (menu) menu.classList.remove('active');
                        if (toggle) toggle.classList.remove('active');
                    }
                }
            });
        });

        // Feature 24: Network Status Indicator (Online/Offline Toast)
        (function() {
            function showNetworkToast(isOnline) {
                let toast = document.getElementById('networkToast');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = 'networkToast';
                    toast.style.cssText = 'position: fixed; bottom: 80px; left: 20px; z-index: 999999; padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 600; font-size: 0.88rem; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;';
                    document.body.appendChild(toast);
                }
                if (isOnline) {
                    toast.style.background = 'rgba(46, 213, 115, 0.95)';
                    toast.style.color = '#ffffff';
                    toast.innerHTML = '<i class="fas fa-wifi"></i> Online — Connection Restored';
                    setTimeout(function() { if (toast) toast.style.opacity = '0'; }, 3000);
                } else {
                    toast.style.opacity = '1';
                    toast.style.background = 'rgba(255, 71, 87, 0.95)';
                    toast.style.color = '#ffffff';
                    toast.innerHTML = '<i class="fas fa-exclamation-triangle"></i> You are Offline — Cached mode active';
                }
            }
            window.addEventListener('online', function() { showNetworkToast(true); });
            window.addEventListener('offline', function() { showNetworkToast(false); });
        })();

        // Instant Link Hover & Touch Prefetching for Sub-50ms Navigation
        (function() {
            const prefetched = new Set();
            function prefetchUrl(url) {
                if (!url || prefetched.has(url) || url.startsWith('#') || url.startsWith('javascript:') || url.includes('logout.php')) return;
                prefetched.add(url);
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = url;
                document.head.appendChild(link);
            }
            document.addEventListener('mouseover', function(e) {
                const anchor = e.target.closest('a');
                if (anchor && anchor.href && anchor.origin === window.location.origin) {
                    prefetchUrl(anchor.href);
                }
            }, { passive: true });
            document.addEventListener('touchstart', function(e) {
                const anchor = e.target.closest('a');
                if (anchor && anchor.href && anchor.origin === window.location.origin) {
                    prefetchUrl(anchor.href);
                }
            }, { passive: true });
        })();
    });
    </script>
</body>
</html>

