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

			        <div class="row">
			            <div class="col-lg-6 mx-auto">
			                <div class="alert alert-info mb-4">
			                    <i class="fa fa-info-circle me-2"></i>Enter the numerical part of an IMDB ID (without the "tt" prefix) to add a movie to the database.
			                </div>

			                <form enctype="multipart/form-data" action="{{url("/admin/movie-add")}}" method="post" id="movieAddForm">
			                    {{csrf_field()}}

			                    <div class="mb-4">
			                        <label for="id" class="form-label fw-bold">IMDB ID:</label>
			                        <div class="input-group">
			                            <span class="input-group-text">tt</span>
			                            <input id="id" class="form-control" name="id" type="text" value="{if isset($imdbId)}{$imdbId}{/if}" placeholder="Enter IMDB ID number (e.g. 0111161)" required>
			                        </div>
			                        <div class="form-text text-muted mt-2">
			                            <i class="fa fa-lightbulb-o me-1"></i>Example: For <a href="https://www.imdb.com/title/tt0111161/" target="_blank">tt0111161</a> (The Shawshank Redemption), enter <strong>0111161</strong>
			                        </div>
			                    </div>

			                    {if isset($update) && $update == 1}
			                        <input type="hidden" name="update" value="1">
			                    {/if}
			                </form>
			            </div>
			        </div>
			    </div>

			    <div class="card-footer">
			        <div class="d-flex justify-content-between">
			            <a href="{{url("/admin/movie-list")}}" class="btn btn-outline-secondary">
			                <i class="fa fa-times me-2"></i>Cancel
			            </a>
			            <button type="submit" form="movieAddForm" class="btn btn-success">
			                {if isset($update) && $update == 1}
			                    <i class="fa fa-refresh me-2"></i>Update Movie
			                {else}
			                    <i class="fa fa-plus-circle me-2"></i>Add Movie
			                {/if}
			            </button>
			        </div>
			    </div>
			</div>

			<script>
			{literal}
			document.addEventListener('DOMContentLoaded', function() {
			    // Basic form validation
			    const form = document.getElementById('movieAddForm');
			    if (form) {
			        form.addEventListener('submit', function(event) {
			            const imdbId = document.getElementById('id').value.trim();

			            if (!imdbId) {
			                event.preventDefault();
			                alert('IMDB ID is required');
			                return false;
			            }

			            // Validate that input only contains numbers
			            if (!/^\d+$/.test(imdbId)) {
			                event.preventDefault();
			                alert('IMDB ID should only contain numbers (without the tt prefix)');
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

			/* Input field improvements */
			.input-group .form-control:focus {
			    border-color: #86b7fe;
			    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
			    z-index: 1;
			}

			/* Card styling */
			.card {
			    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
			    border-radius: 0.5rem;
			}

			/* Alert styling */
			.alert {
			    border-radius: 0.375rem;
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
			}
			{/literal}
			</style>
