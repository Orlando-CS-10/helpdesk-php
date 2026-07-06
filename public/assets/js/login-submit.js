(() => {
    'use strict';

    const resetSubmitButton = (form) => {
        const button = form.querySelector('[data-login-submit]');
        if (!button) {
            return;
        }

        form.removeAttribute('data-submitting');
        form.removeAttribute('aria-busy');
        button.disabled = false;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');

        const text = button.querySelector('[data-submit-text]');
        if (text && text.dataset.defaultText) {
            text.textContent = text.dataset.defaultText;
        }

        const icon = button.querySelector('[data-submit-icon]');
        if (icon && icon.dataset.defaultClass) {
            icon.className = icon.dataset.defaultClass;
        }
    };

    document.querySelectorAll('[data-login-form]').forEach((form) => {
        const button = form.querySelector('[data-login-submit]');
        if (!button) {
            return;
        }

        const text = button.querySelector('[data-submit-text]');
        const icon = button.querySelector('[data-submit-icon]');

        if (text) {
            text.dataset.defaultText = text.textContent.trim();
        }
        if (icon) {
            icon.dataset.defaultClass = icon.className;
        }

        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                return;
            }

            if (form.hasAttribute('data-submitting')) {
                event.preventDefault();
                return;
            }

            form.setAttribute('data-submitting', 'true');
            form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');

            if (text) {
                text.textContent = button.dataset.loadingText || 'Validando acceso...';
            }
            if (icon) {
                icon.className = 'fa-solid fa-circle-notch fa-spin';
            }
        });

        window.addEventListener('pageshow', () => resetSubmitButton(form));
    });
})();
