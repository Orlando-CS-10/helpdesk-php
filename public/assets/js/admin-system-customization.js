(function () {
    'use strict';

    const form = document.getElementById('systemCustomizationForm');
    const preview = document.getElementById('systemCustomizationPreview');

    if (!form || !preview) {
        return;
    }

    const colorNames = ['primary_color', 'secondary_color', 'accent_color'];
    const colorPattern = /^#[0-9a-f]{6}$/i;
    const themeLabel = document.getElementById('previewThemeLabel');
    const restoreButton = document.getElementById('restoreSystemCustomization');
    const systemDarkMode = window.matchMedia
        ? window.matchMedia('(prefers-color-scheme: dark)')
        : null;

    function normalizeColor(value) {
        const color = String(value || '').trim().toLowerCase();
        return colorPattern.test(color) ? color : null;
    }

    function updateColor(name, value, source) {
        const normalized = normalizeColor(value);
        const textInput = form.querySelector('[data-color-text="' + name + '"]');
        const pickerInput = form.querySelector('[data-color-picker="' + name + '"]');
        const dot = form.querySelector('[data-color-dot="' + name + '"]');

        if (!textInput || !pickerInput) {
            return;
        }

        textInput.classList.toggle('is-invalid', !normalized);
        textInput.setCustomValidity(normalized ? '' : 'Usa el formato #RRGGBB.');

        if (!normalized) {
            return;
        }

        if (source !== 'text') {
            textInput.value = normalized;
        }

        if (source !== 'picker') {
            pickerInput.value = normalized;
        }

        if (dot) {
            dot.style.background = normalized;
        }

        const previewVariable = {
            primary_color: '--preview-primary',
            secondary_color: '--preview-secondary',
            accent_color: '--preview-accent'
        }[name];

        if (previewVariable) {
            preview.style.setProperty(previewVariable, normalized);
        }
    }

    function selectedValue(name, fallback) {
        const checked = form.querySelector('input[name="' + name + '"]:checked');
        return checked ? checked.value : fallback;
    }

    function resolveTheme(theme) {
        if (theme === 'auto') {
            return systemDarkMode && systemDarkMode.matches ? 'dark' : 'light';
        }

        return theme === 'dark' ? 'dark' : 'light';
    }

    function updateOptionCards(name) {
        form.querySelectorAll('[data-option-card="' + name + '"]').forEach(function (card) {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', Boolean(radio && radio.checked));
        });
    }

    function updatePreviewOptions() {
        const selectedTheme = selectedValue('theme', 'light');
        const resolvedTheme = resolveTheme(selectedTheme);
        const sidebar = selectedValue('sidebar_default', 'expanded');

        preview.dataset.previewTheme = resolvedTheme;
        preview.dataset.previewSidebar = sidebar;

        if (themeLabel) {
            const labels = {
                light: 'Tema claro',
                dark: 'Tema oscuro',
                auto: 'Automático · ' + (resolvedTheme === 'dark' ? 'oscuro' : 'claro')
            };
            themeLabel.textContent = labels[selectedTheme] || labels.light;
        }

        updateOptionCards('theme');
        updateOptionCards('sidebar_default');
    }

    colorNames.forEach(function (name) {
        const textInput = form.querySelector('[data-color-text="' + name + '"]');
        const pickerInput = form.querySelector('[data-color-picker="' + name + '"]');

        if (textInput) {
            textInput.addEventListener('input', function () {
                updateColor(name, textInput.value, 'text');
            });
            textInput.addEventListener('blur', function () {
                const normalized = normalizeColor(textInput.value);
                if (normalized) {
                    textInput.value = normalized;
                    updateColor(name, normalized, 'text');
                }
            });
        }

        if (pickerInput) {
            pickerInput.addEventListener('input', function () {
                updateColor(name, pickerInput.value, 'picker');
            });
        }

        updateColor(name, textInput ? textInput.value : '', 'initial');
    });

    form.querySelectorAll('input[name="theme"], input[name="sidebar_default"]').forEach(function (radio) {
        radio.addEventListener('change', updatePreviewOptions);
    });

    form.querySelectorAll('[data-preset-primary]').forEach(function (button) {
        button.addEventListener('click', function () {
            const values = {
                primary_color: button.dataset.presetPrimary,
                secondary_color: button.dataset.presetSecondary,
                accent_color: button.dataset.presetAccent
            };

            Object.keys(values).forEach(function (name) {
                updateColor(name, values[name], 'preset');
            });
        });
    });

    form.addEventListener('reset', function () {
        window.setTimeout(function () {
            colorNames.forEach(function (name) {
                const input = form.querySelector('[data-color-text="' + name + '"]');
                updateColor(name, input ? input.value : '', 'initial');
            });
            updatePreviewOptions();
        }, 0);
    });

    form.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        const action = submitter ? submitter.value : 'save';

        if (action === 'restore') {
            return;
        }

        let firstInvalid = null;

        colorNames.forEach(function (name) {
            const input = form.querySelector('[data-color-text="' + name + '"]');
            updateColor(name, input ? input.value : '', 'text');

            if (input && !input.checkValidity() && !firstInvalid) {
                firstInvalid = input;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.reportValidity();
            firstInvalid.focus();
        }
    });

    if (restoreButton) {
        restoreButton.addEventListener('click', function (event) {
            const confirmed = window.confirm(
                'Se restaurarán los colores originales, el tema claro y el menú expandido. ¿Deseas continuar?'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    }

    if (systemDarkMode) {
        const onSystemThemeChange = function () {
            if (selectedValue('theme', 'light') === 'auto') {
                updatePreviewOptions();
            }
        };

        if (typeof systemDarkMode.addEventListener === 'function') {
            systemDarkMode.addEventListener('change', onSystemThemeChange);
        } else if (typeof systemDarkMode.addListener === 'function') {
            systemDarkMode.addListener(onSystemThemeChange);
        }
    }

    updatePreviewOptions();
})();
