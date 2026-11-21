// Template builder preview (livewire/template-builder.blade.php): applies "template-preview-patch"
// events from TemplateBuilder to the iframe without reloading it, and lets the admin edit the selected
// section's ornaments right in the preview. The preview is same-origin, so its document is edited directly.
//
// In the preview an ornament can be dragged to move it, scaled by its corner handle (around its anchor
// point) and turned by the handle above it (Shift snaps to 15°). A click selects it and shows a small
// toolbar: turn 15° either way, mirror, switch layer, duplicate, remove. While one is selected the arrow
// keys nudge it (Shift for bigger steps), Delete removes it and Escape lets go.

const TARGET_CLASS = 'builder-ornament-target';
const HANDLE_CLASS = 'builder-ornament-handle';
const ROTATE_CLASS = 'builder-ornament-rotate';
const TOOLBAR_ID = 'builder-ornament-toolbar';

// Injected into the preview: only the ornaments being edited take the pointer.
const EDIT_CSS = `
.${TARGET_CLASS} .inv-ornament { pointer-events: auto; cursor: move; touch-action: none; outline: 1px dashed rgb(54 81 73 / 0.7); outline-offset: 2px; }
.${TARGET_CLASS} .inv-ornament:hover, .${TARGET_CLASS} .inv-ornament.is-dragging { outline: 2px solid #365149; }
.${TARGET_CLASS} .inv-ornament.is-selected { outline: 2px solid #ae543d; }
.${HANDLE_CLASS} { position: absolute; z-index: 1; width: 16px; height: 16px; translate: -50% -50%; border: 2px solid #365149; border-radius: 9999px; background: #fff; cursor: nwse-resize; }
.${ROTATE_CLASS} { position: absolute; z-index: 1; left: 50%; top: -30px; width: 16px; height: 16px; translate: -50% 0; border: 2px solid #365149; border-radius: 9999px; background: #fff; cursor: grab; }
.${ROTATE_CLASS}::after { content: ''; position: absolute; left: 50%; top: 100%; width: 2px; height: 12px; translate: -50% 0; background: #365149; }
#${TOOLBAR_ID} { position: fixed; z-index: 2147483647; display: flex; gap: 2px; padding: 4px; border-radius: 10px; background: #1f2d2a; box-shadow: 0 6px 20px rgb(0 0 0 / 0.25); font: 500 12px/1 system-ui, sans-serif; }
#${TOOLBAR_ID} button { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 0; border-radius: 7px; background: transparent; color: #fff; font-size: 15px; cursor: pointer; }
#${TOOLBAR_ID} button:hover { background: rgb(255 255 255 / 0.16); }
#${TOOLBAR_ID} button:focus-visible { outline: 2px solid #fff; outline-offset: -2px; }
#${TOOLBAR_ID} button[data-action="remove"] { color: #fecaca; }
`;

const DEFAULT_LABELS = {
    rotateLeft: 'Turn left 15°', rotateRight: 'Turn right 15°', flip: 'Mirror horizontally', flipY: 'Mirror vertically',
    layerToBack: 'Move behind the content', layerToFront: 'Move in front of the content', duplicate: 'Duplicate', remove: 'Remove',
};

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
// Any angle as -180..180.
const normalize = (degrees) => ((((degrees + 180) % 360) + 360) % 360) - 180;
const indexOf = (box) => Number(box.dataset.invOrnamentIndex);

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

    if (patch.kind === 'cover-title') {
        // Same attributes as the cover views print (App\Support\CoverTitle).
        doc.querySelectorAll('[data-inv-cover]').forEach((cover) => {
            patch.position ? cover.setAttribute('data-inv-title-position', patch.position) : cover.removeAttribute('data-inv-title-position');
            patch.offset ? cover.style.setProperty('--inv-title-offset', patch.offset) : cover.style.removeProperty('--inv-title-offset');
        });
    }

    if (patch.kind === 'ornament') {
        const selector = `[data-inv-ornament-owner="${patch.owner}"][data-inv-ornament-list="${patch.list}"] [data-inv-ornament-index="${patch.index}"]`;
        doc.querySelectorAll(selector).forEach((box) => {
            const selected = box.classList.contains('is-selected');
            box.setAttribute('style', patch.style);
            box.className = patch.classes;
            box.classList.toggle('is-selected', selected);
        });
    }
}

/**
 * Mark the ornaments of the edited section (data-ornament-owner/-list on the builder root) and
 * give each a resize handle and a rotate handle.
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
            [HANDLE_CLASS, ROTATE_CLASS].forEach((name) => {
                let handle = box.querySelector(`:scope > .${name}`);
                if (active && ! handle) {
                    handle = doc.createElement('span');
                    handle.className = name;
                    handle.setAttribute('aria-hidden', 'true');
                    box.append(handle);
                } else if (! active && handle) {
                    handle.remove();
                }
                if (active && name === HANDLE_CLASS) {
                    placeHandle(box, handle);
                }
            });

            if (! active) {
                box.classList.remove('is-selected');
            }
        });
    });
}

/**
 * The ornament selected in this preview document, or null.
 */
