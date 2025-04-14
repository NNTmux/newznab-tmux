<div class="card">
					    <div class="card-header">
					        <div class="d-flex justify-content-between align-items-center">
					            <h4 class="mb-0">{$title}</h4>
					            <a href="{{url("/admin/show-list")}}" class="btn btn-outline-secondary">
					                <i class="fa fa-arrow-left me-2"></i>Back to Show List
					            </a>
					        </div>
					    </div>

					    <div class="card-body">
					        <form enctype="multipart/form-data" action="show-edit?action=submit" method="POST" class="needs-validation" novalidate>
					            {{csrf_field()}}
					            <input type="hidden" name="from" value="{$smarty.get.from}"/>

					            <div class="row g-3">
					                <!-- IDs Section -->
					                <div class="col-12">
					                    <h5 class="border-bottom pb-2 mb-3">Show Identifiers</h5>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="id" class="form-label">Videos ID</label>
					                    <div class="input-group">
					                        <input id="id" class="form-control" name="id" type="text" value="{$show.id}" readonly />
					                        <input type="hidden" name="id" value="{$show.id}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric Video ID. Changing this is not recommended.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="tvdb" class="form-label">TVDB ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-database"></i></span>
					                        <input id="tvdb" class="form-control" name="tvdb" type="text" value="{$show.tvdb}" />
					                        <input type="hidden" name="tvdb" value="{$show.tvdb}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric TVDB Show ID.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="tvmaze" class="form-label">TVMaze ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-database"></i></span>
					                        <input id="tvmaze" class="form-control" name="tvmaze" type="text" value="{$show.tvmaze}" />
					                        <input type="hidden" name="tvmaze" value="{$show.tvmaze}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric TVMaze Show ID.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="tmdb" class="form-label">TMDB ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-database"></i></span>
					                        <input id="tmdb" class="form-control" name="tmdb" type="text" value="{$show.tmdb}" />
					                        <input type="hidden" name="tmdb" value="{$show.tmdb}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric TMDB Show ID.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="trakt" class="form-label">Trakt ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-database"></i></span>
					                        <input id="trakt" class="form-control" name="trakt" type="text" value="{$show.trakt}" />
					                        <input type="hidden" name="trakt" value="{$show.trakt}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric Trakt Show ID.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="tvrage" class="form-label">TVRage ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-database"></i></span>
					                        <input id="tvrage" class="form-control" name="tvrage" type="text" value="{$show.tvrage}" />
					                        <input type="hidden" name="tvrage" value="{$show.tvrage}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric TVRage Show ID.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="imdb" class="form-label">IMDB ID</label>
					                    <div class="input-group">
					                        <span class="input-group-text"><i class="fa fa-film"></i></span>
					                        <input id="imdb" class="form-control" name="imdb" type="text" value="{$show.imdb}" />
					                        <input type="hidden" name="imdb" value="{$show.imdb}"/>
					                    </div>
					                    <div class="form-text text-muted">The numeric IMDB Show ID.</div>
					                </div>

					                <!-- Show Information Section -->
					                <div class="col-12 mt-4">
					                    <h5 class="border-bottom pb-2 mb-3">Show Information</h5>
					                </div>

					                <div class="col-12 mb-3">
					                    <label for="title" class="form-label">Show Title</label>
					                    <input id="title" class="form-control" name="title" type="text" value="{$show.title|escape:'htmlall'}" required />
					                    <div class="form-text text-muted">The title of the TV show.</div>
					                    <div class="invalid-feedback">Please enter the show title.</div>
					                </div>

					                <div class="col-12 mb-3">
					                    <label for="summary" class="form-label">Summary</label>
					                    <textarea id="summary" name="summary" class="form-control" rows="5">{$show.summary|escape:'htmlall'}</textarea>
					                    <div class="form-text text-muted">Brief description of the show.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="publisher" class="form-label">Publisher</label>
					                    <input id="publisher" class="form-control" name="publisher" type="text" value="{$show.publisher|escape:'htmlall'}" />
					                    <div class="form-text text-muted">Show's publisher/network.</div>
					                </div>

					                <div class="col-md-6 mb-3">
					                    <label for="countries_id" class="form-label">Country Code</label>
					                    <input id="countries_id" class="form-control" name="countries_id" type="text" value="{$show.countries_id|escape:'htmlall'}" maxlength="2" />
					                    <div class="form-text text-muted">The country code for the TV show (2-letter ISO code).</div>
					                </div>

					                <div class="col-12 mt-4">
					                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
					                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='{{url("/admin/show-list")}}'">
					                            <i class="fa fa-times me-2"></i>Cancel
					                        </button>
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
					});
					{/literal}
					</script>

					<style>
					{literal}
					/* Improve form styling */
					.form-control:focus {
					    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
					}

					/* Form section styling */
					h5.border-bottom {
					    color: #495057;
					}

					/* Input group icons styling */
					.input-group-text {
					    background-color: #f8f9fa;
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
					}
					{/literal}
					</style>
