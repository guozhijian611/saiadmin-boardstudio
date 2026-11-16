<?php

use plugin\boardstudio\app\admin\controller\DatasourceController;
use plugin\boardstudio\app\admin\controller\QueryTemplateController;
use plugin\boardstudio\app\admin\controller\ScreenController;
use plugin\boardstudio\app\api\controller\BoardController;
use Webman\Route;

Route::group('/app/boardstudio/admin', function () {
    fastRoute('Screen', ScreenController::class);
    foreach (['index','sources','grants'] as $action) Route::get('/Dataset/' . $action, [\plugin\boardstudio\app\admin\controller\DatasetController::class, $action]);
    foreach (['save','update','preview','execute','grant'] as $action) Route::post('/Dataset/' . $action, [\plugin\boardstudio\app\admin\controller\DatasetController::class, $action]);
    foreach (['check','releases'] as $action) Route::get('/Document/' . $action, [\plugin\boardstudio\app\admin\controller\DocumentController::class, $action]);
    foreach (['publish','activate'] as $action) Route::post('/Document/' . $action, [\plugin\boardstudio\app\admin\controller\DocumentController::class, $action]);
    Route::get('/Document/read', [\plugin\boardstudio\app\admin\controller\DocumentController::class, 'read']);
    Route::post('/Document/save', [\plugin\boardstudio\app\admin\controller\DocumentController::class, 'save']);
    Route::post('/Screen/changeStatus', [ScreenController::class, 'changeStatus']);
    Route::post('/Screen/saveLayout', [ScreenController::class, 'saveLayout']);
    Route::post('/Screen/publish', [ScreenController::class, 'publish']);
    Route::post('/Screen/copy', [ScreenController::class, 'copy']);
    Route::post('/Screen/generateFromTable', [ScreenController::class, 'generateFromTable']);
    Route::get('/Screen/versions', [ScreenController::class, 'versions']);
    Route::post('/Screen/restoreVersion', [ScreenController::class, 'restoreVersion']);
    Route::delete('/Screen/deleteVersion', [ScreenController::class, 'deleteVersion']);
    Route::get('/Screen/tokens', [ScreenController::class, 'tokens']);
    Route::post('/Screen/createToken', [ScreenController::class, 'createToken']);
    Route::post('/Screen/resetToken', [ScreenController::class, 'resetToken']);
    Route::post('/Screen/changeTokenStatus', [ScreenController::class, 'changeTokenStatus']);
    Route::delete('/Screen/deleteToken', [ScreenController::class, 'deleteToken']);
    Route::get('/Screen/runtimeMetrics', [ScreenController::class, 'runtimeMetrics']);

    fastRoute('Datasource', DatasourceController::class);
    Route::post('/Datasource/changeStatus', [DatasourceController::class, 'changeStatus']);
    Route::post('/Datasource/test', [DatasourceController::class, 'test']);
    Route::get('/Datasource/options', [DatasourceController::class, 'options']);
    Route::get('/Datasource/schema', [DatasourceController::class, 'schema']);

    fastRoute('QueryTemplate', QueryTemplateController::class);
    Route::post('/QueryTemplate/changeStatus', [QueryTemplateController::class, 'changeStatus']);
    Route::post('/QueryTemplate/preview', [QueryTemplateController::class, 'preview']);
    Route::get('/QueryTemplate/options', [QueryTemplateController::class, 'options']);

});

Route::group('/app/boardstudio/api', function () {
    Route::get('/document/{code}', [\plugin\boardstudio\app\api\controller\DocumentPlayerController::class, 'read']);
    Route::post('/document-data', [\plugin\boardstudio\app\api\controller\DocumentPlayerController::class, 'data']);
    Route::get('/screen/{code}', [BoardController::class, 'getScreen']);
    Route::get('/data', [BoardController::class, 'data']);
    Route::post('/data', [BoardController::class, 'data']);
});

Route::get('/app/boardstudio/player/', [\plugin\boardstudio\app\api\controller\PlayerController::class, 'index']);
Route::get('/app/boardstudio/player/assets/{file}', [\plugin\boardstudio\app\api\controller\PlayerController::class, 'asset']);

Route::get('/app/boardstudio/player', static function (\support\Request $request) {
    return new \support\Response(302, ['Location' => 'player/?' . http_build_query($request->get())]);
});
