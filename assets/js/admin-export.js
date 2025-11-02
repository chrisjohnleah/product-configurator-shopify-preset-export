(function () {
    'use strict';

    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('#the-list input[type="checkbox"][name="post[]"]:checked');
        const ids = [];

        checkboxes.forEach(function (checkbox) {
            if (checkbox.value) {
                ids.push(checkbox.value);
            }
        });

        return ids;
    }

    function appendHidden(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    function getPageIds() {
        const checkboxes = document.querySelectorAll('#the-list input[type="checkbox"][name="post[]"]');
        const ids = [];

        checkboxes.forEach(function (checkbox) {
            if (checkbox.value) {
                ids.push(checkbox.value);
            }
        });

        return ids;
    }

    const modal = document.getElementById('mkl-preset-export-modal');
    const triggers = document.querySelectorAll('.mkl-preset-export-modal-trigger');

    if (!modal || !triggers.length) {
        return;
    }

    const dialog = modal.querySelector('.mkl-preset-export-modal__dialog');
    const formatSelect = modal.querySelector('[data-export-format]');
    const scopeRadios = modal.querySelectorAll('input[name="mkl-preset-export-scope"]');
    const extraFields = modal.querySelectorAll('[data-export-field]');
    const submitButton = modal.querySelector('[data-role="submit"]');
    const spinner = modal.querySelector('.mkl-preset-export-spinner');
    const closers = modal.querySelectorAll('[data-role="close"]');

    let activeTrigger = null;

    function setDefaultScope(defaultScope) {
        scopeRadios.forEach(function (radio) {
            radio.checked = (radio.value === defaultScope);
        });
    }

    function setDefaultFormat(defaultFormat) {
        if (!formatSelect || !formatSelect.options.length) {
            return;
        }

        let matched = false;
        for (let i = 0; i < formatSelect.options.length; i += 1) {
            const option = formatSelect.options[i];
            if (option.value === defaultFormat) {
                formatSelect.selectedIndex = i;
                matched = true;
                break;
            }
        }

        if (!matched) {
            formatSelect.selectedIndex = 0;
        }
    }

    function openModal(trigger) {
        activeTrigger = trigger;

        modal.dataset.exportUrl = trigger.dataset.exportUrl || '';
        modal.dataset.nonce = trigger.dataset.nonce || '';
        modal.dataset.sourceQuery = trigger.dataset.sourceQuery || '';
        modal.dataset.paged = trigger.dataset.paged || '1';
        modal.dataset.perPage = trigger.dataset.perPage || '20';
        modal.dataset.defaultScope = trigger.dataset.defaultScope || 'page';
        modal.dataset.defaultAction = trigger.dataset.defaultAction || '';
        modal.dataset.defaultFormat = trigger.dataset.defaultFormat || 'shopify';

        if (spinner) {
            spinner.classList.remove('is-active');
            spinner.style.visibility = 'hidden';
        }

        if (submitButton) {
            submitButton.disabled = false;
        }

        setDefaultScope(modal.dataset.defaultScope || 'page');
        setDefaultFormat(modal.dataset.defaultFormat || 'shopify');

        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mkl-preset-export-modal-open');

        if (dialog) {
            dialog.focus();
        }
    }

    function closeModal() {
        const triggerToFocus = activeTrigger;
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('mkl-preset-export-modal-open');
        activeTrigger = null;
        if (triggerToFocus) {
            triggerToFocus.focus();
        }
    }

    function startExport() {
        if (!activeTrigger) {
            return;
        }

        const exportUrl = modal.dataset.exportUrl || activeTrigger.dataset.exportUrl || '';
        const nonce = modal.dataset.nonce || activeTrigger.dataset.nonce || '';
        const sourceQuery = modal.dataset.sourceQuery || activeTrigger.dataset.sourceQuery || '';
        const paged = modal.dataset.paged || activeTrigger.dataset.paged || '1';
        const perPage = modal.dataset.perPage || activeTrigger.dataset.perPage || '20';
        const defaultAction = modal.dataset.defaultAction || activeTrigger.dataset.defaultAction || '';

        if (!exportUrl || !nonce) {
            window.alert('Export configuration is missing. Please refresh and try again.');
            return;
        }

        let actionSlug = defaultAction;
        let formatValue = '';

        if (formatSelect && formatSelect.options.length) {
            const option = formatSelect.options[formatSelect.selectedIndex];
            if (option) {
                formatValue = option.value || '';
                if (option.dataset && option.dataset.action) {
                    actionSlug = option.dataset.action;
                }
            }
        }

        if (!actionSlug) {
            window.alert('Unable to determine export format. Please choose an export option.');
            return;
        }

        let scope = 'page';
        scopeRadios.forEach(function (radio) {
            if (radio.checked) {
                scope = radio.value;
            }
        });

        const selectedIds = getSelectedIds();
        if (scope === 'selection' && !selectedIds.length) {
            window.alert('Select at least one preset before exporting with "Only selected presets".');
            return;
        }

        if (scope !== 'selection' && !scopeRadios.length && selectedIds.length) {
            scope = 'selection';
        }

        let pageScopeIds = [];
        if (scope === 'page') {
            pageScopeIds = getPageIds();
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = exportUrl;
        form.style.display = 'none';

        appendHidden(form, 'action', actionSlug);
        appendHidden(form, '_wpnonce', nonce);
        appendHidden(form, 'source_query', sourceQuery);
        appendHidden(form, 'preset_ids', selectedIds.join(','));
        appendHidden(form, 'scope', scope);
        appendHidden(form, 'paged', paged);
        appendHidden(form, 'per_page', perPage);
        appendHidden(form, 'export_all', scope === 'all' ? '1' : '0');

        if (formatValue) {
            appendHidden(form, 'export_format', formatValue);
        }

        if (pageScopeIds.length) {
            appendHidden(form, 'page_scope_ids', pageScopeIds.join(','));
        }

        if (extraFields && extraFields.length) {
            extraFields.forEach(function (field) {
                if (!field || !field.dataset || !field.dataset.exportField) {
                    return;
                }

                const value = field.value || '';
                if (value === '') {
                    return;
                }

                appendHidden(form, field.dataset.exportField, value);
            });
        }

        document.body.appendChild(form);

        if (submitButton) {
            submitButton.disabled = true;
        }

        if (spinner) {
            spinner.style.visibility = 'visible';
            spinner.classList.add('is-active');
        }

        form.submit();

        setTimeout(function () {
            if (spinner) {
                spinner.classList.remove('is-active');
                spinner.style.visibility = 'hidden';
            }
            if (submitButton) {
                submitButton.disabled = false;
            }
            if (form.parentNode) {
                form.parentNode.removeChild(form);
            }
            closeModal();
        }, 1500);
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openModal(trigger);
        });
    });

    closers.forEach(function (closer) {
        closer.addEventListener('click', function (event) {
            event.preventDefault();
            closeModal();
        });
    });

    modal.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target && event.target.dataset && event.target.dataset.role === 'close') {
            event.preventDefault();
            closeModal();
        }
    });

    if (submitButton) {
        submitButton.addEventListener('click', function (event) {
            event.preventDefault();
            startExport();
        });
    }
})();
