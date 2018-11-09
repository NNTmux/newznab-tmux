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
    .copyDirectory('node_modules/tinymce/themes/', 'public/assets/js/themes/')
    .copyDirectory('node_modules/tinymce/skins/', 'public/assets/js/skins/')
    .copyDirectory('node_modules/tinymce/plugins/', 'public/assets/js/plugins/')
    .copy('node_modules/icheck/skins/flat/green.png', 'public/assets/css/green.png')
    .styles(
        [
            'node_modules/bootstrap/dist/css/bootstrap.min.css',
            'node_modules/icheck/skins/flat/green.css',
            'node_modules/datatables/media/css/jquery.dataTables.min.css',
            'node_modules/animate.css/animate.min.css',
            'node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css',
            'node_modules/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css',
            'node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css',
            'node_modules/@fancyapps/fancybox/dist/jquery.fancybox.min.css',
            'node_modules/pnotify/dist/pnotify.css',
            'node_modules/flexboxgrid2/flexboxgrid2.min.css',
            'node_modules/multiselect/css/multi-select.css',
            'node_modules/normalize.css/normalize.css',
            'resources/assets/css/custom.css'
    ], 'public/assets/css/all-css.css')
    .scripts(
        [
            'node_modules/jquery/dist/jquery.min.js',
            'node_modules/jquery-migrate/dist/jquery-migrate.min.js',
            'node_modules/bootstrap/dist/js/bootstrap.min.js',
            'node_modules/@fortawesome/fontawesome-free/js/all.min.js',
            'node_modules/@fortawesome/fontawesome-free/js/v4-shims.min.js',
            'node_modules/@fancyapps/fancybox/dist/jquery.fancybox.min.js',
            'node_modules/datatables/media/js/jquery.dataTables.min.js',
            'node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
            'node_modules/autosize/dist/autosize.min.js',
            'node_modules/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js',
            'node_modules/bootstrap-progressbar/bootstrap-progressbar.min.j',
            'node_modules/jquery.nicescroll/dist/jquery.nicescroll.min.js',
            'node_modules/pnotify/dist/pnotify.js',
            'node_modules/pnotify/dist/pnotify.animate.js',
            'node_modules/pnotify/dist/pnotify.buttons.js',
            'node_modules/pnotify/dist/pnotify.callbacks.js',
            'node_modules/pnotify/dist/pnotify.confirm.js',
            'node_modules/pnotify/dist/pnotify.desktop.js',
            'node_modules/pnotify/dist/pnotify.nonblock.js',
            'node_modules/pnotify/dist/pnotify.js',
            'node_modules/tinymce/tinymce.min.js',
            'node_modules/jquery-colorbox/jquery.colorbox-min.js',
            'node_modules/jquery-multifile/jquery.MultiFile.min.js',
            'node_modules/multiselect/js/jquery.multi-select.js',
            'node_modules/pace-js/pace.min.js',
            'node_modules/jquery-slimscroll/jquery.slimscroll.min.js',
            'node_modules/icheck/icheck.min.js',
            'node_modules/jquery-goup/dist/jquery.goup.min.js',
            'resources/assets/js/utils-admin.js',
            'resources/assets/js/custom.js',
            'resources/assets/js/functions.js'
        ]
    , 'public/assets/js/all-js.js')
    .sourceMaps();

