<?php

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

$route_prefix = config('permission.route_prefix', 'manager');
$route_url_prefix = $route_prefix ? $route_prefix . '/' : '';
$route_name_prefix = $route_prefix ? $route_prefix . '.' : '';

Route::prefix("{$route_url_prefix}permission")->name("api.{$route_name_prefix}permission.")->group(function () {
    Route::post('/role', "RoleController@edit")->name('role.edit');
    Route::get('/role', 'RoleController@items')->name('role.items');
    Route::post('/role/delete', 'RoleController@delete')->name('role.delete');
    Route::get('/role/permission', 'RoleController@permissionItems')->name('role.permission.items');
    Route::post('/role/permission', 'RoleController@permissionEdit')->name('role.permission.edit');
    Route::post('/role/permission/clear', 'RoleController@userPermissionClear')->name('user.permission.clear');
    Route::get('/role/data-scope', 'RoleController@dataScopeItems')->name('role.data-scope.items');
    Route::post('/role/data-scope', 'RoleController@dataScopeEdit')->name('role.data-scope.edit');
    Route::post('/role/data-scope/clear', 'RoleController@userDataScopeClear')->name('user.data-scope.clear');
});
