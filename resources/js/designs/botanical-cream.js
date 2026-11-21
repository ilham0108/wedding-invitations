// Botanical Cream design: top bar that turns solid on scroll, full menu, scroll-down arrow.
// The floating menu is resources/js/invitation-nav.js.
// Entrance animations come from invitation.js (template animation setting); Alpine handles galleries and wishes.

const root = document.querySelector('[data-bc-root]');

function setupTopbar() {
    const topbar = document.querySelector('[data-bc-topbar]');

    if (! topbar) {
        return;
    }

    let lastY = window.scrollY;

    const update = () => {
        const y = window.scrollY;

        topbar.classList.toggle('is-scrolled', y > 40);

        // Hidden while the guest reads downwards, so the bar never covers text; back when scrolling up or near the top.
        if (Math.abs(y - lastY) > 6) {
            topbar.classList.toggle('is-hidden', y > lastY && y > 120);
            lastY = y;
        }
    };

    window.addEventListener('scroll', update, { passive: true });
    update();

    // Swiping sideways: a solid bar on every page after the first.
    window.addEventListener('inv-page-change', ({ detail }) => topbar.classList.toggle('is-scrolled', detail.index > 0));
}

function setupFullMenu() {
    const menu = document.querySelector('[data-bc-menu]');
    const opener = document.querySelector('[data-bc-menu-open]');

    if (! menu || ! opener) {
        return;
    }

    const close = () => {
        menu.hidden = true;
        opener.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    };

    opener.addEventListener('click', () => {
        menu.hidden = false;
        opener.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        menu.querySelector('[data-bc-menu-close]')?.focus();
    });

    menu.addEventListener('click', (event) => {
        if (event.target === menu || event.target.closest('[data-bc-menu-close], [data-bc-menu-link]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => event.key === 'Escape' && ! menu.hidden && close());
}

function setupScrollDown() {
    document.querySelector('[data-bc-scroll]')?.addEventListener('click', (event) => {
        event.preventDefault();

        const next = event.currentTarget.closest('[data-section]')?.nextElementSibling;

        if (next && window.invPages) {
            window.invPages.go(next);
        } else if (next) {
            // window.scrollTo, not scrollIntoView(): inside the editor preview iframe the latter also scrolls the editor page.
            window.scrollTo({
                top: next.getBoundingClientRect().top + window.scrollY - 58,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        }
    });
}

if (root) {
    setupTopbar();
    setupFullMenu();
    setupScrollDown();
}
