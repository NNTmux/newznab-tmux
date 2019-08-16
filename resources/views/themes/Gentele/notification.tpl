
{if Illuminate\Support\Facades\Session::has('success')}
    <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        {Illuminate\Support\Facades\Session::get('success')}
    </div>
{/if}
{if Illuminate\Support\Facades\Session::has('error')}
    <div class="alert alert-error alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        {Illuminate\Support\Facades\Session::get('error')}
    </div>
{/if}
{if Illuminate\Support\Facades\Session::has('info')}
    <div class="alert alert-info alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        {Illuminate\Support\Facades\Session::get('info')}
    </div>
{/if}
{if Illuminate\Support\Facades\Session::has('warning')}
    <div class="alert alert-warning alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        {Illuminate\Support\Facades\Session::get('warning')}
    </div>
{/if}
{if Illuminate\Support\Facades\Session::has('danger')}
    <div class="alert alert-danger alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
        {Illuminate\Support\Facades\Session::get('danger')}
    </div>
{/if}
