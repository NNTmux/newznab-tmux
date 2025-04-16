<!DOCTYPE html>
						<html lang="{{App::getLocale()}}">

						<head>
						    <meta charset="utf-8">
						    <meta name="viewport" content="width=device-width, initial-scale=1">
						    <meta name="csrf-token" content="{{csrf_token()}}">
						    <title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
						    {{Html::style("{{asset('/assets/css/all-css.css')}}")}}
						</head>

						<body class="nav-md">
						    <div class="container body">
						        <div class="main_container">
						            <!-- Left sidebar navigation -->
						            <div class="col-md-3 left_col">
						                <div class="left_col scroll-view">
						                    <!-- Mobile toggle button -->
						                    <button id="sidebar-toggle" class="d-md-none btn btn-primary position-absolute"
						                            style="right: 10px; top: 10px; z-index: 1000;" aria-label="Toggle sidebar">
						                        <i class="fa fa-bars" aria-hidden="true"></i>
						                    </button>

						                    <div class="navbar nav_title" style="border: 0;">
						                        <a href="{$site->home_link}" class="site_title">
						                            <div class="app-logo">
						                                <i class="fas fa-file-download" aria-hidden="true"></i>
						                            </div>
						                            <span>{{config('app.name')}} Admin</span>
						                        </a>
						                    </div>
						                    <div class="clearfix"></div>

						                    <!-- Empty space replacing profile section -->
						                    <div class="clearfix"></div>
						                    <div class="sidebar-spacer" style="height: 50px;"></div>

						                    <!-- sidebar menu -->
						                    {$admin_menu}
						                    <!-- /sidebar menu -->
						                </div>
						            </div>

						            <!-- top navigation -->
						            <div class="top_nav">
						                <div class="nav_menu">
						                    <div class="d-flex justify-content-between align-items-center w-100 px-3">
						                        <div class="nav-item">
						                            <a id="menu_toggle" class="d-none d-md-block" aria-label="Toggle menu">
						                                <i class="fa fa-bars"></i>
						                            </a>
						                        </div>
						                        <div class="d-flex align-items-center">
						                            {$page->head}
						                            <div class="nav-item dropdown ms-3">
						                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						                                    <span class="header-profile-img me-2">
						                                        <span class="header-initial img-circle d-flex align-items-center justify-content-center">
						                                            {{substr(Auth::user()->username, 0, 1)|upper}}
						                                        </span>
						                                    </span>
						                                    <span class="ms-1 d-none d-lg-inline-block">{{Auth::user()->username}}</span>
						                                </a>
						                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
						                                    <li><a class="dropdown-item" href="{{url("/profile")}}"><i class="fa fa-user me-2"></i>Profile</a></li>
						                                    <li><a class="dropdown-item" href="{{url("/admin/site-edit")}}"><i class="fa fa-cog me-2"></i>Site Settings</a></li>
						                                    <li><hr class="dropdown-divider"></li>
						                                    <li>
						                                        <a class="dropdown-item" href="{{ url('/logout') }}"
						                                           onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
						                                           <i class="fa fa-sign-out-alt me-2"></i>Logout
						                                        </a>
						                                    </li>
						                                </ul>
						                            </div>
						                        </div>
						                    </div>
						                </div>
						            </div>
						            <!-- /top navigation -->

						            <!-- page content -->
						            <div class="right_col" role="main">
						                <div class="clearfix"></div>
						                <div class="row">
						                    <div class="col-md-12 col-sm-12 col-12">
						                        {$notification}
						                        <div class="card">
						                            <div class="card-body">
						                                {$content}
						                            </div>
						                        </div>
						                        <div class="clearfix"></div>
						                    </div>
						                </div>
						            </div>
						            <!-- /page content -->

						            <!-- footer content -->
						            <footer class="mt-4 pt-3 pb-2 bg-light border-top">
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
						                                <li class="mb-2"><a href="{{url("/")}}" class="text-decoration-none">
						                                    <i class="fa fa-home me-1"></i>Back to Site</a></li>
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
						                                    <strong>Copyright &copy; {$smarty.now|date_format:"%Y"}
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
						            <!-- /footer content -->
						        </div>
						    </div>

						    <!-- jQuery and scripts -->
						    {{Html::script("{{asset("/assets/js/all-js.js")}}")}}

						    {{Form::open(['id' => 'frm-logout', 'route' => 'logout'])}}
						    {{Form::close()}}

						    <script>
						    {literal}
						        document.addEventListener('DOMContentLoaded', function() {
						            // Toggle sidebar on mobile
						            const sidebarToggle = document.getElementById('sidebar-toggle');
						            const sidebarContainer = document.getElementById('sidebar-container');
						            const leftCol = document.querySelector('.left_col');
						            const menuToggle = document.getElementById('menu_toggle');

						            if (sidebarToggle) {
						                sidebarToggle.addEventListener('click', function() {
						                    leftCol.classList.toggle('mobile-expanded');
						                    sidebarContainer.classList.toggle('d-none');
						                });
						            }

						            if (menuToggle) {
						                menuToggle.addEventListener('click', function() {
						                    document.querySelector('body').classList.toggle('nav-sm');
						                });
						            }

						            // Initial state setup based on screen size
						            function adjustSidebar() {
						                if (window.innerWidth < 768) {
						                    if (!leftCol.classList.contains('mobile-expanded')) {
						                        sidebarContainer.classList.add('d-none');
						                    }
						                } else {
						                    sidebarContainer.classList.remove('d-none');
						                }
						            }

						            // Initialize tooltips
						            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
						            tooltipTriggerList.map(function (tooltipTriggerEl) {
						                return new bootstrap.Tooltip(tooltipTriggerEl);
						            });

						            // Initial setup
						            adjustSidebar();

						            // Handle resize events
						            window.addEventListener('resize', function() {
						                clearTimeout(window.resizeTimer);
						                window.resizeTimer = setTimeout(adjustSidebar, 100);
						            });
						        });
						    {/literal}
						    </script>

						    <style>
						    {literal}
						        /* Overall styling improvements */
						        body {
						            background: #f8f9fa;
						        }

						        .card {
						            border-radius: 8px;
						            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
						            margin-bottom: 1.5rem;
						        }

						        .nav_title {
						            background: #2A3F54;
						            padding: 15px;
						        }

						        .site_title {
						            font-size: 1.2rem;
						            font-weight: 600;
						            letter-spacing: 0.5px;
						        }

						        /* Profile section styling */
						        .profile {
						            padding: 15px;
						            display: flex;
						            align-items: center;
						            background: rgba(0, 0, 0, 0.1);
						        }

						        .profile_pic {
						            width: 50px;
						            height: 50px;
						            overflow: hidden;
						            margin-right: 15px;
						        }

						        .profile_info {
						            flex: 1;
						        }

						        .profile_info span {
						            font-size: 0.8rem;
						            display: block;
						            color: rgba(255, 255, 255, 0.6);
						        }

						        .profile_info h2 {
						            font-size: 1rem;
						            margin: 0;
						            color: white;
						        }

						        /* Top navigation */
						        .top_nav {
						            background: white;
						            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
						        }

						        .nav_menu {
						            padding: 10px 0;
						        }

						        /* Footer styling */
						        footer {
						            background: white;
						            padding: 15px 0;
						            border-top: 1px solid #e9ecef;
						            margin-top: 20px;
						        }

						        /* Mobile styles */
						        @media (max-width: 767px) {
						            .left_col {
						                position: fixed;
						                z-index: 999;
						                width: 85%;
						                max-width: 300px;
						                height: 100vh;
						                transform: translateX(-100%);
						                transition: transform 0.3s ease;
						                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
						            }

						            .left_col.mobile-expanded {
						                transform: translateX(0);
						            }

						            .profile_info {
						                padding-right: 45px;
						            }

						            /* Add overlay when sidebar is open */
						            .left_col.mobile-expanded::after {
						                content: "";
						                position: fixed;
						                top: 0;
						                right: 0;
						                bottom: 0;
						                left: 300px;
						                background: rgba(0, 0, 0, 0.5);
						                z-index: -1;
						            }

						            /* Adjust spacing for mobile view */
						            .right_col {
						                padding-left: 15px;
						                padding-right: 15px;
						                margin-left: 0;
						                width: 100%;
						            }
						        }

						        /* Toggle and collapse animations */
						        .nav-md .container.body .right_col {
						            margin-left: 230px;
						            transition: margin-left 0.3s ease;
						        }

						        .nav-sm .container.body .right_col {
						            margin-left: 70px;
						            transition: margin-left 0.3s ease;
						        }

						        .nav-sm .navbar.nav_title {
						            width: 70px;
						        }

						        .nav-sm .navbar.nav_title a span {
						            display: none;
						        }

						        .nav-sm .navbar.nav_title a i {
						            font-size: 27px;
						        }

						        .nav-sm .menu-text {
						            display: none;
						        }

						        /* Sidebar hover effects */
						        .sidebar-submenu .list-group-item:hover {
						            background-color: rgba(255,255,255,.1) !important;
						            border-left: 3px solid #5294ff;
						        }

						        /* Active menu styling */
						        .sidebar-submenu .list-group-item.active {
						            background-color: rgba(255,255,255,.15) !important;
						            border-left: 3px solid #5294ff;
						        }

                                /* App logo styling */
                                .app-logo {
                                    background: linear-gradient(135deg, #4e54c8, #8f94fb);
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    width: 35px;
                                    height: 35px;
                                    border-radius: 8px;
                                    margin-right: 5px;
                                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                                    transition: all 0.3s ease;
                                }

                                .app-logo i {
                                    font-size: 18px;
                                    color: white;
                                }

                                .site_title:hover .app-logo {
                                    transform: rotate(5deg);
                                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                                }

                                .header-profile-img {
                                    position: relative;
                                    width: 24px;
                                    height: 24px;
                                    overflow: hidden;
                                }

                                .header-img, .header-initial {
                                    width: 24px;
                                    height: 24px;
                                    border-radius: 50% !important;
                                    object-fit: cover;
                                    border: 1px solid rgba(255, 255, 255, 0.3);
                                }

                                .header-initial {
                                    background: linear-gradient(135deg, #4a89dc, #5d9cec);
                                    color: white;
                                    font-weight: bold;
                                    font-size: 12px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    text-transform: uppercase;
                                }
						    {/literal}
						    </style>
						</body>
						</html>
