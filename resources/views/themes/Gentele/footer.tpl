<footer class="mt-5 pt-4 pb-3 bg-light border-top">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <h5 class="mb-3 fw-bold">{{config('app.name')}}</h5>
                                    <p class="text-muted">Your trusted source for Usenet indexing and search services.</p>
                                    <div class="social-links mt-3">
                                        <a href="https://github.com/NNTmux/newznab-tmux" class="me-2 text-dark" title="GitHub">
                                            <i class="fab fa-github fa-lg"></i>
                                        </a>
                                        <a href="{{route('contact-us')}}" class="me-2 text-dark" title="Contact Us">
                                            <i class="fas fa-envelope fa-lg"></i>
                                        </a>
                                        <a href="{{url('/rss')}}" class="me-2 text-dark" title="RSS Feeds">
                                            <i class="fas fa-rss fa-lg"></i>
                                        </a>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <h5 class="mb-3 fw-bold">Quick Links</h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><a href="{{url('/')}}" class="text-decoration-none">Home</a></li>
                                        <li class="mb-2"><a href="{{url('/browse/all')}}" class="text-decoration-none">Browse</a></li>
                                        <li class="mb-2"><a href="{{route('search')}}" class="text-decoration-none">Search</a></li>
                                        <li class="mb-2"><a href="{{route('contact-us')}}" class="text-decoration-none">Contact</a></li>
                                    </ul>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <h5 class="mb-3 fw-bold">Resources</h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><a href="{{url('/terms-and-conditions')}}" class="text-decoration-none">Terms & Conditions</a></li>
                                        <li class="mb-2"><a href="{{url('/privacy-policy')}}" class="text-decoration-none">Privacy Policy</a></li>
                                        <li class="mb-2"><a href="https://github.com/NNTmux/newznab-tmux/issues" class="text-decoration-none">Report Issues</a></li>
                                        <li class="mb-2"><a href="https://github.com/NNTmux/newznab-tmux/wiki" class="text-decoration-none">Documentation</a></li>
                                    </ul>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="copyright-info">
                                        <span class="text-muted">
                                            <strong>Copyright &copy; {{Illuminate\Support\Carbon::now()->year}}
                                                <a href="https://github.com/NNTmux/newznab-tmux" class="text-decoration-none">NNTmux</a>
                                                <i class="fab fa-github-alt"></i>
                                            </strong>
                                        </span>
                                        <span class="mx-2 text-muted">|</span>
                                        <span class="text-muted">
                                            This software is open source, released under the GPL license, proudly powered by
                                            <i class="fab fa-laravel"></i>
                                            <a href="https://github.com/laravel/framework/" class="text-decoration-none">Laravel</a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </footer>
