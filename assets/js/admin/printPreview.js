/* eslint n/no-unsupported-features/node-builtins: "off" */
/* globals csrf */
$(function () {
    const container = document.querySelector('[data-print-preview]');
    if (!container || typeof environment === 'undefined') {
        return;
    }

    const form = container.closest('form');
    const image = container.querySelector('[data-print-preview-image]');
    const status = container.querySelector('[data-print-preview-status]');
    const info = container.querySelector('[data-print-preview-info]');
    const warnings = container.querySelector('[data-print-preview-warnings]');
    const refreshButton = container.querySelector('[data-print-preview-refresh]');
    const fieldPattern = /^(print|textonprint)\[/;
    const debounceDelay = 500;

    let debounceTimer = null;
    let controller = null;
    let initialized = false;

    function showStatus(text) {
        status.textContent = text;
        status.style.display = text ? '' : 'none';
    }

    function showWarnings(list) {
        warnings.innerHTML = '';
        (list || []).forEach(function (warning) {
            const item = document.createElement('li');
            item.textContent = warning;
            warnings.appendChild(item);
        });
        warnings.style.display = warnings.children.length ? '' : 'none';
    }

    function collectFormData() {
        const data = new FormData();
        // Only the print layout sections are sent: the preview reflects unsaved changes.
        $(form)
            .serializeArray()
            .forEach(function (field) {
                if (fieldPattern.test(field.name)) {
                    data.append(field.name, field.value);
                }
            });
        if (typeof csrf !== 'undefined') {
            data.append(csrf.key, csrf.token);
        }
        return data;
    }

    function refresh() {
        initialized = true;
        clearTimeout(debounceTimer);
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        showStatus(container.dataset.labelLoading);

        fetch(environment.publicFolders.api + '/printPreview.php', {
            method: 'POST',
            body: collectFormData(),
            cache: 'no-store',
            signal: controller.signal
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error(response.status + ' ' + response.statusText);
                });
            })
            .then(function (data) {
                if (data.status !== 'ok') {
                    throw new Error(data.error || '');
                }
                image.src = data.image;
                image.style.display = '';
                info.textContent =
                    container.dataset.labelSize +
                    ': ' +
                    data.width +
                    ' × ' +
                    data.height +
                    ' px · ' +
                    container.dataset.labelSource +
                    ': ' +
                    data.source;
                showWarnings(data.warnings);
                showStatus('');
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                showWarnings([]);
                showStatus(container.dataset.labelError + (error.message ? ': ' + error.message : ''));
            });
    }

    function scheduleRefresh() {
        if (!initialized) {
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, debounceDelay);
    }

    $(form).on('input change', 'input, select, textarea', function () {
        if (this.name && fieldPattern.test(this.name)) {
            scheduleRefresh();
        }
    });

    refreshButton.addEventListener('click', refresh);

    // Render the first preview only once the card is visible, building it is not free on a Pi.
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                refresh();
            }
        });
        observer.observe(container);
    } else {
        refresh();
    }
});
