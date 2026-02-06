/**
 * Admin Features - TinyMCE, Image Preview, Invitations, Regex, Tmux Colors,
 * Deleted Users, User Edit, Verify Modal, User List Scroll Sync, Tmux Edit
 */

import { escapeHtml } from '../utils.js';

function initTinyMCE() {
    // Only initialize if TinyMCE editor element exists (check for #body or .tinymce-editor)
    if (!document.getElementById('body') && !document.querySelector('.tinymce-editor')) {
        return;
    }

    // Function to dynamically load TinyMCE from CDN
    function loadTinyMCEScript() {
        return new Promise(function(resolve, reject) {
            // Check if already loaded
            if (typeof tinymce !== 'undefined') {
                resolve();
                return;
            }

            // Get API key from textarea data attribute or create meta tag
            const bodyTextarea = document.getElementById('body');
            const tinymceEditors = document.querySelectorAll('.tinymce-editor');
            let apiKey = 'no-api-key';

            // Try to get from existing meta tag first
            let apiKeyMeta = document.querySelector('meta[name="tinymce-api-key"]');

            if (!apiKeyMeta) {
                // Create meta tag dynamically if it doesn't exist
                // Get API key from window config or use default
                if (window.NNTmuxConfig && window.NNTmuxConfig.tinymceApiKey) {
                    apiKey = window.NNTmuxConfig.tinymceApiKey;
                } else if (bodyTextarea && bodyTextarea.dataset.tinymceApiKey) {
                    apiKey = bodyTextarea.dataset.tinymceApiKey;
                } else if (tinymceEditors.length > 0) {
                    // Check if any tinymce-editor has the API key
                    for (let i = 0; i < tinymceEditors.length; i++) {
                        if (tinymceEditors[i].dataset.tinymceApiKey) {
                            apiKey = tinymceEditors[i].dataset.tinymceApiKey;
                            break;
                        }
                    }
                }

                // Create and append meta tag
                apiKeyMeta = document.createElement('meta');
                apiKeyMeta.setAttribute('name', 'tinymce-api-key');
                apiKeyMeta.setAttribute('content', apiKey);
                document.head.appendChild(apiKeyMeta);
            } else {
                apiKey = apiKeyMeta.content;
            }

            // Create script element
            const script = document.createElement('script');
            script.src = 'https://cdn.tiny.cloud/1/' + apiKey + '/tinymce/8/tinymce.min.js';
            script.referrerPolicy = 'origin';

            script.onload = function() {
                console.log('TinyMCE script loaded from CDN');
                resolve();
            };

            script.onerror = function() {
                console.error('Failed to load TinyMCE script from CDN');
                reject(new Error('Failed to load TinyMCE'));
            };

            // Append to head
            document.head.appendChild(script);
        });
    }

    // Function to detect if dark mode is active
    function isDarkMode() {
        return document.documentElement.classList.contains('dark') ||
            (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    // Function to get TinyMCE config
    function getTinyMCEConfig() {
        const darkMode = isDarkMode();
        const apiKeyMeta = document.querySelector('meta[name="tinymce-api-key"]');
        const apiKey = apiKeyMeta ? apiKeyMeta.content : 'no-api-key';

        return {
            selector: '#body, .tinymce-editor',
            height: 500,
            menubar: true,
            skin: darkMode ? 'oxide-dark' : 'oxide',
            content_css: darkMode ? 'dark' : 'default',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | ' +
                'bold italic underline strikethrough | forecolor backcolor | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | ' +
                'link image media table emoticons | ' +
                'removeformat code fullscreen | help',
            toolbar_mode: 'sliding',
            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            branding: false,
            promotion: false,
            resize: true,
            statusbar: true,
            elementpath: true,
            automatic_uploads: false,
            file_picker_types: 'image',
            font_family_formats: 'Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,palatino,serif; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times,serif; Verdana=verdana,geneva,sans-serif',
            font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
            autolink_pattern: /^(https?:\/\/|www\.|(?!www\.)[a-z0-9\-]+\.[a-z]{2,13})/i,
            link_default_protocol: 'https',
            link_assume_external_targets: true,
            link_target_list: [
                {title: 'None', value: ''},
                {title: 'New window', value: '_blank'},
                {title: 'Same window', value: '_self'}
            ],
            block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre; Blockquote=blockquote',
            valid_elements: '*[*]',
            extended_valid_elements: '*[*]',
            valid_children: '+body[style]',
            paste_as_text: false,
            paste_block_drop: false,
            paste_data_images: true,
            image_advtab: true,
            image_caption: true,
            image_description: true,
            image_dimensions: true,
            image_title: true,
            table_default_attributes: {
                border: '1'
            },
            table_default_styles: {
                'border-collapse': 'collapse',
                'width': '100%'
            },
            emoticons_database: 'emojis',
            setup: function(editor) {
                // Auto-save on content change
                editor.on('change', function() {
                    editor.save();
                });
                // Auto-save on blur (when editor loses focus)
                editor.on('blur', function() {
                    editor.save();
                });
                // Auto-save on keyup (for continuous saving)
                editor.on('keyup', function() {
                    editor.save();
                });
                // Save before form submission
                editor.on('submit', function() {
                    editor.save();
                });
            }
        };
    }

    // Function to actually initialize TinyMCE once it's loaded
    function doInitTinyMCE() {
        // Initialize TinyMCE
        tinymce.init(getTinyMCEConfig()).then(function(editors) {
            if (editors && editors.length > 0) {
                console.log('TinyMCE initialized successfully for ' + editors.length + ' editor(s)');

                // Add form submission handler to sync content for all editors
                editors.forEach(function(editor) {
                    const textarea = document.getElementById(editor.id);
                    if (textarea && textarea.form) {
                        // Remove any existing listeners to avoid duplicates
                        const form = textarea.form;
                        if (!form.hasAttribute('data-tinymce-handler')) {
                            form.addEventListener('submit', function(e) {
                                // Sync all TinyMCE editors before form submission
                                tinymce.triggerSave();
                                console.log('TinyMCE content synced to textareas before form submission');
                            });
                            form.setAttribute('data-tinymce-handler', 'true');
                        }
                    }
                });
            }
        }).catch(function(error) {
            console.error('TinyMCE initialization failed:', error);
        });

        // Watch for theme changes and reinitialize TinyMCE
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    // Get all active TinyMCE editors
                    const allEditors = tinymce.editors;
                    if (allEditors && allEditors.length > 0) {
                        // Save content from all editors
                        const editorContents = {};
                        allEditors.forEach(function(editor) {
                            editorContents[editor.id] = editor.getContent();
                        });

                        // Remove all editors
                        tinymce.remove();

                        // Reinitialize with new theme
                        tinymce.init(getTinyMCEConfig()).then(function(editors) {
                            // Restore content to each editor
                            if (editors && editors.length > 0) {
                                editors.forEach(function(editor) {
                                    if (editorContents[editor.id]) {
                                        editor.setContent(editorContents[editor.id]);
                                    }
                                });
                            }
                        });
                    }
                }
            });
        });

        // Start observing theme changes on the html element
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    // Load TinyMCE script dynamically, then initialize
    loadTinyMCEScript()
        .then(function() {
            console.log('TinyMCE available, initializing editor...');
            doInitTinyMCE();
        })
        .catch(function(error) {
            console.error('Failed to load TinyMCE:', error);
            // Show error message to user for all TinyMCE textareas
            const textareas = document.querySelectorAll('#body, .tinymce-editor');
            textareas.forEach(function(textarea) {
                if (textarea && textarea.parentElement) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mt-2 p-3 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 rounded';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>TinyMCE editor failed to load. Please refresh the page or check your internet connection.';
                    textarea.parentElement.insertBefore(errorDiv, textarea.nextSibling);
                }
            });
        });
}

