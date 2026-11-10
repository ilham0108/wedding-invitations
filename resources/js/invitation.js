// Public invitation bundle: Alpine only, no Livewire, kept small for fast mobile loads.
import Alpine from 'alpinejs';
import { setupPageSwipe } from './page-swipe';
import { setupPhotoFrames } from './photo-frame';

const pad = (value) => String(Math.max(0, value)).padStart(2, '0');

Alpine.data('countdown', (target) => ({
    days: '00',
    hours: '00',
    minutes: '00',
    seconds: '00',
    expired: false,
    timer: null,

    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    tick() {
        const remaining = new Date(target).getTime() - Date.now();

        if (Number.isNaN(remaining) || remaining <= 0) {
            this.expired = true;
            clearInterval(this.timer);

            return;
        }

        const totalSeconds = Math.floor(remaining / 1000);
        this.days = pad(Math.floor(totalSeconds / 86400));
        this.hours = pad(Math.floor((totalSeconds % 86400) / 3600));
        this.minutes = pad(Math.floor((totalSeconds % 3600) / 60));
        this.seconds = pad(totalSeconds % 60);
    },
}));

Alpine.data('copyText', (text) => ({
    copied: false,

    async copy() {
        try {
            await navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        } catch {
            this.copied = false;
        }
    },
}));

// Entrance animations: reveal .inv-animate elements as they scroll into view (see invitation.css).
function setupAnimations() {
    const elements = [...document.querySelectorAll('.inv-animate')];
    const mode = document.body.dataset.invAnimation;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (! mode || mode === 'none' || reduced || ! ('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-shown'));

        return;
    }

    // "slide" alternates between entering from the left and from the right.
    elements.forEach((element, index) => (element.dataset.invSide = index % 2 ? 'right' : 'left'));

    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-shown');
            observer.unobserve(entry.target);
        }
    }), { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

    elements.forEach((element) => observer.observe(element));
}

setupPageSwipe();

// Imported designs: data-inv-effect="next" moves to the following section.
document.addEventListener('click', (event) => {
    const next = event.target.closest?.('[data-inv-next]')?.closest('section')?.nextElementSibling;

    if (! next) {
        return;
    }

    event.preventDefault();

    if (window.invPages) {
        window.invPages.go(next);
    } else {
        window.scrollTo({ top: next.getBoundingClientRect().top + window.scrollY, behavior: 'smooth' });
    }
});

setupAnimations();

// Entrance of single elements in sections with their own animations (data-inv-anim, see invitation.css).
// The design's block animation stays still in those sections, so the elements are not animated twice.
function setupElementAnimations() {
    document.querySelectorAll('[data-inv-own-anim]').forEach((section) => {
        [section, ...section.querySelectorAll('.inv-animate')].forEach((element) => element.classList.add('is-shown'));
    });

    const elements = [...document.querySelectorAll('[data-inv-anim]')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced || ! ('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-in'));

        return;
    }

    const finish = (element) => element.classList.remove('inv-anim-run');

    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (! entry.isIntersecting) {
            return;
        }

        const element = entry.target;
        observer.unobserve(element);
        element.classList.add('inv-anim-run', 'is-in');
        element.addEventListener('transitionend', (event) => event.target === element && finish(element));
        // Fallback when no transition ends (e.g. the element is not displayed): duration + delay is at most ~3 s.
        setTimeout(() => finish(element), 3500);
    }), { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

    elements.forEach((element) => observer.observe(element));
}

setupElementAnimations();

// Preview wishes (PreviewGuestbook): send the form in the background, then swap in the wishes list of the freshly
// rendered page, so every design keeps its own list markup. Without JavaScript the form posts and reloads as usual.
function setupPreviewWishes() {
    document.querySelectorAll('form[data-inv-preview-wishes]').forEach((form) => {
        const status = form.querySelector('[data-inv-wish-status]');
        const errors = form.querySelector('[data-inv-wish-errors]');
        const button = form.querySelector('button[type="submit"]');

        const showErrors = (messages) => {
            errors.replaceChildren(...messages.map((text) => Object.assign(document.createElement('li'), { textContent: text })));
            errors.classList.toggle('hidden', messages.length === 0);
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            button.disabled = true;
            form.setAttribute('aria-busy', 'true');
            showErrors([]);
            status.textContent = '';

            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const body = await response.json().catch(() => ({}));

                if (! response.ok) {
                    showErrors(response.status === 422 ? Object.values(body.errors ?? {}).flat() : [form.dataset.errorText]);

                    return;
                }

                form.querySelector('[name="message"]').value = '';
                status.textContent = body.status ?? '';
                await refreshWishesList(form);
            } catch {
                showErrors([form.dataset.errorText]);
            } finally {
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    });
}

async function refreshWishesList(form) {
    const section = form.closest('section');
    const current = section?.querySelector('[data-inv-wishes-list]');

    if (! current) {
        return;
    }

    const html = await (await fetch(location.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } })).text();
    const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector(`#${CSS.escape(section.id)} [data-inv-wishes-list]`);

    if (! fresh) {
        return;
    }

    // The new list is already on screen: show it at once instead of waiting for an entrance.
    fresh.querySelectorAll('[data-inv-anim]').forEach((element) => element.classList.add('is-in'));
    fresh.querySelectorAll('.inv-animate').forEach((element) => element.classList.add('is-shown'));
    current.replaceWith(document.importNode(fresh, true));
}

