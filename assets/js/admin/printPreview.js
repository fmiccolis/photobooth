/* eslint n/no-unsupported-features/node-builtins: "off" */
/* globals csrf */
$(function () {
    const container = document.querySelector('[data-print-preview]');
    if (!container || typeof environment === 'undefined') {
        return;
    }

    const form = container.closest('form');
    const stage = container.querySelector('[data-print-preview-stage]');
    const image = container.querySelector('[data-print-preview-image]');
    const qrBox = container.querySelector('[data-print-preview-qr]');
    const resizeHandle = container.querySelector('[data-print-preview-qr-resize]');
    const hint = container.querySelector('[data-print-preview-hint]');
    const status = container.querySelector('[data-print-preview-status]');
    const info = container.querySelector('[data-print-preview-info]');
    const notes = container.querySelector('[data-print-preview-notes]');
    const warnings = container.querySelector('[data-print-preview-warnings]');
    const refreshButton = container.querySelector('[data-print-preview-refresh]');
    const fields = {
        position: form.querySelector('[name="print[qrPosition]"]'),
        x: form.querySelector('[name="print[qrX]"]'),
        y: form.querySelector('[name="print[qrY]"]'),
        scale: form.querySelector('[name="print[qrScale]"]')
    };
    // Sections which change how a new picture is processed and printed
    const fieldPattern = /^(picture|textonpicture|filters|print|textonprint)\[/;
    const debounceDelay = 500;
    const keyboardStep = 0.5;
    const outlineIdleDelay = 5000;
    const outlineColor = 'var(--brand-1, #1B3FAA)';

    let debounceTimer = null;
    let outlineTimer = null;
    let controller = null;
    let initialized = false;
    let print = null;
    let qr = null;
    let dragging = false;
    let outlineShownOnce = false;

    function showStatus(text) {
        status.textContent = text;
        status.style.display = text ? '' : 'none';
    }

    function showList(element, list) {
        element.innerHTML = '';
        (list || []).forEach(function (text) {
            const item = document.createElement('li');
            item.textContent = text;
            element.appendChild(item);
        });
        element.style.display = element.children.length ? '' : 'none';
    }

    function showWarnings(list) {
        showList(warnings, list);
    }

    function showNotes(codes) {
        showList(
            notes,
            (codes || []).map(function (code) {
                return container.getAttribute('data-label-note-' + code) || code;
            })
        );
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

    // The outline and the resize handle are only shown while the QR code is used: dragging and
    // resizing always work, the outline just doesn't stay in the way when looking at the preview.
    function setOutlineVisible(visible) {
        qrBox.style.borderColor = visible ? outlineColor : 'transparent';
        qrBox.style.backgroundColor = visible ? 'rgba(255, 255, 255, 0.15)' : 'transparent';
        resizeHandle.style.opacity = visible ? '1' : '0';
    }

    function showOutline() {
        if (qr === null) {
            return;
        }
        setOutlineVisible(true);
        clearTimeout(outlineTimer);
        outlineTimer = setTimeout(hideOutline, outlineIdleDelay);
    }

    function hideOutline() {
        clearTimeout(outlineTimer);
        if (dragging) {
            return;
        }
        setOutlineVisible(false);
    }

    function placeQrBox() {
        const visible = qr !== null;
        qrBox.style.display = visible ? '' : 'none';
        hint.style.display = visible ? '' : 'none';
        if (!visible) {
            return;
        }
        // On the first preview show where the QR code can be dragged, if it's freely placed
        if (!outlineShownOnce) {
            outlineShownOnce = true;
            if (fields.position && fields.position.value === 'custom') {
                showOutline();
            }
        }
        qrBox.style.left = qr.x + '%';
        qrBox.style.top = qr.y + '%';
        qrBox.style.width = qr.width + '%';
        qrBox.style.height = qr.height + '%';
    }

    // Settings which don't apply to the selected position are dimmed
    function highlightPositionFields() {
        if (!fields.position) {
            return;
        }
        const custom = fields.position.value === 'custom';
        ['print:print_qrSize', 'print:print_qrOffset'].forEach(function (id) {
            const card = document.getElementById(id);
            if (card) {
                card.style.opacity = custom ? '0.5' : '';
            }
        });
        ['print:print_qrX', 'print:print_qrY', 'print:print_qrScale'].forEach(function (id) {
            const card = document.getElementById(id);
            if (card) {
                card.style.opacity = custom ? '' : '0.5';
            }
        });
    }

    function setField(field, value) {
        if (!field || String(field.value) === String(value)) {
            return;
        }
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Stores the QR area shown on the preview as 'custom' position in the form
    function commitQrBox() {
        const shortSide = Math.min(print.width, print.height);
        const scale = ((qr.width / 100) * print.width * 100) / shortSide;
        setField(fields.position, 'custom');
        setField(fields.x, Math.round(qr.x * 10) / 10);
        setField(fields.y, Math.round(qr.y * 10) / 10);
        setField(fields.scale, Math.round(scale * 10) / 10);
        highlightPositionFields();
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function startDrag(event) {
        const rect = stage.getBoundingClientRect();
        if (qr === null || event.button !== 0 || rect.width === 0 || rect.height === 0) {
            return;
        }
        event.preventDefault();
        qrBox.focus();
        dragging = true;
        clearTimeout(outlineTimer);
        setOutlineVisible(true);

        const resize = event.target === resizeHandle;
        const start = { x: event.clientX, y: event.clientY, qr: Object.assign({}, qr) };
        const startSize = (start.qr.width / 100) * rect.width;
        const minSize = (qr.minScale / 100) * Math.min(rect.width, rect.height);
        qrBox.setPointerCapture(event.pointerId);

        function onMove(moveEvent) {
            const dx = moveEvent.clientX - start.x;
            const dy = moveEvent.clientY - start.y;
            if (resize) {
                // The QR code is square: grow by the larger of the two movements, keep the top left corner
                const maxSize = Math.min(
                    (rect.width * (100 - start.qr.x)) / 100,
                    (rect.height * (100 - start.qr.y)) / 100
                );
                const size = clamp(startSize + Math.max(dx, dy), minSize, maxSize);
                qr.width = (size / rect.width) * 100;
                qr.height = (size / rect.height) * 100;
            } else {
                qr.x = clamp(start.qr.x + (dx / rect.width) * 100, 0, 100 - qr.width);
                qr.y = clamp(start.qr.y + (dy / rect.height) * 100, 0, 100 - qr.height);
            }
            placeQrBox();
        }

        function onEnd() {
            qrBox.removeEventListener('pointermove', onMove);
            qrBox.removeEventListener('pointerup', onEnd);
            qrBox.removeEventListener('pointercancel', onEnd);
            dragging = false;
            if (qr.x !== start.qr.x || qr.y !== start.qr.y || qr.width !== start.qr.width) {
                commitQrBox();
            }
            showOutline();
        }

        qrBox.addEventListener('pointermove', onMove);
        qrBox.addEventListener('pointerup', onEnd);
        qrBox.addEventListener('pointercancel', onEnd);
    }

    function moveWithKeyboard(event) {
        const moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        if (qr === null || !moves[event.key]) {
            return;
        }
        event.preventDefault();
        const step = event.shiftKey ? keyboardStep * 10 : keyboardStep;
        qr.x = clamp(qr.x + moves[event.key][0] * step, 0, 100 - qr.width);
        qr.y = clamp(qr.y + moves[event.key][1] * step, 0, 100 - qr.height);
        placeQrBox();
        commitQrBox();
        showOutline();
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
                stage.style.display = '';
                print = { width: data.width, height: data.height };
                if (!dragging) {
                    qr = data.qr || null;
                    placeQrBox();
                }
                info.textContent =
                    container.dataset.labelSize +
                    ': ' +
                    data.width +
                    ' × ' +
                    data.height +
                    ' px · ' +
                    container.dataset.labelSource +
                    ': ' +
                    data.source +
                    ' (' +
                    (container.getAttribute('data-label-source-' + data.sourceType) || data.sourceType) +
                    ')';
                showNotes(data.notes);
                showWarnings(data.warnings);
                showStatus('');
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                showNotes([]);
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
    if (fields.position) {
        fields.position.addEventListener('change', function () {
            highlightPositionFields();
            // A preset position is not placed by hand: hide the outline right away
            if (fields.position.value !== 'custom') {
                hideOutline();
            }
        });
    }
    highlightPositionFields();

    qrBox.addEventListener('pointerdown', startDrag);
    qrBox.addEventListener('keydown', moveWithKeyboard);
    // Pointing at the QR code or focusing it shows the outline again, it hides after some idle time
    qrBox.addEventListener('pointermove', function () {
        if (!dragging) {
            showOutline();
        }
    });
    qrBox.addEventListener('focus', showOutline);
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