// Admin Image Preview functionality
function initAdminImagePreview() {
    // Handle admin page image previews (for cover images, etc.)
    // This uses the existing image modal functionality
    document.addEventListener('click', function(e) {
        const imageTrigger = e.target.closest('[data-admin-image-preview]');
        if (imageTrigger) {
            e.preventDefault();
            const imageUrl = imageTrigger.getAttribute('data-admin-image-preview');
            if (imageUrl && typeof openImageModal === 'function') {
                openImageModal(imageUrl);
            }
        }
    });

    // Preview image on file input change (for admin edit pages)
    document.querySelectorAll('input[type="file"][accept*="image"]').forEach(function(fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Find preview container
                    const previewContainer = fileInput.closest('div').querySelector('.image-preview');
                    if (previewContainer) {
                        let previewImg = previewContainer.querySelector('img');
                        if (!previewImg) {
                            previewImg = document.createElement('img');
                            previewImg.className = 'w-full h-auto rounded-lg';
                            previewContainer.innerHTML = '';
                            previewContainer.appendChild(previewImg);
                        }
                        previewImg.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Admin Invitations Select All functionality
function initAdminInvitationsSelectAll() {
    const selectAllCheckbox = document.getElementById('select_all');
    if (!selectAllCheckbox) {
        return; // Not on invitations page
    }

    const invitationCheckboxes = document.querySelectorAll('.invitation-checkbox');

    // Handle select all checkbox change
    selectAllCheckbox.addEventListener('change', function() {
        invitationCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Handle individual checkbox changes to update select all state
    invitationCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(invitationCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(invitationCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        });
    });

    // Initialize the select all state on page load
    const allChecked = invitationCheckboxes.length > 0 && Array.from(invitationCheckboxes).every(cb => cb.checked);
    selectAllCheckbox.checked = allChecked;
}

// Admin Regex Form Validation
function initAdminRegexFormValidation() {
    const regexForm = document.getElementById('regexForm');
    if (!regexForm) {
        return; // Not on a regex edit page
    }

    // Validate regex pattern on form submit
    regexForm.addEventListener('submit', function(e) {
        const regexInputs = regexForm.querySelectorAll('input[name*="regex"], textarea[name*="regex"]');
        let isValid = true;
        const errors = [];

        regexInputs.forEach(input => {
            const value = input.value.trim();
            if (value && input.hasAttribute('required')) {
                // Basic regex validation - check if it looks like a valid regex pattern
                // Regex should typically start with a delimiter (/, #, ~, etc.)
                // and have matching delimiters
                if (value.length > 0) {
                    const firstChar = value[0];
                    const delimiters = ['/', '#', '~', '%', '@', '!',];

                    if (delimiters.includes(firstChar)) {
                        // Check if regex has matching closing delimiter
                        let delimiterCount = 0;
                        let flags = '';
                        for (let i = 0; i < value.length; i++) {
                            if (value[i] === firstChar && i > 0) {
                                delimiterCount++;
                                // Check if there are flags after this delimiter
                                flags = value.substring(i + 1);
                                break;
                            }
                        }

                        if (delimiterCount === 0) {
                            isValid = false;
                            errors.push(`Regex pattern "${input.name}" is missing closing delimiter "${firstChar}"`);
                            input.classList.add('border-red-500');
                        } else {
                            input.classList.remove('border-red-500');
                            // Validate flags if present
                            if (flags && !/^[gimsux]*$/.test(flags)) {
                                isValid = false;
                                errors.push(`Invalid regex flags in "${input.name}". Allowed: g, i, m, s, u, x`);
                                input.classList.add('border-red-500');
                            }
                        }
                    } else if (value.length > 0) {
                        // Regex doesn't start with a delimiter, might still be valid but warn
                        input.classList.add('border-yellow-500');
                    }
                }
            }
        });

        if (!isValid && errors.length > 0) {
            e.preventDefault();
            const errorMsg = errors.join('\n');
            if (typeof showToast === 'function') {
                showToast('Please fix regex validation errors', 'error');
            } else {
                alert(errorMsg);
            }
        }
    });

    // Real-time validation feedback for regex inputs
    const regexInputs = regexForm.querySelectorAll('input[name*="regex"], textarea[name*="regex"]');
    regexInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                const firstChar = value[0];
                const delimiters = ['/', '#', '~', '%', '@', '!',];

                if (delimiters.includes(firstChar)) {
                    let delimiterCount = 0;
                    for (let i = 1; i < value.length; i++) {
                        if (value[i] === firstChar) {
                            delimiterCount++;
                            break;
                        }
                    }

                    if (delimiterCount === 0) {
                        this.classList.add('border-red-500');
                        this.classList.remove('border-green-500');
                    } else {
                        this.classList.remove('border-red-500');
                        this.classList.add('border-green-500');
                    }
                } else {
                    this.classList.remove('border-red-500', 'border-green-500');
                }
            } else {
                this.classList.remove('border-red-500', 'border-green-500');
            }
        });
    });
}

