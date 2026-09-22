    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> MedicalAk. All Rights Reserved. Transforming Healthcare with Smart Innovation.</p>
    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
    </script>
</body>
</html>
