// Mocha Glass design: cover that slides away, active bottom menu, sparkles.
// Entrance animations come from invitation.js (template animation setting).
// Plain DOM code; Alpine (from invitation.js) handles the gallery and modals.

const root = document.querySelector('[data-mg-root]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function openInvitation(animate = true) {
    const cover = document.querySelector('[data-mg-cover]');

    if (cover) {
        cover.classList.toggle('is-instant', ! animate);
        cover.classList.add('is-opened');
        cover.setAttribute('aria-hidden', 'true');
    }

    document.body.classList.remove('mg-locked');
    document.body.classList.add('mg-is-open');
}

function closeInvitation() {
    const cover = document.querySelector('[data-mg-cover]');

    if (! cover) {
        return;
    }

    cover.classList.add('is-instant');
    cover.classList.remove('is-opened');
    cover.removeAttribute('aria-hidden');
    document.body.classList.add('mg-locked');
    document.body.classList.remove('mg-is-open');
    window.scrollTo(0, 0);
}

/**
 * Whether an anchor (#rsvp-form, #section-…) points outside the cover section.
 * Unknown anchors count as the cover, so the guest still sees the "Open invitation" screen.
 */
function pointsPastCover(hash) {
    const target = hash ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;

    return target !== null && target.closest('[data-section="cover"]') === null;
}

function setupCover() {
    if (! root.hasAttribute('data-mg-locked')) {
        openInvitation(false);

        return;
    }

    document.querySelector('[data-mg-open]')?.addEventListener('click', () => {
        window.scrollTo(0, 0);
        openInvitation(! reducedMotion);
    });

    // Skip the cover after a form redirect (#rsvp-form), a menu link, or when an editor preview jumps to a section.
    const previewSection = new URLSearchParams(window.location.search).get('preview_section');

    if (pointsPastCover(window.location.hash || (previewSection ? `#section-${previewSection}` : ''))) {
        openInvitation(false);
    } else {
        document.body.classList.add('mg-locked');
    }

    // Editor previews (resources/js/preview-frame.js) announce the section they scroll to.
    window.addEventListener('inv-preview-section', (event) => {
        pointsPastCover(`#section-${event.detail.key}`) ? openInvitation(false) : closeInvitation();
    });
}

function setupMenu() {
    const links = [...document.querySelectorAll('[data-mg-nav]')];
    const targets = links.map((link) => document.querySelector(link.getAttribute('href')));

    if (links.length === 0) {
        return;
    }

    const update = () => {
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
}

function setupParticles() {
    const canvas = document.querySelector('[data-mg-particles]');

    if (! canvas || reducedMotion) {
        canvas?.remove();

        return;
    }

    const context = canvas.getContext('2d');
    let width = 0;
    let height = 0;

    const resize = () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    };

    resize();
    window.addEventListener('resize', resize);

    const particles = Array.from({ length: 45 }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        r: Math.random() * 1.8 + 0.4,
        vx: (Math.random() - 0.5) * 0.25,
        vy: -(Math.random() * 0.35 + 0.08),
        a: Math.random(),
        da: Math.random() * 0.01 + 0.003,
    }));

    const frame = () => {
        context.clearRect(0, 0, width, height);

        particles.forEach((particle) => {
            particle.x += particle.vx;
            particle.y += particle.vy;
            particle.a += particle.da;

            if (particle.a > 1 || particle.a < 0) {
                particle.da *= -1;
            }

            if (particle.y < -10) {
                particle.y = height + 10;
                particle.x = Math.random() * width;
            }

            context.beginPath();
            context.arc(particle.x, particle.y, particle.r, 0, Math.PI * 2);
            context.fillStyle = `rgba(255, 240, 200, ${Math.max(0, Math.min(1, particle.a)) * 0.85})`;
            context.shadowBlur = 8;
            context.shadowColor = 'rgba(255, 230, 170, 0.8)';
            context.fill();
        });

        window.requestAnimationFrame(frame);
    };

    frame();
}

if (root) {
    setupCover();
    setupMenu();
    setupParticles();
}
