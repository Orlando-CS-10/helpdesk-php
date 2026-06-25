(function () {
    function syncSchedule() {
        const select = document.querySelector('[data-sla-schedule-select]');
        const section = document.querySelector('[data-sla-business-section]');
        if (!select || !section) return;
        section.classList.toggle('is-24-7', select.value === '24_7');
    }

    function syncPreview() {
        const name = document.getElementById('sla_name');
        const previewName = document.querySelector('[data-sla-preview-name]');
        const previewSchedule = document.querySelector('[data-sla-preview-schedule]');
        const schedule = document.getElementById('sla_schedule_type');
        const start = document.getElementById('sla_work_start');
        const end = document.getElementById('sla_work_end');

        if (name && previewName) {
            previewName.textContent = name.value.trim() || 'Nuevo perfil';
        }

        if (previewSchedule && schedule) {
            previewSchedule.textContent = schedule.value === '24_7'
                ? 'Atención continua 24/7'
                : 'Horario laboral · ' + (start?.value || '08:00') + '–' + (end?.value || '17:00');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('slaProfileForm');
        const schedule = document.querySelector('[data-sla-schedule-select]');
        syncSchedule();
        syncPreview();
        schedule?.addEventListener('change', function () { syncSchedule(); syncPreview(); });
        form?.addEventListener('input', syncPreview);
    });
})();