// Admin Tmux Select Colors functionality
function initAdminTmuxSelectColors() {
    // This function is for future tmux color selection functionality
    // Currently, no color selection is needed in tmux settings
    // If color pickers are added to tmux-edit page in the future, add them here

    // Check if we're on a page that might have color inputs
    const colorInputs = document.querySelectorAll('input[type="color"], select[data-color-select]');
    if (colorInputs.length > 0) {
        colorInputs.forEach(function(input) {
            // Add color preview functionality if needed
            if (input.type === 'color') {
                input.addEventListener('change', function() {
                    // Update preview or related elements if needed
                    const preview = input.closest('.form-group')?.querySelector('.color-preview');
                    if (preview) {
                        preview.style.backgroundColor = input.value;
                    }
                });
            }
        });
    }
}

// Initialize all admin-specific features
function initAdminSpecificFeatures() {
    initAdminImagePreview();
    initAdminInvitationsSelectAll();
    initAdminRegexFormValidation();
    initAdminTmuxSelectColors();
    initAdminDeletedUsers();

    // Close delete modal when clicking outside
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }

    // Close verify modal when clicking outside
    const verifyUserModal = document.getElementById('verifyUserModal');
    if (verifyUserModal) {
        verifyUserModal.addEventListener('click', function(event) {
            if (event.target === this) {
                hideVerifyModal();
            }
        });
    }
}

