/**
 * Alpine.data('profileEdit') - Profile edit page (2FA toggle, theme radios)
 * Alpine.data('profilePage') - Profile page (progress bars)
 * Alpine.data('copyToClipboard') - Copy button with visual feedback
 */
import Alpine from '@alpinejs/csp';

Alpine.data('profileEdit', () => ({
    show2faForm: false,

    toggle2fa() {
        this.show2faForm = !this.show2faForm;
    },

    cancel2fa() {
        this.show2faForm = false;
        const pwd = document.getElementById('disable_2fa_password');
        if (pwd) pwd.value = '';
    }
}));

Alpine.data('profilePage', () => ({
    init() {
        // Animate progress bars
        this.$el.querySelectorAll('.progress-bar').forEach(bar => {
            const width = bar.dataset.width;
            if (width) setTimeout(() => { bar.style.width = width + '%'; }, 100);
        });
    }
}));

Alpine.data('copyToClipboard', () => ({
    copied: false,

    copy(targetId) {
        const input = document.getElementById(targetId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);

        const text = input.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => this._showFeedback()).catch(() => this._fallbackCopy(text));
        } else {
            this._fallbackCopy(text);
        }
    },

    _fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); this._showFeedback(); } catch (e) { console.error('Failed to copy:', e); }
        document.body.removeChild(ta);
    },

    _showFeedback() {
        this.copied = true;
        setTimeout(() => { this.copied = false; }, 2000);
    }
}));
