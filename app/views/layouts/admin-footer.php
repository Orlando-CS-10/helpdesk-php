<script>
    function toggleAdminUserMenu() {
        const dropdown = document.getElementById('adminUserDropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    function toggleAdminSidebar() {
        const shell = document.querySelector('.admin-shell');

        if (shell) {
            shell.classList.toggle('sidebar-collapsed');
        }
    }

    document.addEventListener('click', function (event) {
        const menu = document.querySelector('.admin-user-menu');
        const dropdown = document.getElementById('adminUserDropdown');

        if (menu && dropdown && !menu.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>