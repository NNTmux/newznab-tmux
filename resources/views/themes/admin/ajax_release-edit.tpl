<div class="card" id="updatePanel">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Edit Release</h4>
                        </div>
                    </div>

                    <div class="card-body">
                        {if $success}
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fa fa-check-circle me-3 fa-2x"></i>
                                <div>
                                    <h5 class="mb-0">Successfully updated!</h5>
                                    <p class="mb-0">The release has been updated in the database.</p>
                                </div>
                            </div>
                            {if $from != ''}
                                <script type="text/javascript">
                                    setTimeout(function() {
                                        window.location = "{$from}";
                                    }, 1500);
                                </script>
                            {/if}
                        {else}
                            <form id="release" action="{{url("/admin/ajax_release-admin/?action=doedit")}}" method="get">
                                {{csrf_field()}}
                                {foreach from=$idArr item=id}
                                    <input type="hidden" name="id[]" value="{$id}"/>
                                {/foreach}
                                <input type="hidden" name="from" value="{$from}"/>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="category" class="form-label fw-bold">Category:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
                                            <select id="category" name="category" class="form-select">
                                                {html_options options=$catlist selected=$release.categories_id}
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="grabs" class="form-label fw-bold">Grabs:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-download"></i></span>
                                            <input id="grabs" name="grabs" type="number" min="0" class="form-control" value="{$release.grabs}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="videosid" class="form-label fw-bold">Video ID:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-film"></i></span>
                                            <input id="videosid" name="videosid" type="number" min="0" class="form-control" value="{$release.videos_id}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="episodesid" class="form-label fw-bold">TV Episode ID:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-tv"></i></span>
                                            <input id="episodesid" name="episodesid" type="number" min="0" class="form-control" value="{$release.tv_episodes_id}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="anidbid" class="form-label fw-bold">AniDB ID:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-play-circle"></i></span>
                                            <input id="anidbid" name="anidbid" type="number" min="0" class="form-control" value="{$release.anidbid}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-4">
                                        <label for="imdbid" class="form-label fw-bold">IMDB ID:</label>
                                    </div>
                                    <div class="col-lg-9 col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-video-camera"></i></span>
                                            <input id="imdbid" name="imdbid" type="text" class="form-control" value="{$release.imdbid}" />
                                        </div>
                                        <small class="text-muted">Format: tt0123456</small>
                                    </div>
                                </div>
                            </form>
                        {/if}
                    </div>

                    <div class="card-footer">
                        {if !$success}
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fa fa-times me-2"></i>Cancel
                                </button>
                                <button type="button" id="save" class="btn btn-success">
                                    <i class="fa fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        {else}
                            <div class="text-center">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fa fa-times me-2"></i>Close
                                </button>
                            </div>
                        {/if}
                    </div>
                </div>

                <script>
                {literal}
                document.addEventListener('DOMContentLoaded', function() {
                    // Form submission handling
                    const saveButton = document.getElementById('save');
                    const releaseForm = document.getElementById('release');
                    const updatePanel = document.getElementById('updatePanel');

                    if (saveButton && releaseForm) {
                        saveButton.addEventListener('click', function() {
                            // Show loading state
                            saveButton.disabled = true;
                            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';

                            // Collect form data
                            const formData = new FormData(releaseForm);
                            const postUrl = releaseForm.getAttribute('action');

                            // Create request
                            fetch(postUrl + '&' + new URLSearchParams(formData).toString(), {
                                method: 'GET',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(response => response.text())
                            .then(data => {
                                // Update panel with response
                                updatePanel.innerHTML = data;

                                // Optional: if inside a modal, refresh parent
                                if (window.parent && window.parent.location) {
                                    setTimeout(() => {
                                        window.parent.location.reload();
                                    }, 1500);
                                }
                            })
                            .catch(error => {
                                console.error('Error submitting form:', error);

                                // Reset button state
                                saveButton.disabled = false;
                                saveButton.innerHTML = '<i class="fa fa-save me-2"></i>Save Changes';

                                // Show error message
                                const errorAlert = document.createElement('div');
                                errorAlert.className = 'alert alert-danger mt-3';
                                errorAlert.innerHTML = '<i class="fa fa-exclamation-triangle me-2"></i>An error occurred while saving. Please try again.';
                                releaseForm.prepend(errorAlert);

                                // Auto-dismiss error after 5 seconds
                                setTimeout(() => {
                                    errorAlert.remove();
                                }, 5000);
                            });
                        });
                    }
                });
                {/literal}
                </script>

                <style>
                {literal}
                /* Form styling improvements */
                .form-label {
                    margin-bottom: 0.5rem;
                }

                /* Responsive adjustments */
                @media (max-width: 767.98px) {
                    .card-footer .btn {
                        padding: 0.375rem 0.75rem;
                    }

                    .input-group .input-group-text {
                        padding: 0.375rem 0.75rem;
                    }
                }

                /* Number input styling */
                input[type="number"] {
                    -moz-appearance: textfield;
                }

                input[type="number"]::-webkit-inner-spin-button,
                input[type="number"]::-webkit-outer-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }

                /* Improve input focus states */
                .form-control:focus,
                .form-select:focus {
                    border-color: #80bdff;
                    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
                }
                {/literal}
                </style>
