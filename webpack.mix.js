const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix
    .copy('node_modules/bootstrap/dist/css/bootstrap.min.css', 'resources/assets/css/bootstrap.min.css')
    .copy('node_modules/bootstrap/dist/css/bootstrap.min.css.map', 'resources/assets/css/bootstrap.min.css.map')
    .copy('node_modules/bootstrap/dist/js/bootstrap.min.js', 'resources/assets/js/bootstrap.min.js')
    .copy('node_modules/datatables/media/css/jquery.dataTables.min.css', 'resources/assets/css/jquery.dataTables.min.css')
    .copy('node_modules/datatables/media/js/jquery.dataTables.min.js', 'resources/assets/js/jquery.dataTables.min.js')
    .copy('node_modules/animate.css/animate.min.css', 'resources/assets/css/animate.min.css')
    .copy('node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css', 'resources/assets/css/bootstrap-datepicker.standalone.min.css')
    .copy('node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js', 'resources/assets/js/bootstrap-datepicker.min.js')
    .copy('node_modules/autosize/dist/autosize.min.js', 'resources/assets/js/autosize.min.js')
    .copy('node_modules/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js', 'resources/assets/js/bootstrap-hover-dropdown.min.js')
    .copy('node_modules/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css', 'resources/assets/css/bootstrap-progressbar-3.3.4.min.css')
    .copy('node_modules/bootstrap-progressbar/bootstrap-progressbar.min.js', 'resources/assets/js/bootstrap-progressbar.min.js')
    .copy('node_modules/jquery/dist/jquery.min.js', 'resources/assets/js/jquery.min.js')
    .copy('node_modules/jquery-migrate/dist/jquery-migrate.min.js', 'resources/assets/js/jquery-migrate.min.js')
    .copy('node_modules/jquery.nicescroll/dist/jquery.nicescroll.min.js', 'resources/assets/js/jquery.nicescroll.min.js')
    .copy('node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css', 'resources/assets/css/svg-with-js.min.css')
    .copy('node_modules/@fortawesome/fontawesome-free/js/v4-shims.min.js', 'resources/assets/js/v4-shims.min.js')
    .copy('node_modules/@fortawesome/fontawesome-free/js/all.min.js', 'resources/assets/js/all.min.js')
    .copy('node_modules/@fancyapps/fancybox/dist/jquery.fancybox.min.css', 'resources/assets/css/jquery.fancybox.min.css')
    .copy('node_modules/@fancyapps/fancybox/dist/jquery.fancybox.min.js', 'resources/assets/js/jquery.fancybox.min.js')
    .copy('node_modules/jquery-goup/dist/jquery.goup.min.js', 'resources/assets/js/jquery.goup.min.js')
    .copy('node_modules/icheck/icheck.min.js', 'resources/assets/js/icheck.min.js')
    .copyDirectory('node_modules/icheck/skins/', 'public/assets/css/skins')
    .copy('node_modules/pnotify/dist/pnotify.css', 'resources/assets/css/pnotify.css')
    .copy('node_modules/pnotify/dist/pnotify.animate.js','resources/assets/js/pnotify.animate.js')
    .copy('node_modules/pnotify/dist/pnotify.animate.js.map','resources/assets/js/pnotify.animate.js.map')
    .copy('node_modules/pnotify/dist/pnotify.buttons.js', 'resources/assets/js/pnotify.buttons.js')
    .copy('node_modules/pnotify/dist/pnotify.buttons.js.map', 'resources/assets/js/pnotify.buttons.js.map')
    .copy('node_modules/pnotify/dist/pnotify.callbacks.js', 'resources/assets/js/pnotify.callbacks.js')
    .copy('node_modules/pnotify/dist/pnotify.callbacks.js.map', 'resources/assets/js/pnotify.callbacks.js.map')
    .copy('node_modules/pnotify/dist/pnotify.confirm.js', 'resources/assets/js/pnotify.confirm.js')
    .copy('node_modules/pnotify/dist/pnotify.confirm.js.map', 'resources/assets/js/pnotify.confirm.js.map')
    .copy('node_modules/pnotify/dist/pnotify.desktop.js', 'resources/assets/js/pnotify.desktop.js')
    .copy('node_modules/pnotify/dist/pnotify.desktop.js.map', 'resources/assets/js/pnotify.desktop.js.map')
    .copy('node_modules/pnotify/dist/pnotify.nonblock.js', 'resources/assets/js/pnotify.nonblock.js')
    .copy('node_modules/pnotify/dist/pnotify.nonblock.js.map', 'resources/assets/js/pnotify.nonblock.js.map')
    .copy('node_modules/pnotify/dist/pnotify.js', 'resources/assets/js/pnotify.js')
    .copy('node_modules/pnotify/dist/pnotify.js.map', 'resources/assets/js/pnotify.js.map')
    .copy('node_modules/tinymce/tinymce.min.js', 'resources/assets/js/tinymce.min.js')
    .copyDirectory('node_modules/tinymce/themes/', 'public/assets/js/themes/')
    .copyDirectory('node_modules/tinymce/skins/', 'public/assets/js/skins/')
    .copyDirectory('node_modules/tinymce/plugins/', 'public/assets/js/plugins/')
    .copy('node_modules/jquery-colorbox/jquery.colorbox-min.js', 'resources/assets/js/jquery.colorbox-min.js')
    .copy('node_modules/flexboxgrid2/flexboxgrid2.min.css', 'resources/assets/css/flexboxgrid2.min.css')
    .copy('node_modules/jquery-multifile/jquery.MultiFile.min.js', 'resources/assets/js/jquery.MultiFile.min.js')
    .copy('node_modules/multiselect/css/multi-select.css', 'resources/assets/css/multi-select.css')
    .copy('node_modules/multiselect/js/jquery.multi-select.js', 'resources/assets/js/jquery.multi-select.js')
    .copy('node_modules/pace-js/pace.min.js', 'resources/assets/js/pace.min.js')
    .copy('node_modules/jquery-slimscroll/jquery.slimscroll.min.js', 'resources/assets/js/jquery.slimscroll.min.js')
    .styles(
        [
            'resources/assets/css/bootstrap.min.css',
            'resources/assets/css/green.css',
            'resources/assets/css/jquery.dataTables.min.css',
            'resources/assets/css/animate.min.css',
            'resources/assets/css/bootstrap-datepicker.standalone.min.css',
            'resources/assets/css/bootstrap-progressbar-3.3.4.min.css',
            'resources/assets/css/svg-with-js.min.css',
            'resources/assets/css/jquery.fancybox.min.css',
            'resources/assets/css/pnotify.css',
            'resources/assets/css/flexboxgrid2.min.css',
            'resources/assets/css/multi-select.css',
            'resources/assets/css/normalize.css',
            'resources/assets/css/custom.css'
    ], 'public/assets/css/all-css.css')
    .scripts(
        [
            'resources/assets/js/jquery.min.js',
            'resources/assets/js/jquery-migrate.min.js',
            'resources/assets/js/bootstrap.min.js',
            'resources/assets/js/all.min.js',
            'resources/assets/js/v4-shims.min.js',
            'resources/assets/js/jquery.dataTables.min.js',
            'resources/assets/js/bootstrap-datepicker.min.js',
            'resources/assets/js/autosize.min.js',
            'resources/assets/js/bootstrap-hover-dropdown.min.js',
            'resources/assets/js/bootstrap-progressbar.min.js',
            'resources/assets/js/jquery.nicescroll.min.js',
            'resources/assets/js/pnotify.js',
            'resources/assets/js/pnotify.animate.js',
            'resources/assets/js/pnotify.buttons.js',
            'resources/assets/js/pnotify.callbacks.js',
            'resources/assets/js/pnotify.confirm.js',
            'resources/assets/js/pnotify.desktop.js',
            'resources/assets/js/pnotify.nonblock.js',
            'resources/assets/js/tinymce.min.js',
            'resources/assets/js/jquery.colorbox-min.js',
            'resources/assets/js/jquery.MultiFile.min.js',
            'resources/assets/js/jquery.multi-select.js',
            'resources/assets/js/pace.min.js',
            'resources/assets/js/jquery.slimscroll.min.js',
            'resources/assets/js/icheck.min.js',
            'resources/assets/js/jquery.goup.min.js',
            'resources/assets/js/utils-admin.js',
            'resources/assets/js/custom.js',
            'resources/assets/js/functions.js'
        ]
    , 'public/assets/js/all-js.js')
    .sourceMaps();

