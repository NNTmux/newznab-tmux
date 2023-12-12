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
        <div class="col-md-3 left_col">
            <div class="left_col scroll-view">
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
                <br/>
                <div class="clearfix"></div>
                <!-- sidebar menu -->
                <div id="sidebar-container" class="sidebar-expanded d-none d-md-block">
                    <ul class="list-group">
                        <li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
                            <small>MAIN MENU</small>
                        </li>
                        {if Auth::check()}
                            {$sidebar}
                        {else}
                            <a href="{{route('login')}}"
                               class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                                <span class="fa fa-lock mr-3"></span>
                                <span>Sign In</span>
                            </a>
                        {/if}
                        <a href="{{route('contact-us')}}"
                           class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                            <span class="fa fa-envelope-open-text mr-3"></span>
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

<!-- jQuery 3.3.0 -->
{{Html::script("{{asset("/assets/js/all-js.js")}}")}}


<script>
    jQuery(document).ready(function () {
        jQuery.goup({
            containerColor: "#8bc5c5",
            arrowColor: "#fdf8f8",
            bottomOffset: 100,
            goupSpeed: "normal",
            title: "Scroll to top"
        });
    });
</script>

{{Form::open(['id' => 'frm-logout', 'route' => 'logout'])}}
{{Form::close()}}

</body>

</html>
