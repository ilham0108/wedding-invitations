// Template builder preview (livewire/template-builder.blade.php): applies "template-preview-patch"
// events from TemplateBuilder to the iframe without reloading it, and lets the admin drag and resize
// the selected section's ornaments right in the preview. The preview is same-origin, so its
// document is edited directly.

const TARGET_CLASS = 'builder-ornament-target';
const HANDLE_CLASS = 'builder-ornament-handle';

// Injected into the preview: only the ornaments being edited take the pointer.
const EDIT_CSS = `
.${TARGET_CLASS} .inv-ornament { pointer-events: auto; cursor: move; touch-action: none; outline: 1px dashed rgb(54 81 73 / 0.7); outline-offset: 2px; }
.${TARGET_CLASS} .inv-ornament:hover, .${TARGET_CLASS} .inv-ornament.is-dragging { outline: 2px solid #365149; }
.${HANDLE_CLASS} { position: absolute; z-index: 1; width: 16px; height: 16px; translate: -50% -50%; border: 2px solid #365149; border-radius: 9999px; background: #fff; cursor: nwse-resize; }
`;

/**
 * Put the handle on the corner opposite the anchor point: ornaments are usually anchored on a section
 * edge, so that corner faces into the section instead of being clipped. Works for mirrored boxes too,
 * since the handle is mirrored along with its box.
 */
function placeHandle(box, handle) {
    const [anchorX = 50, anchorY = 50] = (box.style.translate || '').split(' ').map((value) => Math.abs(parseFloat(value)) || 0);
    const centered = anchorX === 50 && anchorY === 50;

    handle.style.left = `${centered ? 100 : 100 - anchorX}%`;
    handle.style.top = `${centered ? 100 : 100 - anchorY}%`;
}

function documentOf(frame) {
    try {
        return frame?.contentDocument?.body ? frame.contentDocument : null;
    } catch {
        return null;
    }
}

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const round = (value) => Math.round(value * 10) / 10;

/**
 * Apply one patch built by TemplateBuilder (theme | attributes | section-class | ornament).
 */
function applyPatch(frame, patch) {
    const doc = documentOf(frame);

    if (! doc || ! patch) {
        return;
    }

    if (patch.kind === 'theme') {
        // A later :root rule wins over the one rendered in <head>.
        let style = doc.getElementById('builder-live-theme');
        if (! style) {
            style = doc.createElement('style');
            style.id = 'builder-live-theme';
            doc.head.append(style);
        }
        style.textContent = `:root { ${patch.css} }`;

        if (! doc.querySelector(`link[href="${CSS.escape(patch.font)}"]`)) {
            const link = doc.createElement('link');
            link.rel = 'stylesheet';
            link.href = patch.font;
            doc.head.append(link);
        }

        doc.body.className = doc.body.className.replace(/\binv-btn-\S+/, `inv-btn-${patch.button}`);

        Object.entries(patch.attributes ?? {}).forEach(([name, value]) => (value === null ? doc.body.removeAttribute(name) : doc.body.setAttribute(name, value)));
    }

    if (patch.kind === 'attributes') {
        Object.entries(patch.attributes).forEach(([name, value]) => doc.body.setAttribute(name, value));
    }

    if (patch.kind === 'section-class') {
        const section = doc.getElementById(`section-${patch.owner}`);
        if (section) {
            [...section.classList].filter((name) => name.startsWith(patch.prefix)).forEach((name) => section.classList.remove(name));
            if (patch.value) {
                section.classList.add(patch.prefix + patch.value);
            }
        }
    }

    if (patch.kind === 'ornament') {
        const selector = `[data-inv-ornament-owner="${patch.owner}"][data-inv-ornament-list="${patch.list}"] [data-inv-ornament-index="${patch.index}"]`;
        doc.querySelectorAll(selector).forEach((box) => {
            box.setAttribute('style', patch.style);
            box.className = patch.classes;
        });
    }
}

/**
 * Mark the ornaments of the edited section (data-ornament-owner/-list on the builder root) and
 * give each a resize handle.
 */
