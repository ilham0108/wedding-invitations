// Live preview iframe helpers shared by the invitation editor and the template builder.
// Previews are same-origin, so the parent page can scroll the frame's document directly.

const OFFSET = 12;

/**
 * Smoothly scroll the preview to "#section-{key}". Uses the frame's own window.scrollTo,
 * because scrollIntoView() would also scroll the editor page around the iframe.
 */
export function scrollPreviewTo(frame, key, behavior = 'smooth') {
    try {
        const win = frame?.contentWindow;
        const target = win?.document.getElementById(`section-${key}`);

        if (target) {
            // Lets designs react first, e.g. Mocha Glass shows its cover for the cover section.
            win.dispatchEvent(new win.CustomEvent('inv-preview-section', { detail: { key } }));
            win.scrollTo({ top: target.getBoundingClientRect().top + win.scrollY - OFFSET, behavior });
        }
    } catch {
        // The frame is still loading or not same-origin: the next reload carries the anchor instead.
    }
}

/**
 * Reload the preview and land on "#section-{key}" (when given). The key is not put in the URL
 * fragment: the browser's jump to a fragment scrolls the editor page around the iframe as well.
 * It travels as ?preview_section= (read by designs before load) and the frame is scrolled once loaded.
 */
export function reloadPreview(frame, url, key = null) {
    if (! frame) {
        return;
    }

    if (key) {
        frame.addEventListener('load', () => scrollPreviewTo(frame, key, 'instant'), { once: true });
    }

    frame.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now() + (key ? `&preview_section=${encodeURIComponent(key)}` : '');
}

window.previewFrame = { scrollTo: scrollPreviewTo, reload: reloadPreview };
