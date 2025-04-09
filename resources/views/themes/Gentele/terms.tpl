<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-10">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h4 class="mb-0">Terms and Conditions</h4>
                    <div class="breadcrumb-wrapper mt-2">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 bg-transparent p-0">
                                <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                <li class="breadcrumb-item active">Terms and Conditions</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="alert alert-info d-flex align-items-center mb-4">
                        <i class="fas fa-info-circle me-3 fa-lg"></i>
                        <div>
                            Please read our terms and conditions carefully. By using our services, you agree to be bound by these terms.
                        </div>
                    </div>

                    <div class="terms-content">
                        {$site->tandc}
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <div class="text-center">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <span>Last updated: {$smarty.now|date_format:"%B %e, %Y"}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
