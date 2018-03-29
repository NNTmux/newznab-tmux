<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('themes.Gentele.basepage');
});

/*
|
| We need to allow the post route to '/'
| as admin area needs the permission
|
*/
Route::post('/', function () {
    redirect('/');
})->middleware('auth');

Auth::routes();

Route::get('/login', 'Auth\LoginController@showLoginForm');
Route::post('/login', 'Auth\LoginController@login');

Route::get('/register', 'Auth\RegisterController@showRegistrationForm');
Route::post('/register', 'Auth\RegisterController@register');

Route::get('/forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');
Route::post('/forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');

Route::get('/resetpassword', 'Auth\ResetPasswordController@reset');
Route::post('/resetpassword', 'Auth\ResetPasswordController@reset');

Route::get('/profile', 'ProfileController@show')->middleware('auth');

Route::get('/browse', function () {
    redirect('/browse');
})->middleware('auth');

Route::get('/console', function () {
    redirect('/console');
})->middleware('auth');

Route::get('/details/{id}', function () {
    redirect('/details');
})->middleware('auth');

Route::get('/games', function () {
    redirect('/games');
})->middleware('auth');

Route::get('/movies', function () {
    redirect('/movies');
})->middleware('auth');

Route::get('/browsegroup', function () {
    redirect('/browsegroup');
})->middleware('auth');

Route::get('/pc', function () {
    redirect('/pc');
})->middleware('auth');

Route::get('/music', function () {
    redirect('/music');
})->middleware('auth');

Route::get('/xxx', function () {
    redirect('/xxx');
})->middleware('auth');

Route::get('/books', function () {
    redirect('/books');
})->middleware('auth');

Route::get('/contact-us', function () {
    redirect('/contact-us');
})->middleware('guest');

Route::get('/getnzb/{id}', function () {
    redirect('/getnzb');
})->middleware('auth', 'api');

Route::post('/contact-us', function () {
    redirect('/contact-us');
})->middleware('guest');

Route::get('/forum', function () {
    redirect('/forum');
})->middleware('auth');

Route::post('/forum', function () {
    redirect('/forum');
})->middleware('auth');

Route::get('/forumpost/{id}', function () {
    redirect('/forumpost');
})->middleware('auth');

Route::post('/forumpost/{id}', function () {
    redirect('/forumpost');
})->middleware('auth');

Route::get('/profileedit', function () {
    redirect('/profileedit');
})->middleware('auth');

Route::post('/profileedit', function () {
    redirect('/profileedit');
})->middleware('auth');

Route::get('/profile_delete', function () {
    redirect('/profile_delete');
})->middleware('auth');

Route::post('/profile_delete', function () {
    redirect('/login');
})->middleware('guest');

Route::get('/profile', 'ProfileController@show')->middleware('auth');

Route::get('/apihelp', function () {
    redirect('/apihelp');
})->middleware('auth');

Route::get('/api', function () {
    redirect('/api');
})->middleware('api');

Route::post('/api', function () {
    redirect('/api');
})->middleware('api');

Route::get('/search', function () {
    redirect('/search');
})->middleware('auth');

Route::get('/cart', function () {
    redirect('/cart');
})->middleware('auth');

Route::post('/cart', function () {
    redirect('/cart');
})->middleware('auth');

Route::post('/search/{id}', function () {
    redirect('/search');
})->middleware('auth', 'api');

Route::get('/filelist/{id}', function () {
    redirect('/filelist');
})->middleware('auth');

Route::get('/btc_payment', function () {
    redirect('/btc_payment');
})->middleware('auth');

Route::post('/btc_payment', function () {
    redirect('/btc_payment');
})->middleware('auth');

Route::get('/btc_payment_callback', function () {
    redirect('/btc_payment_callback');
});

Route::post('/btc_payment_callback', function () {
    redirect('/btc_payment_callback');
});

Route::get('/rss', function () {
    redirect('/rss');
})->middleware('api');

Route::post('/rss', function () {
    redirect('/rss');
})->middleware('api');

Route::get('/logout', 'Auth\LoginController@logout')->name('logout');
