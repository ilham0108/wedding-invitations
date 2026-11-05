// Fits framed portraits (invitation.partials.framed-photo) to the frame's opening: the transparent area
// connected to the frame's center. The photo is sized to that area and masked by it, so its corners never
// show past a curved frame. Frames whose opening cannot be read keep the inset from invitation.css.

const WIDTH = 360;
const WALL_ALPHA = 32;
const MIN_AREA = 0.05;
// Small gaps in thin frames are closed before filling; the opening then grows back under the frame's line.
const GAPS = [0, 2, 4, 8];
const BLEED = 3;

const measured = new Map();

function grow(mask, width, height, passes) {
    let current = mask;

    for (let pass = 0; pass < passes; pass++) {
        const next = current.slice();

        for (let i = 0; i < current.length; i++) {
            if (current[i]) {
                if (i % width > 0) next[i - 1] = 1;
                if (i % width < width - 1) next[i + 1] = 1;
                if (i >= width) next[i - width] = 1;
                if (i < current.length - width) next[i + width] = 1;
            }
        }

        current = next;
    }

    return current;
}

// The area connected to the center, or null when the center is covered or the fill escapes past the edge.
function fill(walls, width, height) {
    const start = Math.floor(height / 2) * width + Math.floor(width / 2);

    if (walls[start]) {
        return null;
    }

    const region = new Uint8Array(walls.length);
    const stack = new Int32Array(walls.length);
    let size = 0;
    stack[size++] = start;
    region[start] = 1;

    while (size > 0) {
        const i = stack[--size];
        const x = i % width;

        if (x === 0 || x === width - 1 || i < width || i >= walls.length - width) {
            return null;
        }

        for (const next of [i - 1, i + 1, i - width, i + width]) {
            if (! walls[next] && ! region[next]) {
                region[next] = 1;
                stack[size++] = next;
            }
        }
    }

    return region;
}

function opening(walls, width, height) {
    for (const gap of GAPS) {
        const region = fill(grow(walls, width, height, gap), width, height);

        if (region) {
            return grow(region, width, height, gap + BLEED);
        }
    }

    return null;
}

function maskOf(region, width, left, top, right, bottom) {
    const canvas = document.createElement('canvas');
    canvas.width = right - left + 1;
    canvas.height = bottom - top + 1;

    const context = canvas.getContext('2d');
    const image = context.createImageData(canvas.width, canvas.height);

    for (let y = top; y <= bottom; y++) {
        for (let x = left; x <= right; x++) {
            image.data[((y - top) * canvas.width + (x - left)) * 4 + 3] = region[y * width + x] ? 255 : 0;
        }
    }

    context.putImageData(image, 0, 0);

    return canvas.toDataURL('image/png');
}

async function measure(url) {
    const source = new Image();

    if (new URL(url, location.href).origin !== location.origin) {
        source.crossOrigin = 'anonymous';
    }

    source.src = url;
    await source.decode();

    const width = WIDTH;
    const height = Math.max(1, Math.round((WIDTH * source.naturalHeight) / source.naturalWidth));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(source, 0, 0, width, height);

    const { data } = context.getImageData(0, 0, width, height);
    const walls = new Uint8Array(width * height);

    for (let i = 0; i < walls.length; i++) {
        walls[i] = data[i * 4 + 3] >= WALL_ALPHA ? 1 : 0;
    }

    const region = opening(walls, width, height);

    if (! region) {
        return null;
    }

    let [left, top, right, bottom, area] = [width, height, 0, 0, 0];

    for (let i = 0; i < region.length; i++) {
        if (region[i]) {
            const x = i % width;
            const y = Math.floor(i / width);
            left = Math.min(left, x);
            right = Math.max(right, x);
            top = Math.min(top, y);
            bottom = Math.max(bottom, y);
            area++;
        }
    }

    if (area < region.length * MIN_AREA) {
        return null;
    }

    return {
        left: (left / width) * 100,
        top: (top / height) * 100,
        width: ((right - left + 1) / width) * 100,
        height: ((bottom - top + 1) / height) * 100,
        mask: maskOf(region, width, left, top, right, bottom),
    };
}

function fit(photo, box) {
    const mask = `url("${box.mask}")`;

    Object.assign(photo.style, {
        inset: 'auto',
        left: `${box.left}%`,
        top: `${box.top}%`,
        width: `${box.width}%`,
        height: `${box.height}%`,
        maskImage: mask,
        webkitMaskImage: mask,
        maskSize: '100% 100%',
        webkitMaskSize: '100% 100%',
    });
}

export function setupPhotoFrames() {
    document.querySelectorAll('.inv-photo--framed').forEach(async (block) => {
        const photo = block.querySelector('.inv-photo__image');
        const frame = block.querySelector('.inv-photo__frame');

        if (! photo || ! frame) {
            return;
        }

        try {
            if (! measured.has(frame.src)) {
                measured.set(frame.src, measure(frame.src));
            }

            const box = await measured.get(frame.src);

            if (box) {
                fit(photo, box);
            }
        } catch {
            // Unreadable frame (other origin without CORS, broken file): the inset from invitation.css stays.
        }

        photo.classList.add('is-fitted');
    });
}