// Admin Deleted Users page functionality
function initAdminDeletedUsers() {
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Update selectAll state when individual checkboxes change
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(userCheckboxes).some(cb => cb.checked);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
        });
    });

    // Bulk action form submit handler
    const bulkActionForm = document.getElementById('bulkActionForm');
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const action = document.getElementById('bulkAction')?.value;
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const validationError = document.getElementById('validationError');
            const validationErrorMessage = document.getElementById('validationErrorMessage');

            // Hide any previous validation errors
            if (validationError) {
                validationError.classList.add('hidden');
            }

            // Validate action selected
            if (!action) {
                if (validationError && validationErrorMessage) {
                    validationErrorMessage.textContent = 'Please select an action from the dropdown.';
                    validationError.classList.remove('hidden');
                }
                return false;
            }

            // Validate at least one user selected
            if (checkedBoxes.length === 0) {
                if (validationError && validationErrorMessage) {
                    validationErrorMessage.textContent = 'Please select at least one user.';
                    validationError.classList.remove('hidden');
                }
                return false;
            }

            const count = checkedBoxes.length;
            const actionText = action === 'restore' ? 'restore' : 'permanently delete';
            const type = action === 'restore' ? 'success' : 'danger';
            const title = action === 'restore' ? 'Restore Users' : 'Delete Users';

            showConfirm({
                title: title,
                message: `Are you sure you want to ${actionText} ${count} user${count > 1 ? 's' : ''}?`,
                type: type,
                confirmText: action === 'restore' ? 'Restore' : 'Delete',
                onConfirm: function() {
                    bulkActionForm.submit();
                }
            });
        });
    }

    // Event delegation for restore buttons
    document.addEventListener('click', function(e) {
        const restoreBtn = e.target.closest('.restore-user-btn');
        if (restoreBtn) {
            e.preventDefault();
            const userId = restoreBtn.dataset.userId;
            const username = restoreBtn.dataset.username;
            if (userId && username) {
                restoreUser(userId, username);
            }
        }

        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            e.preventDefault();
            const userId = deleteBtn.dataset.userId;
            const username = deleteBtn.dataset.username;
            if (userId && username) {
                permanentDeleteUser(userId, username);
            }
        }
    });

    // Bulk action confirmation (kept for backward compatibility)
    window.confirmBulkAction = function(event) {
        event?.preventDefault();

        const action = document.getElementById('bulkAction')?.value;
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const validationError = document.getElementById('validationError');
        const validationErrorMessage = document.getElementById('validationErrorMessage');

        if (!action) {
            if (validationError && validationErrorMessage) {
                validationErrorMessage.textContent = 'Please select an action from the dropdown.';
                validationError.classList.remove('hidden');
            }
            return false;
        }

        if (checkedBoxes.length === 0) {
            if (validationError && validationErrorMessage) {
                validationErrorMessage.textContent = 'Please select at least one user.';
                validationError.classList.remove('hidden');
            }
            return false;
        }

        // Hide validation error if showing
        if (validationError) {
            validationError.classList.add('hidden');
        }

        const count = checkedBoxes.length;
        const actionText = action === 'restore' ? 'restore' : 'permanently delete';
        const type = action === 'restore' ? 'success' : 'danger';
        const title = action === 'restore' ? 'Restore Users' : 'Delete Users';

        showConfirm({
            title: title,
            message: `Are you sure you want to ${actionText} ${count} user${count > 1 ? 's' : ''}?`,
            type: type,
            confirmText: action === 'restore' ? 'Restore' : 'Delete',
            onConfirm: function() {
                document.getElementById('bulkActionForm')?.submit();
            }
        });

        return false;
    };
}

