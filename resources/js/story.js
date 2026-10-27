/*
| Instagram Story image (1080×1920) of an invitation, drawn on a canvas so the theme's web fonts
| apply (dashboard.invitations.story). Data comes from App\Services\Invitation\StoryCard.
*/

const loadImage = (src) => new Promise((resolve) => {
    if (! src) {
        resolve(null);

        return;
    }

    const image = new Image();
    image.decoding = 'async';

    // Same-origin files keep the canvas exportable; other hosts must allow CORS or are skipped.
    if (new URL(src, location.href).origin !== location.origin) {
        image.crossOrigin = 'anonymous';
    }

    image.onload = () => resolve(image);
    image.onerror = () => resolve(null);
    image.src = src;
});

const font = (weight, size, family, fallback) => `${weight} ${size}px "${family}", ${fallback}`;

function setSpacing(ctx, pixels) {
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = `${pixels}px`;
    }
}

/**
 * Draw the image like CSS object-fit: cover inside the box.
 */
function drawCover(ctx, image, x, y, width, height) {
    const scale = Math.max(width / image.naturalWidth, height / image.naturalHeight);
    const sw = width / scale;
    const sh = height / scale;

    ctx.drawImage(image, (image.naturalWidth - sw) / 2, (image.naturalHeight - sh) / 2, sw, sh, x, y, width, height);
}

/**
 * An SVG with only a viewBox has no intrinsic size (naturalWidth 0), which breaks drawCover.
 * Give it one from the viewBox, at a size large enough to stay sharp.
 */
async function withIntrinsicSize(image) {
    if (! image || image.naturalWidth > 0) {
        return image;
    }

    try {
        const svg = await (await fetch(image.src)).text();
        const [, , w, h] = (svg.match(/viewBox=["']([^"']+)["']/)?.[1] ?? '').split(/[\s,]+/).map(Number);

        if (! (w > 0 && h > 0)) {
            return null;
        }

        const scale = 2000 / Math.max(w, h);
        const sized = svg.replace(/<svg(?=[\s>])/, `<svg width="${Math.round(w * scale)}" height="${Math.round(h * scale)}"`);

        return loadImage(URL.createObjectURL(new Blob([sized], { type: 'image/svg+xml' })));
    } catch {
        return null;
    }
}

/**
 * Split text into at most maxLines lines that fit maxWidth, shrinking the font until they do.
 * Couple names break before "&" first ("Romeo" / "& Juliet").
 *
 * @returns {{ lines: string[], size: number }}
 */
