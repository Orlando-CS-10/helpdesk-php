(function () {
    'use strict';

    const form = document.getElementById('systemSecurityForm');

    document.querySelectorAll('[data-security-confirm]').forEach(function (actionForm) {
        actionForm.addEventListener('submit', function (event) {
            const message = actionForm.dataset.securityConfirm || '¿Deseas continuar?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    if (!form) {
        return;
    }

    const levelLabel = document.getElementById('securityLevelLabel');
    const scoreValue = document.getElementById('securityScoreValue');
    const scoreBar = document.getElementById('securityScoreBar');
    const summaryPasswordLength = document.getElementById('summaryPasswordLength');
    const summaryAttempts = document.getElementById('summaryAttempts');
    const summaryLockout = document.getElementById('summaryLockout');
    const summaryIdle = document.getElementById('summaryIdle');
    const summarySingleSession = document.getElementById('summarySingleSession');

    function intValue(name, fallback) {
        const input = form.elements[name];
        const parsed = input ? parseInt(input.value, 10) : NaN;
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function checked(name) {
        const input = form.elements[name];
        return Boolean(input && input.checked);
    }

    function updateSummary() {
        const length = intValue('min_password_length', 8);
        const attempts = intValue('max_failed_attempts', 5);
        const lockout = intValue('lockout_minutes', 15);
        const idle = intValue('session_idle_minutes', 30);

        let score = Math.min(20, Math.max(0, (length - 6) * 4));
        score += checked('require_uppercase') ? 8 : 0;
        score += checked('require_lowercase') ? 8 : 0;
        score += checked('require_number') ? 8 : 0;
        score += checked('require_special') ? 10 : 0;
        score += checked('block_common_passwords') ? 8 : 0;
        score += attempts <= 5 ? 10 : 5;
        score += lockout >= 10 ? 8 : 4;
        score += idle <= 30 ? 10 : 5;
        score += checked('invalidate_sessions_on_password_change') ? 8 : 0;
        score += checked('audit_enabled') ? 8 : 0;
        score = Math.min(100, score);

        const label = score >= 80 ? 'Alto' : (score >= 55 ? 'Moderado' : 'Básico');

        if (levelLabel) levelLabel.textContent = label;
        if (scoreValue) scoreValue.textContent = score + '%';
        if (scoreBar) scoreBar.style.width = score + '%';
        if (summaryPasswordLength) summaryPasswordLength.textContent = length + ' caracteres';
        if (summaryAttempts) summaryAttempts.textContent = String(attempts);
        if (summaryLockout) summaryLockout.textContent = lockout + ' min';
        if (summaryIdle) summaryIdle.textContent = idle + ' min';
        if (summarySingleSession) summarySingleSession.textContent = checked('single_session') ? 'Activada' : 'Desactivada';
    }

    form.querySelectorAll('input').forEach(function (input) {
        input.addEventListener('input', updateSummary);
        input.addEventListener('change', updateSummary);
    });

    updateSummary();
})();