// Individual user restore/delete actions for deleted users page
window.restoreUser = function(userId, username) {
    showConfirm({
        title: 'Restore User',
        message: `Are you sure you want to restore user '${username}'?`,
        type: 'success',
        confirmText: 'Restore',
        onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) {
                const baseUrl = window.location.origin;
                form.action = `${baseUrl}/admin/deleted-users/restore/${userId}`;
                form.submit();
            }
        }
    });
};

window.permanentDeleteUser = function(userId, username) {
    showConfirm({
        title: 'Permanently Delete User',
        message: `Are you sure you want to PERMANENTLY delete user '${username}'?`,
        details: 'This action cannot be undone!',
        type: 'danger',
        confirmText: 'Delete Permanently',
        onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) {
                const baseUrl = window.location.origin;
                // Use the correct route: permanent-delete
                form.action = `${baseUrl}/admin/deleted-users/permanent-delete/${userId}`;
                form.submit();
            }
        }
    });
};

function initAdminUserEdit() {
    // Only run if we're on the admin user edit page
    if (!document.getElementById('rolechangedate')) {
        return;
    }

    initializeDateTimePicker();
    setupTypeToSelect();
    setupExpiryDateHandlers();
}

// Initialize the datetime picker with existing values
function initializeDateTimePicker() {
    const hiddenInput = document.getElementById('rolechangedate');
    if (!hiddenInput) return;

    if (hiddenInput.value) {
        const date = new Date(hiddenInput.value);
        if (!isNaN(date.getTime())) {
            document.getElementById('expiry_year').value = date.getFullYear().toString();
            document.getElementById('expiry_month').value = String(date.getMonth() + 1).padStart(2, '0');

            // Update valid days before setting the day value
            if (typeof updateValidDays === 'function') {
                updateValidDays();
            }

            document.getElementById('expiry_day').value = String(date.getDate()).padStart(2, '0');
            document.getElementById('expiry_hour').value = String(date.getHours()).padStart(2, '0');
            document.getElementById('expiry_minute').value = String(date.getMinutes()).padStart(2, '0');
            updateDateTimePreview();
        }
    }

    // Add change listeners to all selectors
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', updateDateTime);
        }
    });

    // Add listeners for year and month changes to update valid days
    const yearSelect = document.getElementById('expiry_year');
    const monthSelect = document.getElementById('expiry_month');
    if (yearSelect && monthSelect) {
        yearSelect.addEventListener('change', updateValidDays);
        monthSelect.addEventListener('change', updateValidDays);
    }
}

// Update valid days based on selected month and year
function updateValidDays() {
    const yearSelect = document.getElementById('expiry_year');
    const monthSelect = document.getElementById('expiry_month');
    const daySelect = document.getElementById('expiry_day');

    if (!yearSelect || !monthSelect || !daySelect) return;

    const year = parseInt(yearSelect.value);
    const month = parseInt(monthSelect.value);

    if (!year || !month) return;

    // Get number of days in the selected month
    const daysInMonth = new Date(year, month, 0).getDate();

    // Store current selection
    const currentDay = parseInt(daySelect.value);

    // Clear and rebuild day options
    daySelect.innerHTML = '<option value="">--</option>';

    for (let d = 1; d <= daysInMonth; d++) {
        const option = document.createElement('option');
        option.value = String(d).padStart(2, '0');
        option.textContent = d;
        daySelect.appendChild(option);
    }

    // Restore selection if still valid
    if (currentDay && currentDay <= daysInMonth) {
        daySelect.value = String(currentDay).padStart(2, '0');
    } else if (currentDay > daysInMonth) {
        // If previously selected day is now invalid, clear it and show warning
        daySelect.value = '';

        // Flash the day selector to indicate it needs attention
        daySelect.classList.add('border-yellow-500', 'dark:border-yellow-400', 'bg-yellow-50', 'dark:bg-yellow-900/20');
        setTimeout(() => {
            daySelect.classList.remove('border-yellow-500', 'dark:border-yellow-400', 'bg-yellow-50', 'dark:bg-yellow-900/20');
        }, 1500);
    }
}

