<div class="card">
				    <div class="card-header">
				        <div class="d-flex justify-content-between align-items-center">
				            <h4 class="mb-0">{$title}</h4>
				            <a href="{{url("/admin/user-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-arrow-left me-2"></i>Back to Users
				            </a>
				        </div>
				    </div>

				    <div class="card-body">
				        {if isset($error) && $error != ''}
				            <div class="alert alert-danger mb-4">
				                <i class="fa fa-exclamation-circle me-2"></i>{$error}
				            </div>
				        {/if}

				        <form action="{{url("/admin/user-edit?action=submit")}}" method="POST" id="userEditForm">
				            {{csrf_field()}}
				            <input type="hidden" name="id" value="{if !empty($user)}{$user.id}{/if}"/>

				            <div class="row">
				                <!-- User Information Section -->
				                <div class="col-lg-6">
				                    <div class="card mb-4">
				                        <div class="card-header bg-light">
				                            <h5 class="mb-0"><i class="fa fa-user me-2"></i>User Information</h5>
				                        </div>
				                        <div class="card-body">
				                            <div class="mb-3">
				                                <label for="username" class="form-label fw-bold">Username:</label>
				                                <input type="text" class="form-control" id="username" name="username"
				                                       value="{if !empty($user)}{$user.username}{/if}" required autocomplete="off">
				                            </div>

				                            <div class="mb-3">
				                                <label for="email" class="form-label fw-bold">Email Address:</label>
				                                <input type="email" class="form-control" id="email" name="email"
				                                       value="{if !empty($user)}{$user.email}{/if}" required autocomplete="off">
				                            </div>

				                            <div class="mb-3">
				                                <label for="password" class="form-label fw-bold">Password:</label>
				                                <input type="password" class="form-control" id="password" name="password" autocomplete="off">
				                                {if !empty($user.id)}
				                                    <div class="form-text text-muted">Only enter a password if you want to change it.</div>
				                                {/if}
				                            </div>

				                            <div class="mb-3">
				                                <label for="role" class="form-label fw-bold">User Role:</label>
				                                <select class="form-select" id="role" name="role">
				                                    {html_options values=$role_ids output=$role_names selected=$user.role.id}
				                                </select>
				                            </div>

                                            {if !empty($user.id)}
                                                <div class="mb-3">
                                                    <label for="rolechangedate" class="form-label fw-bold">Role Change Date:</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="rolechangedate" name="rolechangedate"
                                                               value="{$user.rolechangedate}" placeholder="YYYY-MM-DD HH:MM:SS" autocomplete="off">
                                                        <span class="input-group-text">
                                                            <i class="fa fa-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            {/if}


                                            <div class="mb-3">
				                                <label for="notes" class="form-label fw-bold">Notes:</label>
				                                <input type="text" class="form-control" id="notes" name="notes"
				                                       value="{$user.notes|escape:htmlall}">
				                            </div>
				                        </div>
				                    </div>
				                </div>

				                <!-- User Permissions & Stats Section -->
				                <div class="col-lg-6">
				                    {if !empty($user.id)}
				                        <div class="card mb-4">
				                            <div class="card-header bg-light">
				                                <h5 class="mb-0"><i class="fa fa-chart-bar me-2"></i>User Statistics</h5>
				                            </div>
				                            <div class="card-body">
				                                <div class="row mb-3">
				                                    <div class="col-md-6">
				                                        <label for="grabs" class="form-label fw-bold">Grabs:</label>
				                                        <input type="number" class="form-control" id="grabs" name="grabs"
				                                               value="{$user.grabs}" min="0">
				                                    </div>
				                                    <div class="col-md-6">
				                                        <label for="invites" class="form-label fw-bold">Invites:</label>
				                                        <input type="number" class="form-control" id="invites" name="invites"
				                                               value="{$user.invites}" min="0">
				                                    </div>
				                                </div>
				                            </div>
				                        </div>

				                        <div class="card">
				                            <div class="card-header bg-light">
				                                <h5 class="mb-0"><i class="fa fa-lock me-2"></i>Access Permissions</h5>
				                            </div>
				                            <div class="card-body">
				                                <div class="row">
				                                    <div class="col-md-6">
				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="movieview"
				                                                   name="movieview" value="1" {if $user.movieview==1}checked{/if}>
				                                            <label class="form-check-label" for="movieview">
				                                                Movie View
				                                            </label>
				                                        </div>

				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="musicview"
				                                                   name="musicview" value="1" {if $user.musicview==1}checked{/if}>
				                                            <label class="form-check-label" for="musicview">
				                                                Music View
				                                            </label>
				                                        </div>

				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="gameview"
				                                                   name="gameview" value="1" {if $user.gameview==1}checked{/if}>
				                                            <label class="form-check-label" for="gameview">
				                                                Game View
				                                            </label>
				                                        </div>
				                                    </div>

				                                    <div class="col-md-6">
				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="xxxview"
				                                                   name="xxxview" value="1" {if $user.xxxview==1}checked{/if}>
				                                            <label class="form-check-label" for="xxxview">
				                                                XXX View
				                                            </label>
				                                        </div>

				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="consoleview"
				                                                   name="consoleview" value="1" {if $user.consoleview==1}checked{/if}>
				                                            <label class="form-check-label" for="consoleview">
				                                                Console View
				                                            </label>
				                                        </div>

				                                        <div class="form-check mb-3">
				                                            <input class="form-check-input" type="checkbox" id="bookview"
				                                                   name="bookview" value="1" {if $user.bookview==1}checked{/if}>
				                                            <label class="form-check-label" for="bookview">
				                                                Book View
				                                            </label>
				                                        </div>
				                                    </div>
				                                </div>
				                            </div>
				                        </div>
				                    {/if}
				                </div>
				            </div>
				        </form>
				    </div>

				    <div class="card-footer">
				        <div class="d-flex justify-content-between">
				            <a href="{{url("/admin/user-list")}}" class="btn btn-outline-secondary">
				                <i class="fa fa-times me-2"></i>Cancel
				            </a>
				            <div>
				                {if !empty($user.id) && $user->role->id != 2}
				                    <a href="{{url("/admin/user-delete?id={$user.id}")}}" class="btn btn-danger confirm_action me-2">
				                        <i class="fa fa-trash me-2"></i>Delete User
				                    </a>
				                {/if}
				                <button type="submit" form="userEditForm" class="btn btn-success">
				                    <i class="fa fa-save me-2"></i>Save User
				                </button>
				            </div>
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

                    // *** START: Added Flatpickr Initialization ***
                    const datepickerInput = document.getElementById('rolechangedate');
                    if (datepickerInput) {
                        flatpickr(datepickerInput, {
                            enableTime: true,       // Enable time selection
                            time_24hr: true,        // Use 24-hour format for time
                            dateFormat: "Y-m-d H:i:S", // Format for the server (YYYY-MM-DD HH:MM:SS)
                            allowInput: true,       // Allow manual input
                            altInput: true,         // Show a user-friendly format
                            altFormat: "F j, Y H:i", // How the date and time are displayed (e.g., June 10, 2024 14:30)
                        });
                    }

                    // *** END: Added Flatpickr Initialization ***


                    // Basic form validation
				    const form = document.getElementById('userEditForm');
				    if (form) {
				        form.addEventListener('submit', function(event) {
				            const username = document.getElementById('username').value.trim();
				            const email = document.getElementById('email').value.trim();

				            if (!username) {
				                event.preventDefault();
				                alert('Username is required');
				                return false;
				            }

				            if (!email) {
				                event.preventDefault();
				                alert('Email address is required');
				                return false;
				            }

				            // Validate email format
				            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				            if (!emailRegex.test(email)) {
				                event.preventDefault();
				                alert('Please enter a valid email address');
				                return false;
				            }
				        });
				    }

				    // Confirm deletion dialog
				    document.querySelectorAll('.confirm_action').forEach(function(element) {
				        element.addEventListener('click', function(e) {
				            if (!confirm('Are you sure you want to delete this user? This cannot be undone.')) {
				                e.preventDefault();
				                return false;
				            }
				        });
				    });
				});
				{/literal}
				</script>

				<style>
				{literal}
				/* Form styling improvements */
				.form-label {
				    margin-bottom: 0.5rem;
				}

				/* Card styling */
				.card {
				    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
				    border-radius: 0.375rem;
				}

				.card-header {
				    padding: 0.75rem 1.25rem;
				}

				/* Form check adjustments */
				.form-check-input {
				    margin-top: 0.25rem;
				}

				/* Role radio buttons */
				#role {
				    margin-left: 0.5rem;
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
				    .card-footer .d-flex div,
				    .card-footer .d-flex button {
				        width: 100%;
				    }

				    .card-footer .d-flex div {
				        display: flex;
				        flex-direction: column;
				        gap: 0.5rem;
				    }

				    .card-footer .d-flex div a,
				    .card-footer .d-flex div button {
				        margin-right: 0 !important;
				    }
				}
				{/literal}
				</style>
