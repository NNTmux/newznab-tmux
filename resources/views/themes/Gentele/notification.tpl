<div class="container-fluid">
        {if Session::has('success')}
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-check-circle me-2"></i>
                    <div>{Session::get('success')}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {/if}

        {if Session::has('error')}
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-times-circle me-2"></i>
                    <div>{Session::get('error')}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {/if}

        {if Session::has('info')}
            <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-info-circle me-2"></i>
                    <div>{Session::get('info')}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {/if}

        {if Session::has('warning')}
            <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    <div>{Session::get('warning')}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {/if}

        {if Session::has('danger')}
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-exclamation-circle me-2"></i>
                    <div>{Session::get('danger')}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {/if}
    </div>

    <style>
    .alert {
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        position: relative;
        padding: 0.75rem 1.25rem;
    }

    .alert .btn-close {
        position: absolute;
        top: 0.75rem;
        right: 1rem;
        padding: 0.25rem;
        opacity: 0.8;
    }

    .alert-dismissible {
        padding-right: 4rem;
    }

    .alert.fade {
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>

    <script>
    {literal}
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }, 5000);
            });
        });
    {/literal}
    </script>
