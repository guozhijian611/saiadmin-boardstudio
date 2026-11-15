<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use plugin\saiadmin\exception\ApiException;

/** Validate the bounded document before normalization or persistence. */
final class LayoutValidator
{
    public function validate(array $layout): array
    {
        if (strlen(json_encode($layout, JSON_THROW_ON_ERROR)) > 2 * 1024 * 1024) {
            throw new ApiException('布局不能超过 2 MB');
        }
        foreach (['width', 'height'] as $key) {
            $value = $layout['canvas'][$key] ?? 1920;
            if (!is_numeric($value) || !is_finite((float) $value) || $value < 1 || $value > 16384) {
                throw new ApiException('画布尺寸必须在 1 到 16384 之间');
            }
        }
        $components = $layout['components'] ?? [];
        if (!is_array($components) || count($components) > 200) {
            throw new ApiException('画布最多支持 200 个组件');
        }
        $seen = [];
        foreach ($components as $component) {
            if (!is_array($component)) throw new ApiException('组件格式不正确');
            $id = $component['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id) || isset($seen[$id])) {
                throw new ApiException('组件 ID 不合法或重复');
            }
            $seen[$id] = true;
            foreach (['x', 'y', 'w', 'h', 'z'] as $key) {
                $value = $component['rect'][$key] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || abs((float) $value) > 100000) {
                    throw new ApiException('组件坐标不合法');
                }
                if (in_array($key, ['w', 'h'], true) && $value <= 0) throw new ApiException('组件尺寸必须大于零');
            }
        }
        return $layout;
    }
}
