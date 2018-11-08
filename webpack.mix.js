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

mix.copy('node_modules/bootstrap/dist/css/bootstrap.min.css', 'public/assets/css/bootstrap.min.css')
    .copy('node_modules/bootstrap/dist/js/bootstrap.min.js', 'public/assets/js/bootstrap.min.js')
    .copy('node_modules/datatables/media/css/jquery.dataTables.min.css', 'public/assets/css/jquery.dataTables.min.css')
    .copy('node_modules/datatables/media/js/jquery.dataTables.min.js', 'public/assets/js/jquery.dataTables.min.js')
    .copy('node_modules/animate.css/animate.min.css', 'public/assets/css/animate.min.css')
    .copy('node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css', 'public/assets/css/bootstrap-datepicker.standalone.min.css')
    .copy('node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js', 'public/assets/js/bootstrap-datepicker.min.js')
    .copy('node_modules/autosize/dist/autosize.min.js', 'public/assets/js/autosize.min.js')
    .copy('node_modules/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js', 'public/assets/js/bootstrap-hover-dropdown.min.js')
    .copy('node_modules/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css', 'public/assets/css/bootstrap-progressbar-3.3.4.min.css')
    .copy('node_modules/bootstrap-progressbar/bootstrap-progressbar.min.js', 'public/assets/js/bootstrap-progressbar.min.js')
    .copy('node_modules/jquery/dist/jquery.min.js', 'public/assets/js/jquery.min.js')
    .copy('node_modules/jquery-migrate/dist/jquery-migrate.min.js', 'public/assets/js/jquery-migrate.min.js')
    .copy('node_modules/jquery.nicescroll/dist/jquery.nicescroll.min.js', 'public/assets/js/jquery.nicescroll.min.js')
    .copy('node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css', 'public/assets/css/svg-with-js.min.css')
    .copy('node_modules/@fortawesome/fontawesome-free/js/v4-shims.min.js', 'public/assets/js/v4-shims.min.js')
    .copy('node_modules/@fortawesome/fontawesome-free/js/all.min.js', 'public/assets/js/all.min.js')
    .copy('node_modules/@fancyapps/fancybox/dist/jquery.fancybox.min.js', 'public/assets/js/jquery.fancybox.min.js')
    .copy('node_modules/jquery-goup/dist/jquery.goup.min.js', 'public/assets/js/jquery.goup.min.js')
    .copy('node_modules/icheck/icheck.min.js', 'public/assets/js/icheck.min.js')
    .copy('node_modules/icheck/skins/square/green.css', 'public/assets/css/green.css')
    .copy('node_modules/pnotify/dist/pnotify.css', 'public/assets/css/pnotify.css')
    .copy('node_modules/pnotify/dist/pnotify.animate.js','public/assets/js/pnotify.animate.js')
    .copy('node_modules/pnotify/dist/pnotify.buttons.js', 'public/assets/js/pnotify.buttons.js')
    .copy('node_modules/pnotify/dist/pnotify.callbacks.js', 'public/assets/js/pnotify.callbacks.js')
    .copy('node_modules/pnotify/dist/pnotify.confirm.js', 'public/assets/js/pnotify.confirm.js')
    .copy('node_modules/pnotify/dist/pnotify.desktop.js', 'public/assets/js/pnotify.desktop.js')
    .copy('node_modules/pnotify/dist/pnotify.nonblock.js', 'public/assets/js/pnotify.nonblock.js')
    .copy('node_modules/pnotify/dist/pnotify.js', 'public/assets/js/pnotify.js')
    .copy('node_modules/tinymce/tinymce.min.js', 'public/assets/js/tinymce.min.js');

