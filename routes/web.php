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
    redirect('/');
});

Auth::routes();

Route::get('/login', function () {
    redirect('/login');
})->middleware('guest');

Route::post('/login', 'Auth\LoginController@login');

Route::get('/register', function () {
    redirect('/register');
})->middleware('guest');

Route::post('/register', function () {
    redirect('/register');
})->middleware('guest');


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

Route::get('/forumpost', function () {
    redirect('/forumpost');
})->middleware('auth');

Route::post('/forumpost', function () {
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
    redirect('/profile_delete');
})->middleware('auth');

Route::get('/profile', function () {
    redirect('/profile');
})->middleware('auth');

Route::get('/apihelp', function () {
    redirect('/apihelp');
})->middleware('auth');

Route::get('/api', function () {
    redirect('/api');
})->middleware('auth', 'api');

Route::post('/api', function () {
    redirect('/api');
})->middleware('auth', 'api');

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

Route::get('/rss', function () {
    redirect('/rss');
})->middleware('auth', 'api');

Route::post('/rss', function () {
    redirect('/rss');
})->middleware('auth', 'api');

Route::get('/logout', 'Auth\LoginController@logout')->name('logout' );
