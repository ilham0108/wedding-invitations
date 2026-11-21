// Floating section menu (invitation.partials.nav), shared by every design. It floats over the page, so it stays
// hidden while the cover is on screen (the cover's open button sits where the menu would be) and while the
// Mocha Glass cover layer is closed. It marks the section on screen and, on a click, scrolls there (or turns the
// page when guests swipe sideways). window.scrollTo, not scrollIntoView(): inside the editor preview iframe the
// latter also scrolls the editor page.

const ACTIVE_LINE = 0.4;
const COVER_LEFT = 0.35;

export function setupNav() {
    const nav = document.querySelector('[data-inv-nav]');

    if (! nav) {
        return;
    }

    const links = [...nav.querySelectorAll('[data-inv-nav-link]')];
    const targets = links.map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))));
    const sections = [...document.querySelectorAll('[data-inv-pages] > section')];
    const cover = sections[0]?.dataset.section === 'cover' ? sections[0] : null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let pageIndex = 0;

    const setActive = (index) => {
        links.forEach((link, linkIndex) => {
            link.classList.toggle('is-active', linkIndex === index);
            linkIndex === index ? link.setAttribute('aria-current', 'location') : link.removeAttribute('aria-current');
        });
    };

    const setHidden = (hidden) => {
        nav.classList.toggle('is-hidden', hidden);
        nav.inert = hidden;
        document.body.classList.toggle('inv-has-nav', ! hidden);
    };

    const onCover = () => {
        if (document.body.classList.contains('mg-locked')) {
            return true;
        }

        if (! cover) {
            return false;
        }

        return window.invPages
            ? sections[pageIndex] === cover
            : cover.getBoundingClientRect().bottom > window.innerHeight * COVER_LEFT;
    };

    const update = () => {
        setHidden(onCover());

        if (window.invPages) {
            const page = sections[pageIndex];
            // The entry of the page on screen, else of the last section before it.
            setActive(targets.reduce((found, target, index) => (target && sections.indexOf(target) <= sections.indexOf(page) ? index : found), 0));

            return;
        }

        const line = window.innerHeight * ACTIVE_LINE;
        setActive(targets.reduce((found, target, index) => (target && target.getBoundingClientRect().top <= line ? index : found), 0));
    };

    let queued = false;
    const queue = () => {
        if (! queued) {
            queued = true;
            window.requestAnimationFrame(() => {
                queued = false;
                update();
            });
        }
    };

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue, { passive: true });
    window.addEventListener('inv-page-change', ({ detail }) => {
        pageIndex = detail.index;
        update();
    });

    // Mocha Glass opens and closes its cover layer by toggling a body class.
    new MutationObserver(queue).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    links.forEach((link, index) => link.addEventListener('click', (event) => {
        const target = targets[index];

        if (! target) {
            return;
        }

        event.preventDefault();

        if (window.invPages) {
            window.invPages.go(target);

            return;
        }

        const margin = parseFloat(window.getComputedStyle(target).scrollMarginTop) || 0;

        window.scrollTo({
            top: target.getBoundingClientRect().top + window.scrollY - margin,
            behavior: reducedMotion ? 'auto' : 'smooth',
        });
    }));

    update();
}
