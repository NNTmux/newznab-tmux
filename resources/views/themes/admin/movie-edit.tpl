<div class="card">
			    <div class="card-header">
			        <div class="d-flex justify-content-between align-items-center">
			            <h4 class="mb-0">{$title}</h4>
			            <a href="{{url("/admin/movie-list")}}" class="btn btn-outline-secondary">
			                <i class="fa fa-arrow-left me-2"></i>Back to Movies
			            </a>
			        </div>
			    </div>

			    <div class="card-body">
			        {if isset($error) && $error != ''}
			            <div class="alert alert-danger mb-4">
			                <i class="fa fa-exclamation-circle me-2"></i>{$error}
			            </div>
			        {/if}

			        {if isset($success) && $success != ''}
			            <div class="alert alert-success mb-4">
			                <i class="fa fa-check-circle me-2"></i>{$success}
			            </div>
			        {/if}

			        <form enctype="multipart/form-data" action="{{url("/admin/movie-edit?action=submit")}}" method="post" id="movieForm">
			            {{csrf_field()}}
			            <input type="hidden" name="id" value="{$movie.imdbid}"/>

			            <div class="row mb-4">
			                <div class="col-md-4 d-flex flex-column align-items-center">
			                    <!-- Movie Images -->
			                    <div class="text-center mb-4">
			                        <h5 class="fw-bold mb-3">Cover Image</h5>
			                        <div class="cover-preview mb-3">
			                            {if $movie.cover == 1}
			                                <img src="{{url("/covers/movies/{$movie.imdbid}-cover.jpg")}}" alt="Cover" class="img-fluid rounded shadow" style="max-height: 250px;">
			                            {else}
			                                <div class="no-image-placeholder d-flex justify-content-center align-items-center bg-light rounded shadow" style="width: 167px; height: 250px;">
			                                    <i class="fa fa-image fa-3x text-muted"></i>
			                                </div>
			                            {/if}
			                        </div>
			                        <div class="input-group">
			                            <input type="file" class="form-control" id="cover" name="cover" accept="image/*">
			                        </div>
			                    </div>

			                    <div class="text-center">
			                        <h5 class="fw-bold mb-3">Backdrop Image</h5>
			                        <div class="backdrop-preview mb-3">
			                            {if $movie.backdrop == 1}
			                                <img src="{{url("/covers/movies/{$movie.imdbid}-backdrop.jpg")}}" alt="Backdrop" class="img-fluid rounded shadow" style="max-height: 140px;">
			                            {else}
			                                <div class="no-image-placeholder d-flex justify-content-center align-items-center bg-light rounded shadow" style="width: 250px; height: 140px;">
			                                    <i class="fa fa-image fa-3x text-muted"></i>
			                                </div>
			                            {/if}
			                        </div>
			                        <div class="input-group">
			                            <input type="file" class="form-control" id="backdrop" name="backdrop" accept="image/*">
			                        </div>
			                    </div>

			                    <!-- Additional info badges -->
			                    <div class="movie-details mt-4 text-center">
			                        <div class="mb-2">
			                            <a href="http://www.imdb.com/title/tt{$movie.imdbid}" target="_blank" class="badge bg-secondary text-white" title="View in IMDB">
			                                <i class="fa fa-film me-1"></i>tt{$movie.imdbid}
			                            </a>
			                        </div>
			                        <div class="mb-2">
			                            <a href="http://www.themoviedb.org/movie/{$movie.tmdbid}" target="_blank" class="badge bg-info text-white" title="View in TMDb">
			                                <i class="fa fa-database me-1"></i>{$movie.tmdbid}
			                            </a>
			                        </div>
			                    </div>
			                </div>

			                <div class="col-md-8">
			                    <!-- Movie Details Form -->
			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="title" class="form-label fw-bold">Title:</label>
			                            <input id="title" class="form-control" name="title" type="text" value="{$movie.title|escape:'htmlall'}" required/>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-6">
			                            <label for="year" class="form-label fw-bold">Year:</label>
			                            <input id="year" class="form-control" name="year" type="number" min="1900" max="2099" value="{$movie.year|escape:'htmlall'}"/>
			                        </div>
			                        <div class="col-md-6">
			                            <label for="rating" class="form-label fw-bold">Rating:</label>
			                            <input id="rating" class="form-control" name="rating" type="number" min="0" max="10" step="0.1" value="{$movie.rating|escape:'htmlall'}"/>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="tagline" class="form-label fw-bold">Tagline:</label>
			                            <input id="tagline" class="form-control" name="tagline" type="text" value="{$movie.tagline|escape:'htmlall'}"/>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="plot" class="form-label fw-bold">Plot:</label>
			                            <textarea id="plot" class="form-control" name="plot" rows="4">{$movie.plot|escape:'htmlall'}</textarea>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="genre" class="form-label fw-bold">Genre:</label>
			                            <input id="genre" class="form-control" name="genre" type="text" value="{$movie.genre|escape:'htmlall'}" placeholder="Action, Adventure, Drama"/>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="director" class="form-label fw-bold">Director:</label>
			                            <input id="director" class="form-control" name="director" type="text" value="{$movie.director|escape:'htmlall'}"/>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="actors" class="form-label fw-bold">Actors:</label>
			                            <textarea id="actors" class="form-control" name="actors" rows="3" placeholder="One actor per line or comma separated">{$movie.actors|escape:'htmlall'}</textarea>
			                        </div>
			                    </div>

			                    <div class="row mb-3">
			                        <div class="col-md-12">
			                            <label for="language" class="form-label fw-bold">Language:</label>
			                            <input id="language" class="form-control" name="language" type="text" value="{$movie.language|escape:'htmlall'}" placeholder="English, Spanish, French"/>
			                        </div>
			                    </div>
			                </div>
			            </div>
			        </form>
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between">
			            <a href="{{url("/admin/movie-list")}}" class="btn btn-outline-secondary">
			                <i class="fa fa-times me-2"></i>Cancel
			            </a>
			            <div>
			                <a href="{{url("/admin/movie-add?id={$movie.imdbid}&update=1")}}" class="btn btn-warning me-2">
			                    <i class="fa fa-refresh me-2"></i>Update from TMDb
			                </a>
			                <button type="submit" form="movieForm" class="btn btn-success">
			                    <i class="fa fa-save me-2"></i>Save Changes
			                </button>
			            </div>
			        </div>
			    </div>
			</div>

			<script>
			{literal}
			document.addEventListener('DOMContentLoaded', function() {
			    // Preview uploaded images before submission
			    function setupImagePreview(inputId, previewContainer) {
			        const input = document.getElementById(inputId);
			        const container = document.querySelector(previewContainer);

			        if (input && container) {
			            input.addEventListener('change', function() {
			                if (this.files && this.files[0]) {
			                    const reader = new FileReader();

			                    reader.onload = function(e) {
			                        // Remove existing placeholder if present
			                        const placeholder = container.querySelector('.no-image-placeholder');
			                        if (placeholder) {
			                            placeholder.remove();
			                        }

			                        // Check if image already exists
			                        let img = container.querySelector('img');
			                        if (!img) {
			                            img = document.createElement('img');
			                            img.classList.add('img-fluid', 'rounded', 'shadow');
			                            container.appendChild(img);
			                        }

			                        // Update image source
			                        img.src = e.target.result;
			                        if (inputId === 'cover') {
			                            img.style.maxHeight = '250px';
			                        } else {
			                            img.style.maxHeight = '140px';
			                        }
			                    };

			                    reader.readAsDataURL(this.files[0]);
			                }
			            });
			        }
			    }

			    // Setup image previews
			    setupImagePreview('cover', '.cover-preview');
			    setupImagePreview('backdrop', '.backdrop-preview');

			    // Basic form validation
			    const form = document.getElementById('movieForm');
			    if (form) {
			        form.addEventListener('submit', function(event) {
			            const title = document.getElementById('title').value.trim();
			            const year = document.getElementById('year').value.trim();

			            if (!title) {
			                event.preventDefault();
			                alert('Movie title is required');
			                return false;
			            }

			            if (year && (isNaN(year) || year < 1900 || year > 2099)) {
			                event.preventDefault();
			                alert('Please enter a valid year between 1900 and 2099');
			                return false;
			            }
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

			/* Cover and backdrop containers */
			.cover-preview, .backdrop-preview {
			    display: flex;
			    justify-content: center;
			    align-items: center;
			}

			/* Custom file inputs */
			input[type="file"].form-control {
			    padding: 0.375rem 0.75rem;
			}

			/* Badge styling */
			.badge {
			    font-weight: 500;
			    padding: 0.4em 0.6em;
			}

			/* Responsive adjustments */
			@media (max-width: 767.98px) {
			    .card-footer .btn {
			        padding: 0.375rem 0.75rem;
			    }

			    .card-footer .d-flex {
			        flex-direction: column;
			        gap: 0.5rem;
			    }

			    .card-footer .d-flex a,
			    .card-footer .d-flex button {
			        width: 100%;
			    }

			    .col-md-4 {
			        margin-bottom: 2rem;
			    }
			}
			{/literal}
			</style>
