<script>
(function () {
    const STORAGE_KEY = 'helpdesk_company_sidebar_collapsed';
    const mobileQuery = window.matchMedia
        ? window.matchMedia('(max-width: 920px)')
        : null;

    function shell() {
        const sidebar = document.getElementById('adminSidebar');
        return sidebar ? sidebar.closest('.admin-shell') : document.querySelector('.admin-shell');
    }

    function updateSidebarControl(collapsed) {
        const button = document.getElementById('adminSidebarToggle');

        if (!button) {
            return;
        }

        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        button.setAttribute(
            'aria-label',
            collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'
        );
        button.setAttribute(
            'title',
            collapsed ? 'Expandir menú' : 'Contraer menú'
        );
    }

    function applySidebarState(collapsed, saveState) {
        const currentShell = shell();

        if (!currentShell) {
            return;
        }

        const isMobile = Boolean(mobileQuery && mobileQuery.matches);
        const resolvedCollapsed = isMobile ? false : collapsed;

        currentShell.classList.toggle('sidebar-collapsed', resolvedCollapsed);
        document.documentElement.classList.remove('admin-sidebar-initial-collapsed');
        updateSidebarControl(resolvedCollapsed);

        if (saveState && !isMobile) {
            try {
                localStorage.setItem(STORAGE_KEY, resolvedCollapsed ? '1' : '0');
            } catch (error) {
                // La navegación sigue operativa sin almacenamiento local.
            }
        }
    }

    window.toggleAdminSidebar = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const currentShell = shell();

        if (!currentShell || (mobileQuery && mobileQuery.matches)) {
            return;
        }

        applySidebarState(!currentShell.classList.contains('sidebar-collapsed'), true);
    };

    window.toggleAdminUserMenu = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const dropdown = document.getElementById('adminUserDropdown');
        const trigger = document.getElementById('adminUserTrigger');

        if (!dropdown) {
            return;
        }

        const willOpen = !dropdown.classList.contains('show');
        dropdown.classList.toggle('show', willOpen);

        if (trigger) {
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    };

    document.addEventListener('click', function (event) {
        const menu = document.querySelector('.company-user-menu');
        const dropdown = document.getElementById('adminUserDropdown');
        const trigger = document.getElementById('adminUserTrigger');

        if (menu && dropdown && !menu.contains(event.target)) {
            dropdown.classList.remove('show');

            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        const dropdown = document.getElementById('adminUserDropdown');
        const trigger = document.getElementById('adminUserTrigger');

        dropdown?.classList.remove('show');
        trigger?.setAttribute('aria-expanded', 'false');
    });

    function initializeSidebar() {
        let collapsed = false;

        try {
            collapsed = localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            collapsed = false;
        }

        applySidebarState(collapsed, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSidebar, { once: true });
    } else {
        initializeSidebar();
    }

    if (mobileQuery) {
        const onViewportChange = function () {
            initializeSidebar();
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', onViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(onViewportChange);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
