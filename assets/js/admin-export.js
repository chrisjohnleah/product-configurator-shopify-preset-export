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
    const rangeStartField = modal.querySelector('[data-export-field="range_start_id"]');
    const rangeLimitField = modal.querySelector('[data-export-field="range_limit"]');

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

        let rangeStartValue = 0;
        if (rangeStartField) {
            const parsedStart = parseInt(rangeStartField.value, 10);
            if (Number.isFinite(parsedStart) && parsedStart > 0) {
                rangeStartValue = parsedStart;
                scope = 'range';
            }
        }

        if (rangeStartValue > 0 && rangeLimitField) {
            const parsedLimit = parseInt(rangeLimitField.value, 10);
            if (!Number.isFinite(parsedLimit) || parsedLimit <= 0) {
                rangeLimitField.value = '200';
            }
        }

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

    const deleteModal = document.getElementById('mkl-preset-delete-modal');
    const deleteTriggers = document.querySelectorAll('.mkl-preset-delete-modal-trigger');

    if (deleteModal && deleteTriggers.length) {
        const deleteDialog = deleteModal.querySelector('.mkl-preset-export-modal__dialog');
        const deleteScopeRadios = deleteModal.querySelectorAll('input[name="mkl-preset-delete-scope"]');
        const deleteFields = deleteModal.querySelectorAll('[data-delete-field]');
        const deleteSubmitButton = deleteModal.querySelector('[data-role="delete-submit"]');
        const deleteSpinner = deleteModal.querySelector('.mkl-preset-delete-spinner');
        const deleteClosers = deleteModal.querySelectorAll('[data-role="close"]');
        const deleteRangeStartField = deleteModal.querySelector('[data-delete-field="range_start_id"]');
        const deleteRangeLimitField = deleteModal.querySelector('[data-delete-field="range_limit"]');
        const deletePermanentCheckbox = deleteModal.querySelector('[data-delete-permanent]');

        let activeDeleteTrigger = null;

        function setDeleteDefaultScope(defaultScope) {
            deleteScopeRadios.forEach(function (radio) {
                radio.checked = (radio.value === defaultScope);
            });
        }

        function openDeleteModal(trigger) {
            activeDeleteTrigger = trigger;

            deleteModal.dataset.deleteUrl = trigger.dataset.deleteUrl || '';
            deleteModal.dataset.nonce = trigger.dataset.nonce || '';
            deleteModal.dataset.sourceQuery = trigger.dataset.sourceQuery || '';
            deleteModal.dataset.paged = trigger.dataset.paged || '1';
            deleteModal.dataset.perPage = trigger.dataset.perPage || '20';
            deleteModal.dataset.defaultScope = trigger.dataset.defaultScope || 'page';
            deleteModal.dataset.defaultAction = trigger.dataset.defaultAction || '';

            if (deleteSpinner) {
                deleteSpinner.classList.remove('is-active');
                deleteSpinner.style.visibility = 'hidden';
            }

            if (deleteSubmitButton) {
                deleteSubmitButton.disabled = false;
            }

            if (deletePermanentCheckbox) {
                deletePermanentCheckbox.checked = false;
            }

            if (deleteFields && deleteFields.length) {
                deleteFields.forEach(function (field) {
                    field.value = '';
                });
            }

            setDeleteDefaultScope(deleteModal.dataset.defaultScope || 'page');

            deleteModal.removeAttribute('hidden');
            deleteModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('mkl-preset-export-modal-open');

            if (deleteDialog) {
                deleteDialog.focus();
            }
        }

        function closeDeleteModal() {
            const triggerToFocus = activeDeleteTrigger;
            deleteModal.setAttribute('aria-hidden', 'true');
            deleteModal.setAttribute('hidden', 'hidden');
            document.body.classList.remove('mkl-preset-export-modal-open');
            activeDeleteTrigger = null;

            if (triggerToFocus) {
                triggerToFocus.focus();
            }
        }

        function startDelete() {
            if (! activeDeleteTrigger) {
                return;
            }

            const deleteUrl = deleteModal.dataset.deleteUrl || activeDeleteTrigger.dataset.deleteUrl || '';
            const nonce = deleteModal.dataset.nonce || activeDeleteTrigger.dataset.nonce || '';
            const sourceQuery = deleteModal.dataset.sourceQuery || activeDeleteTrigger.dataset.sourceQuery || '';
            const paged = deleteModal.dataset.paged || activeDeleteTrigger.dataset.paged || '1';
            const perPage = deleteModal.dataset.perPage || activeDeleteTrigger.dataset.perPage || '20';
            const actionSlug = deleteModal.dataset.defaultAction || activeDeleteTrigger.dataset.defaultAction || '';

            if (! deleteUrl || ! nonce || ! actionSlug) {
                window.alert('Delete configuration is missing. Please refresh and try again.');
                return;
            }

            let scope = 'page';
            deleteScopeRadios.forEach(function (radio) {
                if (radio.checked) {
                    scope = radio.value;
                }
            });

            const selectedIds = getSelectedIds();

            if ('selection' === scope && ! selectedIds.length) {
                window.alert('Select at least one preset before deleting with "Only selected presets".');
                return;
            }

            let rangeStartValue = 0;
            if (deleteRangeStartField) {
                const parsedStart = parseInt(deleteRangeStartField.value, 10);
                if (Number.isFinite(parsedStart) && parsedStart > 0) {
                    rangeStartValue = parsedStart;
                    scope = 'range';
                }
            }

            if (rangeStartValue > 0 && deleteRangeLimitField) {
                const parsedLimit = parseInt(deleteRangeLimitField.value, 10);
                if (! Number.isFinite(parsedLimit) || parsedLimit <= 0) {
                    deleteRangeLimitField.value = '200';
                }
            }

            if ('selection' !== scope && ! deleteScopeRadios.length && selectedIds.length) {
                scope = 'selection';
            }

            let pageScopeIds = [];
            if ('page' === scope) {
                pageScopeIds = getPageIds();
            }

            let scopeSummary = '';
            switch (scope) {
                case 'selection':
                    scopeSummary = selectedIds.length === 1 ? 'the selected preset' : selectedIds.length + ' selected presets';
                    break;
                case 'all':
                    scopeSummary = 'all presets matching the current filters';
                    break;
                case 'range':
                    scopeSummary = 'presets starting from ID ' + rangeStartValue;
                    if (deleteRangeLimitField && deleteRangeLimitField.value) {
                        scopeSummary += ' (' + deleteRangeLimitField.value + ' presets)';
                    }
                    break;
                default:
                    scopeSummary = 'all presets on this page';
                    break;
            }

            const isPermanent = deletePermanentCheckbox && deletePermanentCheckbox.checked;
            const confirmMessage = isPermanent
                ? 'This will permanently delete ' + scopeSummary + '. This cannot be undone. Continue?'
                : 'This will move ' + scopeSummary + ' to the bin. Continue?';

            if (! window.confirm(confirmMessage)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'post';
            form.action = deleteUrl;
            form.style.display = 'none';

            appendHidden(form, 'action', actionSlug);
            appendHidden(form, '_wpnonce', nonce);
            appendHidden(form, 'source_query', sourceQuery);
            appendHidden(form, 'preset_ids', selectedIds.join(','));
            appendHidden(form, 'scope', scope);
            appendHidden(form, 'paged', paged);
            appendHidden(form, 'per_page', perPage);
            appendHidden(form, 'export_all', scope === 'all' ? '1' : '0');

            if (pageScopeIds.length) {
                appendHidden(form, 'page_scope_ids', pageScopeIds.join(','));
            }

            if (deleteFields && deleteFields.length) {
                deleteFields.forEach(function (field) {
                    if (! field || ! field.dataset || ! field.dataset.deleteField) {
                        return;
                    }

                    const value = field.value || '';
                    if (value === '') {
                        return;
                    }

                    appendHidden(form, field.dataset.deleteField, value);
                });
            }

            if (isPermanent) {
                appendHidden(form, 'delete_permanently', '1');
            }

            document.body.appendChild(form);

            if (deleteSubmitButton) {
                deleteSubmitButton.disabled = true;
            }

            if (deleteSpinner) {
                deleteSpinner.style.visibility = 'visible';
                deleteSpinner.classList.add('is-active');
            }

            form.submit();

            setTimeout(function () {
                if (deleteSpinner) {
                    deleteSpinner.classList.remove('is-active');
                    deleteSpinner.style.visibility = 'hidden';
                }
                if (deleteSubmitButton) {
                    deleteSubmitButton.disabled = false;
                }
                if (form.parentNode) {
                    form.parentNode.removeChild(form);
                }
                closeDeleteModal();
            }, 1500);
        }

        deleteTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openDeleteModal(trigger);
            });
        });

        deleteClosers.forEach(function (closer) {
            closer.addEventListener('click', function (event) {
                event.preventDefault();
                closeDeleteModal();
            });
        });

        deleteModal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeDeleteModal();
            }
        });

        deleteModal.addEventListener('click', function (event) {
            if (event.target && event.target.dataset && event.target.dataset.role === 'close') {
                event.preventDefault();
                closeDeleteModal();
            }
        });

        if (deleteSubmitButton) {
            deleteSubmitButton.addEventListener('click', function (event) {
                event.preventDefault();
                startDelete();
            });
        }
    }

})();