setupPreviewWishes();
setupPhotoFrames();

// Background photos that change while scrolling (invitation.partials.scroll-backdrop): show the
// photo of the last section that reached the middle of the screen; sections without one keep it.
function setupScrollBackdrop() {
    const root = document.querySelector('[data-inv-backdrop-root]');

    if (! root) {
        return;
    }

    const layers = [...root.querySelectorAll('[data-photo]')];
    const sections = [...document.querySelectorAll('section[data-section]')];
    let active;

    const load = (layer) => {
        if (layer.dataset.src) {
            layer.style.backgroundImage = layer.dataset.src;
            delete layer.dataset.src;
        }
    };

    const pages = window.invPages?.pages;

    const update = () => {
        const line = window.innerHeight * 0.5;
        const middle = pages ? pages.getBoundingClientRect().left + pages.clientWidth * 0.5 : 0;
        let photo = null;
        let overlay = null;

        for (const section of sections) {
            const box = section.getBoundingClientRect();

            if (pages ? box.left >= middle : box.top > line) {
                break;
            }

            if (section.dataset.invBackdrop) {
                photo = section.dataset.invBackdrop;
                overlay = section.dataset.invBackdropOverlay ?? null;
            }
        }

        // The overlay over the photo follows the section that brought it (designs style [data-overlay]).
        if (overlay) {
            root.dataset.overlay = overlay;
        } else {
            delete root.dataset.overlay;
        }

        if (photo === active) {
            return;
        }

        active = photo;
        layers.forEach((layer) => {
            const isActive = layer.dataset.photo === photo;

            if (isActive) {
                load(layer);
            }

            layer.classList.toggle('is-active', isActive);
        });
    };

    let queued = false;
    (pages ?? window).addEventListener('scroll', () => {
        if (! queued) {
            queued = true;
            window.requestAnimationFrame(() => {
                queued = false;
                update();
            });
        }
    }, { passive: true });
    window.addEventListener('resize', update);
    window.addEventListener('hashchange', update);
    window.addEventListener('load', () => {
        // Images and fonts may have moved the sections (and the browser may have jumped to an anchor).
        update();
        // Load the remaining photos once the page is ready, so later switches are instant.
        setTimeout(() => layers.forEach(load), 1500);
    });
    update();
}

setupScrollBackdrop();

// Ornaments that "rise" slide in once their section scrolls into view (invitation.partials.ornaments).
function setupOrnaments() {
    const groups = document.querySelectorAll('[data-inv-ornaments]');

    if (! ('IntersectionObserver' in window)) {
        groups.forEach((group) => group.classList.add('is-shown'));

        return;
    }

    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-shown');
            observer.unobserve(entry.target);
        }
    }), { threshold: 0.2 });

    groups.forEach((group) => observer.observe(group));
}

setupOrnaments();

// Background music (invitation.partials.music). Browsers only allow sound after the guest interacts,
// so the song starts on the first tap, click or key press, e.g. "Open Invitation".
function setupMusic() {
    const root = document.querySelector('[data-inv-music]');

    if (! root) {
        return;
    }

    const audio = root.querySelector('audio');
    const toggle = root.querySelector('[data-inv-music-toggle]');
    const startAt = Number(root.dataset.start) || 0;
    const loop = root.dataset.loop === '1';
    let started = false;
    let pausedByPage = false;

    const render = () => {
        const playing = ! audio.paused;
        toggle.setAttribute('aria-pressed', String(playing));
        toggle.setAttribute('aria-label', playing ? toggle.dataset.labelPause : toggle.dataset.labelPlay);
    };

    const play = () => {
        if (! started) {
            started = true;

            if (startAt > 0) {
                audio.addEventListener('loadedmetadata', () => { audio.currentTime = Math.min(startAt, audio.duration || startAt); }, { once: true });
            }
        }

        audio.play().catch(() => {});
    };

    audio.addEventListener('play', render);
    audio.addEventListener('pause', render);
    audio.addEventListener('ended', () => {
        if (loop) {
            audio.currentTime = startAt;
            audio.play().catch(() => {});
        }
    });

    const interactions = ['pointerdown', 'keydown'];
    const firstInteraction = (event) => {
        stopAutoplay();

        // A first press on the button itself is handled by its click handler.
        if (! toggle.contains(event.target)) {
            play();
        }
    };
    const stopAutoplay = () => interactions.forEach((type) => document.removeEventListener(type, firstInteraction, true));

    toggle.addEventListener('click', () => {
        stopAutoplay();
        audio.paused ? play() : audio.pause();
    });

    if (root.dataset.autoplay === '1') {
        interactions.forEach((type) => document.addEventListener(type, firstInteraction, true));
    }

    // Pause while the tab is hidden and resume when the guest comes back.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && ! audio.paused) {
            pausedByPage = true;
            audio.pause();
        } else if (! document.hidden && pausedByPage) {
            pausedByPage = false;
            audio.play().catch(() => {});
        }
    });
}

setupMusic();

window.Alpine = Alpine;
Alpine.start();