// Setup type-to-select functionality for all dropdowns
function setupTypeToSelect() {
    const selects = ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'];

    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;

        let typedValue = '';
        let typingTimer;

        select.addEventListener('keypress', function(e) {
            clearTimeout(typingTimer);

            // Add typed character
            typedValue += e.key;

            // Find matching option
            const options = Array.from(this.options);
            const match = options.find(opt =>
                opt.value.startsWith(typedValue) ||
                opt.text.toLowerCase().startsWith(typedValue.toLowerCase())
            );

            if (match) {
                this.value = match.value;
                updateDateTime();

                // Visual feedback
                this.classList.add('ring-2', 'ring-green-500', 'dark:ring-green-400');
                setTimeout(() => {
                    this.classList.remove('ring-2', 'ring-green-500', 'dark:ring-green-400');
                }, 300);
            }

            // Clear typed value after 1 second
            typingTimer = setTimeout(() => {
                typedValue = '';
            }, 1000);
        });
    });
}

// Update the hidden input and preview when any selector changes
function updateDateTime() {
    const year = document.getElementById('expiry_year')?.value;
    const month = document.getElementById('expiry_month')?.value;
    const day = document.getElementById('expiry_day')?.value;
    const hour = document.getElementById('expiry_hour')?.value;
    const minute = document.getElementById('expiry_minute')?.value;

    if (year && month && day && hour && minute) {
        const dateTimeStr = `${year}-${month}-${day}T${hour}:${minute}:00`;
        document.getElementById('rolechangedate').value = dateTimeStr;
        updateDateTimePreview();

        // Show success flash on all filled selectors
        ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.value) {
                el.classList.add('border-green-500', 'dark:border-green-400');
                setTimeout(() => {
                    el.classList.remove('border-green-500', 'dark:border-green-400');
                }, 500);
            }
        });
    } else {
        const preview = document.getElementById('datetime_preview');
        if (preview) {
            preview.classList.add('hidden');
        }
    }
}

// Update the datetime preview display
function updateDateTimePreview() {
    const hiddenInput = document.getElementById('rolechangedate');
    const preview = document.getElementById('datetime_preview');
    const display = document.getElementById('datetime_display');

    if (!hiddenInput || !preview || !display) return;

    if (hiddenInput.value) {
        const date = new Date(hiddenInput.value);
        const options = {
            weekday: 'short',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        display.textContent = date.toLocaleDateString('en-US', options);
        preview.classList.remove('hidden');

        // Check if date is in past or expiring soon
        const now = new Date();
        const diff = date - now;
        if (diff < 0) {
            display.classList.add('text-red-600', 'dark:text-red-400');
            display.classList.remove('text-blue-600', 'dark:text-blue-400', 'text-yellow-600', 'dark:text-yellow-400');
        } else if (diff < 7 * 24 * 60 * 60 * 1000) {
            display.classList.add('text-yellow-600', 'dark:text-yellow-400');
            display.classList.remove('text-blue-600', 'dark:text-blue-400', 'text-red-600', 'dark:text-red-400');
        } else {
            display.classList.add('text-blue-600', 'dark:text-blue-400');
            display.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
        }
    } else {
        preview.classList.add('hidden');
    }
}

// Setup event handlers for expiry date quick actions
function setupExpiryDateHandlers() {
    // Event delegation for all expiry date buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-expiry-action]');
        if (!target) return;

        e.preventDefault();
        const action = target.getAttribute('data-expiry-action');
        const days = parseInt(target.getAttribute('data-days') || '0', 10);
        const hours = parseInt(target.getAttribute('data-hours') || '0', 10);

        if (action === 'set') {
            setExpiryDateTime(days, hours);
        } else if (action === 'end-of-day') {
            setEndOfDay();
        } else if (action === 'clear') {
            clearExpiryDate();
        }
    });
}

