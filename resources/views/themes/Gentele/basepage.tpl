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
                                            <span>{{config('app.name')}}</span>
                                        </a>
                                    </div>
                                    <div class="clearfix"></div>

                                    <!-- sidebar menu -->
                                    <div id="sidebar-container" class="sidebar-expanded d-md-block">
                                        <ul class="list-group">
                                            <li class="bg-transparent list-group-item sidebar-separator-title text-white d-flex align-items-center menu-collapsed">
                                                <small>MAIN MENU</small>
                                            </li>
                                            {if Auth::check()}
                                                {$sidebar}
                                            {else}
                                                <a href="{{route('login')}}"
                                                   class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                                                    <span class="fa fa-lock me-3"></span>
                                                    <span>Sign In</span>
                                                </a>
                                            {/if}
                                            <a href="{{route('contact-us')}}"
                                               class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                                                <span class="fa fa-envelope-open-text me-3"></span>
                                                <span>Contact</span>
                                            </a>
                                        </ul>
                                    </div>
                                    <!-- /sidebar menu -->
                                </div>
                            </div>

                            <!-- top navigation -->
                            {if Auth::check()}
                                <div class="top_nav">
                                    <div class="nav_menu">
                                        {$header_menu}
                                    </div>
                                </div>
                            {/if}
                            <!-- /top navigation -->

                            <!-- page content -->
                            <div class="right_col" role="main">
                                <div class="clearfix"></div>
                                <div class="row">
                                    <div class="col-md-12 col-sm-12 col-12">
                                        {$notification}
                                        {$content}
                                        <div class="clearfix"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- /page content -->
                        </div>
                    </div>

                    <!-- footer content -->
                    {$footer}
                    <!-- /footer content -->

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

                            if (sidebarToggle) {
                                sidebarToggle.addEventListener('click', function() {
                                    leftCol.classList.toggle('mobile-expanded');
                                    sidebarContainer.classList.toggle('d-none');
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
                        /* Mobile styles only - large screens remain unchanged */
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

                            /* Bootstrap 5 spacing utilities */
                            .me-3 {
                                margin-right: 1rem !important;
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
                    /* Improved app logo styling */
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
                    {/literal}
                    </style>
                </body>
                </html>
