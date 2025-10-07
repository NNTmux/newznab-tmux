<!-- NFO Modal -->
<div id="nfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeNfoModal()"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                        <i class="fas fa-file-alt mr-2 text-yellow-600 dark:text-yellow-400"></i>NFO File
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" onclick="closeNfoModal()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="nfoContent" class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto font-mono text-sm whitespace-pre" style="max-height: 70vh;">
                    <div class="flex items-center justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mr-2"></i>
                        <span>Loading NFO...</span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeNfoModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openNfoModal(guid) {
        const modal = document.getElementById('nfoModal');
        const content = document.getElementById('nfoContent');

        // Show modal
        modal.classList.remove('hidden');

        // Reset content with loading message
        content.innerHTML = '<div class="flex items-center justify-center py-8"><i class="fas fa-spinner fa-spin text-2xl mr-2"></i><span>Loading NFO...</span></div>';

        // Fetch NFO content
        fetch(`{{ url('/nfo') }}/${guid}?modal=1`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('NFO not found');
                }
                return response.text();
            })
            .then(html => {
                // Extract the NFO content from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nfoText = doc.querySelector('pre')?.textContent || doc.body.textContent;
                content.textContent = nfoText;
            })
            .catch(error => {
                content.innerHTML = '<div class="text-red-400 text-center py-8"><i class="fas fa-exclamation-triangle mr-2"></i>Error loading NFO file</div>';
                console.error('Error loading NFO:', error);
            });
    }

    function closeNfoModal() {
        const modal = document.getElementById('nfoModal');
        modal.classList.add('hidden');
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeNfoModal();
        }
    });

    // Add click handlers for NFO badges
    document.addEventListener('click', function(event) {
        if (event.target.closest('.nfo-badge')) {
            event.preventDefault();
            const badge = event.target.closest('.nfo-badge');
            const guid = badge.getAttribute('data-guid');
            if (guid) {
                openNfoModal(guid);
            }
        }
    });
</script>

