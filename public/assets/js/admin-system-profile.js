(function () {
    const form = document.getElementById('systemProfileForm');

    if (!form) {
        return;
    }

    const companyInput = document.getElementById('company_name');
    const commercialInput = document.getElementById('commercial_name');
    const systemInput = document.getElementById('system_name');
    const sloganInput = document.getElementById('slogan');
    const descriptionInput = document.getElementById('description');
    const descriptionCounter = document.getElementById('descriptionCounter');
    const logoInput = document.getElementById('logo');
    const removeLogoInput = document.getElementById('remove_logo');
    const logoPreviewBox = document.getElementById('systemLogoPreviewBox');
    const logoFileName = document.getElementById('systemLogoFileName');
    const resetButton = document.getElementById('resetSystemProfileForm');

    const previewCompany = document.getElementById('brandPreviewCompany');
    const previewCommercial = document.getElementById('brandPreviewCommercial');
    const previewSystem = document.getElementById('brandPreviewSystem');
    const previewSlogan = document.getElementById('brandPreviewSlogan');
    const previewLogo = document.getElementById('brandPreviewLogo');

    const originalLogoImage = logoPreviewBox ? logoPreviewBox.querySelector('img') : null;
    const originalLogoSrc = originalLogoImage ? originalLogoImage.getAttribute('data-original-src') || originalLogoImage.src : '';

    function textOrFallback(value, fallback) {
        const cleanValue = String(value || '').trim();
        return cleanValue !== '' ? cleanValue : fallback;
    }

    function syncTextPreview() {
        if (previewCompany && companyInput) {
            previewCompany.textContent = textOrFallback(companyInput.value, 'Nombre de la empresa');
        }

        if (previewCommercial && commercialInput) {
            previewCommercial.textContent = textOrFallback(commercialInput.value, 'Nombre comercial');
        }

        if (previewSystem && systemInput) {
            previewSystem.textContent = textOrFallback(systemInput.value, 'Nombre del sistema');
        }

        if (previewSlogan && sloganInput) {
            previewSlogan.textContent = textOrFallback(sloganInput.value, 'El eslogan aparecerá en este espacio.');
        }
    }

    function updateDescriptionCounter() {
        if (descriptionInput && descriptionCounter) {
            descriptionCounter.textContent = String(descriptionInput.value.length);
        }
    }

    function renderLogo(src) {
        if (logoPreviewBox) {
            logoPreviewBox.innerHTML = '';

            if (src) {
                const image = document.createElement('img');
                image.src = src;
                image.alt = 'Vista previa del logo institucional';
                image.id = 'systemLogoPreview';
                logoPreviewBox.appendChild(image);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'system-profile-logo-placeholder';
                placeholder.innerHTML = '<i class="fa-regular fa-image"></i><span>Sin logo</span>';
                logoPreviewBox.appendChild(placeholder);
            }
        }

        if (previewLogo) {
            previewLogo.innerHTML = '';

            if (src) {
                const image = document.createElement('img');
                image.src = src;
                image.alt = 'Logo de vista previa';
                previewLogo.appendChild(image);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-building';
                previewLogo.appendChild(icon);
            }
        }
    }

    [companyInput, commercialInput, systemInput, sloganInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', syncTextPreview);
        }
    });

    if (descriptionInput) {
        descriptionInput.addEventListener('input', updateDescriptionCounter);
    }

    if (logoInput) {
        logoInput.addEventListener('change', function () {
            const file = logoInput.files && logoInput.files[0] ? logoInput.files[0] : null;

            if (!file) {
                if (logoFileName) {
                    logoFileName.textContent = 'Ningún archivo nuevo seleccionado';
                }
                renderLogo(removeLogoInput && removeLogoInput.checked ? '' : originalLogoSrc);
                return;
            }

            if (logoFileName) {
                logoFileName.textContent = file.name;
            }

            if (removeLogoInput) {
                removeLogoInput.checked = false;
            }

            const reader = new FileReader();
            reader.addEventListener('load', function () {
                renderLogo(String(reader.result || ''));
            });
            reader.readAsDataURL(file);
        });
    }

    if (removeLogoInput) {
        removeLogoInput.addEventListener('change', function () {
            if (removeLogoInput.checked) {
                if (logoInput) {
                    logoInput.value = '';
                }
                if (logoFileName) {
                    logoFileName.textContent = 'El logo actual se quitará al guardar';
                }
                renderLogo('');
            } else {
                if (logoFileName) {
                    logoFileName.textContent = 'Ningún archivo nuevo seleccionado';
                }
                renderLogo(originalLogoSrc);
            }
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            window.setTimeout(function () {
                if (logoInput) {
                    logoInput.value = '';
                }
                if (logoFileName) {
                    logoFileName.textContent = 'Ningún archivo nuevo seleccionado';
                }
                renderLogo(originalLogoSrc);
                syncTextPreview();
                updateDescriptionCounter();
            }, 0);
        });
    }

    syncTextPreview();
    updateDescriptionCounter();
})();
