/**
 * Admin feature components: TinyMCE, user edit, verify modal, scroll sync, etc.
 */
import Alpine from '@alpinejs/csp';

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// TinyMCE editor (loads from CDN, remains imperative)
Alpine.data('tinyMceEditor', () => ({
    init() {
        if (!document.getElementById('body') && !document.querySelector('.tinymce-editor')) return;
        this._loadAndInit();
    },

    _loadAndInit() {
        if (typeof tinymce !== 'undefined') { this._doInit(); return; }
        const body = document.getElementById('body');
        const editors = document.querySelectorAll('.tinymce-editor');
        let apiKey = 'no-api-key';
        const meta = document.querySelector('meta[name="tinymce-api-key"]');
        if (meta) { apiKey = meta.content; }
        else if (window.NNTmuxConfig?.tinymceApiKey) apiKey = window.NNTmuxConfig.tinymceApiKey;
        else if (body?.dataset.tinymceApiKey) apiKey = body.dataset.tinymceApiKey;
        else { for (let e of editors) { if (e.dataset.tinymceApiKey) { apiKey = e.dataset.tinymceApiKey; break; } } }

        const script = document.createElement('script');
        script.src = 'https://cdn.tiny.cloud/1/' + apiKey + '/tinymce/8/tinymce.min.js';
        script.referrerPolicy = 'origin';
        script.onload = () => this._doInit();
        script.onerror = () => {
            document.querySelectorAll('#body, .tinymce-editor').forEach(ta => {
                if (ta.parentElement) {
                    const err = document.createElement('div');
                    err.className = 'mt-2 p-3 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 rounded';
                    err.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>TinyMCE editor failed to load.';
                    ta.parentElement.insertBefore(err, ta.nextSibling);
                }
            });
        };
        document.head.appendChild(script);
    },

    _doInit() {
        const dark = document.documentElement.classList.contains('dark');
        tinymce.init({
            selector: '#body, .tinymce-editor', height: 500, menubar: true,
            skin: dark ? 'oxide-dark' : 'oxide', content_css: dark ? 'dark' : 'default',
            plugins: ['advlist','autolink','lists','link','image','charmap','preview','anchor','searchreplace','visualblocks','code','fullscreen','insertdatetime','media','table','help','wordcount','emoticons'],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table emoticons | removeformat code fullscreen | help',
            toolbar_mode: 'sliding', content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            branding: false, promotion: false, resize: true, statusbar: true, valid_elements: '*[*]', extended_valid_elements: '*[*]',
            setup: function(editor) { editor.on('change blur keyup submit', function() { editor.save(); }); }
        });

        // Watch dark mode changes to reinit TinyMCE
        new MutationObserver(() => {
            if (!tinymce.editors.length) return;
            const contents = {};
            tinymce.editors.forEach(e => { contents[e.id] = e.getContent(); });
            tinymce.remove();
            const d = document.documentElement.classList.contains('dark');
            tinymce.init({
                selector: '#body, .tinymce-editor', height: 500, menubar: true,
                skin: d ? 'oxide-dark' : 'oxide', content_css: d ? 'dark' : 'default',
                plugins: ['advlist','autolink','lists','link','image','charmap','preview','anchor','searchreplace','visualblocks','code','fullscreen','insertdatetime','media','table','help','wordcount','emoticons'],
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table emoticons | removeformat code fullscreen | help',
                toolbar_mode: 'sliding', branding: false, promotion: false, valid_elements: '*[*]', extended_valid_elements: '*[*]',
                setup: function(editor) { editor.on('change blur keyup submit', function() { editor.save(); }); }
            }).then(eds => { eds.forEach(e => { if (contents[e.id]) e.setContent(contents[e.id]); }); });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
}));

// User Edit datetime picker
Alpine.data('adminUserEdit', () => ({
    init() {
        if (!document.getElementById('rolechangedate')) return;
        this._initDateTime();
        this._setupExpiry();
    },

    _initDateTime() {
        const hidden = document.getElementById('rolechangedate');
        if (!hidden?.value) return;
        const d = new Date(hidden.value);
        if (isNaN(d.getTime())) return;
        this._setSelects(d);
        this._updatePreview();
    },

    _setSelects(d) {
        const ids = ['expiry_year','expiry_month','expiry_day','expiry_hour','expiry_minute'];
        const vals = [d.getFullYear().toString(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0'), String(d.getHours()).padStart(2,'0'), String(d.getMinutes()).padStart(2,'0')];
        ids.forEach((id, i) => { const el = document.getElementById(id); if (el) el.value = vals[i]; });
    },

    updateDateTime() {
        const y = document.getElementById('expiry_year')?.value;
        const mo = document.getElementById('expiry_month')?.value;
        const d = document.getElementById('expiry_day')?.value;
        const h = document.getElementById('expiry_hour')?.value;
        const mi = document.getElementById('expiry_minute')?.value;
        if (y && mo && d && h && mi) {
            document.getElementById('rolechangedate').value = y + '-' + mo + '-' + d + 'T' + h + ':' + mi + ':00';
            this._updatePreview();
        }
    },

    updateValidDays() {
        const y = parseInt(document.getElementById('expiry_year')?.value);
        const m = parseInt(document.getElementById('expiry_month')?.value);
        const daySelect = document.getElementById('expiry_day');
        if (!y || !m || !daySelect) return;
        const days = new Date(y, m, 0).getDate();
        const cur = parseInt(daySelect.value);
        daySelect.innerHTML = '<option value="">--</option>';
        for (let i = 1; i <= days; i++) { const o = document.createElement('option'); o.value = String(i).padStart(2,'0'); o.textContent = i; daySelect.appendChild(o); }
        if (cur && cur <= days) daySelect.value = String(cur).padStart(2,'0');
    },

    setExpiry(days, hours) {
        let base;
        const y = document.getElementById('expiry_year')?.value;
        const mo = document.getElementById('expiry_month')?.value;
        const d = document.getElementById('expiry_day')?.value;
        const h = document.getElementById('expiry_hour')?.value;
        const mi = document.getElementById('expiry_minute')?.value;
        const orig = document.getElementById('original_user_expiry')?.value;
        if (y && mo && d && h && mi) base = new Date(y, parseInt(mo)-1, d, h, mi);
        else if (orig) base = new Date(orig);
        else base = new Date();
        base.setDate(base.getDate() + days);
        base.setHours(base.getHours() + hours);
        this._setSelects(base);
        this.updateValidDays();
        this.updateDateTime();
    },

    setEndOfDay() {
        const d = new Date(); d.setHours(23,59,0,0);
        this._setSelects(d);
        this.updateValidDays();
        this.updateDateTime();
    },

    clearExpiry() {
        ['expiry_year','expiry_month','expiry_day','expiry_hour','expiry_minute'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        document.getElementById('rolechangedate').value = '';
        const p = document.getElementById('datetime_preview'); if (p) p.classList.add('hidden');
    },

    _setupExpiry() {
        ['expiry_year','expiry_month','expiry_day','expiry_hour','expiry_minute'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => this.updateDateTime());
        });
        const y = document.getElementById('expiry_year'), m = document.getElementById('expiry_month');
        if (y) y.addEventListener('change', () => this.updateValidDays());
        if (m) m.addEventListener('change', () => this.updateValidDays());
    },

    _updatePreview() {
        const hidden = document.getElementById('rolechangedate');
        const preview = document.getElementById('datetime_preview');
        const display = document.getElementById('datetime_display');
        if (!hidden?.value || !preview || !display) { if (preview) preview.classList.add('hidden'); return; }
        const d = new Date(hidden.value);
        display.textContent = d.toLocaleDateString('en-US', { weekday:'short', year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
        preview.classList.remove('hidden');
        const diff = d - new Date();
        display.classList.remove('text-red-600','dark:text-red-400','text-yellow-600','dark:text-yellow-400','text-blue-600','dark:text-blue-400');
        if (diff < 0) display.classList.add('text-red-600','dark:text-red-400');
        else if (diff < 7*24*60*60*1000) display.classList.add('text-yellow-600','dark:text-yellow-400');
        else display.classList.add('text-blue-600','dark:text-blue-400');
    }
}));

// Verify User Modal
Alpine.data('verifyUser', () => ({
    open: false,
    _form: null,

    show(form) { this._form = form; this.open = true; },
    hide() { this.open = false; this._form = null; },
    submit() { if (this._form) this._form.submit(); this.hide(); },

    init() {
        const self = this;
        window.showVerifyModal = function(e, form) { e.preventDefault(); self.show(form); };
        window.hideVerifyModal = function() { self.hide(); };
        window.submitVerifyForm = function() { self.submit(); };
    }
}));

// User List Scroll Sync
Alpine.data('adminUserList', () => ({
    init() {
        const top = document.getElementById('topScroll');
        const bottom = document.getElementById('bottomScroll');
        const content = document.getElementById('topScrollContent');
        const table = bottom?.querySelector('table');
        if (!top || !bottom || !content || !table) return;

        const sync = () => { content.style.width = table.scrollWidth + 'px'; };
        sync();
        window.addEventListener('resize', sync);
        top.addEventListener('scroll', function() { if (!top._syncing) { bottom._syncing = true; bottom.scrollLeft = top.scrollLeft; bottom._syncing = false; } });
        bottom.addEventListener('scroll', function() { if (!bottom._syncing) { top._syncing = true; top.scrollLeft = bottom.scrollLeft; top._syncing = false; } });
    }
}));

// Admin deleted users
Alpine.data('adminDeletedUsers', () => ({
    allChecked: false,

    toggleAll() {
        this.$el.querySelectorAll('.user-checkbox').forEach(cb => { cb.checked = this.allChecked; });
    },

    onCheckboxChange() {
        const all = this.$el.querySelectorAll('.user-checkbox');
        const checked = this.$el.querySelectorAll('.user-checkbox:checked');
        this.allChecked = all.length > 0 && checked.length === all.length;
    },

    submitBulkAction(e) {
        e.preventDefault();
        const action = this.$el.querySelector('#bulkAction')?.value;
        const checked = this.$el.querySelectorAll('.user-checkbox:checked');
        const errEl = document.getElementById('validationError');
        const errMsg = document.getElementById('validationErrorMessage');
        if (errEl) errEl.classList.add('hidden');

        if (!action) { if (errEl && errMsg) { errMsg.textContent = 'Please select an action.'; errEl.classList.remove('hidden'); } return; }
        if (checked.length === 0) { if (errEl && errMsg) { errMsg.textContent = 'Please select at least one user.'; errEl.classList.remove('hidden'); } return; }

        const text = action === 'restore' ? 'restore' : 'permanently delete';
        const type = action === 'restore' ? 'success' : 'danger';
        const form = this.$el.querySelector('#bulkActionForm');

        showConfirm({
            title: action === 'restore' ? 'Restore Users' : 'Delete Users',
            message: 'Are you sure you want to ' + text + ' ' + checked.length + ' user(s)?',
            type: type,
            confirmText: action === 'restore' ? 'Restore' : 'Delete',
            onConfirm: function() { if (form) form.submit(); }
        });
    },

    restoreUser(userId, username) {
        showConfirm({ title: 'Restore User', message: "Are you sure you want to restore user '" + username + "'?", type: 'success', confirmText: 'Restore', onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) { form.action = window.location.origin + '/admin/deleted-users/restore/' + userId; form.submit(); }
        }});
    },

    deleteUser(userId, username) {
        showConfirm({ title: 'Permanently Delete User', message: "Are you sure you want to PERMANENTLY delete user '" + username + "'?", details: 'This action cannot be undone!', type: 'danger', confirmText: 'Delete Permanently', onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) { form.action = window.location.origin + '/admin/deleted-users/permanent-delete/' + userId; form.submit(); }
        }});
    }
}));

// Invitations select all
Alpine.data('adminInvitations', () => ({
    allChecked: false,
    toggleAll() { this.$el.querySelectorAll('.invitation-checkbox').forEach(cb => { cb.checked = this.allChecked; }); },
    onCheckboxChange() {
        const all = this.$el.querySelectorAll('.invitation-checkbox');
        const checked = this.$el.querySelectorAll('.invitation-checkbox:checked');
        this.allChecked = all.length > 0 && checked.length === all.length;
    }
}));

// Regex form validation
Alpine.data('adminRegexForm', () => ({
    validate(e) {
        const inputs = this.$el.querySelectorAll('input[name*="regex"], textarea[name*="regex"]');
        let valid = true;
        inputs.forEach(input => {
            const val = input.value.trim();
            if (val && input.hasAttribute('required')) {
                const delims = ['/','/','#','~','%','@','!'];
                if (delims.includes(val[0])) {
                    let found = false;
                    for (let i = 1; i < val.length; i++) { if (val[i] === val[0]) { found = true; break; } }
                    if (!found) { valid = false; input.classList.add('border-red-500'); }
                    else input.classList.remove('border-red-500');
                }
            }
        });
        if (!valid) { e.preventDefault(); showToast('Please fix regex validation errors', 'error'); }
    }
}));

// Tmux crap types toggle
Alpine.data('tmuxEdit', () => ({
    customCrap: false,
    init() {
        const checked = document.querySelector('input[name="fix_crap_opt"]:checked');
        if (checked) this.customCrap = checked.value === 'Custom';
    },
    setCrapOpt(value) { this.customCrap = value === 'Custom'; }
}));

// Image fallbacks (global)
Alpine.data('imageFallback', () => ({
    onError(e) {
        const fallback = e.target.getAttribute('data-fallback-src');
        if (fallback && e.target.src !== fallback) e.target.src = fallback;
    }
}));

// Select redirect
Alpine.data('selectRedirect', () => ({
    onChange(e) {
        const url = e.target.value;
        if (url && url !== '#') window.location.href = url;
    }
}));

// Confirm delete (link or form)
Alpine.data('confirmAction', () => ({
    confirmDelete(e, message) {
        e.preventDefault();
        e.stopPropagation();
        const el = e.currentTarget;
        const form = el.closest('form');
        showConfirm({
            message: message || 'Are you sure you want to delete this item?',
            type: 'danger',
            confirmText: 'Delete',
            onConfirm: function() { if (form) form.submit(); else if (el.href) window.location.href = el.href; }
        });
    }
}));

// Download NZB toast
Alpine.data('downloadNzb', () => ({
    onClick() { showToast('Downloading NZB...', 'success'); }
}));

// Logout
Alpine.data('logout', () => ({
    submit(e) {
        e.preventDefault();
        const form = document.getElementById('logout-form') || document.getElementById('sidebar-logout-form');
        if (form) form.submit();
    }
}));

// Promotion toggle/delete
Alpine.data('promotionAction', () => ({
    togglePromotion(e, name, isActive, href) {
        e.preventDefault();
        const action = isActive ? 'deactivate' : 'activate';
        showConfirm({
            title: action.charAt(0).toUpperCase() + action.slice(1) + ' Promotion',
            message: 'Are you sure you want to ' + action + ' the promotion "' + name + '"?',
            type: isActive ? 'warning' : 'success',
            confirmText: action.charAt(0).toUpperCase() + action.slice(1),
            onConfirm: function() { window.location.href = href; }
        });
    },
    deletePromotion(e, name) {
        e.preventDefault();
        const form = e.currentTarget.closest('form');
        showConfirm({
            title: 'Delete Promotion',
            message: 'Are you sure you want to delete the promotion "' + name + '"?',
            details: 'This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            onConfirm: function() { if (form) form.submit(); }
        });
    }
}));

// Binary blacklist
Alpine.data('binaryBlacklist', () => ({
    deleteBlacklist(e, id) {
        e.preventDefault();
        if (confirm('Are you sure? This will delete the blacklist from this list.')) {
            if (typeof ajax_binaryblacklist_delete === 'function') ajax_binaryblacklist_delete(id);
        }
    }
}));

// Regex delete
Alpine.data('regexDelete', () => ({
    deleteRegex(e, id, deleteUrl) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this regex? This action cannot be undone.')) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(deleteUrl + '?id=' + id, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } })
            .then(r => r.json())
            .then(d => {
                if (d.success) { const row = document.getElementById('row-' + id); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); } showToast('Regex deleted successfully', 'success'); }
                else showToast('Error deleting regex', 'error');
            }).catch(() => showToast('Error deleting regex', 'error'));
        }
    }
}));

// Release delete
Alpine.data('releaseDelete', () => ({
    deleteRelease(e, id, deleteUrl) {
        e.preventDefault();
        const el = e.currentTarget;
        showConfirm({ title: 'Delete Release', message: 'Are you sure you want to delete this release? This action cannot be undone.', type: 'danger', confirmText: 'Delete', onConfirm: function() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            showToast('Deleting release...', 'info');
            fetch(deleteUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' } })
            .then(r => {
                if (r.ok) { const row = el.closest('tr'); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); } showToast('Release deleted successfully', 'success'); }
                else showToast('Error deleting release: ' + r.status, 'error');
            }).catch(err => showToast('Error deleting release: ' + err.message, 'error'));
        }});
    }
}));

// My Movies confirm
Alpine.data('myMovies', () => ({
    confirmRemove(e) {
        if (!confirm('Are you sure you want to remove this movie from your watchlist?')) e.preventDefault();
    }
}));
