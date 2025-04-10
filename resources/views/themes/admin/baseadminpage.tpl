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
						                            <i class="fab fa-phoenix-framework" aria-hidden="true"></i>
						                            <span>{{config('app.name')}} Admin</span>
						                        </a>
						                    </div>
						                    <div class="clearfix"></div>

						                    <!-- Admin profile info -->
						                    {if Auth::check()}
						                        <div class="profile">
						                            <div class="profile_pic">
						                                <img src="{{asset('/assets/images/userimage.png')}}" alt="Admin Profile"
						                                     class="img-circle profile_img">
						                            </div>
						                            <div class="profile_info">
						                                <span>Welcome,</span>
						                                <h2 class="text-break">{{Auth::user()->username}}</h2>
						                            </div>
						                        </div>
						                    {/if}
						                    <!-- /profile info -->
						                    <div class="clearfix"></div>

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
						                                    <img src="{{asset('/assets/images/userimage.png')}}" alt="Admin Profile" class="img-circle" width="30">
						                                    <span class="ms-2 d-none d-lg-inline-block">{{Auth::user()->username}}</span>
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
						            <footer>
						                <div class="container">
						                    <div class="row">
						                        <div class="col-md-6">
						                            <strong>Copyright &copy; {$smarty.now|date_format:"%Y"}
						                                <a href="https://github.com/NNTmux/" class="text-decoration-none">newznab-tmux</a>.
						                            </strong>
						                            <span class="d-block d-md-inline-block mt-1 mt-md-0">This software is open source, released under the GPL license</span>
						                        </div>
						                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
						                            <a href="{{url("/")}}" class="btn btn-sm btn-outline-secondary me-2">
						                                <i class="fa fa-home me-1"></i>Back to Site
						                            </a>
						                            <a href="https://github.com/NNTmux/newznab-tmux/issues" target="_blank" class="btn btn-sm btn-outline-secondary">
						                                <i class="fab fa-github me-1"></i>Report Issue
						                            </a>
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
						    {/literal}
						    </style>
						</body>
						</html>