function fitText(ctx, text, family, fallback, weight, size, minSize, maxWidth, maxLines) {
    const words = text.replace(/\s*&\s*/g, ' & ').split(/\s+/).filter(Boolean);

    for (let current = size; current >= minSize; current -= 4) {
        ctx.font = font(weight, current, family, fallback);

        if (ctx.measureText(text).width <= maxWidth) {
            return { lines: [text], size: current };
        }

        const lines = [];
        let line = '';

        for (const word of words) {
            const candidate = line ? `${line} ${word}` : word;

            if ((word === '&' && line) || ctx.measureText(candidate).width > maxWidth) {
                if (line) lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        }

        if (line) lines.push(line);

        if (lines.length <= maxLines && lines.every((item) => ctx.measureText(item).width <= maxWidth)) {
            return { lines, size: current };
        }
    }

    return { lines: [text], size: minSize };
}

/**
 * Word-wrap text (keeping its own line breaks) to maxWidth; at most maxLines lines, the last one shortened with "…".
 */
function wrapLines(ctx, text, maxWidth, maxLines) {
    const lines = [];

    for (const paragraph of text.split('\n')) {
        let line = '';

        for (const word of paragraph.split(/\s+/).filter(Boolean)) {
            const candidate = line ? `${line} ${word}` : word;

            if (ctx.measureText(candidate).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        }

        lines.push(line);
    }

    if (lines.length > maxLines) {
        lines.length = maxLines;
        lines[maxLines - 1] = `${lines[maxLines - 1].replace(/\s*\S*$/, '')}…`;
    }

    return lines.filter((line, index) => line !== '' || index < lines.length - 1);
}

function roundedRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.roundRect(x, y, width, height, radius);
}

/**
 * The text block shared by both layouts, centered horizontally; returns the y below it.
 * With paint = false nothing is drawn, so layouts can measure the block before placing it.
 */
function drawText(ctx, card, options, y, colors, paint = true) {
    const { heading, body } = card.fonts;
    const center = card.width / 2;
    const fillText = (...args) => paint && ctx.fillText(...args);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';

    // The owner's headline (e.g. "Save the Date") replaces the cover subtitle.
    const headline = (options.headline || card.subtitle || '').trim();

    if (headline) {
        ctx.fillStyle = colors.muted;
        const spaced = fitText(ctx, headline.toUpperCase(), body, 'sans-serif', 500, 36, 24, card.width - 240, 1);
        ctx.font = font(500, spaced.size, body, 'sans-serif');
        setSpacing(ctx, Math.round(spaced.size / 3));
        fillText(headline.toUpperCase(), center, y, card.width - 200);
        setSpacing(ctx, 0);
        y += 70;
    }

    const names = fitText(ctx, card.names, heading, 'serif', 500, 150, 72, card.width - 260, 3);
    ctx.fillStyle = colors.names;
    ctx.font = font(500, names.size, heading, 'serif');

    for (const line of names.lines) {
        y += names.size * 0.95;
        fillText(line, center, y);
    }

    y += 60;
    ctx.fillStyle = card.colors.secondary;
    if (paint) ctx.fillRect(center - 60, y, 120, 3);
    y += 80;

    ctx.fillStyle = colors.text;

    if (card.date) {
        ctx.font = font(500, 46, body, 'sans-serif');
        fillText(card.date, center, y);
        y += 62;
    }

    if (options.venue && card.venue) {
        const venue = fitText(ctx, card.venue, body, 'sans-serif', 400, 36, 26, card.width - 200, 2);
        ctx.fillStyle = colors.muted;
        ctx.font = font(400, venue.size, body, 'sans-serif');

        for (const line of venue.lines) {
            fillText(line, center, y);
            y += venue.size * 1.35;
        }
    }

    const message = (options.message || '').trim();

    if (message) {
        y += 24;
        ctx.fillStyle = colors.text;
        ctx.font = font('italic 400', 34, body, 'sans-serif');

        for (const line of wrapLines(ctx, message, card.width - 260, 3)) {
            fillText(line, center, y);
            y += 48;
        }
    }

    return y;
}

/**
 * Where the footer (link pill, branding) begins; the text block must end above it.
 */
function footerTop(card, options) {
    return options.link || card.branding ? card.height - 230 : card.height - 140;
}

function drawFooter(ctx, card, options, colors) {
    const center = card.width / 2;
    const { body } = card.fonts;
    let y = card.height - 150;

    if (options.link) {
        ctx.font = font(500, 30, body, 'sans-serif');
        const width = Math.min(card.width - 120, ctx.measureText(card.link).width + 80);
        ctx.fillStyle = colors.pill;
        roundedRect(ctx, center - width / 2, y - 48, width, 72, 36);
        ctx.fill();
        ctx.fillStyle = colors.pillText;
        ctx.fillText(card.link, center, y, width - 48);
        y += 80;
    }

    if (card.branding) {
        ctx.fillStyle = colors.muted;
        ctx.font = font(400, 24, body, 'sans-serif');
        ctx.fillText(card.branding, center, y);
    }
}

function drawPhotoLayout(ctx, card, options, photo) {
    const { width, height, colors } = card;

    if (photo) {
        drawCover(ctx, photo, 0, 0, width, height);
    } else {
        const gradient = ctx.createLinearGradient(0, 0, 0, height);
        gradient.addColorStop(0, colors.primary);
        gradient.addColorStop(1, colors.text);
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
    }

    // Darken the lower part so white text stays readable on any photo.
    const shade = ctx.createLinearGradient(0, 0, 0, height);
    shade.addColorStop(0, 'rgba(0, 0, 0, 0.15)');
    shade.addColorStop(0.45, 'rgba(0, 0, 0, 0.25)');
    shade.addColorStop(1, 'rgba(0, 0, 0, 0.72)');
    ctx.fillStyle = shade;
    ctx.fillRect(0, 0, width, height);

    const palette = { names: '#ffffff', text: '#ffffff', muted: 'rgba(255, 255, 255, 0.82)', pill: 'rgba(255, 255, 255, 0.92)', pillText: colors.text };
    // Text sits just above the footer, however many lines it has.
    const blockHeight = drawText(ctx, card, options, 0, palette, false);
    drawText(ctx, card, options, Math.max(700, footerTop(card, options) - blockHeight + 10), palette);
    drawFooter(ctx, card, options, palette);
}

function drawMinimalLayout(ctx, card, options, photo) {
    const { width, height, colors } = card;
    ctx.fillStyle = colors.background;
    ctx.fillRect(0, 0, width, height);

    // Thin double frame in the accent color.
    ctx.strokeStyle = colors.secondary;
    ctx.lineWidth = 3;
    roundedRect(ctx, 56, 56, width - 112, height - 112, 28);
    ctx.stroke();
    ctx.lineWidth = 1.5;
    roundedRect(ctx, 76, 76, width - 152, height - 152, 20);
    ctx.stroke();

    const palette = { names: colors.primary, text: colors.text, muted: colors.text + 'b3', pill: colors.primary, pillText: '#ffffff' };
    const blockHeight = drawText(ctx, card, options, 0, palette, false);
    const bottom = footerTop(card, options) + 10;
    let y = Math.max(300, (bottom + 160 - blockHeight) / 2);

    if (photo) {
        // Arch-shaped photo: a rectangle whose top is a half circle. It gets shorter when the text is long.
        const archWidth = 600;
        const top = 190;
        const archHeight = Math.max(360, Math.min(760, bottom - blockHeight - 130 - top));
        const x = (width - archWidth) / 2;

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, top + archWidth / 2);
        ctx.arc(width / 2, top + archWidth / 2, archWidth / 2, Math.PI, 0);
        ctx.lineTo(x + archWidth, top + archHeight);
        ctx.lineTo(x, top + archHeight);
        ctx.closePath();
        ctx.clip();
        drawCover(ctx, photo, x, top, archWidth, archHeight);
        ctx.restore();

        y = top + archHeight + 130;
    }

    drawText(ctx, card, options, y, palette);
    drawFooter(ctx, card, options, palette);
}

