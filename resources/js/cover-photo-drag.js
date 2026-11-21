// Move, resize and turn a freely placed cover photo (cover "photo_placement" = free) inside an editor's
// preview frame. The preview is same-origin, so the editor page reaches into it. A photo carries its
// section key (data-inv-cover-photo-free); it can only be edited while that section's settings are open,
// i.e. the page has [data-cover-photo-editor="<key>"] (or "*" in the template builder, where content is
// per type). Editable photos get the class "is-editable": a dashed outline, a corner handle that resizes
// around the photo's centre, and a handle above it that turns it (Shift snaps to 15°).
// On release the new centre (percent of the cover), width (percent) and tilt (degrees) go out as a
// "cover-photo-placed" window event with the section key; the matching panel saves them.
//
// A photo on the "behind the text" layer is covered by the text block, so the photo is found by hit-testing
// every element under the pointer (elementsFromPoint), not by the event target.

const PHOTO = '[data-inv-cover-photo-free]';
const RESIZE = 'cover-photo-resize';
const ROTATE = 'cover-photo-rotate';

const EDIT_CSS = `
${PHOTO}.is-editable { cursor: move; touch-action: none; outline: 1px dashed rgb(54 81 73 / 0.7); outline-offset: 4px; }
${PHOTO}.is-editable:hover, ${PHOTO}.is-dragging { outline: 2px dashed #365149; }
.${RESIZE}, .${ROTATE} { display: none; position: absolute; z-index: 1; width: 18px; height: 18px; border: 2px solid #365149; border-radius: 9999px; background: #fff; }
${PHOTO}.is-editable .${RESIZE}, ${PHOTO}.is-editable .${ROTATE} { display: block; }
.${RESIZE} { right: -9px; bottom: -9px; cursor: nwse-resize; }
.${ROTATE} { left: 50%; top: -34px; translate: -50% 0; cursor: grab; }
.${ROTATE}::after { content: ''; position: absolute; left: 50%; top: 100%; width: 2px; height: 14px; translate: -50% 0; background: #365149; }
`;

const round = (value) => Math.round(value * 10) / 10;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
// Any angle as -180..180.
const normalize = (degrees) => ((((degrees + 180) % 360) + 360) % 360) - 180;

function editorOpenFor(section) {
    return [...document.querySelectorAll('[data-cover-photo-editor]')]
        .some((panel) => panel.dataset.coverPhotoEditor === '*' || panel.dataset.coverPhotoEditor === section);
}

/**
 * The editable photo under the pointer (also when other content is painted over it), and which part
 * was hit: a handle or the photo itself.
 */
function hitAt(doc, event) {
    for (const element of doc.elementsFromPoint(event.clientX, event.clientY)) {
        const photo = element.closest(PHOTO);

        if (photo?.classList.contains('is-editable')) {
            const mode = element.classList.contains(RESIZE) ? 'resize' : (element.classList.contains(ROTATE) ? 'rotate' : 'move');

            return { photo, mode };
        }
    }

    return null;
}

