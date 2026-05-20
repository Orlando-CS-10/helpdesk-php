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

<script>
    function openAssignModal(ticketId, assignedTo) {
        const modal = document.getElementById('assignTechModal');
        const ticketInput = document.getElementById('assignTicketId');
        const modalTicketInputs = document.querySelectorAll('.modal-ticket-id');
        const assignButtons = document.querySelectorAll('.tech-assign-btn');

        ticketInput.value = ticketId;

        modalTicketInputs.forEach(input => {
            input.value = ticketId;
        });

        assignButtons.forEach(button => {
            const techId = parseInt(button.dataset.techId || '0', 10);
            const currentAssigned = parseInt(assignedTo || '0', 10);

            if (techId === currentAssigned) {
                button.textContent = 'Asignado';
                button.disabled = true;
                button.classList.add('tech-assigned-btn');
            } else {
                button.textContent = 'Asignar';
                button.disabled = false;
                button.classList.remove('tech-assigned-btn');
            }
        });

        modal.classList.add('show');
    }

    function closeAssignModal() {
        const modal = document.getElementById('assignTechModal');
        modal.classList.remove('show');
    }

    function filterTechLevel(level, button) {
        const cards = document.querySelectorAll('.tech-card');
        const buttons = document.querySelectorAll('.tech-level-btn');

        buttons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        cards.forEach(card => {
            card.style.display = (level === 'all' || card.dataset.level === level) ? 'grid' : 'none';
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>