async function draw(canvas, card, options) {
    const { heading, body } = card.fonts;
    await Promise.all([
        document.fonts.load(font(500, 150, heading, 'serif')),
        document.fonts.load(font(400, 36, body, 'sans-serif')),
        document.fonts.load(font(500, 36, body, 'sans-serif')),
    ]).catch(() => {});

    const photo = options.photo ? await withIntrinsicSize(await loadImage(card.photo)) : null;

    canvas.width = card.width;
    canvas.height = card.height;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, card.width, card.height);

    (options.layout === 'minimal' ? drawMinimalLayout : drawPhotoLayout)(ctx, card, options, photo);
}

/**
 * PNG of the canvas; a photo from a host without CORS makes the canvas unexportable,
 * so the image is redrawn without it.
 */
async function toBlob(canvas, card, options) {
    const blob = () => new Promise((resolve, reject) => {
        try {
            canvas.toBlob((result) => (result ? resolve(result) : reject(new Error('empty'))), 'image/png');
        } catch (error) {
            reject(error);
        }
    });

    try {
        return await blob();
    } catch {
        await draw(canvas, card, { ...options, photo: false });

        return blob();
    }
}

async function download(canvas, card, options) {
    const url = URL.createObjectURL(await toBlob(canvas, card, options));
    const link = Object.assign(document.createElement('a'), { href: url, download: card.file_name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * Phones open the share sheet (Instagram is one of the targets); returns false when unsupported.
 */
async function share(canvas, card, options) {
    const file = new File([await toBlob(canvas, card, options)], card.file_name, { type: 'image/png' });

    if (! navigator.canShare?.({ files: [file] })) {
        return false;
    }

    await navigator.share({ files: [file] }).catch(() => {});

    return true;
}

window.InvitationStory = { draw, download, share };