function syncTargets(frame, root) {
    const doc = documentOf(frame);

    if (! doc) {
        return;
    }

    if (! doc.getElementById('builder-ornament-edit')) {
        const style = doc.createElement('style');
        style.id = 'builder-ornament-edit';
        style.textContent = EDIT_CSS;
        doc.head.append(style);
    }

    const owner = root.dataset.ornamentOwner;
    const list = root.dataset.ornamentList;

    doc.querySelectorAll('[data-inv-ornaments]').forEach((group) => {
        const active = Boolean(owner) && group.dataset.invOrnamentOwner === owner && group.dataset.invOrnamentList === list;
        group.classList.toggle(TARGET_CLASS, active);

        group.querySelectorAll('.inv-ornament').forEach((box) => {
            let handle = box.querySelector(`:scope > .${HANDLE_CLASS}`);
            if (active && ! handle) {
                handle = doc.createElement('span');
                handle.className = HANDLE_CLASS;
                box.append(handle);
            } else if (! active && handle) {
                handle.remove();
            }
            if (active) {
                placeHandle(box, handle);
            }
        });
    });
}

/**
 * Drag an ornament to move it; drag its handle to scale it around its anchor point.
 * The new values go to commit(index, x, y, width) once the pointer is released.
 */
function startDrag(event, commit) {
    const box = event.target.closest('.inv-ornament');
    const group = box?.closest(`.${TARGET_CLASS}`);

    if (! box || ! group || event.button !== 0) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const resizing = event.target.classList.contains(HANDLE_CLASS);
    const area = group.getBoundingClientRect();
    const start = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        x: parseFloat(box.style.left) || 0,
        y: parseFloat(box.style.top) || 0,
        width: parseFloat(box.style.width) || 10,
    };
    // The anchor point stays put while resizing (the box is translated around it).
    const anchorX = area.left + (start.x / 100) * area.width;
    const anchorY = area.top + (start.y / 100) * area.height;
    const startDistance = Math.hypot(start.pointerX - anchorX, start.pointerY - anchorY) || 1;
    const value = { ...start };
    let moved = false;

    box.classList.add('is-dragging');
    box.setPointerCapture(event.pointerId);

    const move = (moveEvent) => {
        const dx = moveEvent.clientX - start.pointerX;
        const dy = moveEvent.clientY - start.pointerY;
        moved = moved || Math.hypot(dx, dy) > 2;

        if (resizing) {
            const distance = Math.hypot(moveEvent.clientX - anchorX, moveEvent.clientY - anchorY);
            value.width = round(clamp(start.width * (distance / startDistance), 2, 150));
            box.style.width = `${value.width}%`;
        } else {
            value.x = round(clamp(start.x + (dx / area.width) * 100, -50, 150));
            value.y = round(clamp(start.y + (dy / area.height) * 100, -50, 150));
            box.style.left = `${value.x}%`;
            box.style.top = `${value.y}%`;
        }
    };

    const end = () => {
        box.classList.remove('is-dragging');
        box.removeEventListener('pointermove', move);
        box.removeEventListener('pointerup', end);
        box.removeEventListener('pointercancel', end);

        if (moved) {
            commit(Number(box.dataset.invOrnamentIndex), value.x, value.y, value.width);
        }
    };

    box.addEventListener('pointermove', move);
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);
}

/**
 * Wire the preview frame to the builder root: re-mark targets after every load and whenever
 * Livewire changes the root's data-ornament-* attributes.
 */
function attach(frame, root, commit) {
    const setup = () => {
        const doc = documentOf(frame);
        if (! doc) {
            return;
        }
        if (! doc.builderOrnamentEditor) {
            doc.builderOrnamentEditor = true;
            doc.addEventListener('pointerdown', (event) => startDrag(event, commit), true);
        }
        syncTargets(frame, root);
    };

    frame.addEventListener('load', setup);
    setup();

    new MutationObserver(() => syncTargets(frame, root))
        .observe(root, { attributes: true, attributeFilter: ['data-ornament-owner', 'data-ornament-list'] });

    return {
        apply(patch) {
            applyPatch(frame, patch);
            syncTargets(frame, root);
        },
    };
}

window.builderPreview = { attach };
