// Marketing home live demo (marketing/home.blade.php, x-data="mkDemo(...)"): a published
// template in an iframe. Items jump the frame to a section type; scrolling the frame by hand
// updates the active item. The frame is same-origin, so its document is read directly.
import { scrollPreviewTo } from './preview-frame';

window.mkDemo = (options, initialSlug) => ({
    options,
    slug: initialSlug,
    active: null,
    loaded: false,
    sections: [],

    current() {
        return this.options.find((option) => option.slug === this.slug) ?? this.options[0];
    },

    has(type) {
        return this.sections.some((section) => section.type === type);
    },

    ready() {
        const frame = this.$refs.frame;

        try {
            const doc = frame.contentDocument;
            this.sections = [...doc.querySelectorAll('section[data-section]')].map((element) => ({
                type: element.dataset.section,
                key: element.id.replace(/^section-/, ''),
            }));
            frame.contentWindow.addEventListener('scroll', () => this.track(), { passive: true });
            frame.contentWindow.addEventListener('inv-page-change', () => this.track());
        } catch {
            this.sections = [];
        }

        this.loaded = true;
        this.track();
    },

    go(type) {
        const section = this.sections.find((item) => item.type === type);

        if (section) {
            this.active = type;
            scrollPreviewTo(this.$refs.frame, section.key);
        }
    },

    switchTo() {
        this.loaded = false;
        this.sections = [];
        this.active = null;
        this.$refs.frame.src = this.current().embed;
    },

    // The last section whose top has passed the upper third of the phone screen.
    track() {
        const win = this.$refs.frame.contentWindow;

        if (! win || this.sections.length === 0) {
            return;
        }

        const line = win.innerHeight / 3;
        let current = this.sections[0];

        for (const section of this.sections) {
            const element = win.document.getElementById(`section-${section.key}`);

            const box = element?.getBoundingClientRect();

            if (box && (win.invPages ? box.left < win.innerWidth / 2 : box.top <= line)) {
                current = section;
            }
        }

        this.active = current.type;
    },
});
