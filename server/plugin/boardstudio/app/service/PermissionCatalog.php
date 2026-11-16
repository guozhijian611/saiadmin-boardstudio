<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

/** Stable installed action points; only migrate grants from their former API rights. */
final class PermissionCatalog
{
    public const ACTIONS = [
        'boardstudio:dataset:execute' => ['name' => '执行数据集', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:query_template:preview'],
        'boardstudio:dataset:index' => ['name' => '数据集列表', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:query_template:index'],
        'boardstudio:dataset:preview' => ['name' => '预览数据集', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:query_template:preview'],
        'boardstudio:dataset:save' => ['name' => '新建数据集', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:query_template:save'],
        'boardstudio:dataset:sources' => ['name' => '数据集可用连接及结构', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:datasource:index'],
        'boardstudio:dataset:update' => ['name' => '修改数据集', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => 'boardstudio:query_template:save'],
        'boardstudio:datasource:changeStatus' => ['name' => '数据源状态', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:destroy' => ['name' => '数据源删除', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:index' => ['name' => '数据源列表', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:read' => ['name' => '数据源读取', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:save' => ['name' => '数据源添加', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:test' => ['name' => '测试数据源', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:datasource:update' => ['name' => '数据源修改', 'parent' => 'BoardStudioDatasource', 'legacy' => null],
        'boardstudio:query_template:changeStatus' => ['name' => '查询模板状态', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:destroy' => ['name' => '查询模板删除', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:index' => ['name' => '查询模板列表', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:preview' => ['name' => '预览查询模板', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:read' => ['name' => '查询模板读取', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:save' => ['name' => '查询模板添加', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:query_template:update' => ['name' => '查询模板修改', 'parent' => 'BoardStudioQueryTemplate', 'legacy' => null],
        'boardstudio:resource:grant' => ['name' => '修改资源授权', 'parent' => 'BoardStudio', 'legacy' => 'boardstudio:query_template:update'],
        'boardstudio:resource:grants' => ['name' => '读取资源授权', 'parent' => 'BoardStudio', 'legacy' => 'boardstudio:query_template:update'],
        'boardstudio:screen:changeStatus' => ['name' => '大屏状态', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:changeTokenStatus' => ['name' => '大屏访问令牌状态', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:copy' => ['name' => '复制大屏', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:createToken' => ['name' => '创建大屏访问令牌', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:deleteToken' => ['name' => '删除大屏访问令牌', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:deleteVersion' => ['name' => '删除大屏版本', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:destroy' => ['name' => '大屏删除', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:generateFromTable' => ['name' => '从数据表生成大屏', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:index' => ['name' => '大屏列表', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:publish' => ['name' => '发布 V2 文档', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:read' => ['name' => '大屏读取', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:resetToken' => ['name' => '重置大屏访问令牌', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:restoreVersion' => ['name' => '切换发布版本', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:save' => ['name' => '大屏添加', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:saveLayout' => ['name' => '保存大屏布局', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:tokens' => ['name' => '大屏访问令牌列表', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:update' => ['name' => '大屏修改', 'parent' => 'BoardStudioScreen', 'legacy' => null],
        'boardstudio:screen:versions' => ['name' => '发布历史', 'parent' => 'BoardStudioScreen', 'legacy' => null],
    ];

    public static function title(string $slug): string
    {
        return 'menus.boardstudio.actions.'.str_replace(':', '_', substr($slug, strlen('boardstudio:')));
    }
}
