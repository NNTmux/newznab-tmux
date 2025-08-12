<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{$title}</h4>
            <a href="{{url("/admin/invitations")}}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back to Invitations
            </a>
        </div>
    </div>

    <div class="card-body">
        <div class="row">
            <!-- Invitation Details -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fa fa-envelope me-2"></i>Invitation Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Email:</dt>
                            <dd class="col-sm-9">
                                <i class="fa fa-envelope text-muted me-2"></i>
                                {$invitation->email}
                            </dd>

                            <dt class="col-sm-3">Token:</dt>
                            <dd class="col-sm-9">
                                <code class="bg-light p-2 rounded">{$invitation->token}</code>
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('{$invitation->token}')">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </dd>

                            <dt class="col-sm-3">Invitation URL:</dt>
                            <dd class="col-sm-9">
                                {assign var="inviteUrl" value="{url("/invitation/{$invitation->token}")}"}
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{$inviteUrl}" readonly id="inviteUrl">
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('{$inviteUrl}')">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </div>
                            </dd>

                            <dt class="col-sm-3">Status:</dt>
                            <dd class="col-sm-9">
                                {if $invitation->used_at}
                                    <span class="badge bg-success fs-6">
                                        <i class="fa fa-check me-1"></i>Used
                                    </span>
                                {elseif $invitation->expires_at|strtotime < $smarty.now}
                                    <span class="badge bg-danger fs-6">
                                        <i class="fa fa-times me-1"></i>Expired
                                    </span>
                                {elseif !$invitation->is_active}
                                    <span class="badge bg-secondary fs-6">
                                        <i class="fa fa-ban me-1"></i>Cancelled
                                    </span>
                                {else}
                                    <span class="badge bg-warning text-dark fs-6">
                                        <i class="fa fa-clock-o me-1"></i>Pending
                                    </span>
                                {/if}
                            </dd>

                            <dt class="col-sm-3">Created:</dt>
                            <dd class="col-sm-9">
                                <i class="fa fa-calendar text-muted me-2"></i>
                                {$invitation->created_at|date_format:"%B %d, %Y at %H:%M"}
                            </dd>

                            <dt class="col-sm-3">Expires:</dt>
                            <dd class="col-sm-9">
                                <i class="fa fa-calendar-times-o text-muted me-2"></i>
                                <span class="{if $invitation->expires_at|strtotime < $smarty.now}text-danger{/if}">
                                    {$invitation->expires_at|date_format:"%B %d, %Y at %H:%M"}
                                </span>
                                {if $invitation->expires_at|strtotime < $smarty.now}
                                    <small class="text-danger">(Expired)</small>
                                {/if}
                            </dd>

                            {if $invitation->used_at}
                            <dt class="col-sm-3">Used:</dt>
                            <dd class="col-sm-9">
                                <i class="fa fa-check-circle text-success me-2"></i>
                                {$invitation->used_at|date_format:"%B %d, %Y at %H:%M"}
                            </dd>
                            {/if}

                            {if $invitation->metadata && $invitation->metadata != '[]'}
                            <dt class="col-sm-3">Metadata:</dt>
                            <dd class="col-sm-9">
                                <pre class="bg-light p-2 rounded">{$invitation->metadata|json_encode:JSON_PRETTY_PRINT}</pre>
                            </dd>
                            {/if}
                        </dl>
                    </div>
                </div>
            </div>

            <!-- User Information -->
            <div class="col-lg-4">
                <!-- Invited By -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fa fa-user me-2"></i>Invited By</h5>
                    </div>
                    <div class="card-body">
                        {if $invitation->invitedBy}
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="fa fa-user-circle fa-3x text-muted"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">
                                        <a href="{{url("/admin/user-edit?id={$invitation->invitedBy->id}")}}"
                                           class="text-decoration-none">
                                            {$invitation->invitedBy->username}
                                        </a>
                                    </h6>
                                    <small class="text-muted">{$invitation->invitedBy->email}</small>
                                    <br>
                                    <small class="text-muted">
                                        Role: <span class="badge bg-secondary">{$invitation->invitedBy->role->name}</span>
                                    </small>
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <strong class="d-block">{$invitation->invitedBy->invites}</strong>
                                        <small class="text-muted">Remaining Invites</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <strong class="d-block">{$invitation->invitedBy->invitation|@count}</strong>
                                    <small class="text-muted">Total Sent</small>
                                </div>
                            </div>
                        {else}
                            <div class="text-center py-3">
                                <i class="fa fa-user-times fa-3x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Inviter information not available</p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Used By -->
                {if $invitation->usedBy}
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fa fa-user-check me-2"></i>Used By</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <i class="fa fa-user-circle fa-3x text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">
                                    <a href="{{url("/admin/user-edit?id={$invitation->usedBy->id}")}}"
                                       class="text-decoration-none">
                                        {$invitation->usedBy->username}
                                    </a>
                                </h6>
                                <small class="text-muted">{$invitation->usedBy->email}</small>
                                <br>
                                <small class="text-muted">
                                    Role: <span class="badge bg-secondary">{$invitation->usedBy->role->name}</span>
                                </small>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <strong class="d-block">{$invitation->usedBy->grabs}</strong>
                                    <small class="text-muted">Grabs</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <strong class="d-block">
                                    {if $invitation->usedBy->created_at}
                                        {$invitation->usedBy->created_at|date_format:"%m/%d/%Y"}
                                    {else}
                                        -
                                    {/if}
                                </strong>
                                <small class="text-muted">Joined</small>
                            </div>
                        </div>
                    </div>
                </div>
                {/if}

                <!-- Actions -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fa fa-cogs me-2"></i>Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            {if !$invitation->used_at && $invitation->expires_at|strtotime >= $smarty.now && $invitation->is_active}
                                <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#resendModal">
                                    <i class="fa fa-repeat me-2"></i>Resend Invitation
                                </button>

                                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                    <i class="fa fa-times me-2"></i>Cancel Invitation
                                </button>
                            {/if}

                            <a href="{{url("/invitation/{$invitation->token}")}}"
                               class="btn btn-outline-info w-100" target="_blank">
                                <i class="fa fa-external-link me-2"></i>View Public Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modals -->

