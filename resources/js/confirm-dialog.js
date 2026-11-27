/**
 * App-wide styled confirmation dialog (markup: resources/views/components/ui/confirm-dialog.blade.php,
 * rendered once by the base layout). Replaces the browser's native confirm / prompt boxes.
 *
 *   wire:confirm="Delete this photo?"             red confirm button (default)
 *   wire:confirm.primary="Turn it back on?"       orange confirm button, for non-destructive actions
 *   wire:confirm.prompt="Delete gym?|DELETE"      user must type the expected text
 *   data-confirm-title="Delete photo?"            the bold title; the wire:confirm text becomes the message
 *   data-confirm-button="Yes, delete"             overrides the confirm button label
 *
 * From scripts: `if (await window.confirmDialog({ title: "Delete photo?", message: "..." })) { ... }`.
 */

/**
 * Splits "Delete Rahul? Their history is kept." into a short title ("Delete Rahul?") and body text.
 *
 * @param {string} text
 * @returns {{ title: string, message: string }}
 */
const splitMessage = (text) => {
    const match = text.match(/^([^?\n]{1,90}\?)\s+([\s\S]+)$/);

    return match ? { title: match[1], message: match[2].trim() } : { title: text, message: '' };
};

/**
 * Opens the dialog and resolves true when confirmed, false when cancelled (Cancel, Esc or backdrop).
 *
 * @param {{ title?: string, message?: string, confirmText?: string, cancelText?: string|null, tone?: 'danger'|'primary', icon?: "trash"|"warning"|"question", expected?: string|null }} options
 * @returns {Promise<boolean>}
 */
window.confirmDialog = (options = {}) =>
    new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('confirm-dialog', { detail: { ...options, resolve } }));
    });

document.addEventListener('alpine:init', () => {
    window.Alpine.data('confirmDialog', () => ({
        open: false,
        title: '',
        message: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        tone: 'danger',
        icon: 'warning',
        expected: null,
        typed: '',
        resolve: null,

        show(detail) {
            this.settle(false);

            const parts = detail.title ? { title: detail.title, message: detail.message ?? '' } : splitMessage(detail.message || 'Are you sure?');
            this.title = parts.title;
            this.message = parts.message;
            this.confirmText = detail.confirmText || 'Confirm';
            this.cancelText = detail.cancelText === undefined ? 'Cancel' : detail.cancelText;
            this.tone = detail.tone === 'primary' ? 'primary' : 'danger';
            this.icon = detail.icon ?? (this.tone === 'primary' ? 'question' : 'warning');
            this.expected = detail.expected ?? null;
            this.typed = '';
            this.resolve = detail.resolve ?? null;
            this.open = true;
        },

        accept() {
            if (this.expected !== null && this.typed !== this.expected) {
                return;
            }
            this.open = false;
            this.settle(true);
        },

        cancel() {
            this.open = false;
            this.settle(false);
        },

        settle(result) {
            const resolve = this.resolve;
            this.resolve = null;
            resolve?.(result);
        },
    }));
});

/** The confirm button label: the clicked button's own short text ("Delete", "Withdraw consent"), else "Confirm". */
const buttonLabel = (el) => {
    const text = el.textContent.replace(/\s+/g, ' ').trim();

    return text && text.length <= 32 ? text : 'Confirm';
};

/*
 * Livewire's own wire:confirm directive calls the blocking native confirm box. Livewire runs its built-in
 * directive first, then this hook replaces the element's confirm handler with the styled dialog.
 * The click is stopped straight away and the action runs only once the dialog is confirmed.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm') {
            return;
        }

        const isPrompt = directive.modifiers.includes('prompt');
        let text = directive.expression.replaceAll('\\n', '\n') || 'Are you sure?';
        let expected = null;
        if (isPrompt) {
            [text, expected = null] = text.split('|');
        }

        el.__livewire_confirm = (action, instead) => {
            instead();

            const tone = directive.modifiers.includes('primary') ? 'primary' : 'danger';
            const label = buttonLabel(el);
            const parts = el.dataset.confirmTitle ? { title: el.dataset.confirmTitle, message: text } : splitMessage(text);
            const startsWith = (pattern) => pattern.test(label) || pattern.test(parts.title);
            const isDelete = tone === 'danger' && startsWith(/^(delete|remove)\b/i);
            let message = parts.message;
            if (tone === 'danger' && startsWith(/^delete\b/i) && !/undone|restored/i.test(message)) {
                message = `${message} This action cannot be undone.`.trim();
            }

            window
                .confirmDialog({
                    title: parts.title,
                    message,
                    confirmText: el.dataset.confirmButton || (tone === 'danger' && label !== 'Confirm' ? `Yes, ${label}` : label),
                    tone,
                    icon: tone === 'primary' ? 'question' : isDelete ? 'trash' : 'warning',
                    expected,
                })
                .then((confirmed) => confirmed && action());
        };
    });

    /* Expired session (419) / empty response: Livewire would ask with the native confirm box; ask with the dialog instead. */
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, body, preventDefault }) => {
            const expired = response.status === 419;
            if (!expired && !(response.ok && (body ?? '').trim() === '')) {
                return;
            }
            preventDefault();
            window
                .confirmDialog({
                    title: expired ? 'This page has expired' : 'Something went wrong',
                    message: expired ? 'Refresh the page to continue.' : 'The server returned an empty response. Refresh the page to try again.',
                    confirmText: 'Refresh page',
                    cancelText: 'Not now',
                    tone: 'primary',
                })
                .then((confirmed) => confirmed && window.location.reload());
        });
    });
});

/**
 * Shows a toast (markup: resources/views/components/ui/toasts.blade.php), e.g. `window.toast('Saved.', 'success')`.
 *
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} [type]
 * @param {string|null} [title]
 */
window.toast = (message, type = 'info', title = null) => {
    window.dispatchEvent(new CustomEvent('notify', { detail: { message, type, title } }));
};
