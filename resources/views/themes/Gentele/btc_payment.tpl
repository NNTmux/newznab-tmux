<div class="container">
                        <div class="row justify-content-center mt-5">
                            <div class="col-md-8">
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h4 class="mb-0">Bitcoin Payment</h4>
                                        <div class="breadcrumb-wrapper mt-2">
                                            <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0 bg-transparent p-0">
                                                    <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                                    <li class="breadcrumb-item"><a href="{{url("/profile")}}">Profile</a></li>
                                                    <li class="breadcrumb-item active">Bitcoin Payment</li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>

                                    <div class="card-body p-4">
                                        <div class="alert alert-info d-flex align-items-center mb-4">
                                            <i class="fas fa-info-circle me-3 fa-2x"></i>
                                            <div>
                                                <p class="mb-1">This page will redirect you to a site outside of {{config('app.name')}} to make your payment.</p>
                                                <p class="mb-0">If, for some reason, your account isn't updated automatically, please send us an email or use our <a href="{{route('contact-us')}}">contact form</a> to inform us so we can fix the issue.</p>
                                            </div>
                                        </div>

                                        <h5 class="mb-4">Select a Donation Option</h5>

                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Membership Level</th>
                                                        <th>Price</th>
                                                        <th>Duration</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {foreach $donation as $donate}
                                                        <tr>
                                                            <td>
                                                                <span class="fw-semibold">{$donate->name}</span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-success">${$donate->donation}</span>
                                                            </td>
                                                            <td>
                                                                <span>{$donate->addyears} {if $donate->addyears == 1}Year{else}Years{/if}</span>
                                                            </td>
                                                            <td class="text-end">
                                                                {{Form::open(['url' => 'btc_payment?action=submit'])}}
                                                                    {{Form::hidden('price', {$donate->donation})}}
                                                                    {{Form::hidden('role', {$donate->id})}}
                                                                    {{Form::hidden('rolename', {$donate->name})}}
                                                                    {{Form::hidden('addyears', {$donate->addyears})}}
                                                                    <button type="submit" class="btn btn-primary">
                                                                        <i class="fab fa-bitcoin me-2"></i>Pay with Bitcoin
                                                                    </button>
                                                                {{Form::close()}}
                                                            </td>
                                                        </tr>
                                                    {/foreach}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="card-footer bg-light">
                                        <div class="text-center">
                                            <i class="fas fa-lock me-1"></i>
                                            <span>All transactions are secure and encrypted</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