<!-- Resend Invitation Modal -->
<div class="modal fade" id="resendModal" tabindex="-1" aria-labelledby="resendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-info text-white border-0">
                <h5 class="modal-title fw-bold" id="resendModalLabel">
                    <i class="fa fa-repeat me-2"></i>Resend Invitation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 me-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="fa fa-envelope fa-3x text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-3 fw-semibold">Resend invitation to:</h6>
                        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 d-flex align-items-center mb-3">
                            <i class="fa fa-envelope me-2"></i>
                            <strong>{$invitation->email}</strong>
                        </div>
                        <p class="text-muted mb-2">This will send a new invitation email to the recipient with the same invitation token and expiration date.</p>
                        <div class="bg-light rounded p-3">
                            <small class="text-muted d-flex align-items-center">
                                <i class="fa fa-info-circle me-2 text-info"></i>
                                The original invitation token will remain valid and functional.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light bg-opacity-50">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Cancel
                </button>
                <form method="POST" action="{{url("/admin/invitations/{$invitation->id}/resend")}}" style="display: inline;">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-danger text-white border-0">
                <h5 class="modal-title fw-bold" id="cancelModalLabel">
                    <i class="fa fa-exclamation-triangle me-2"></i>Cancel Invitation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 me-3">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                            <i class="fa fa-exclamation-triangle fa-3x text-danger"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-3 fw-semibold">You are about to cancel the invitation for:</h6>
                        <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 d-flex align-items-center mb-3">
                            <i class="fa fa-envelope me-2"></i>
                            <strong>{$invitation->email}</strong>
                        </div>
                        <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25">
                            <h6 class="alert-heading d-flex align-items-center mb-2">
                                <i class="fa fa-exclamation-triangle me-2"></i>Warning!
                            </h6>
                            <p class="mb-2">This action will permanently cancel this invitation.</p>
                            <ul class="mb-0 small">
                                <li>The recipient will no longer be able to use this invitation to register</li>
                                <li>The invitation token will become invalid</li>
                                <li>This action cannot be undone</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light bg-opacity-50">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-arrow-left me-1"></i>Keep Invitation
                </button>
                <form method="POST" action="{{url("/admin/invitations/{$invitation->id}/cancel")}}" style="display: inline;">
                    {{csrf_field()}}
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-times me-1"></i>Cancel Invitation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fa fa-check me-2"></i>Copied to clipboard!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', function() {
            document.body.removeChild(toast);
        });
    }).catch(function(err) {
        console.error('Failed to copy text: ', err);
        alert('Failed to copy to clipboard');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Confirmation dialogs
    document.querySelectorAll('.confirm_action').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-message') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
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
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.375rem;
}

.badge.fs-6 {
    font-size: 0.9rem !important;
}

.border-end {
    border-right: 1px solid #dee2e6 !important;
}

code {
    font-size: 0.875em;
    word-break: break-all;
}

pre {
    font-size: 0.8rem;
    max-height: 200px;
    overflow-y: auto;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .row.text-center .col-6 {
        margin-bottom: 1rem;
    }

    .border-end {
        border-right: none !important;
        border-bottom: 1px solid #dee2e6 !important;
        padding-bottom: 0.5rem;
    }
}
{/literal}
</style>
