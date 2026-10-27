import Swal from 'sweetalert2';

/*
| SweetAlert2 for the whole dashboard:
|  - toasts from session flashes (#flash-messages) and from Livewire ($this->notify(...));
|  - confirm dialogs for any element with data-confirm="Question?".
| Messages are always passed as `text` (never `html`) so user content cannot inject markup.
*/

const strings = () => {
    try {
        return JSON.parse(document.documentElement.dataset.notifyI18n || '{}');
    } catch {
        return {};
    }
};

const ICONS = ['success', 'error', 'warning', 'info'];

const Toast = Swal.mixin({
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    showCloseButton: true,
    timer: 5000,
    timerProgressBar: true,
    customClass: {
        popup: '!rounded-xl !border !border-line !shadow-soft !font-sans',
        title: '!text-sm !font-medium !text-ink',
        closeButton: '!text-muted hover:!text-ink',
        timerProgressBar: '!bg-sage/40',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

// SweetAlert2 shows one popup at a time; queue toasts so none is lost.
let queue = Promise.resolve();

export function notify(type, message) {
    if (!message) {
        return;
    }

    const icon = ICONS.includes(type) ? type : 'info';

    // Errors often carry instructions to read, so they stay until closed.
    const options = icon === 'error' ? { timer: undefined, timerProgressBar: false } : {};

    queue = queue.then(() => Toast.fire({ icon, title: String(message), ...options })).catch(() => {});
}

export async function confirmDialog(message, { danger = true, confirmText = null } = {}) {
    const t = strings();

    const result = await Swal.fire({
        text: String(message),
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        focusCancel: danger,
        reverseButtons: true,
        confirmButtonText: confirmText || (danger ? t.confirmDanger : t.confirm) || 'OK',
        cancelButtonText: t.cancel || 'Cancel',
        buttonsStyling: false,
        customClass: {
            popup: '!rounded-xl !font-sans',
            htmlContainer: '!text-base !text-ink',
            actions: '!gap-2',
            confirmButton: danger
                ? 'inline-flex h-10 items-center rounded-lg bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2'
                : 'inline-flex h-10 items-center rounded-lg bg-ink px-4 text-sm font-medium text-white hover:bg-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2',
            cancelButton: 'inline-flex h-10 items-center rounded-lg border border-line bg-white px-4 text-sm font-medium text-ink hover:border-ink/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2',
        },
    });

    return result.isConfirmed;
}

/**
 * Intercept clicks on [data-confirm] before Livewire/forms see them; replay the click once confirmed.
 */
function interceptConfirmations() {
    document.addEventListener('click', async (event) => {
        const element = event.target.closest('[data-confirm]');

        if (!element) {
            return;
        }

        if (element.dataset.confirmed === 'true') {
            delete element.dataset.confirmed;

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const confirmed = await confirmDialog(element.dataset.confirm, {
            danger: element.dataset.confirmVariant !== 'default',
            confirmText: element.dataset.confirmButton || null,
        });

        if (confirmed) {
            element.dataset.confirmed = 'true';
            element.click();
        }
    }, true);
}

function showFlashMessages() {
    const node = document.getElementById('flash-messages');

    if (!node) {
        return;
    }

    try {
        JSON.parse(node.textContent || '[]').forEach(({ type, message }) => notify(type, message));
    } catch {
        // Malformed payload: ignore rather than break the page.
    }

    node.remove();
}

export function registerNotifications(Livewire) {
    window.notify = notify;
    window.confirmDialog = confirmDialog;

    interceptConfirmations();

    Livewire.on('notify', ({ type, message }) => notify(type, message));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showFlashMessages);
    } else {
        showFlashMessages();
    }
}