function selectedBox(doc) {
    return doc.querySelector(`.${TARGET_CLASS} .inv-ornament.is-selected`);
}

function removeToolbar(doc) {
    doc.getElementById(TOOLBAR_ID)?.remove();
}

/**
 * Show the toolbar above the selected ornament (below it when there is no room above).
 */
function placeToolbar(doc, labels) {
    const box = selectedBox(doc);

    if (! box) {
        removeToolbar(doc);

        return;
    }

    let toolbar = doc.getElementById(TOOLBAR_ID);

    if (! toolbar) {
        toolbar = doc.createElement('div');
        toolbar.id = TOOLBAR_ID;
        toolbar.setAttribute('role', 'toolbar');
        doc.body.append(toolbar);
    }

    const back = box.closest('.inv-ornaments--back') !== null;
    // Font Awesome is loaded by every invitation page, the preview included.
    const buttons = [
        ['rotate-left', 'fa-rotate-left', labels.rotateLeft],
        ['rotate-right', 'fa-rotate-right', labels.rotateRight],
        ['flip', 'fa-arrows-left-right', labels.flip],
        ['flip-y', 'fa-arrows-up-down', labels.flipY],
        ['layer', back ? 'fa-angles-up' : 'fa-angles-down', back ? labels.layerToFront : labels.layerToBack],
        ['duplicate', 'fa-clone', labels.duplicate],
        ['remove', 'fa-trash-can', labels.remove],
    ];
    toolbar.setAttribute('aria-label', labels.toolbar ?? 'Ornament');
    toolbar.innerHTML = '';
    buttons.forEach(([action, icon, label]) => {
        const button = doc.createElement('button');
        const glyph = doc.createElement('i');
        button.type = 'button';
        button.dataset.action = action;
        button.title = label;
        button.setAttribute('aria-label', label);
        glyph.className = `fa-solid ${icon}`;
        glyph.setAttribute('aria-hidden', 'true');
        button.append(glyph);
        toolbar.append(button);
    });

    const rect = box.getBoundingClientRect();
    const view = doc.defaultView;
    const width = toolbar.offsetWidth;
    const top = rect.top - toolbar.offsetHeight - 40 >= 0 ? rect.top - toolbar.offsetHeight - 40 : Math.min(rect.bottom + 12, view.innerHeight - toolbar.offsetHeight - 4);
    toolbar.style.top = `${Math.max(4, top)}px`;
    toolbar.style.left = `${clamp(rect.left + rect.width / 2 - width / 2, 4, view.innerWidth - width - 4)}px`;
}

function select(doc, box, labels) {
    doc.querySelectorAll('.inv-ornament.is-selected').forEach((other) => other !== box && other.classList.remove('is-selected'));
    box?.classList.add('is-selected');
    placeToolbar(doc, labels);
}

/**
 * Drag an ornament to move it, its corner handle to scale it around its anchor point, or its top
 * handle to turn it. Moves and scales go to actions.place(index, x, y, width), turns to
 * actions.update(index, 'rotate', degrees). A click without moving selects it.
 */
function startDrag(event, actions, labels) {
    const box = event.target.closest('.inv-ornament');
    const group = box?.closest(`.${TARGET_CLASS}`);

    if (! box || ! group || event.button !== 0) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const doc = box.ownerDocument;
    const mode = event.target.classList.contains(HANDLE_CLASS) ? 'resize' : (event.target.classList.contains(ROTATE_CLASS) ? 'rotate' : 'move');
    const area = group.getBoundingClientRect();
    const bounds = box.getBoundingClientRect();
    const centre = { x: bounds.left + bounds.width / 2, y: bounds.top + bounds.height / 2 };
    const angleTo = (x, y) => (Math.atan2(y - centre.y, x - centre.x) * 180) / Math.PI;
    const start = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        x: parseFloat(box.style.left) || 0,
        y: parseFloat(box.style.top) || 0,
        width: parseFloat(box.style.width) || 10,
        rotate: parseFloat(box.style.rotate) || 0,
        angle: angleTo(event.clientX, event.clientY),
    };
    // The anchor point stays put while resizing (the box is translated around it).
    const anchorX = area.left + (start.x / 100) * area.width;
    const anchorY = area.top + (start.y / 100) * area.height;
    const startDistance = Math.hypot(start.pointerX - anchorX, start.pointerY - anchorY) || 1;
    const value = { ...start };
    let moved = false;

    box.classList.add('is-dragging');
    box.setPointerCapture(event.pointerId);
    removeToolbar(doc);

    const move = (moveEvent) => {
        const dx = moveEvent.clientX - start.pointerX;
        const dy = moveEvent.clientY - start.pointerY;
        moved = moved || Math.hypot(dx, dy) > 2;

        if (mode === 'resize') {
            const distance = Math.hypot(moveEvent.clientX - anchorX, moveEvent.clientY - anchorY);
            value.width = round(clamp(start.width * (distance / startDistance), 2, 150));
            box.style.width = `${value.width}%`;
        } else if (mode === 'rotate') {
            const turned = normalize(start.rotate + angleTo(moveEvent.clientX, moveEvent.clientY) - start.angle);
            value.rotate = Math.round(moveEvent.shiftKey ? Math.round(turned / 15) * 15 : turned);
            box.style.rotate = `${value.rotate}deg`;
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

        if (! moved) {
            select(doc, box, labels);
        } else if (mode === 'rotate') {
            actions.update(indexOf(box), 'rotate', String(value.rotate));
            select(doc, box, labels);
        } else {
            actions.place(indexOf(box), value.x, value.y, value.width);
            select(doc, box, labels);
        }
    };

    box.addEventListener('pointermove', move);
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);
}

