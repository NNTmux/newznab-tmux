<div class="header">
    <div class="breadcrumb-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{$site->home_link}">Home</a></li>
                <li class="breadcrumb-item"><a href="{url('/profile')}">Profile</a></li>
                <li class="breadcrumb-item"><a href="{url('/invitations')}">My Invitations</a></li>
                <li class="breadcrumb-item active">Send New Invitation</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-paper-plane me-2"></i>Send New Invitation</h5>
                    <a href="{url('/invitations')}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Invitations
                    </a>
                        </div>
                    {/if}

                    {if $errors}
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                {foreach $errors as $error}
                                    <li>{$error}</li>
                                {/foreach}
                            </ul>
                        </div>
                    {/if}

                    <form method="POST" action="{url('/invitations/store')}">
                        <input type="hidden" name="_token" value="{$csrf_token}">

                        <div class="mb-4">
                            <label for="email" class="form-label">
                                <i class="fa fa-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                    <form method="POST" action="{url('/invitations/store')}">
                        <input type="hidden" name="_token" value="{$csrf_token}">
                            <label for="expiry_days" class="form-label">
                                <i class="fa fa-clock-o me-1"></i>Expiry Period
                            </label>
                            <select class="form-select {if $errors.expiry_days}is-invalid{/if}"
                                    id="expiry_days"
                                    name="expiry_days">
                                <option value="1" {if $old.expiry_days == '1'}selected{/if}>1 Day</option>
                        <div class="mb-4">
                            <label for="email" class="form-label">
                                <i class="fa fa-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   class="form-control {if $errors.email}is-invalid{/if}"
                                   id="email"
                                   name="email"
                                   value="{$old.email|default:''|escape:'htmlall'}"
                                   required
                                   placeholder="Enter recipient's email address">
                            {if $errors.email}
                                <div class="invalid-feedback">{$errors.email}</div>
                            {/if}
                            <div class="form-text">
                                <i class="fa fa-info-circle me-1"></i>The person will receive an invitation email at this address.
                            </div>
                        </div>
                                {/if}
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>The role to assign to the user when they accept the invitation.
                                </div>
                            </div>
                        {/if}

                        <div class="alert alert-info mb-4">
                            <div class="d-flex">
                                <i class="fa fa-lightbulb-o fa-lg me-3 mt-1"></i>
                                <div>
                                    <strong>How it works:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>The recipient will receive an email with a secure invitation link</li>
                                        <li>They can use this link to create their account within the specified time period</li>
                                        <li>Once the time expires, the invitation link becomes invalid</li>
                        <div class="mb-4">
                            <label for="expiry_days" class="form-label">
                                <i class="fa fa-clock-o me-1"></i>Expiry Period
                            </label>
                            <select class="form-select {if $errors.expiry_days}is-invalid{/if}"
                                    id="expiry_days"
                                    name="expiry_days">
                                <option value="1" {if $old.expiry_days == '1'}selected{/if}>1 Day</option>
                                <option value="3" {if $old.expiry_days == '3'}selected{/if}>3 Days</option>
                                <option value="7" {if $old.expiry_days == '7' || !$old.expiry_days}selected{/if}>1 Week (Default)</option>
                                <option value="14" {if $old.expiry_days == '14'}selected{/if}>2 Weeks</option>
                                <option value="30" {if $old.expiry_days == '30'}selected{/if}>1 Month</option>
                            </select>
                            {if $errors.expiry_days}
                                <div class="invalid-feedback">{$errors.expiry_days}</div>
                            {/if}
                            <div class="form-text">
                                <i class="fa fa-info-circle me-1"></i>How long the invitation will remain valid.
                            </div>
                        </div>

