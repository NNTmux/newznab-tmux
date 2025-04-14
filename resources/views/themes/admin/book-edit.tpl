<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/book-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-arrow-left me-2"></i>Back to Books
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        <form enctype="multipart/form-data" action="book-edit?action=submit" method="post" class="needs-validation" novalidate>
				            {{csrf_field()}}
				            <input type="hidden" name="id" value="{$book.id}"/>

				            <div class="row mb-3">
				                <div class="col-md-12">
				                    <label for="title" class="form-label">Title</label>
				                    <input id="title" class="form-control" name="title" type="text" value="{$book.title|escape:'htmlall'}" required />
				                    <div class="invalid-feedback">Please enter a book title</div>
				                </div>
				            </div>

				            <div class="row mb-3">
				                <div class="col-md-6">
				                    <label for="asin" class="form-label">ASIN</label>
				                    <input id="asin" class="form-control" name="asin" type="text" value="{$book.asin|escape:'htmlall'}" />
				                </div>
				                <div class="col-md-6">
				                    <label for="publishdate" class="form-label">Published Date</label>
				                    <input id="publishdate" class="form-control" name="publishdate" type="date" value="{$book.publishdate|escape:'htmlall'}" />
				                </div>
				            </div>

				            <div class="row mb-3">
				                <div class="col-md-6">
				                    <label for="author" class="form-label">Author</label>
				                    <input id="author" class="form-control" name="author" type="text" value="{$book.author|escape:'htmlall'}" />
				                </div>
				                <div class="col-md-6">
				                    <label for="publisher" class="form-label">Publisher</label>
				                    <input id="publisher" class="form-control" name="publisher" type="text" value="{$book.publisher|escape:'htmlall'}" />
				                </div>
				            </div>

				            <div class="row mb-3">
				                <div class="col-md-12">
				                    <label for="url" class="form-label">URL</label>
				                    <input id="url" class="form-control" name="url" type="url" value="{$book.url|escape:'htmlall'}" />
				                </div>
				            </div>

				            <div class="row mb-4">
				                <div class="col-md-12">
				                    <label for="cover" class="form-label">Cover Image</label>
				                    <input type="file" id="cover" name="cover" class="form-control" accept="image/*" />

				                    {if $book.cover == 1}
				                    <div class="mt-3">
				                        <div class="card cover-preview" style="max-width: 200px;">
				                            <div class="position-relative">
				                                <img class="card-img-top" src="{{url("/covers/book/{$book.id}.jpg")}}" alt="Book Cover" />
				                                <div class="position-absolute top-0 end-0 p-2">
				                                    <span class="badge bg-success"><i class="fa fa-check me-1"></i>Cover Exists</span>
				                                </div>
				                            </div>
				                        </div>
				                    </div>
				                    {/if}
				                </div>
				            </div>

				            <div class="d-flex justify-content-between">
				                <a href="{{url("/admin/book-list")}}" class="btn btn-outline-secondary">
				                    <i class="fa fa-times me-2"></i>Cancel
				                </a>
				                <button type="submit" class="btn btn-success">
				                    <i class="fa fa-save me-2"></i>Save Book
				                </button>
				            </div>
				        </form>
				    </div>
				</div>

				<script>
				{literal}
				document.addEventListener('DOMContentLoaded', function() {
				    // Form validation
				    const form = document.querySelector('.needs-validation');

				    form.addEventListener('submit', function(event) {
				        if (!form.checkValidity()) {
				            event.preventDefault();
				            event.stopPropagation();
				        }
				        form.classList.add('was-validated');
				    });

				    // File input preview (optional enhancement)
				    const coverInput = document.getElementById('cover');
				    if (coverInput) {
				        coverInput.addEventListener('change', function() {
				            const file = this.files[0];
				            if (file) {
				                const reader = new FileReader();
				                const previewContainer = document.querySelector('.cover-preview');

				                reader.onload = function(e) {
				                    if (!previewContainer) {
				                        const newPreview = document.createElement('div');
				                        newPreview.className = 'mt-3';
				                        newPreview.innerHTML = `
				                            <div class="card cover-preview" style="max-width: 200px;">
				                                <div class="position-relative">
				                                    <img class="card-img-top" src="${e.target.result}" alt="Cover Preview" />
				                                    <div class="position-absolute top-0 end-0 p-2">
				                                        <span class="badge bg-info"><i class="fa fa-eye me-1"></i>New Cover</span>
				                                    </div>
				                                </div>
				                            </div>
				                        `;
				                        coverInput.parentNode.appendChild(newPreview);
				                    } else {
				                        const img = previewContainer.querySelector('img');
				                        if (img) {
				                            img.src = e.target.result;
				                            const badge = previewContainer.querySelector('.badge');
				                            if (badge) {
				                                badge.className = 'badge bg-info';
				                                badge.innerHTML = '<i class="fa fa-eye me-1"></i>New Cover';
				                            }
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

				/* Responsive adjustments */
				@media (max-width: 767.98px) {
				    .d-flex.justify-content-between {
				        flex-direction: column-reverse;
				        gap: 1rem;
				    }

				    .d-flex.justify-content-between .btn {
				        width: 100%;
				    }

				    .cover-preview {
				        margin: 0 auto;
				    }
				}
				{/literal}
				</style>