/**
 * A toolbar button was pressed for the selected ornament.
 */
function runAction(doc, action, actions) {
    const box = selectedBox(doc);

    if (! box) {
        return;
    }

    const index = indexOf(box);
    const rotate = parseFloat(box.style.rotate) || 0;

    if (action === 'rotate-left' || action === 'rotate-right') {
        actions.update(index, 'rotate', String(normalize(rotate + (action === 'rotate-left' ? -15 : 15))));
    } else if (action === 'flip') {
        actions.update(index, 'flip', box.classList.contains('inv-ornament--flip') ? '0' : '1');
    } else if (action === 'flip-y') {
        actions.update(index, 'flip_y', box.classList.contains('inv-ornament--flip-y') ? '0' : '1');
    } else if (action === 'layer') {
        actions.update(index, 'layer', box.closest('.inv-ornaments--back') ? 'front' : 'back');
        removeToolbar(doc);
    } else if (action === 'duplicate') {
        actions.duplicate(index);
        removeToolbar(doc);
    } else if (action === 'remove') {
        actions.remove(index);
        removeToolbar(doc);
    }
}

/**
 * Arrow keys nudge the selected ornament (saved shortly after the last key), Delete removes it,
 * Escape lets go of it.
 */
function onKey(event, actions, labels) {
    const doc = event.target.ownerDocument ?? event.currentTarget;
    const box = selectedBox(doc);

    if (! box || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) {
        return;
    }

    const steps = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

    if (steps[event.key]) {
        event.preventDefault();
        const size = event.shiftKey ? 5 : 0.5;
        const x = round(clamp((parseFloat(box.style.left) || 0) + steps[event.key][0] * size, -50, 150));
        const y = round(clamp((parseFloat(box.style.top) || 0) + steps[event.key][1] * size, -50, 150));
        box.style.left = `${x}%`;
        box.style.top = `${y}%`;
        placeToolbar(doc, labels);
        clearTimeout(box.nudgeTimer);
        box.nudgeTimer = setTimeout(() => actions.place(indexOf(box), x, y, parseFloat(box.style.width) || 10), 400);
    } else if (event.key === 'Delete' || event.key === 'Backspace') {
        event.preventDefault();
        actions.remove(indexOf(box));
        removeToolbar(doc);
    } else if (event.key === 'Escape') {
        box.classList.remove('is-selected');
        removeToolbar(doc);
    }
}

/**
 * Wire the preview frame to the builder root: re-mark targets after every load and whenever
 * Livewire changes the root's data-ornament-* attributes.
 *
 * $actions: { place(index, x, y, width), update(index, key, value), duplicate(index), remove(index) };
 * a plain function is taken as place(). $labels: toolbar button names (translated by the page).
 */
function attach(frame, root, actions, labels = {}) {
    const handlers = typeof actions === 'function' ? { place: actions, update() {}, duplicate() {}, remove() {} } : actions;
    const names = { ...DEFAULT_LABELS, ...labels };

    const setup = () => {
        const doc = documentOf(frame);
        if (! doc) {
            return;
        }
        if (! doc.builderOrnamentEditor) {
            doc.builderOrnamentEditor = true;
            doc.addEventListener('pointerdown', (event) => {
                if (event.target.closest(`#${TOOLBAR_ID}`)) {
                    return;
                }
                if (! event.target.closest(`.${TARGET_CLASS} .inv-ornament`)) {
                    doc.querySelectorAll('.inv-ornament.is-selected').forEach((box) => box.classList.remove('is-selected'));
                    removeToolbar(doc);
                }
                startDrag(event, handlers, names);
            }, true);
            doc.addEventListener('click', (event) => {
                const button = event.target.closest(`#${TOOLBAR_ID} button`);
                if (button) {
                    runAction(doc, button.dataset.action, handlers);
                }
            });
            doc.addEventListener('keydown', (event) => onKey(event, handlers, names));
            doc.addEventListener('scroll', () => placeToolbar(doc, names), { passive: true });
        }
        syncTargets(frame, root);
    };

    frame.addEventListener('load', setup);
    setup();

    new MutationObserver(() => {
        syncTargets(frame, root);
        const doc = documentOf(frame);
        if (doc) {
            placeToolbar(doc, names);
        }
    }).observe(root, { attributes: true, attributeFilter: ['data-ornament-owner', 'data-ornament-list'] });

    return {
        apply(patch) {
            applyPatch(frame, patch);
            syncTargets(frame, root);
            const doc = documentOf(frame);
            if (doc) {
                placeToolbar(doc, names);
            }
        },
    };
}

window.builderPreview = { attach };