// Function to set expiry date and time by adding days and hours (stackable)
function setExpiryDateTime(days, hours) {
    let baseDate;
    let isAddingTime = false;

    // First, check if user has an existing expiry date (from the original user data)
    const originalUserExpiry = document.getElementById('original_user_expiry')?.value;

    // Check if there's already a selected datetime in the form
    const year = document.getElementById('expiry_year')?.value;
    const month = document.getElementById('expiry_month')?.value;
    const day = document.getElementById('expiry_day')?.value;
    const hour = document.getElementById('expiry_hour')?.value;
    const minute = document.getElementById('expiry_minute')?.value;

    if (year && month && day && hour && minute) {
        // Use existing selected datetime as base (user is modifying an already set date)
        baseDate = new Date(year, parseInt(month) - 1, day, hour, minute);
        isAddingTime = true;
        showExpiryToast('Added ' + (days > 0 ? days + ' day' + (days !== 1 ? 's' : '') : '') + (days > 0 && hours > 0 ? ' and ' : '') + (hours > 0 ? hours + ' hour' + (hours !== 1 ? 's' : '') : ''), 'info');
    } else if (originalUserExpiry && originalUserExpiry !== '') {
        // User has an existing expiry date - add time from that date (proper stacking)
        baseDate = new Date(originalUserExpiry);
        isAddingTime = true;
        const originalDate = formatDateTimeForDisplay(new Date(originalUserExpiry));
        showExpiryToast('Adding time from current expiry: ' + originalDate, 'info');
    } else {
        // No existing date - start from current time
        baseDate = new Date();
        showExpiryToast('Setting expiry date from now', 'success');
    }

    // Add the days and hours
    baseDate.setDate(baseDate.getDate() + days);
    baseDate.setHours(baseDate.getHours() + hours);

    // Update all selectors
    document.getElementById('expiry_year').value = baseDate.getFullYear().toString();
    document.getElementById('expiry_month').value = String(baseDate.getMonth() + 1).padStart(2, '0');

    // Update valid days before setting the day value
    if (typeof updateValidDays === 'function') {
        updateValidDays();
    }

    document.getElementById('expiry_day').value = String(baseDate.getDate()).padStart(2, '0');
    document.getElementById('expiry_hour').value = String(baseDate.getHours()).padStart(2, '0');
    document.getElementById('expiry_minute').value = String(baseDate.getMinutes()).padStart(2, '0');

    updateDateTime();

    // Add animated visual feedback
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-green-500', 'dark:ring-green-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-green-500', 'dark:ring-green-400', 'scale-105');
            }, 600);
        }
    });
}

// Function to set expiry to end of current day (23:59)
function setEndOfDay() {
    const endOfDay = new Date();
    endOfDay.setHours(23, 59, 0, 0);

    document.getElementById('expiry_year').value = endOfDay.getFullYear().toString();
    document.getElementById('expiry_month').value = String(endOfDay.getMonth() + 1).padStart(2, '0');

    // Update valid days before setting the day value
    if (typeof updateValidDays === 'function') {
        updateValidDays();
    }

    document.getElementById('expiry_day').value = String(endOfDay.getDate()).padStart(2, '0');
    document.getElementById('expiry_hour').value = '23';
    document.getElementById('expiry_minute').value = '59';

    updateDateTime();

    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-indigo-500', 'dark:ring-indigo-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-indigo-500', 'dark:ring-indigo-400', 'scale-105');
            }, 600);
        }
    });

    showExpiryToast('Expiry set to end of today (23:59)', 'info');
}

// Function to clear expiry date
function clearExpiryDate() {
    document.getElementById('expiry_year').value = '';
    document.getElementById('expiry_month').value = '';
    document.getElementById('expiry_day').value = '';
    document.getElementById('expiry_hour').value = '';
    document.getElementById('expiry_minute').value = '';
    document.getElementById('rolechangedate').value = '';
    const preview = document.getElementById('datetime_preview');
    if (preview) {
        preview.classList.add('hidden');
    }

    // Add a visual feedback with gray pulse
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-gray-500', 'dark:ring-gray-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-gray-500', 'dark:ring-gray-400', 'scale-105');
            }, 600);
        }
    });

    showExpiryToast('Expiry date cleared - role is now permanent', 'info');
}

// Format date for display
function formatDateTimeForDisplay(date) {
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
}

