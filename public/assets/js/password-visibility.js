(() => {
    'use strict';

    const setVisibility = (button, input, visible) => {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('title', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');

        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye', !visible);
            icon.classList.toggle('fa-eye-slash', visible);
        }
    };

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const inputId = button.getAttribute('aria-controls');
        const input = inputId ? document.getElementById(inputId) : null;

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            setVisibility(button, input, shouldShow);
            input.focus({ preventScroll: true });

            try {
                const cursor = input.value.length;
                input.setSelectionRange(cursor, cursor);
            } catch (_) {
                // Algunos navegadores no permiten controlar el cursor en este campo.
            }
        });
    });

    document.querySelectorAll('[data-password-input]').forEach((input) => {
        const warning = document.querySelector(`[data-caps-warning="${input.id}"]`);
        if (!warning) {
            return;
        }

        const updateCapsLock = (event) => {
            const isActive = typeof event.getModifierState === 'function'
                && event.getModifierState('CapsLock');
            warning.hidden = !isActive;
        };

        input.addEventListener('keydown', updateCapsLock);
        input.addEventListener('keyup', updateCapsLock);
        input.addEventListener('blur', () => {
            warning.hidden = true;
        });
    });
})();
