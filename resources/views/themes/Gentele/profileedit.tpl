<div class="container-fluid px-4 py-3">
                                        <!-- Breadcrumb -->
                                        <nav aria-label="breadcrumb" class="mb-3">
                                            <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                                <li class="breadcrumb-item"><a href="{{url("/profile")}}">Profile</a></li>
                                                <li class="breadcrumb-item active">{$user.username|escape:"htmlall"}</li>
                                            </ol>
                                        </nav>

                                        <!-- Profile Edit Card -->
                                        <div class="card shadow-sm mb-4">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0"><i class="fa fa-user-edit me-2"></i>Edit Profile: <strong>{$user.username|escape:"htmlall"}</strong></h5>
                                            </div>

                                            <div class="card-body">
                                                {if $error != ''}
                                                    <div class="alert alert-danger">
                                                        <i class="fa fa-exclamation-circle me-2"></i>{$error}
                                                    </div>
                                                {/if}

                                                {{Form::open(['url' => 'profileedit?action=submit'])}}

                                                <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" href="#account" id="account-tab" data-bs-toggle="tab" role="tab" aria-controls="account" aria-selected="true">
                                                            <i class="fa fa-user-circle me-2"></i>Account
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" href="#categories" id="categories-tab" data-bs-toggle="tab" role="tab" aria-controls="categories" aria-selected="false">
                                                            <i class="fa fa-folder-open me-2"></i>Categories
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" href="#preferences" id="preferences-tab" data-bs-toggle="tab" role="tab" aria-controls="preferences" aria-selected="false">
                                                            <i class="fa fa-cogs me-2"></i>Preferences
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" href="#security" id="security-tab" data-bs-toggle="tab" role="tab" aria-controls="security" aria-selected="false">
                                                            <i class="fa fa-shield me-2"></i>Security
                                                        </a>
                                                    </li>
                                                </ul>

                                                <div class="tab-content" id="profileTabContent">
                                                    <!-- Account Tab -->
                                                    <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
                                                        <div class="card border mb-4">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fa fa-id-card me-2"></i>Account Information</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row mb-3">
                                                                    <label for="email" class="col-md-3 col-form-label">Current Email</label>
                                                                    <div class="col-md-9">
                                                                        <p class="form-control-plaintext">{$user.email|escape:"htmlall"}</p>
                                                                    </div>
                                                                </div>

                                                                <div class="row mb-3">
                                                                    <label for="email" class="col-md-3 col-form-label">New Email</label>
                                                                    <div class="col-md-9">
                                                                        <input id="email" class="form-control" name="email" type="text" value="">
                                                                        <div class="form-text text-muted">
                                                                            <i class="fa fa-info-circle me-1"></i>Only enter your email if you want to change it. If you change your email you will need to verify it. You will not be able to access the site until verification is complete.
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row mb-3">
                                                                    <label for="password" class="col-md-3 col-form-label">New Password</label>
                                                                    <div class="col-md-9">
                                                                        <input autocomplete="off" id="password" name="password" type="password" class="form-control" value="">
                                                                        <div class="form-text text-muted">
                                                                            <i class="fa fa-info-circle me-1"></i>Only enter your password if you want to change it.
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row mb-3">
                                                                    <label for="password_confirmation" class="col-md-3 col-form-label">Confirm Password</label>
                                                                    <div class="col-md-9">
                                                                        <input autocomplete="off" id="password_confirmation" name="password_confirmation" type="password" class="form-control" value="">
                                                                    </div>
                                                                </div>

                                                                <div class="row">
                                                                    <label class="col-md-3 col-form-label">API Key</label>
                                                                    <div class="col-md-9">
                                                                        <div class="input-group">
                                                                            <input type="text" class="form-control" value="{$user.api_token}" readonly>
                                                                            <button class="btn btn-outline-secondary" type="button" id="copyApiKey" data-bs-toggle="tooltip" title="Copy to clipboard">
                                                                                <i class="fa fa-copy"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Categories Tab -->
                                                    <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                                                        <div class="card border mb-4">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fa fa-folder-open me-2"></i>Category Visibility Settings</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <p class="text-muted mb-4">Choose which categories you want to see in browse and search results:</p>

                                                                <div class="row g-4">
                                                                    {if $user->can('view console')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-gamepad fa-2x text-primary me-3"></i>
                                                                                    <h6 class="mb-0">Console Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewconsole" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewconsole1" name="viewconsole" value="1" {if $user->hasDirectPermission('view console')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewconsole1">Show Console releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view movies')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-film fa-2x text-danger me-3"></i>
                                                                                    <h6 class="mb-0">Movie Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewmovies" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewmovies1" name="viewmovies" value="1" {if $user->hasDirectPermission('view movies')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewmovies1">Show Movie releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view audio')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-music fa-2x text-success me-3"></i>
                                                                                    <h6 class="mb-0">Audio Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewaudio" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewaudio1" name="viewaudio" value="1" {if $user->hasDirectPermission('view audio')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewaudio1">Show Audio releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view pc')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-desktop fa-2x text-info me-3"></i>
                                                                                    <h6 class="mb-0">PC Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewpc" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewpc1" name="viewpc" value="1" {if $user->hasDirectPermission('view pc')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewpc1">Show PC releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view tv')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-tv fa-2x text-primary me-3"></i>
                                                                                    <h6 class="mb-0">TV Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewtv" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewtv1" name="viewtv" value="1" {if $user->hasDirectPermission('view tv')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewtv1">Show TV releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view adult')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-venus-mars fa-2x text-danger me-3"></i>
                                                                                    <h6 class="mb-0">Adult Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewadult" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewadult1" name="viewadult" value="1" {if $user->hasDirectPermission('view adult')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewadult1">Show Adult releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view books')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-book fa-2x text-success me-3"></i>
                                                                                    <h6 class="mb-0">Book Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewbooks" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewbooks1" name="viewbooks" value="1" {if $user->hasDirectPermission('view books')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewbooks1">Show Book releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}

                                                                    {if $user->can('view other')}
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-archive fa-2x text-secondary me-3"></i>
                                                                                    <h6 class="mb-0">Other Releases</h6>
                                                                                </div>
                                                                                <input type="hidden" name="viewother" value="0">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="viewother1" name="viewother" value="1" {if $user->hasDirectPermission('view other')}checked{/if}>
                                                                                    <label class="form-check-label" for="viewother1">Show Other releases</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    {/if}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Preferences Tab -->
                                                    <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                                                        <div class="card border mb-4">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fa fa-sliders me-2"></i>UI Preferences</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <p class="text-muted mb-4">Customize how you browse different sections of the site:</p>

                                                                <div class="row g-4">
                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-film fa-2x text-danger me-3"></i>
                                                                                    <h6 class="mb-0">Movie View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="movieview" name="movieview" value="1" {if $user.movieview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="movieview">Browse movie covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows movies with known IMDB info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-music fa-2x text-success me-3"></i>
                                                                                    <h6 class="mb-0">Music View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="musicview" name="musicview" value="1" {if $user.musicview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="musicview">Browse music covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows music with known lookup info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-gamepad fa-2x text-primary me-3"></i>
                                                                                    <h6 class="mb-0">Console View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="consoleview" name="consoleview" value="1" {if $user.consoleview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="consoleview">Browse console covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows games with known lookup info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-desktop fa-2x text-info me-3"></i>
                                                                                    <h6 class="mb-0">Games View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="gameview" name="gameview" value="1" {if $user.gameview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="gameview">Browse game covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows games with known lookup info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-book fa-2x text-success me-3"></i>
                                                                                    <h6 class="mb-0">Book View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="bookview" name="bookview" value="1" {if $user.bookview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="bookview">Browse book covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows books with known lookup info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 col-lg-4">
                                                                        <div class="card h-100 border">
                                                                            <div class="card-body">
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <i class="fa fa-venus-mars fa-2x text-danger me-3"></i>
                                                                                    <h6 class="mb-0">XXX View</h6>
                                                                                </div>
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox" id="xxxview" name="xxxview" value="1" {if $user.xxxview == "1"}checked{/if}>
                                                                                    <label class="form-check-label" for="xxxview">Browse XXX covers</label>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-2">Only shows XXX releases with known lookup info.</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Security Tab -->
                                                    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                                                        <div class="card border mb-4">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fa fa-shield me-2"></i>Security Settings</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center mb-4">
                                                                    <div class="me-4">
                                                                        <i class="fa fa-lock fa-3x text-primary"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h5 class="mb-1">Two-Factor Authentication</h5>
                                                                        <p class="mb-0 text-muted">Add an extra layer of security to your account</p>
                                                                    </div>
                                                                    <div class="ms-auto">
                                                                        <a href="{{url("{'2fa'}")}}" class="btn btn-primary">
                                                                            <i class="fa fa-cog me-2"></i>Manage 2FA
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex gap-2 mt-4">
                                                    {{Form::submit('Save Changes', ['class' => 'btn btn-success'])}}
                                                    <a href="{{url("/profile")}}" class="btn btn-outline-secondary">Cancel</a>
                                                </div>

                                                {{Form::close()}}
                                            </div>
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

                                        // API Key copy functionality
                                        document.getElementById('copyApiKey').addEventListener('click', function() {
                                            var apiKeyInput = this.previousElementSibling;
                                            apiKeyInput.select();
                                            document.execCommand('copy');

                                            // Show tooltip with "Copied!" message
                                            var tooltip = bootstrap.Tooltip.getInstance(this);
                                            tooltip.hide();
                                            this.setAttribute('data-bs-original-title', 'Copied!');
                                            tooltip = new bootstrap.Tooltip(this);
                                            tooltip.show();

                                            // Reset tooltip after 2 seconds
                                            setTimeout(() => {
                                                this.setAttribute('data-bs-original-title', 'Copy to clipboard');
                                            }, 2000);
                                        });
                                    });
                                    {/literal}
                                    </script>
