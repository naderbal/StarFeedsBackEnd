<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/api/test', 'apiController@testFacebook');
Route::get('/api', 'apiController@getPosts');
Route::get('/api/celebs', 'apiController@getCelebs');
Route::get('/api/twitter', 'apiController@makeTwitterCall');
Route::get('/api/save','apiController@saveFeedsToDatabase');
Route::get('/api/feeds','apiController@getFeeds');
Route::get('/api/userFeeds/{id1}/','apiController@getUserFeeds');
Route::get('/api/join','apiController@join');
Route::get('/api/testTwitter','apiController@testTwitter');

Route::post('/api/postExample','apiController@testPost');
Route::post('/api/followCeleb','apiController@followCeleb');

Route::get('/web/feeds','apiController@getWebFeeds');
