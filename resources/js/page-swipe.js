// Decoration "scroll_direction" = horizontal: each section is a page of a sideways strip (see invitation.css
// "Swipe between sections"). A sideways swipe is the browser's own snap scrolling. An up or down swipe, the mouse
// wheel and the arrow keys also turn the page: inside a long section they first scroll it, and turn the page at its end.
// Sets window.invPages for the designs and previews, and announces the page with the "inv-page-change" window event.

const SWIPE_DISTANCE = 50;
const WHEEL_DISTANCE = 30;
const WHEEL_PAUSE = 700;
const SCROLL_SETTLE = 250;
const IGNORED = 'input, textarea, select, [contenteditable], dialog, [role="dialog"], [class*="lightbox"]';

export function setupPageSwipe() {
    const pages = document.querySelector('[data-inv-scroll="horizontal"] [data-inv-pages]');

    if (! pages) {
        return;
    }

    const sections = [...pages.querySelectorAll(':scope > section')];

    // The "Made with" line sits after the sections; on its own it would become a page.
    const lastPage = sections.at(-1);
    document.querySelectorAll('[data-inv-branding]').forEach((line) => (lastPage?.querySelector(':scope > .inv-section-body') ?? lastPage)?.append(line));

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hint = document.querySelector('[data-inv-page-hint]');

    const go = (section, behavior = reducedMotion ? 'auto' : 'smooth') => {
        pages.scrollTo({ left: section.getBoundingClientRect().left - pages.getBoundingClientRect().left + pages.scrollLeft, behavior });
    };

    let current = -1;

    const update = () => {
        const index = Math.min(sections.length - 1, Math.max(0, Math.round(pages.scrollLeft / pages.clientWidth)));

        if (index === current) {
            return;
        }

        current = index;
        hint?.classList.toggle('is-hidden', index !== 0);
        window.dispatchEvent(new CustomEvent('inv-page-change', { detail: { index, section: sections[index] } }));
    };

    const step = (direction) => {
        const target = sections[current + direction];

        if (target) {
            go(target);
        }
    };

    // The Mocha Glass cover layer lies over the pages until the guest opens the invitation.
    const blocked = (target) => document.body.classList.contains('mg-locked') || Boolean(target.closest?.(IGNORED));

    const edges = () => {
        const section = sections[current];

        return { top: section.scrollTop <= 1, bottom: section.scrollTop + section.clientHeight >= section.scrollHeight - 2 };
    };

    let queued = false;
    pages.addEventListener('scroll', () => {
        if (! queued) {
            queued = true;
            window.requestAnimationFrame(() => {
                queued = false;
                update();
            });
        }
    }, { passive: true });

    // Touch: the section's position when the finger came down decides, so reaching the end of a long
    // section takes one swipe and turning the page a second one.
    let touch = null;

    pages.addEventListener('touchstart', (event) => {
        const [finger] = event.touches;
        touch = event.touches.length === 1 && ! blocked(event.target) ? { x: finger.clientX, y: finger.clientY, ...edges() } : null;
    }, { passive: true });

    pages.addEventListener('touchend', (event) => {
        if (! touch) {
            return;
        }

        const [finger] = event.changedTouches;
        const across = touch.x - finger.clientX;
        const down = touch.y - finger.clientY;

        if (Math.abs(down) >= SWIPE_DISTANCE && Math.abs(down) > Math.abs(across) * 1.5) {
            if (down > 0 && touch.bottom) {
                step(1);
            } else if (down < 0 && touch.top) {
                step(-1);
            }
        }

        touch = null;
    }, { passive: true });

    // Mouse wheel and trackpad: one page per gesture, and never in the same gesture that scrolled the section.
    let lastTurn = 0;
    let lastScroll = 0;

    pages.addEventListener('scroll', (event) => {
        if (event.target !== pages) {
            lastScroll = Date.now();
        }
    }, { capture: true, passive: true });

    pages.addEventListener('wheel', (event) => {
        const now = Date.now();

        if (Math.abs(event.deltaY) < WHEEL_DISTANCE || Math.abs(event.deltaX) > Math.abs(event.deltaY) || blocked(event.target)
            || now - lastTurn < WHEEL_PAUSE || now - lastScroll < SCROLL_SETTLE) {
            return;
        }

        const { top, bottom } = edges();

        if ((event.deltaY > 0 && bottom) || (event.deltaY < 0 && top)) {
            lastTurn = now;
            step(event.deltaY > 0 ? 1 : -1);
        }
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (! ['ArrowLeft', 'ArrowRight'].includes(event.key) || blocked(event.target)) {
            return;
        }

        step(event.key === 'ArrowRight' ? 1 : -1);
    });

    // The hint stays over the invitation, which is a narrow column on wide screens.
    const placeHint = () => {
        if (hint) {
            const box = pages.getBoundingClientRect();
            hint.style.left = `${box.left}px`;
            hint.style.width = `${box.width}px`;
        }
    };

    window.addEventListener('resize', placeHint);
    window.invPages = { pages, go };

    if (hint) {
        hint.hidden = false;
    }

    placeHint();
    update();
}
