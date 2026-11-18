// Drag a freely placed cover photo (cover "photo_placement" = free) inside an editor's preview frame.
// The preview is same-origin, so the editor page reaches into it. A photo carries its section key
// (data-inv-cover-photo-free); it can only be dragged while that section's settings are open, i.e. the
// page has [data-cover-photo-editor="<key>"] (or "*" in the template builder, where content is per type).
// On release the new centre, in percent of the cover, goes out as a "cover-photo-placed" window event
// with the section key; the matching panel saves it.
//
// A photo on the "behind the text" layer is covered by the text block, so the photo is found by hit-testing
// every element under the pointer (elementsFromPoint), not by the event target; the dashed outline keeps
// its place visible in the editor even where the text hides it.

const PHOTO = '[data-inv-cover-photo-free]';

const EDIT_CSS = `
${PHOTO} { cursor: move; touch-action: none; outline: 1px dashed rgb(54 81 73 / 0.7); outline-offset: 4px; }
${PHOTO}:hover, ${PHOTO}.is-dragging { outline: 2px dashed #365149; }
`;

const round = (value) => Math.round(value * 10) / 10;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

function editorOpenFor(section) {
    return [...document.querySelectorAll('[data-cover-photo-editor]')]
        .some((panel) => panel.dataset.coverPhotoEditor === '*' || panel.dataset.coverPhotoEditor === section);
}

/**
 * The draggable photo under the pointer, also when other content is painted over it.
 */
function photoAt(doc, event) {
    return doc.elementsFromPoint(event.clientX, event.clientY)
        .map((element) => element.closest(PHOTO))
        .find((photo) => photo && editorOpenFor(photo.dataset.invCoverPhotoFree ?? '')) ?? null;
}

function startDrag(event, photo) {
    const section = photo.dataset.invCoverPhotoFree ?? '';
    const area = photo.offsetParent?.getBoundingClientRect();

    if (! area) {
        return;
    }

    // Taken over from whatever was hit (e.g. the text block above the photo).
    event.preventDefault();
    event.stopPropagation();

    const start = { pointerX: event.clientX, pointerY: event.clientY, x: parseFloat(photo.style.left) || 50, y: parseFloat(photo.style.top) || 30 };
    const value = { x: start.x, y: start.y };
    let moved = false;

    photo.classList.add('is-dragging');
    photo.setPointerCapture(event.pointerId);

    const move = (moveEvent) => {
        const dx = moveEvent.clientX - start.pointerX;
        const dy = moveEvent.clientY - start.pointerY;
        moved = moved || Math.hypot(dx, dy) > 2;
        value.x = round(clamp(start.x + (dx / area.width) * 100, -10, 110));
        value.y = round(clamp(start.y + (dy / area.height) * 100, -10, 110));
        photo.style.left = `${value.x}%`;
        photo.style.top = `${value.y}%`;
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

function bind(frame) {
    let doc;

    try {
        doc = frame.contentDocument;
    } catch {
        return;
    }

    if (! doc?.head || doc.coverPhotoDrag) {
        return;
    }

    // One listener per preview document, in the capture phase so covering content cannot swallow it.
    doc.coverPhotoDrag = true;
    doc.addEventListener('pointerdown', (event) => {
        const photo = event.button === 0 ? photoAt(doc, event) : null;

        if (photo) {
            startDrag(event, photo);
        }
    }, true);

    const style = doc.createElement('style');
    style.id = 'cover-photo-drag';
    style.textContent = EDIT_CSS;
    doc.head.append(style);
}

/**
 * Make the cover photo draggable in this preview frame, now and after every reload.
 */
export function attachCoverPhotoDrag(frame) {
    if (! frame) {
        return;
    }

    frame.addEventListener('load', () => bind(frame));
    bind(frame);
}

window.coverPhotoDrag = { attach: attachCoverPhotoDrag };
