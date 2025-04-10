<div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">{$title}</h4>
                        <a href="{{url("/admin/role-list")}}" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Roles
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    {if isset($error) && $error != ''}
                        <div class="alert alert-danger mb-4">
                            <i class="fa fa-exclamation-circle me-2"></i>{$error}
                        </div>
                    {/if}

                    <form action="{{url("/admin/role-add?action=submit")}}" method="post" id="roleAddForm">
                        {{csrf_field()}}

                        <div class="row">
                            <!-- Role Information -->
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fa fa-id-badge me-2"></i>Role Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="name" class="form-label fw-bold">Name:</label>
                                            <input type="text" class="form-control" id="name" name="name" value=" " required>
                                            <div class="form-text">The name of the role</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="defaultinvites" class="form-label fw-bold">Invites:</label>
                                            <input type="number" class="form-control" id="defaultinvites" name="defaultinvites" value="" min="0">
                                            <div class="form-text">Default number of invites to give users on account creation</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="donation" class="form-label fw-bold">Donation amount:</label>
                                            <input type="number" class="form-control" id="donation" name="donation" value="0" min="0">
                                            <div class="form-text">Donation amount for this role</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="addyears" class="form-label fw-bold">Years Added:</label>
                                            <input type="number" class="form-control" id="addyears" name="addyears" value="0" min="0">
                                            <div class="form-text">Years to add for this role</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Is Default Role:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="isdefault" id="isdefault1" value="1">
                                                <label class="form-check-label" for="isdefault1">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="isdefault" id="isdefault0" value="0" checked>
                                                <label class="form-check-label" for="isdefault0">No</label>
                                            </div>
                                            <div class="form-text">Make this the default role for new users</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fa fa-sliders-h me-2"></i>Role Permissions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Can Preview:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="canpreview" id="canpreview1" value="1">
                                                <label class="form-check-label" for="canpreview1">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="canpreview" id="canpreview0" value="0" checked>
                                                <label class="form-check-label" for="canpreview0">No</label>
                                            </div>
                                            <div class="form-text">Whether the role can preview screenshots</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Hide Ads:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="hideads" id="hideads1" value="1">
                                                <label class="form-check-label" for="hideads1">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="hideads" id="hideads0" value="0" checked>
                                                <label class="form-check-label" for="hideads0">No</label>
                                            </div>
                                            <div class="form-text">Whether ad's are hidden</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Edit Release:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="editrelease" id="editrelease1" value="1">
                                                <label class="form-check-label" for="editrelease1">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="editrelease" id="editrelease0" value="0" checked>
                                                <label class="form-check-label" for="editrelease0">No</label>
                                            </div>
                                            <div class="form-text">Can the role edit releases</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- API and Content Access -->
                            <div class="col-lg-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fa fa-exchange-alt me-2"></i>API & Download Limits</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="apirequests" class="form-label fw-bold">API Requests:</label>
                                            <input type="number" class="form-control" id="apirequests" name="apirequests" value="" min="0">
                                            <div class="form-text">Number of API requests allowed per 24 hour period</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="rate_limit" class="form-label fw-bold">API Rate Limit:</label>
                                            <input type="number" class="form-control" id="rate_limit" name="rate_limit" value="" min="0">
                                            <div class="form-text">Number of API requests allowed per 1 minute</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="downloadrequests" class="form-label fw-bold">Download Requests:</label>
                                            <input type="number" class="form-control" id="downloadrequests" name="downloadrequests" value="" min="0">
                                            <div class="form-text">Number of downloads allowed per 24 hour period</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fa fa-eye me-2"></i>Content Access Permissions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Console releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewconsole" id="viewconsole1" value="1">
                                                        <label class="form-check-label" for="viewconsole1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewconsole" id="viewconsole0" value="0" checked>
                                                        <label class="form-check-label" for="viewconsole0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Movie releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewmovies" id="viewmovies1" value="1">
                                                        <label class="form-check-label" for="viewmovies1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewmovies" id="viewmovies0" value="0" checked>
                                                        <label class="form-check-label" for="viewmovies0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Audio releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewaudio" id="viewaudio1" value="1">
                                                        <label class="form-check-label" for="viewaudio1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewaudio" id="viewaudio0" value="0" checked>
                                                        <label class="form-check-label" for="viewaudio0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view PC releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewpc" id="viewpc1" value="1">
                                                        <label class="form-check-label" for="viewpc1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewpc" id="viewpc0" value="0" checked>
                                                        <label class="form-check-label" for="viewpc0">No</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view TV releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewtv" id="viewtv1" value="1">
                                                        <label class="form-check-label" for="viewtv1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewtv" id="viewtv0" value="0" checked>
                                                        <label class="form-check-label" for="viewtv0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Adult releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewadult" id="viewadult1" value="1">
                                                        <label class="form-check-label" for="viewadult1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewadult" id="viewadult0" value="0" checked>
                                                        <label class="form-check-label" for="viewadult0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Book releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewbooks" id="viewbooks1" value="1">
                                                        <label class="form-check-label" for="viewbooks1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewbooks" id="viewbooks0" value="0" checked>
                                                        <label class="form-check-label" for="viewbooks0">No</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Can view Other releases:</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewother" id="viewother1" value="1">
                                                        <label class="form-check-label" for="viewother1">Yes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="viewother" id="viewother0" value="0" checked>
                                                        <label class="form-check-label" for="viewother0">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="{{url("/admin/role-list")}}" class="btn btn-outline-secondary">
                            <i class="fa fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" form="roleAddForm" class="btn btn-success">
                            <i class="fa fa-save me-2"></i>Save Role
                        </button>
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

                // Basic form validation
                const form = document.getElementById('roleAddForm');
                if (form) {
                    form.addEventListener('submit', function(event) {
                        const name = document.getElementById('name').value.trim();

                        if (!name) {
                            event.preventDefault();
                            alert('Role name is required');
                            return false;
                        }

                        // Validate numeric fields
                        const numericFields = ['apirequests', 'rate_limit', 'downloadrequests', 'defaultinvites', 'donation', 'addyears'];
                        for (const fieldId of numericFields) {
                            const field = document.getElementById(fieldId);
                            if (field && field.value && !/^\d+$/.test(field.value)) {
                                event.preventDefault();
                                alert(`${fieldId} must be a positive number`);
                                field.focus();
                                return false;
                            }
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

            /* Card styling */
            .card {
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                border-radius: 0.375rem;
                margin-bottom: 1.5rem;
            }

            .card-header {
                padding: 0.75rem 1.25rem;
            }

            /* Form check improvements */
            .form-check-input {
                margin-top: 0.25rem;
            }

            /* Form radios spacing improvement */
            .form-check {
                margin-bottom: 0.375rem;
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
