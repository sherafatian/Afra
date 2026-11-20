<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController as W;
use Illuminate\Support\Facades\Route;

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
foreach (['case', 'run', 'item', 'file', 'record', 'group', 'role'] as $parameter) {
    Route::pattern($parameter, $uuid);
}
Route::pattern('user', '[1-9][0-9]{0,17}');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:afra-login');
Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:afra-refresh');
Route::middleware(['afra.auth', 'throttle:afra-api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::prefix('v1')->group(function () {
        Route::get('me', [W::class, 'me']);
        Route::patch('me/profile', [W::class, 'profile']);
        Route::post('me/password', [AuthController::class, 'password']);
        Route::get('system/users', [UserController::class, 'index']);
        Route::post('system/users', [UserController::class, 'create']);
        Route::patch('system/users/{user}', [UserController::class, 'update']);
        Route::post('system/users/{user}/reset-password', [UserController::class, 'reset']);
        Route::get('projects', [W::class, 'projects']);
        Route::post('projects', [W::class, 'createProject']);
        Route::prefix('projects/{project}')->whereUuid('project')->group(function () {
            Route::get('files', [FileController::class, 'index']);
            Route::post('files', [FileController::class, 'upload']);
            Route::get('files/{file}', [FileController::class, 'download']);
            Route::get('me/capabilities', [AccessController::class, 'capabilities']);
            Route::get('permissions', [AccessController::class, 'catalog']);
            Route::post('member-options', [AccessController::class, 'memberOptions']);
            Route::get('directory', [AccessController::class, 'directory']);
            Route::put('groups/{group}', [AccessController::class, 'saveGroup']);
            Route::get('roles', [AccessController::class, 'roles']);
            Route::post('roles', [AccessController::class, 'saveRole']);
            Route::put('roles/{role}', [AccessController::class, 'saveRole']);
            Route::get('section-access', [AccessController::class, 'sectionRules']);
            Route::put('section-access', [AccessController::class, 'saveSectionRules']);
            Route::get('members', [W::class, 'members']);
            Route::post('members', [AccessController::class, 'member']);
            Route::get('groups', [AccessController::class, 'groups']);
            Route::post('groups', [AccessController::class, 'saveGroup']);
            Route::post('defects/{defect}/retest', [W::class, 'retest']);
            Route::get('dashboard', [W::class, 'dashboard']);
            Route::get('sections', [W::class, 'sections']);
            Route::post('sections', [W::class, 'createSection']);
            Route::get('cases', [W::class, 'cases']);
            Route::post('cases', [W::class, 'saveCase']);
            Route::get('cases/{case}', [W::class, 'caseDetail']);
            Route::put('cases/{case}', [W::class, 'saveCase']);
            Route::post('cases/{case}/assignment', [AssignmentController::class, 'update']);
            Route::post('cases/{case}/review', [W::class, 'review']);
            Route::get('records/{kind}', [W::class, 'records']);
            Route::post('records/{kind}', [W::class, 'saveRecord']);
            Route::put('records/{kind}/{record}', [W::class, 'saveRecord']);
            Route::get('runs', [W::class, 'runs']);
            Route::post('runs', [W::class, 'createRun']);
            Route::get('runs/{run}', [W::class, 'runDetail']);
            Route::post('runs/{run}/items/{item}/results', [W::class, 'result']);
            Route::get('defects', [W::class, 'defects']);
            Route::post('defects', [W::class, 'createDefect']);
            Route::post('defects/{defect}/transition', [W::class, 'transitionDefect']);
            Route::get('audit', [W::class, 'auditEvents']);
        });
    });
});
