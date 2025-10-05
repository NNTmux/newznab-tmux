import './bootstrap';

// Import jQuery and make it globally available
import $ from 'jquery';
window.$ = window.jQuery = $;

// Import Bootstrap JS
import 'bootstrap';

// Import FontAwesome
import '@fortawesome/fontawesome-free/js/all.min.js';

// Import DataTables
import 'datatables';

// Import other necessary libraries
import autosize from 'autosize';
window.autosize = autosize;

// Initialize autosize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea[data-autosize]');
    if (textareas.length > 0) {
        autosize(textareas);
    }
});

