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
                                        <span>{{config('app.name')}}</span>
                                    </a>
                                </div>
                                <div class="clearfix"></div>

                                <!-- menu profile quick info -->
                                {if Auth::check()}
                                    <div class="profile">
                                        <div class="profile_pic">
                                            {if isset(Auth::user()->avatar) && Auth::user()->avatar}
                                                <img src="{{Auth::user()->avatar}}" alt="{{Auth::user()->username}}"
                                                     class="img-circle profile_img">
                                            {elseif isset(Auth::user()->email)}
                                                <img src="https://www.gravatar.com/avatar/{{md5(strtolower(trim(Auth::user()->email)))}}.jpg?s=200&d=mp"
                                                     alt="{{Auth::user()->username}}" class="img-circle profile_img">
                                            {else}
                                                <div class="profile_initial img-circle profile_img d-flex align-items-center justify-content-center">
                                                    {{substr(Auth::user()->username, 0, 1)}}
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="profile_info">
                                            <span>Welcome,</span>
                                            <h2 class="text-break">{{Auth::user()->username}}</h2>
                                        </div>
                                    </div>
                                {/if}
                                <!-- /menu profile quick info -->
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

                        .profile_info {
                            padding-right: 45px;
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

                        /* Improve smaller screens layout */
                        .profile_pic {
                            width: 40px;
                            height: 40px;
                        }

                        .profile_info h2 {
                            font-size: 1rem;
                        }
                    }
                /* Profile image enhancements */
                .profile_pic {
                    position: relative;
                    overflow: hidden;
                }

                .profile_img {
                    width: 50px;
                    height: 50px;
                    object-fit: cover;
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                    transition: all 0.3s ease;
                }

                .profile_img:hover {
                    border-color: rgba(255, 255, 255, 0.6);
                    transform: scale(1.05);
                }

                .profile_initial {
                    background: linear-gradient(135deg, #4a89dc, #5d9cec);
                    color: white;
                    font-weight: bold;
                    font-size: 20px;
                    width: 50px;
                    height: 50px;
                    text-transform: uppercase;
                }

                @media (max-width: 767px) {
                    .profile_img, .profile_initial {
                        width: 40px;
                        height: 40px;
                        font-size: 16px;
                    }
                }
                .img-circle,
                .profile_img {
                    border-radius: 50% !important;
                    overflow: hidden;
                }

                .profile_initial {
                    border-radius: 50% !important;
                }
                {/literal}
                </style>
            </body>
            </html>
