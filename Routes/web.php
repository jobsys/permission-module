<?php

use Illuminate\Support\Facades\Route;

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

$route_prefix = config('module.Permission.route_prefix', 'manager');
$route_url_prefix = $route_prefix ? $route_prefix . '/' : '';
$route_name_prefix = $route_prefix ? $route_prefix . '.' : '';

Route::prefix("{$route_url_prefix}permission")->name("page.{$route_name_prefix}permission.")->group(function () {
    Route::get('/role', 'RoleController@pageRole')->name('role');
});
