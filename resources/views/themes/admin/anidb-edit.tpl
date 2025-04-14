<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/anidb-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-arrow-left me-2"></i>Back to AniDB List
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <form enctype="multipart/form-data" action="anidb-edit?action=submit" method="post" class="needs-validation" novalidate>
				            {{csrf_field()}}
				            <input type="hidden" name="from" value="{$smarty.get.from}"/>

				            <div class="row g-3">
				                <!-- Basic Information -->
				                <div class="col-12">
				                    <h5 class="border-bottom pb-2 mb-3">Anime Information</h5>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="anidbid" class="form-label">AniDB ID</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-id-card"></i></span>
				                        <input id="anidbid" class="form-control" name="anidbid" type="text" value="{$anime.anidbid|escape:'htmlall'}" required />
				                    </div>
				                    <div class="invalid-feedback">Please enter the AniDB ID</div>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="title" class="form-label">Anime Name</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-film"></i></span>
				                        <input id="title" class="form-control" name="title" type="text" value="{$anime.title|escape:'htmlall'}" required />
				                    </div>
				                    <div class="invalid-feedback">Please enter the anime name</div>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="type" class="form-label">Type</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-tag"></i></span>
				                        <input id="type" class="form-control" name="type" type="text" value="{$anime.type|escape:'htmlall'}" />
				                    </div>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="rating" class="form-label">Rating</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-star"></i></span>
				                        <input id="rating" class="form-control" name="rating" type="text" value="{$anime.rating|escape:'htmlall'}" />
				                    </div>
				                </div>

				                <!-- Date Information -->
				                <div class="col-12 mt-4">
				                    <h5 class="border-bottom pb-2 mb-3">Date Information</h5>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="startdate" class="form-label">Start Date</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-calendar-plus-o"></i></span>
				                        <input id="startdate" class="form-control" name="startdate" type="text" value="{$anime.startdate|escape:'htmlall'}" placeholder="YYYY-MM-DD" />
				                    </div>
				                </div>

				                <div class="col-md-6 mb-3">
				                    <label for="enddate" class="form-label">End Date</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-calendar-check-o"></i></span>
				                        <input id="enddate" class="form-control" name="enddate" type="text" value="{$anime.enddate|escape:'htmlall'}" placeholder="YYYY-MM-DD" />
				                    </div>
				                </div>

				                <!-- Content Details -->
				                <div class="col-12 mt-4">
				                    <h5 class="border-bottom pb-2 mb-3">Content Details</h5>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="categories" class="form-label">Categories</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-list"></i></span>
				                        <input id="categories" class="form-control" name="categories" type="text" value="{$anime.categories|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Categories separated by commas</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="creators" class="form-label">Creators</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-users"></i></span>
				                        <input id="creators" class="form-control" name="creators" type="text" value="{$anime.creators|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Creators separated by commas</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="characters" class="form-label">Characters</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-user-circle"></i></span>
				                        <input id="characters" class="form-control" name="characters" type="text" value="{$anime.characters|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Characters separated by commas</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="related" class="form-label">Related</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-link"></i></span>
				                        <input id="related" class="form-control" name="related" type="text" value="{$anime.related|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Related anime IDs separated by commas</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="description" class="form-label">Description</label>
				                    <textarea id="description" name="description" class="form-control" rows="5">{$anime.description|escape:'htmlall'}</textarea>
				                </div>

				                <!-- Episode Information -->
				                <div class="col-12 mt-4">
				                    <h5 class="border-bottom pb-2 mb-3">Episode Information</h5>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="epnos" class="form-label">Episode Numbers</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-hashtag"></i></span>
				                        <input id="epnos" class="form-control" name="epnos" type="text" value="{$anime.episode_no|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Episode numbers separated by pipe (|)</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="airdates" class="form-label">Episode Air Dates</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
				                        <input id="airdates" class="form-control" name="airdates" type="text" value="{$anime.airdate|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Air dates separated by pipe (|), format: YYYY-MM-DD</div>
				                </div>

				                <div class="col-md-12 mb-3">
				                    <label for="episodetitles" class="form-label">Episode Titles</label>
				                    <div class="input-group">
				                        <span class="input-group-text"><i class="fa fa-text-width"></i></span>
				                        <input id="episodetitles" class="form-control" name="episodetitles" type="text" value="{$anime.episode_title|escape:'htmlall'}" />
				                    </div>
				                    <div class="form-text">Episode titles separated by pipe (|)</div>
				                </div>

				                <div class="col-12 mt-4">
				                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
				                        <a href="{{url("/admin/anidb-list")}}" class="btn btn-outline-secondary">
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
				/* Form styling */
				.form-control:focus {
				    border-color: #80bdff;
				    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
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
				}
				{/literal}
				</style>
