@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fa fa-file-alt mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Content Form -->
        <form method="post" action="{{ url('admin/content-add') }}" class="p-6">
            @csrf
            <input type="hidden" name="action" value="submit">
            @if(!empty($content['id']))
                <input type="hidden" name="id" value="{{ is_array($content) ? $content['id'] : $content->id }}">
            @endif

            <div class="space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="title"
                           name="title"
                           value="{{ is_array($content) ? ($content['title'] ?? '') : ($content->title ?? '') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- URL -->
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        URL
                    </label>
                    <input type="text"
                           id="url"
                           name="url"
                           value="{{ is_array($content) ? ($content['url'] ?? '') : ($content->url ?? '') }}"
                           placeholder="/page-slug or https://example.com"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400">
                    <p class="mt-1 text-sm text-gray-500">Internal URL (e.g., /about) or external URL (e.g., https://example.com)</p>
                </div>

                <!-- Body -->
                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Body Content
                    </label>
                    <textarea id="body"
                              name="body"
                              rows="15"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">{{ is_array($content) ? trim(($content['body'] ?? ''), '\'"') : trim(($content->body ?? ''), '\'"') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">Use the rich text editor to format your content</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Content Type -->
                    <div>
                        <label for="contenttype" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Content Type <span class="text-red-500">*</span>
                        </label>
                        <select id="contenttype"
                                name="contenttype"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($contenttypelist as $typeId => $typeName)
                                <option value="{{ $typeId }}"
                                    {{ (is_array($content) ? ($content['contenttype'] ?? '') : ($content->contenttype ?? '')) == $typeId ? 'selected' : '' }}>
                                    {{ $typeName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Visible To <span class="text-red-500">*</span>
                        </label>
                        <select id="role"
                                name="role"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($rolelist as $roleId => $roleName)
                                <option value="{{ $roleId }}"
                                    {{ (is_array($content) ? ($content['role'] ?? '') : ($content->role ?? '')) == $roleId ? 'selected' : '' }}>
                                    {{ $roleName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status"
                                name="status"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($status_ids as $index => $statusId)
                                <option value="{{ $statusId }}"
                                    {{ (is_array($content) ? ($content['status'] ?? '') : ($content->status ?? '')) == $statusId ? 'selected' : '' }}>
                                    {{ $status_names[$index] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Ordinal -->
                    <div>
                        <label for="ordinal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Order (Ordinal)
                        </label>
                        <input type="number"
                               id="ordinal"
                               name="ordinal"
                               value="{{ is_array($content) ? ($content['ordinal'] ?? 0) : ($content->ordinal ?? 0) }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                        <p class="mt-1 text-sm text-gray-500">Lower numbers appear first</p>
                    </div>
                </div>

                <!-- Meta Description -->
                <div>
                    <label for="metadescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Meta Description
                    </label>
                    <textarea id="metadescription"
                              name="metadescription"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">{{ is_array($content) ? ($content['metadescription'] ?? '') : ($content->metadescription ?? '') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">SEO meta description</p>
                </div>

                <!-- Meta Keywords -->
                <div>
                    <label for="metakeywords" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Meta Keywords
                    </label>
                    <input type="text"
                           id="metakeywords"
                           name="metakeywords"
                           value="{{ is_array($content) ? ($content['metakeywords'] ?? '') : ($content->metakeywords ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="mt-1 text-sm text-gray-500">Comma-separated keywords for SEO</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-save mr-2"></i>{{ !empty($content['id']) ? 'Update' : 'Create' }} Content
                    </button>
                    <a href="{{ url('admin/content-list') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<!-- TinyMCE 8 (Latest Version) -->
<script src="https://cdn.tiny.cloud/1/{{ config('tinymce.api_key', 'no-api-key') }}/tinymce/8/tinymce.min.js" referrerpolicy="origin"></script>
<script>
// Function to detect if dark mode is active
function isDarkMode() {
    return document.documentElement.classList.contains('dark') ||
           (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
}

// Function to get TinyMCE config
function getTinyMCEConfig() {
    const darkMode = isDarkMode();
    return {
        selector: '#body',
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
        images_upload_url: false,
        automatic_uploads: false,
        file_picker_types: 'image',
        /* Font options */
        font_family_formats: 'Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,palatino,serif; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times,serif; Verdana=verdana,geneva,sans-serif',
        font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
        /* Enable automatic link creation */
        autolink_pattern: /^(https?:\/\/|www\.|(?!www\.)[a-z0-9\-]+\.[a-z]{2,13})/i,
        link_default_protocol: 'https',
        link_assume_external_targets: true,
        /* Link target options */
        link_target_list: [
            {title: 'None', value: ''},
            {title: 'New window', value: '_blank'},
            {title: 'Same window', value: '_self'}
        ],
        /* Block formats */
        block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre; Blockquote=blockquote',
        /* Content filtering - allow all HTML */
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style]',
        /* Paste settings */
        paste_as_text: false,
        paste_block_drop: false,
        paste_data_images: true,
        paste_retain_style_properties: 'all',
        /* Image settings */
        image_advtab: true,
        image_caption: true,
        image_description: true,
        image_dimensions: true,
        image_title: true,
        /* Table settings */
        table_default_attributes: {
            border: '1'
        },
        table_default_styles: {
            'border-collapse': 'collapse',
            'width': '100%'
        },
        table_responsive_width: true,
        /* Auto-save */
        setup: function(editor) {
            editor.on('change', function() {
                editor.save();
            });
            editor.on('blur', function() {
                editor.save();
            });
        }
    };
}

// Initialize TinyMCE
tinymce.init(getTinyMCEConfig());

// Watch for theme changes and reinitialize TinyMCE
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const editor = tinymce.get('body');
            if (editor) {
                // Save current content
                const content = editor.getContent();
                // Remove the editor
                tinymce.remove('#body');
                // Reinitialize with new theme
                tinymce.init(getTinyMCEConfig()).then(function(editors) {
                    // Restore content
                    editors[0].setContent(content);
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
</script>
@endpush
@endsection

