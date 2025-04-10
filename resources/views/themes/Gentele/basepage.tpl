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
        <div class="col-md-3 col-sm-12 left_col">
            <div class="left_col scroll-view">
                <!-- Mobile toggle button -->
                <button id="sidebar-toggle" class="d-md-none btn btn-primary position-absolute"
                        style="right: 10px; top: 10px; z-index: 1000;">
                    <i class="fa fa-bars"></i>
                </button>

                <div class="navbar nav_title" style="border: 0;">
                    <a href="{$site->home_link}" class="site_title"><i class="fab fa-phoenix-framework"></i>
                        <span>{{config('app.name')}}</span></a>
                </div>
                <div class="clearfix"></div>

                <!-- menu profile quick info -->
                {if Auth::check()}
                    <div class="profile">
                        <div class="profile_pic">
                            <img src="{{asset('/assets/images/userimage.png')}}" alt="User Image"
                                 class="img-circle profile_img">
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
                        <li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
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
            </div>
        </div>

        <script>
        {literal}
            document.addEventListener('DOMContentLoaded', function() {
                // Toggle sidebar on mobile
                const sidebarToggle = document.getElementById('sidebar-toggle');
                const sidebarContainer = document.getElementById('sidebar-container');
                const leftCol = document.querySelector('.left_col');

                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', function() {
                        if (sidebarContainer.classList.contains('d-none')) {
                            sidebarContainer.classList.remove('d-none');
                            leftCol.classList.add('mobile-expanded');
                        } else {
                            sidebarContainer.classList.add('d-none');
                            leftCol.classList.remove('mobile-expanded');
                        }
                    });
                }

                // Hide sidebar initially on small screens
                if (window.innerWidth < 768) {
                    sidebarContainer.classList.add('d-none');
                }

                // Handle resize events
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        sidebarContainer.classList.remove('d-none');
                    } else if (!leftCol.classList.contains('mobile-expanded')) {
                        sidebarContainer.classList.add('d-none');
                    }
                });
            });
        {/literal}
        </script>

        <style>
        {literal}
            @media (max-width: 767px) {
                .left_col {
                    position: fixed;
                    z-index: 999;
                    width: 80%;
                    max-width: 300px;
                    height: 100vh;
                    transform: translateX(-100%);
                    transition: transform 0.3s ease;
                }

                .left_col.mobile-expanded {
                    transform: translateX(0);
                }

                .profile_info {
                    padding-right: 45px;
                }

                /* Update mr-3 to me-3 for newer Bootstrap */
                .me-3 {
                    margin-right: 1rem !important;
                }
            }
        {/literal}
        </style>
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

<!-- jQuery 3.3.0 -->
{{Html::script("{{asset("/assets/js/all-js.js")}}")}}

{{Form::open(['id' => 'frm-logout', 'route' => 'logout'])}}
{{Form::close()}}

</body>

</html>
