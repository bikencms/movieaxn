<?php

use App\Http\Middleware\IfModifiedSince;
use App\Http\Middleware\LastModified;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::apiResource('homepage', 'Api\HomepageController')->only(['index'])->middleware([IfModifiedSince::class, LastModified::class]);
Route::apiResource('movies', 'Api\MovieController')->only(['show']);
Route::apiResource('tvshows', 'Api\TvshowController')->only(['index', 'show'])->middleware([IfModifiedSince::class, LastModified::class]);
Route::apiResource('episode', 'Api\EpisodeController')->only(['show']);
Route::get('search', 'App\Http\Controllers\Api\HomepageController@search')->middleware([IfModifiedSince::class, LastModified::class]);
Route::get('tvShowHomepage', 'App\Http\Controllers\Api\HomepageController@tvShowHomepage')->middleware([IfModifiedSince::class, LastModified::class]);
Route::get('movies', 'App\Http\Controllers\Api\MovieController@index')->middleware([IfModifiedSince::class, LastModified::class])->name('movies.index');
Route::get('/outlink', 'App\Http\Controllers\Api\OutlinkController@getOutlink');

Route::get('clearCache', 'App\Http\Controllers\Api\HomepageController@clearCache');
Route::get('clearCacheByKey/{key}', 'App\Http\Controllers\Api\HomepageController@clearCacheByKey');

Route::get('putGmtTime', 'App\Http\Controllers\Api\HomepageController@putGmtTime');


Route::get('getMovieTMDBId', 'App\Http\Controllers\Api\HomepageController@getMovieTMDBId')->name('movie.tmdb');
Route::get('getMovieLimit', 'App\Http\Controllers\Api\HomepageController@getMovieLimit');
Route::get('getTvshowTMDBId', 'App\Http\Controllers\Api\HomepageController@getTvshowTMDBId')->name('tvshow.tmdb');
Route::get('getTvshowLimit', 'App\Http\Controllers\Api\HomepageController@getTvshowLimit');
Route::get('insertPerson', 'App\Http\Controllers\Api\HomepageController@insertPerson');
Route::get('autoImportPerson', 'App\Http\Controllers\Api\HomepageController@autoImportPerson')->name('cast.import');

Route::get('cast/detail', 'App\Http\Controllers\Api\CastController@show');
Route::get('casts', 'App\Http\Controllers\Api\CastController@index')->name('casts');
Route::get('cast/makeCacheCast', 'App\Http\Controllers\Api\CastController@makeCacheCast');
Route::get('cast/checkCastOfMovie', 'App\Http\Controllers\Api\CastController@checkCastOfMovie');
Route::get('cast/checkCastOfTvShow', 'App\Http\Controllers\Api\CastController@checkCastOfTvShow');

Route::get('updated-activity', 'App\Http\Controllers\Api\HomepageController@updatedActivity');
Route::post('{token}/webhook', 'App\Http\Controllers\Api\HomepageController@handleWebhook');

Route::get('cast/updateCastOfMovie', 'App\Http\Controllers\Api\CastController@updateCastOfMovie');
Route::get('cast/updateCastOfTvShow', 'App\Http\Controllers\Api\CastController@updateCastOfTvShow');
