<div class="card">
					    <div class="card-header">
					        <div class="d-flex justify-content-between align-items-center">
					            <h4 class="mb-0">{$title}</h4>
					            <a href="{{url("/admin/game-list")}}" class="btn btn-outline-secondary">
					                <i class="fa fa-arrow-left me-2"></i>Back to Game List
					            </a>
					        </div>
					    </div>

					    <div class="card-body">
					        <form enctype="multipart/form-data" action="game-edit?action=submit" method="post" class="needs-validation" novalidate>
					            {{csrf_field()}}
					            <input type="hidden" name="id" value="{$game.id}"/>

					            <div class="row g-3">
					                <!-- Basic Information -->
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">Game Information</h5>
					                </div>

					                <div class="col-md-12 mb-3">
					                    <label for="title" class="form-label">Title</label>
					                    <input id="title" class="form-control" name="title" type="text" value="{$game.title|escape:'htmlall'}" required />
					                    <div class="invalid-feedback">Please enter a title for this game</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="asin" class="form-label">Game ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-gamepad"></i></span>
					                        <input id="asin" class="form-control" name="asin" type="text" value="{$game.asin|escape:'htmlall'}" />
					                        <div class="form-text mt-1">Game ID of the URL posted below. If unknown, enter 0.</div>
					                    </div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="genre_list" class="form-label">Genre</label>
					                    <select id="genre_list" name="genre" class="form-select" required>
					                        {foreach $genres as $gen}
					                            <option {if $gen.id == $game.genres_id}selected="selected"{/if} value="{$gen.id}">{$gen.title|escape:'htmlall'}</option>
					                        {/foreach}
					                    </select>
					                    <div class="invalid-feedback">Please select a genre</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="publisher" class="form-label">Publisher</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-building"></i></span>
					                        <input id="publisher" class="form-control" name="publisher" type="text" value="{$game.publisher|escape:'htmlall'}" />
					                    </div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="releasedate" class="form-label">Release Date</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
					                        <input id="releasedate" class="form-control" name="releasedate" type="text" value="{$game.releasedate|escape:'htmlall'}" placeholder="YYYY-MM-DD" />
					                    </div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="esrb" class="form-label">Rating</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-star"></i></span>
					                        <input id="esrb" class="form-control" name="esrb" type="text" value="{$game.esrb|escape:'htmlall'}" placeholder="E, T, M, etc." />
					                    </div>
					                </div>

					                <!-- URLs -->
					                <div class="col-12 mt-4">
					                    <h5 class="border-bottom pb-2 mb-3">URLs</h5>
					                </div>

					                <div class="col-md-12 mb-3">
					                    <label for="url" class="form-label">Game URL</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-link"></i></span>
					                        <input id="url" class="form-control" name="url" type="text" value="{$game.url|escape:'htmlall'}" />
					                    </div>
					                </div>

					                <div class="col-md-12 mb-3">
					                    <label for="trailerurl" class="form-label">Trailer URL</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-film"></i></span>
					                        <input id="trailerurl" class="form-control" name="trailerurl" type="text" value="{$game.trailer|escape:'htmlall'}" />
					                    </div>
					                </div>

					                <!-- Cover Image -->
					                <div class="col-12 mt-4">
					                    <h5 class="border-bottom pb-2 mb-3">Game Cover</h5>
					                </div>

					                <div class="col-md-12 mb-3">
					                    <label for="cover" class="form-label">Cover Image</label>
					                    <div class="row">
					                        <div class="col-md-8">
					                            <input type="file" id="cover" name="cover" class="form-control" accept="image/*" />
					                            <div class="form-text">Recommended size: 600x900 pixels (JPG format)</div>
					                        </div>
					                        <div class="col-md-4">
					                            {if $game.cover == 1}
					                                <div class="cover-preview text-center">
					                                    <img src="{{url("/covers/games/{$game.id}.jpg")}}" alt="Cover" class="img-thumbnail" style="max-height: 200px;" />
					                                </div>
					                                <div class="text-center mt-2">
					                                    <span class="badge bg-success">Current Cover</span>
					                                </div>
					                            {else}
					                                <div class="text-center">
					                                    <span class="badge bg-warning">No Cover Available</span>
					                                </div>
					                            {/if}
					                        </div>
					                    </div>
					                </div>

					                <div class="col-12 mt-4">
					                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
					                        <a href="{{url("/admin/game-list")}}" class="btn btn-outline-secondary">
					                            <i class="fa fa-times me-2"></i>Cancel
					                        </a>
					                        <button type="submit" class="btn btn-success">
					                            <i class="fa fa-save me-2"></i>Save Changes
					                        </button>
					                    </div>
					                </div>
					            </div>
					        </form>
					    </div>
					</div>

					<script>
					{literal}
					document.addEventListener('DOMContentLoaded', function() {
					    // Initialize tooltips
					    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
					    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
					        return new bootstrap.Tooltip(tooltipTriggerEl);
					    });

					    // Form validation
					    var forms = document.querySelectorAll('.needs-validation');
					    Array.prototype.slice.call(forms).forEach(function (form) {
					        form.addEventListener('submit', function (event) {
					            if (!form.checkValidity()) {
					                event.preventDefault();
					                event.stopPropagation();
					            }
					            form.classList.add('was-validated');
					        }, false);
					    });

					    // Image preview functionality
					    const coverInput = document.getElementById('cover');
					    if (coverInput) {
					        coverInput.addEventListener('change', function() {
					            if (this.files && this.files[0]) {
					                const file = this.files[0];
					                const reader = new FileReader();

					                reader.onload = function(e) {
					                    const previewContainer = document.querySelector('.cover-preview');
					                    if (previewContainer) {
					                        previewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview" class="img-thumbnail" style="max-height: 200px;">`;
					                    } else {
					                        const newPreview = document.createElement('div');
					                        newPreview.className = 'cover-preview text-center';
					                        newPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="img-thumbnail" style="max-height: 200px;">`;

					                        const parent = coverInput.closest('.col-md-8').nextElementSibling;
					                        if (parent) {
					                            parent.innerHTML = '';
					                            parent.appendChild(newPreview);

					                            const badgeContainer = document.createElement('div');
					                            badgeContainer.className = 'text-center mt-2';
					                            badgeContainer.innerHTML = `<span class="badge bg-info">New Cover Preview</span>`;
					                            parent.appendChild(badgeContainer);
					                        }
					                    }
					                };

					                reader.readAsDataURL(file);
					            }
					        });
					    }
					});
					{/literal}
					</script>

					<style>
					{literal}
					/* Form styling */
					.form-control:focus {
					    border-color: #80bdff;
					    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
					}

					/* Cover image preview */
					.cover-preview {
					    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
					    transition: transform 0.2s;
					}

					.cover-preview:hover {
					    transform: translateY(-5px);
					}

					/* Section headings */
					h5.border-bottom {
					    color: #495057;
					}

					/* Responsive adjustments */
					@media (max-width: 767.98px) {
					    .card-header .d-flex {
					        flex-direction: column;
					        gap: 1rem;
					    }

					    .card-header .btn {
					        width: 100%;
					    }

					    .cover-preview {
					        margin: 0 auto;
					        margin-top: 1rem;
					    }
					}
					{/literal}
					</style>
