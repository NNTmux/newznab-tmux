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

Route::get('/login', function () {
    redirect('/login');
});

Route::post('/login', 'Auth\LoginController@login');

Route::get('/browse', function () {
    redirect('/browse');
});

Route::get('/console', function () {
    redirect('/console');
});

Route::get('/details/{id}', function () {
    redirect('/details');
});

Route::get('/games', function () {
    redirect('/games');
});

Route::get('/movies', function () {
    redirect('/movies');
});

Route::get('/browsegroup', function () {
    redirect('/browsegroup');
});

Route::get('/pc', function () {
    redirect('/pc');
});

Route::get('/music', function () {
    redirect('/music');
});

Route::get('/xxx', function () {
    redirect('/xxx');
});

Route::get('/admin/', function () {
    redirect('/admin/');
});

Route::get('/books', function () {
    redirect('/books');
});

Route::get('/contact-us', function () {
    redirect('/contact-us');
});

Route::get('/getnzb/{id}', function () {
    redirect('/getnzb');
});

Route::post('/contact-us', function () {
    redirect('/contact-us');
});

Route::get('/forum', function () {
    redirect('/forum');
});

Route::post('/forum', function () {
    redirect('/forum');
});

Route::get('/forumpost', function () {
    redirect('/forumpost');
});

Route::post('/forumpost', function () {
    redirect('/forumpost');
});

Route::get('/profileedit', function () {
    redirect('/profileedit');
});

Route::post('/profileedit', function () {
    redirect('/profileedit');
});

Route::get('/profile_delete', function () {
    redirect('/profile_delete');
});

Route::get('/profile', function () {
    redirect('/profile');
});

Route::get('/apihelp', function () {
    redirect('/apihelp');
});

Route::get('/api', function () {
    redirect('/api');
});

Route::post('/api', function () {
    redirect('/api');
});

Route::get('/search', function () {
    redirect('/search');
});

Route::post('/search/{id}', function () {
    redirect('/search');
});

Route::get('/rss', function () {
    redirect('/rss');
});

Route::post('/rss', function () {
    redirect('/rss');
});

Route::get('/logout', function () {
    Auth::logout();
});
