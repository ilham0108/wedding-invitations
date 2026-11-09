// Botanical Cream design: top bar that turns solid on scroll, active bottom menu, full menu, scroll-down arrow.
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

function setupBottomMenu() {
    const links = [...document.querySelectorAll('[data-bc-nav]')];
    const targets = links.map((link) => document.querySelector(link.getAttribute('href')));

    if (links.length === 0) {
        return;
    }

    const update = () => {
        // Swiping sideways: "inv-page-change" below keeps the menu current.
        if (window.invPages) {
            return;
        }

        const line = window.scrollY + window.innerHeight * 0.4;
        let current = 0;

        targets.forEach((target, index) => {
            if (target && target.offsetTop <= line) {
                current = index;
            }
        });

        links.forEach((link, index) => link.classList.toggle('is-active', index === current));
    };

    window.addEventListener('scroll', update, { passive: true });
    update();

    window.addEventListener('inv-page-change', ({ detail }) => {
        const current = targets.reduce((found, target, index) => ([...target.parentElement.children].indexOf(target) <= detail.index ? index : found), 0);

        links.forEach((link, index) => link.classList.toggle('is-active', index === current));
    });
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
    setupBottomMenu();
    setupFullMenu();
    setupScrollDown();
}
