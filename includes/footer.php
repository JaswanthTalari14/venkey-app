    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> MedicalAk. All Rights Reserved. Transforming Healthcare with Smart Innovation.</p>
    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
    </script>
</body>
</html>
