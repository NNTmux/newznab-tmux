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

Auth::routes();

Route::get('/', function () {
    redirect('/');
});

Route::get('/browse', function () {
    redirect('/browse');
});

Route::get('/browse/{$group}', function () {
    redirect('/browse');
});

Route::get('/browsegroup', function () {
    redirect('/browsegroup');
});

Route::get('/cart', function () {
    redirect('/cart');
});

Route::get('/logout', function () {
    auth()->logout();
});

Route::get('/forum', function () {
    redirect('/forum');
});

Route::get('/profile', function () {
    redirect('/profile');
});

Route::get('/movies', function () {
    redirect('/movies');
});

Route::get('/details/{id}', function () {
    redirect('/details');
});

Route::get('/home', 'HomeController@index')->name('home');

