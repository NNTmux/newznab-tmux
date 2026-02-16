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
        // Add CSP nonce for Content Security Policy compliance
        const nonceMeta = document.querySelector('meta[name="csp-nonce"]');
        if (nonceMeta) {
            script.nonce = nonceMeta.content;
        }
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

        // Setup quick action buttons
        document.querySelectorAll('[data-expiry-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.expiryAction;
                if (action === 'set') {
                    const days = parseInt(btn.dataset.days) || 0;
                    const hours = parseInt(btn.dataset.hours) || 0;
                    this.setExpiry(days, hours);
                } else if (action === 'end-of-day') {
                    this.setEndOfDay();
                } else if (action === 'clear') {
                    this.clearExpiry();
                }
            });
        });
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

/**
 * Document-level delegation for admin and global UI handlers.
 * Replaces event-bridge.js; keeps delegation for elements that may not have x-data.
 */
(function() {
    // --- Click delegation ---
    document.addEventListener('click', function(e) {
        // Admin Release Reports: Description modal
        var reportDescBtn = e.target.closest('.report-description-btn');
        if (reportDescBtn) {
            e.preventDefault();
            var modal = document.getElementById('reportDescriptionModal');
            if (modal) {
                document.getElementById('reportDescReason').textContent = reportDescBtn.dataset.reason || '';
                document.getElementById('reportDescReporter').textContent = reportDescBtn.dataset.reporter || '';
                document.getElementById('reportDescContent').textContent = reportDescBtn.dataset.description || '';
                modal.classList.remove('hidden');
            }
            return;
        }

        // Admin Release Reports: Close description modal
        if (e.target.closest('.report-desc-modal-close') || e.target.closest('.report-desc-modal-backdrop')) {
            e.preventDefault();
            var modal = document.getElementById('reportDescriptionModal');
            if (modal) modal.classList.add('hidden');
            return;
        }

        // Admin Release Reports: Revert button
        var revertBtn = e.target.closest('.revert-report-btn');
        if (revertBtn) {
            e.preventDefault();
            var modal = document.getElementById('revertConfirmModal');
            var form = document.getElementById('revertConfirmForm');
            var statusSpan = document.getElementById('revertReportStatus');
            if (modal && form) {
                form.action = revertBtn.dataset.actionUrl || '';
                if (statusSpan) statusSpan.textContent = revertBtn.dataset.reportStatus || '';
                modal.classList.remove('hidden');
            }
            return;
        }

        // Admin Release Reports: Close revert modal
        if (e.target.closest('.revert-modal-close') || e.target.closest('.revert-modal-backdrop')) {
            e.preventDefault();
            var modal = document.getElementById('revertConfirmModal');
            if (modal) modal.classList.add('hidden');
            return;
        }

        // Admin menu submenu toggle
        var menuToggle = e.target.closest('[data-toggle-submenu]');
        if (menuToggle) {
            var menuId = menuToggle.getAttribute('data-toggle-submenu');
            if (menuId) {
                var submenu = document.getElementById(menuId);
                var icon = document.getElementById(menuId + '-icon');
                if (submenu) submenu.classList.toggle('hidden');
                if (icon) icon.classList.toggle('rotate-180');
            }
            return;
        }

        // Admin groups data-action delegation
        var actionTarget = e.target.closest('[data-action]');
        if (actionTarget) {
            var action = actionTarget.dataset.action;
            var groupId = actionTarget.dataset.groupId;
            var status = actionTarget.dataset.status;
            switch (action) {
                case 'show-reset-modal': if (typeof showResetAllModal === 'function') showResetAllModal(); break;
                case 'hide-reset-modal': if (typeof hideResetAllModal === 'function') hideResetAllModal(); break;
                case 'show-purge-modal': if (typeof showPurgeAllModal === 'function') showPurgeAllModal(); break;
                case 'hide-purge-modal': if (typeof hidePurgeAllModal === 'function') hidePurgeAllModal(); break;
                case 'show-reset-selected-modal': if (typeof showResetSelectedModal === 'function') showResetSelectedModal(); break;
                case 'hide-reset-selected-modal': if (typeof hideResetSelectedModal === 'function') hideResetSelectedModal(); break;
                case 'select-all-groups': if (typeof toggleSelectAllGroups === 'function') toggleSelectAllGroups(actionTarget); break;
                case 'toggle-group-status': if (typeof ajax_group_status === 'function') ajax_group_status(groupId, status); break;
                case 'toggle-backfill': if (typeof ajax_backfill_status === 'function') ajax_backfill_status(groupId, status); break;
                case 'reset-group': if (typeof ajax_group_reset === 'function') ajax_group_reset(groupId); break;
                case 'delete-group': if (typeof confirmGroupDelete === 'function') confirmGroupDelete(groupId); break;
                case 'purge-group': if (typeof confirmGroupPurge === 'function') confirmGroupPurge(groupId); break;
                case 'reset-all': if (typeof ajax_group_reset_all === 'function') ajax_group_reset_all(); break;
                case 'purge-all': if (typeof ajax_group_purge_all === 'function') ajax_group_purge_all(); break;
                case 'reset-selected': if (typeof ajax_group_reset_selected === 'function') ajax_group_reset_selected(); break;
            }
            return;
        }

        // Verify user modal
        var verifyBtn = e.target.closest('[data-show-verify-modal]');
        if (verifyBtn) { var form = verifyBtn.closest('form'); if (form && typeof showVerifyModal === 'function') showVerifyModal(e, form); return; }
        if (e.target.closest('[data-close-verify-modal]')) { e.preventDefault(); if (typeof hideVerifyModal === 'function') hideVerifyModal(); return; }
        if (e.target.closest('[data-submit-verify-form]')) { e.preventDefault(); if (typeof submitVerifyForm === 'function') submitVerifyForm(); return; }

        // Restore/delete user buttons
        var restoreBtn = e.target.closest('.restore-user-btn');
        if (restoreBtn) {
            e.preventDefault();
            var userId = restoreBtn.getAttribute('data-user-id');
            var username = restoreBtn.getAttribute('data-username') || 'this user';
            showConfirm({ title: 'Restore User', message: 'Are you sure you want to restore user "' + username + '"?', type: 'success', confirmText: 'Restore', onConfirm: function() {
                var form = document.getElementById('individualActionForm');
                if (form) { form.action = '/admin/deleted-users/restore/' + userId; form.method = 'POST'; form.submit(); }
            }});
            return;
        }

        var deleteUserBtn = e.target.closest('.delete-user-btn');
        if (deleteUserBtn) {
            e.preventDefault();
            var userId = deleteUserBtn.getAttribute('data-user-id');
            var username = deleteUserBtn.getAttribute('data-username') || 'this user';
            showConfirm({ title: 'Permanently Delete User', message: 'Are you sure you want to permanently delete user "' + username + '"? This action cannot be undone.', type: 'danger', confirmText: 'Delete Permanently', onConfirm: function() {
                var form = document.getElementById('individualActionForm');
                if (form) { form.action = '/admin/deleted-users/permanent-delete/' + userId; form.method = 'POST'; form.submit(); }
            }});
            return;
        }

        // Promotion toggle/delete
        var promoToggle = e.target.closest('.promotion-toggle-btn');
        if (promoToggle) {
            e.preventDefault();
            var name = promoToggle.getAttribute('data-promotion-name');
            var active = promoToggle.getAttribute('data-promotion-active') === '1';
            var action = active ? 'deactivate' : 'activate';
            showConfirm({
                title: action.charAt(0).toUpperCase() + action.slice(1) + ' Promotion',
                message: 'Are you sure you want to ' + action + ' the promotion "' + name + '"?',
                type: active ? 'warning' : 'success',
                confirmText: action.charAt(0).toUpperCase() + action.slice(1),
                onConfirm: function() { window.location.href = promoToggle.href; }
            });
            return;
        }

        var promoDelete = e.target.closest('.promotion-delete-btn');
        if (promoDelete) {
            e.preventDefault();
            var name = promoDelete.getAttribute('data-promotion-name');
            var form = promoDelete.closest('form');
            showConfirm({ title: 'Delete Promotion', message: 'Are you sure you want to delete the promotion "' + name + '"?', details: 'This action cannot be undone.', type: 'danger', confirmText: 'Delete', onConfirm: function() { if (form) form.submit(); } });
            return;
        }

        // Content toggle/delete
        var contentToggle = e.target.closest('.content-toggle-status');
        if (contentToggle) {
            e.preventDefault();
            var id = contentToggle.getAttribute('data-content-id');
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!id || !csrf) return;
            contentToggle.disabled = true; contentToggle.style.opacity = '0.6';
            fetch('/admin/content-toggle-status', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: id })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) {
                    var row = contentToggle.closest('tr'), sc = row.cells[5], ns = data.status;
                    if (ns === 1) { sc.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100"><i class="fa fa-check mr-1"></i>Enabled</span>'; contentToggle.className = 'content-toggle-status text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300'; contentToggle.innerHTML = '<i class="fa fa-toggle-on"></i>'; }
                    else { sc.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100"><i class="fa fa-times mr-1"></i>Disabled</span>'; contentToggle.className = 'content-toggle-status text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300'; contentToggle.innerHTML = '<i class="fa fa-toggle-off"></i>'; }
                    contentToggle.setAttribute('data-current-status', ns); contentToggle.title = ns === 1 ? 'Disable' : 'Enable';
                    showToast(data.message, 'success');
                } else showToast(data.message || 'Failed', 'error');
                contentToggle.disabled = false; contentToggle.style.opacity = '1';
            }).catch(function() { showToast('Error', 'error'); contentToggle.disabled = false; contentToggle.style.opacity = '1'; });
            return;
        }

        var contentDelete = e.target.closest('.content-delete');
        if (contentDelete) {
            e.preventDefault();
            var id = contentDelete.getAttribute('data-content-id');
            var title = contentDelete.getAttribute('data-content-title');
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!id || !csrf) return;
            showConfirm({ title: 'Delete Content', message: 'Are you sure you want to delete "' + title + '"?', details: 'This action cannot be undone.', type: 'danger', confirmText: 'Delete',
                onConfirm: function() {
                    contentDelete.disabled = true; contentDelete.style.opacity = '0.6';
                    fetch('/admin/content-delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ id: id }) })
                    .then(function(r) { return r.json(); }).then(function(data) {
                        if (data.success) { var row = contentDelete.closest('tr'); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(function() { row.remove(); if (document.querySelector('tbody')?.children.length === 0) location.reload(); }, 300); } showToast(data.message, 'success'); }
                        else { showToast(data.message || 'Failed', 'error'); contentDelete.disabled = false; contentDelete.style.opacity = '1'; }
                    }).catch(function() { showToast('Error', 'error'); contentDelete.disabled = false; contentDelete.style.opacity = '1'; });
                }
            });
            return;
        }

        // Regex delete
        var regexDel = e.target.closest('[data-delete-regex]');
        if (regexDel) {
            e.preventDefault();
            var id = regexDel.getAttribute('data-delete-regex');
            var url = regexDel.getAttribute('data-delete-url');
            if (confirm('Are you sure you want to delete this regex?')) {
                var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                fetch(url + '?id=' + id, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } })
                .then(function(r) { return r.json(); }).then(function(d) {
                    if (d.success) { var row = document.getElementById('row-' + id); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(function() { row.remove(); }, 300); } showToast('Regex deleted', 'success'); }
                    else showToast('Error', 'error');
                }).catch(function() { showToast('Error', 'error'); });
            }
            return;
        }

        // Release delete
        var relDel = e.target.closest('[data-delete-release]');
        if (relDel) {
            e.preventDefault();
            var deleteUrl = relDel.getAttribute('data-delete-url');
            showConfirm({ title: 'Delete Release', message: 'Are you sure? This cannot be undone.', type: 'danger', confirmText: 'Delete',
                onConfirm: function() {
                    var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                    showToast('Deleting...', 'info');
                    fetch(deleteUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' } })
                    .then(function(r) { if (r.ok) { var row = relDel.closest('tr'); if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(function() { row.remove(); },300); } showToast('Deleted', 'success'); } else showToast('Error: ' + r.status, 'error'); })
                    .catch(function(err) { showToast('Error: ' + err.message, 'error'); });
                }
            });
            return;
        }

        // Binary blacklist delete
        var blDel = e.target.closest('[data-delete-blacklist]');
        if (blDel) {
            e.preventDefault();
            var id = blDel.getAttribute('data-delete-blacklist');
            if (confirm('Are you sure? This will delete the blacklist.')) {
                if (typeof ajax_binaryblacklist_delete === 'function') ajax_binaryblacklist_delete(id);
            }
            return;
        }

        // Season tab
        var seasonTab = e.target.closest('.season-tab');
        if (seasonTab) {
            e.preventDefault();
            var num = seasonTab.getAttribute('data-season');
            if (num && typeof switchSeason === 'function') switchSeason(num);
            return;
        }

        // My Movies confirm
        var confirmAction = e.target.closest('.confirm_action');
        if (confirmAction) {
            if (!confirm('Are you sure you want to remove this movie from your watchlist?')) e.preventDefault();
            return;
        }
    });

    // --- Change event delegation ---
    document.addEventListener('change', function(e) {
        if (e.target.hasAttribute('data-redirect-on-change')) {
            var url = e.target.value;
            if (url && url !== '#') window.location.href = url;
        }
        if (e.target.classList.contains('group-checkbox')) {
            if (typeof updateSelectionUI === 'function') updateSelectionUI();
        }
    });

    // --- Image fallbacks ---
    document.querySelectorAll('img[data-fallback-src], img[data-hide-on-error]').forEach(function(img) {
        img.addEventListener('error', function() {
            var fb = this.getAttribute('data-fallback-src');
            if (fb && this.src !== fb) { this.src = fb; return; }
            if (this.hasAttribute('data-hide-on-error')) this.style.display = 'none';
        });
    });

    // --- Escape key for admin modals ---
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        var descModal = document.getElementById('reportDescriptionModal');
        if (descModal && !descModal.classList.contains('hidden')) descModal.classList.add('hidden');
        var revertModal = document.getElementById('revertConfirmModal');
        if (revertModal && !revertModal.classList.contains('hidden')) revertModal.classList.add('hidden');
    });

    // --- Admin select-alls and bulk forms ---
    // Release reports select-all
    var selectAllReports = document.getElementById('select-all');
    if (selectAllReports) {
        var boxes = document.querySelectorAll('.report-checkbox');
        if (boxes.length > 0) {
            selectAllReports.addEventListener('change', function() { boxes.forEach(function(cb) { cb.checked = selectAllReports.checked; }); });
            boxes.forEach(function(cb) { cb.addEventListener('change', function() {
                var all = Array.from(boxes).every(function(c) { return c.checked; });
                var some = Array.from(boxes).some(function(c) { return c.checked; });
                selectAllReports.checked = all; selectAllReports.indeterminate = some && !all;
            }); });
        }
    }

    // Invitations select-all
    var selectAllInv = document.getElementById('select_all');
    if (selectAllInv) {
        var invBoxes = document.querySelectorAll('.invitation-checkbox');
        selectAllInv.addEventListener('change', function() { invBoxes.forEach(function(cb) { cb.checked = selectAllInv.checked; }); });
        invBoxes.forEach(function(cb) { cb.addEventListener('change', function() {
            var all = Array.from(invBoxes).every(function(c) { return c.checked; });
            var some = Array.from(invBoxes).some(function(c) { return c.checked; });
            selectAllInv.checked = all; selectAllInv.indeterminate = some && !all;
        }); });
    }

    // Deleted users select-all
    var selectAllUsers = document.getElementById('selectAll');
    if (selectAllUsers) {
        var userBoxes = document.querySelectorAll('.user-checkbox');
        selectAllUsers.addEventListener('change', function() { userBoxes.forEach(function(cb) { cb.checked = selectAllUsers.checked; }); });
        userBoxes.forEach(function(cb) { cb.addEventListener('change', function() {
            var all = Array.from(userBoxes).every(function(c) { return c.checked; });
            var some = Array.from(userBoxes).some(function(c) { return c.checked; });
            selectAllUsers.checked = all; selectAllUsers.indeterminate = some && !all;
        }); });
    }

    // Bulk action form
    var bulkForm = document.getElementById('bulkActionForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            var action = document.getElementById('bulkAction')?.value;
            var checked = document.querySelectorAll('.user-checkbox:checked');
            var errEl = document.getElementById('validationError'), errMsg = document.getElementById('validationErrorMessage');
            if (errEl) errEl.classList.add('hidden');
            if (!action) { if (errEl && errMsg) { errMsg.textContent = 'Please select an action.'; errEl.classList.remove('hidden'); } return; }
            if (!checked.length) { if (errEl && errMsg) { errMsg.textContent = 'Please select at least one user.'; errEl.classList.remove('hidden'); } return; }
            var text = action === 'restore' ? 'restore' : 'permanently delete';
            showConfirm({ title: action === 'restore' ? 'Restore Users' : 'Delete Users', message: 'Are you sure you want to ' + text + ' ' + checked.length + ' user(s)?', type: action === 'restore' ? 'success' : 'danger', confirmText: action === 'restore' ? 'Restore' : 'Delete', onConfirm: function() { bulkForm.submit(); } });
        });
    }

    // Tmux crap types toggle
    var checkedRadio = document.querySelector('input[name="fix_crap_opt"]:checked');
    var crapContainer = document.getElementById('crap_types_container');
    if (crapContainer) {
        if (checkedRadio) crapContainer.style.display = checkedRadio.value === 'Custom' ? 'block' : 'none';
        document.querySelectorAll('input[name="fix_crap_opt"]').forEach(function(r) { r.addEventListener('change', function() { crapContainer.style.display = this.value === 'Custom' ? 'block' : 'none'; }); });
    }
})();