function startDrag(event, { photo, mode }) {
    const section = photo.dataset.invCoverPhotoFree ?? '';
    const area = photo.offsetParent?.getBoundingClientRect();

    if (! area) {
        return;
    }

    // Taken over from whatever was hit (e.g. the text block above the photo).
    event.preventDefault();
    event.stopPropagation();

    const box = photo.getBoundingClientRect();
    const centre = { x: box.left + box.width / 2, y: box.top + box.height / 2 };
    const angleTo = (x, y) => (Math.atan2(y - centre.y, x - centre.x) * 180) / Math.PI;
    const start = {
        pointerX: event.clientX,
        pointerY: event.clientY,
        x: parseFloat(photo.style.left) || 50,
        y: parseFloat(photo.style.top) || 30,
        width: parseFloat(photo.style.getPropertyValue('--inv-cover-photo-width')) || 50,
        tilt: parseFloat(photo.style.rotate) || 0,
        distance: Math.hypot(event.clientX - centre.x, event.clientY - centre.y) || 1,
        angle: angleTo(event.clientX, event.clientY),
    };
    const value = { x: start.x, y: start.y, width: start.width, tilt: start.tilt };
    let moved = false;

    photo.classList.add('is-dragging');
    photo.setPointerCapture(event.pointerId);

    const move = (moveEvent) => {
        const dx = moveEvent.clientX - start.pointerX;
        const dy = moveEvent.clientY - start.pointerY;
        moved = moved || Math.hypot(dx, dy) > 2;

        if (mode === 'resize') {
            const distance = Math.hypot(moveEvent.clientX - centre.x, moveEvent.clientY - centre.y);
            value.width = Math.round(clamp(start.width * (distance / start.distance), 10, 90));
            photo.style.setProperty('--inv-cover-photo-width', String(value.width));
        } else if (mode === 'rotate') {
            const turned = normalize(start.tilt + angleTo(moveEvent.clientX, moveEvent.clientY) - start.angle);
            value.tilt = Math.round(moveEvent.shiftKey ? Math.round(turned / 15) * 15 : turned);
            photo.style.rotate = `${value.tilt}deg`;
        } else {
            value.x = round(clamp(start.x + (dx / area.width) * 100, -10, 110));
            value.y = round(clamp(start.y + (dy / area.height) * 100, -10, 110));
            photo.style.left = `${value.x}%`;
            photo.style.top = `${value.y}%`;
        }
    };

    const end = () => {
        photo.classList.remove('is-dragging');
        photo.removeEventListener('pointermove', move);
        photo.removeEventListener('pointerup', end);
        photo.removeEventListener('pointercancel', end);

        if (moved) {
            window.dispatchEvent(new CustomEvent('cover-photo-placed', { detail: { section, ...value } }));
        }
    };

    photo.addEventListener('pointermove', move);
    photo.addEventListener('pointerup', end);
    photo.addEventListener('pointercancel', end);
}

function documentOf(frame) {
    try {
        return frame?.contentDocument?.head ? frame.contentDocument : null;
    } catch {
        return null;
    }
}

/**
 * Give the photos their handles and mark the ones whose settings are open as editable.
 */
function sync(frame) {
    const doc = documentOf(frame);

    doc?.querySelectorAll(PHOTO).forEach((photo) => {
        if (! photo.querySelector(`:scope > .${RESIZE}`)) {
            [RESIZE, ROTATE].forEach((name) => {
                const handle = doc.createElement('span');
                handle.className = name;
                handle.setAttribute('aria-hidden', 'true');
                photo.append(handle);
            });
        }

        photo.classList.toggle('is-editable', editorOpenFor(photo.dataset.invCoverPhotoFree ?? ''));
    });
}

function bind(frame) {
    const doc = documentOf(frame);

    if (! doc || doc.coverPhotoDrag) {
        sync(frame);

        return;
    }

    // One listener per preview document, in the capture phase so covering content cannot swallow it.
    doc.coverPhotoDrag = true;
    doc.addEventListener('pointerdown', (event) => {
        const hit = event.button === 0 ? hitAt(doc, event) : null;

        if (hit) {
            startDrag(event, hit);
        }
    }, true);

    const style = doc.createElement('style');
    style.id = 'cover-photo-drag';
    style.textContent = EDIT_CSS;
    doc.head.append(style);

    sync(frame);
}

/**
 * Make the cover photo editable in this preview frame, now and after every reload, and follow the
 * editor opening and closing the cover's settings.
 */
export function attachCoverPhotoDrag(frame) {
    if (! frame) {
        return;
    }

    frame.addEventListener('load', () => bind(frame));
    bind(frame);

    let queued = false;
    new MutationObserver(() => {
        if (! queued) {
            queued = true;
            requestAnimationFrame(() => {
                queued = false;
                sync(frame);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

window.coverPhotoDrag = { attach: attachCoverPhotoDrag };
