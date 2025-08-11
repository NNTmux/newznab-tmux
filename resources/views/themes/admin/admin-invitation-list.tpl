<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                    <i class="fa fa-broom me-1"></i>Cleanup Expired
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">{$stats.total}</h4>
                                <p class="mb-0">Total Invitations</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fa fa-envelope fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">{$stats.pending}</h4>
                                <p class="mb-0">Pending</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fa fa-clock-o fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">{$stats.used}</h4>
                                <p class="mb-0">Used</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fa fa-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">{$stats.expired}</h4>
                                <p class="mb-0">Expired</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fa fa-times fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time-based Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info">Today</h5>
                        <h3>{$stats.today}</h3>
                        <small class="text-muted">Invitations sent today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info">This Week</h5>
                        <h3>{$stats.this_week}</h3>
                        <small class="text-muted">Invitations sent this week</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info">This Month</h5>
                        <h3>{$stats.this_month}</h3>
                        <small class="text-muted">Invitations sent this month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Inviters -->
        {if $topInviters|@count > 0}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fa fa-trophy me-2"></i>Top Inviters</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Total Sent</th>
                                        <th>Successful</th>
                                        <th>Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach $topInviters as $inviter}
                                    <tr>
                                        <td>
                                            <a href="{{url("/admin/user-edit?id={$inviter.id}")}}" class="text-decoration-none">
                                                {$inviter.username}
                                            </a>
                                        </td>
                                        <td>{$inviter.total_invitations}</td>
                                        <td>{$inviter.successful_invitations}</td>
                                        <td>
                                            {if $inviter.total_invitations > 0}
                                                {math equation="round((x/y)*100, 1)" x=$inviter.successful_invitations y=$inviter.total_invitations}%
                                            {else}
                                                0%
                                            {/if}
                                        </td>
                                    </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}

        <!-- Filters -->
        <form method="GET" action="{{url("/admin/invitations")}}" class="mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa fa-filter me-2"></i>Filters</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                {html_options options=$statusOptions selected=$status}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="invited_by" class="form-label">Invited By</label>
                            <input type="text" name="invited_by" id="invited_by" value="{$invited_by}"
                                   class="form-control" placeholder="Username">
                        </div>
                        <div class="col-md-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" name="email" id="email" value="{$email}"
                                   class="form-control" placeholder="Email address">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fa fa-search me-1"></i>Filter
                            </button>
                            <a href="{{url("/admin/invitations")}}" class="btn btn-outline-secondary">
                                <i class="fa fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Bulk Actions -->
        <form method="POST" action="{{url("/admin/invitations/bulk")}}" id="bulkForm">
            {{csrf_field()}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <select name="bulk_action" class="form-select" style="width: auto;">
                        <option value="">Bulk Actions</option>
                        <option value="cancel">Cancel Selected</option>
                        <option value="resend">Resend Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm" id="bulkSubmit" disabled>
                        Apply
                    </button>
                </div>
                <div>
                    <small class="text-muted">
                        Showing {$invitations->firstItem()} to {$invitations->lastItem()} of {$invitations->total()} invitations
                    </small>
                </div>
            </div>

            <!-- Invitations Table -->
            {if $invitations->count() > 0}
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 30px;">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Email</th>
                            <th>Invited By</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Used By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $invitations as $invitation}
                        <tr>
                            <td>
                                <input type="checkbox" name="invitation_ids[]" value="{$invitation->id}"
                                       class="form-check-input invitation-checkbox">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fa fa-envelope text-muted me-2"></i>
                                    <span>{$invitation->email}</span>
                                </div>
                            </td>
                            <td>
                                {if $invitation->invitedBy}
                                    <a href="{{url("/admin/user-edit?id={$invitation->invitedBy->id}")}}"
                                       class="text-decoration-none">
                                        {$invitation->invitedBy->username}
                                    </a>
                                {else}
                                    <span class="text-muted">Unknown</span>
                                {/if}
                            </td>
                            <td>
                                {if $invitation->used_at}
                                    <span class="badge bg-success">Used</span>
                                {elseif $invitation->expires_at|strtotime < $smarty.now}
                                    <span class="badge bg-danger">Expired</span>
                                {elseif !$invitation->is_active}
                                    <span class="badge bg-secondary">Cancelled</span>
                                {else}
                                    <span class="badge bg-warning text-dark">Pending</span>
                                {/if}
                            </td>
                            <td>
                                <small title="{$invitation->created_at}">
                                    {$invitation->created_at|date_format:"%m/%d/%Y %H:%M"}
                                </small>
                            </td>
                            <td>
                                <small title="{$invitation->expires_at}"
                                       class="{if $invitation->expires_at|strtotime < $smarty.now}text-danger{/if}">
                                    {$invitation->expires_at|date_format:"%m/%d/%Y %H:%M"}
                                </small>
                            </td>
                            <td>
                                {if $invitation->usedBy}
                                    <a href="{{url("/admin/user-edit?id={$invitation->usedBy->id}")}}"
                                       class="text-decoration-none">
                                        {$invitation->usedBy->username}
                                    </a>
                                    <br><small class="text-muted">{$invitation->used_at|date_format:"%m/%d/%Y %H:%M"}</small>
                                {else}
                                    <span class="text-muted">-</span>
                                {/if}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{url("/admin/invitations/{$invitation->id}")}}"
                                       class="btn btn-outline-info btn-sm" title="View Details">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    {if !$invitation->used_at && $invitation->expires_at|strtotime >= $smarty.now && $invitation->is_active}
                                        <button type="button" class="btn btn-outline-warning btn-sm resend-invitation-btn"
                                                title="Resend" data-invitation-id="{$invitation->id}"
                                                data-invitation-email="{$invitation->email}">
                                            <i class="fa fa-repeat"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm cancel-invitation-btn"
                                                title="Cancel" data-invitation-id="{$invitation->id}"
                                                data-invitation-email="{$invitation->email}">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    {/if}
                                </div>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        {if $invitations->hasPages()}
            <div class="d-flex justify-content-center mt-4">
                {$invitations->onEachSide(5)->links()}
            </div>
        {/if}

        {else}
            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>No invitations found matching your criteria.
            </div>
        {/if}
    </div>