// Show toast notification specific to expiry date changes
function showExpiryToast(message, type) {
    type = type || 'success';

    // Remove existing toast if any
    const existingToast = document.getElementById('expiryToast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast
    const toast = document.createElement('div');
    toast.id = 'expiryToast';
    toast.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50 transform transition-all duration-300 translate-y-0 opacity-100';

    if (type === 'success') {
        toast.classList.add('bg-green-500', 'dark:bg-green-600', 'text-white');
        toast.innerHTML = '<i class="fa fa-check-circle"></i><span>' + escapeHtml(message) + '</span>';
    } else if (type === 'info') {
        toast.classList.add('bg-blue-500', 'dark:bg-blue-600', 'text-white');
        toast.innerHTML = '<i class="fa fa-info-circle"></i><span>' + escapeHtml(message) + '</span>';
    }

    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    }, 10);

    // Remove after 3 seconds with fade out
    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function initVerifyUserModal() {
    let verifyForm = null;

    // Show verify modal
    window.showVerifyModal = function(event, form) {
        event.preventDefault();
        verifyForm = form;
        const modal = document.getElementById('verifyUserModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    };

    // Hide verify modal
    window.hideVerifyModal = function() {
        const modal = document.getElementById('verifyUserModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        verifyForm = null;
    };

    // Submit verify form
    window.submitVerifyForm = function() {
        if (verifyForm) {
            verifyForm.submit();
        } else {
            // Fallback: find form by data attribute
            const formId = document.querySelector('[data-show-verify-modal]')?.getAttribute('data-form-id');
            if (formId) {
                const form = document.querySelector(`form[action*="admin.verify"] input[value="${formId}"]`)?.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }
        hideVerifyModal();
    };

    // Event delegation for verify modal
    document.addEventListener('click', function(e) {
        if (e.target.hasAttribute('data-show-verify-modal') || e.target.closest('[data-show-verify-modal]')) {
            const element = e.target.hasAttribute('data-show-verify-modal') ? e.target : e.target.closest('[data-show-verify-modal]');
            const form = element.closest('form');
            if (form) {
                showVerifyModal(e, form);
            }
        }

        if (e.target.hasAttribute('data-close-verify-modal') || e.target.closest('[data-close-verify-modal]')) {
            e.preventDefault();
            hideVerifyModal();
        }

        if (e.target.hasAttribute('data-submit-verify-form') || e.target.closest('[data-submit-verify-form]')) {
            e.preventDefault();
            submitVerifyForm();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('verifyUserModal');
            if (modal && !modal.classList.contains('hidden')) {
                hideVerifyModal();
            }
        }
    });
}

function initUserListScrollSync() {
    const topScroll = document.getElementById('topScroll');
    const bottomScroll = document.getElementById('bottomScroll');
    const topScrollContent = document.getElementById('topScrollContent');
    const table = bottomScroll ? bottomScroll.querySelector('table') : null;

    if (!topScroll || !bottomScroll || !topScrollContent || !table) {
        return; // Elements not found, likely not on user list page
    }

    // Set the width of the top scroll content to match the table width
    function updateTopScrollWidth() {
        topScrollContent.style.width = table.scrollWidth + 'px';
    }

    // Initial width setup
    updateTopScrollWidth();

    // Update on window resize
    window.addEventListener('resize', updateTopScrollWidth);

    // Synchronize scrolling from top to bottom
    topScroll.addEventListener('scroll', function() {
        if (!topScroll.isSyncing) {
            bottomScroll.isSyncing = true;
            bottomScroll.scrollLeft = topScroll.scrollLeft;
            bottomScroll.isSyncing = false;
        }
    });

    // Synchronize scrolling from bottom to top
    bottomScroll.addEventListener('scroll', function() {
        if (!bottomScroll.isSyncing) {
            topScroll.isSyncing = true;
            topScroll.scrollLeft = bottomScroll.scrollLeft;
            topScroll.isSyncing = false;
        }
    });
}

// Tmux Edit - Remove Crap Releases Toggle
function toggleCrapTypes(value) {
    const container = document.getElementById('crap_types_container');
    if (container) {
        if (value === 'Custom') {
            container.style.display = 'block';
            // Add a slight animation effect
            setTimeout(() => {
                container.style.opacity = '1';
            }, 10);
        } else {
            container.style.opacity = '0.5';
            setTimeout(() => {
                container.style.display = 'none';
                container.style.opacity = '1';
            }, 200);
        }
    }
}

// Initialize tmux edit page functionality
function initTmuxEdit() {
    // Initialize crap types toggle on page load
    const checkedRadio = document.querySelector('input[name="fix_crap_opt"]:checked');
    if (checkedRadio) {
        toggleCrapTypes(checkedRadio.value);
    }

    // Add event listeners to all radio buttons
    document.querySelectorAll('input[name="fix_crap_opt"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleCrapTypes(this.value);
        });
    });
}

// Initialize on DOMContentLoaded if on tmux-edit page
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('crap_types_container')) {
            initTmuxEdit();
        }
    });
} else {
    if (document.getElementById('crap_types_container')) {
        initTmuxEdit();
    }
}

export { initAdminUserEdit, initAdminSpecificFeatures, initTinyMCE, initVerifyUserModal, initUserListScrollSync };