</div>

<!-- Confirmation Modals -->

<!-- Cleanup Expired Invitations Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="cleanupModalLabel">
                    <i class="fa fa-broom me-2"></i>Cleanup Expired Invitations
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fa fa-exclamation-triangle fa-2x text-warning me-3"></i>
                    </div>
                    <div>
                        <p class="mb-2"><strong>You are about to cleanup all expired invitations.</strong></p>
                        <p class="mb-0">This action will permanently remove all expired invitation records from the database. This cannot be undone.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Cancel
                </button>
                <form method="POST" action="{{url("/admin/invitations/cleanup")}}" style="display: inline;">
                    {{csrf_field()}}
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-broom me-1"></i>Cleanup Expired
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Resend Invitation Modal -->
<div class="modal fade" id="resendModal" tabindex="-1" aria-labelledby="resendModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="resendModalLabel">
                    <i class="fa fa-repeat me-2"></i>Resend Invitation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fa fa-envelope fa-2x text-info me-3"></i>
                    </div>
                    <div>
                        <p class="mb-2"><strong>Resend invitation email</strong></p>
                        <p class="mb-2">Email: <code id="resendEmail"></code></p>
                        <p class="mb-0">This will send a new invitation email to the recipient with the same invitation token.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Cancel
                </button>
                <form method="POST" action="" id="resendForm" style="display: inline;">
                    {{csrf_field()}}
                    <button type="submit" class="btn btn-info">
                        <i class="fa fa-repeat me-1"></i>Resend Invitation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Invitation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancelModalLabel">
                    <i class="fa fa-times me-2"></i>Cancel Invitation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fa fa-exclamation-triangle fa-2x text-danger me-3"></i>
                    </div>
                    <div>
                        <p class="mb-2"><strong>Cancel invitation</strong></p>
                        <p class="mb-2">Email: <code id="cancelEmail"></code></p>
                        <p class="mb-0">This will permanently cancel the invitation. The recipient will no longer be able to use this invitation to register.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Keep Invitation
                </button>
                <form method="POST" action="" id="cancelForm" style="display: inline;">
                    {{csrf_field()}}
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-times me-1"></i>Cancel Invitation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Action Confirmation Modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1" aria-labelledby="bulkActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="bulkActionHeader">
                <h5 class="modal-title" id="bulkActionModalLabel">
                    <i class="fa fa-list me-2"></i>Bulk Action Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i id="bulkActionIcon" class="fa fa-2x me-3"></i>
                    </div>
                    <div>
                        <p class="mb-2"><strong id="bulkActionTitle"></strong></p>
                        <p class="mb-2">Selected invitations: <span id="bulkActionCount" class="badge bg-primary"></span></p>
                        <p class="mb-0" id="bulkActionDescription"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn" id="bulkActionConfirm">
                    <i class="fa me-1"></i><span id="bulkActionButtonText"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const invitationCheckboxes = document.querySelectorAll('.invitation-checkbox');
    const bulkSubmit = document.getElementById('bulkSubmit');
    const bulkAction = document.querySelector('select[name="bulk_action"]');

    function updateBulkSubmitState() {
        const selectedCheckboxes = document.querySelectorAll('.invitation-checkbox:checked');
        const hasSelection = selectedCheckboxes.length > 0;
        const hasAction = bulkAction.value !== '';

        bulkSubmit.disabled = !(hasSelection && hasAction);
    }

    selectAllCheckbox.addEventListener('change', function() {
        invitationCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkSubmitState();
    });

    invitationCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.invitation-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === invitationCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < invitationCheckboxes.length;
            updateBulkSubmitState();
        });
    });

    bulkAction.addEventListener('change', updateBulkSubmitState);

    // Individual invitation actions
    document.querySelectorAll('.resend-invitation-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const invitationId = this.getAttribute('data-invitation-id');
            const invitationEmail = this.getAttribute('data-invitation-email');

            // Set email in modal
            document.getElementById('resendEmail').textContent = invitationEmail;

            // Set form action with correct invitation ID
            const resendForm = document.getElementById('resendForm');
            resendForm.setAttribute('action', `/admin/invitations/${invitationId}/resend`);

            // Show modal
            const resendModal = new bootstrap.Modal(document.getElementById('resendModal'));
            resendModal.show();
        });
    });

    document.querySelectorAll('.cancel-invitation-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const invitationId = this.getAttribute('data-invitation-id');
            const invitationEmail = this.getAttribute('data-invitation-email');

            // Set email in modal
            document.getElementById('cancelEmail').textContent = invitationEmail;

            // Set form action with correct invitation ID
            const cancelForm = document.getElementById('cancelForm');
            cancelForm.setAttribute('action', `/admin/invitations/${invitationId}/cancel`);

            // Show modal
            const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
            cancelModal.show();
        });
    });

    // Bulk action handling
    document.getElementById('bulkSubmit').addEventListener('click', function(e) {
        e.preventDefault();

        const selectedCheckboxes = document.querySelectorAll('.invitation-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;
        const action = bulkAction.value;

        if (selectedCount === 0) {
            alert('Please select at least one invitation.');
            return;
        }

        if (!action) {
            alert('Please select a bulk action.');
            return;
        }

        // Configure modal based on action
        let modalConfig = {};
        switch (action) {
            case 'cancel':
                modalConfig = {
                    title: 'Cancel Invitations',
                    description: 'This will permanently cancel the selected invitations. Recipients will no longer be able to use these invitations to register.',
                    icon: 'fa-times text-danger',
                    buttonClass: 'btn-danger',
                    buttonText: 'Cancel Invitations',
                    headerClass: 'bg-danger text-white'
                };
                break;
            case 'resend':
                modalConfig = {
                    title: 'Resend Invitations',
                    description: 'This will send new invitation emails to all selected recipients with their existing invitation tokens.',
                    icon: 'fa-repeat text-info',
                    buttonClass: 'btn-info',
                    buttonText: 'Resend Invitations',
                    headerClass: 'bg-info text-white'
                };
                break;
            case 'delete':
                modalConfig = {
                    title: 'Delete Invitations',
                    description: 'This will permanently delete the selected invitation records from the database. This action cannot be undone.',
                    icon: 'fa-trash text-danger',
                    buttonClass: 'btn-danger',
                    buttonText: 'Delete Invitations',
                    headerClass: 'bg-danger text-white'
                };
                break;
        }

        // Update modal content
        document.getElementById('bulkActionModalLabel').innerHTML = `<i class="fa fa-list me-2"></i>${modalConfig.title}`;
        document.getElementById('bulkActionTitle').textContent = modalConfig.title;
        document.getElementById('bulkActionCount').textContent = selectedCount;
        document.getElementById('bulkActionDescription').textContent = modalConfig.description;
        document.getElementById('bulkActionIcon').className = `fa fa-2x me-3 ${modalConfig.icon.split(' ')[0]} ${modalConfig.icon.split(' ')[1]}`;

        const confirmButton = document.getElementById('bulkActionConfirm');
        confirmButton.className = `btn ${modalConfig.buttonClass}`;
        confirmButton.innerHTML = `<i class="fa ${modalConfig.icon.split(' ')[0]} me-1"></i>${modalConfig.buttonText}`;

        const modalHeader = document.getElementById('bulkActionHeader');
        modalHeader.className = `modal-header ${modalConfig.headerClass}`;

        // Set up confirmation button
        confirmButton.onclick = function() {
            // Submit the bulk form
            document.getElementById('bulkForm').submit();
        };

        // Show modal
        const bulkActionModal = new bootstrap.Modal(document.getElementById('bulkActionModal'));
        bulkActionModal.show();
    });

    // Toast notification system
    function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

        const toastElement = document.createElement('div');
        toastElement.className = `toast align-items-center text-white bg-${type} border-0`;
        toastElement.setAttribute('role', 'alert');
        toastElement.setAttribute('aria-live', 'assertive');
        toastElement.setAttribute('aria-atomic', 'true');

        toastElement.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fa fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        toastContainer.appendChild(toastElement);

        const toast = new bootstrap.Toast(toastElement, {
            delay: 5000
        });
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    // Check for success/error messages in URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(decodeURIComponent(urlParams.get('success')), 'success');
    }
    if (urlParams.has('error')) {
        showToast(decodeURIComponent(urlParams.get('error')), 'danger');
    }
});
{/literal}
</script>

<style>
{literal}
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.375rem;
}

.table th {
    border-top: none;
    font-weight: 600;
    background-color: #f8f9fa;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}

.badge {
    font-size: 0.75em;
}

/* Custom checkbox styling */
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm > .btn {
        padding: 0.125rem 0.25rem;
    }
}
{/literal}
</style>
