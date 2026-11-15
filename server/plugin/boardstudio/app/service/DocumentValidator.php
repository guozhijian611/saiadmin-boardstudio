<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use plugin\saiadmin\exception\ApiException;

/** Validate the persisted tree independently of the browser implementation. */
final class DocumentValidator
{
    public function validate(array $document): array
    {
        $this->check(strlen(json_encode($document, JSON_THROW_ON_ERROR)) <= 2 * 1024 * 1024, '文档不能超过 2 MB');
        $this->check(($document['schemaVersion'] ?? null) === 2, '不支持的文档版本');
        $page = $document['page'] ?? [];
        $this->number($page['width'] ?? null, 1, 16384);
        $this->number($page['height'] ?? null, 1, 16384);
        $this->check(in_array($page['sizing'] ?? '', ['fixed', 'responsive'], true), '页面尺寸模式不合法');
        $this->color($page['background'] ?? null);
        $nodes = $document['nodes'] ?? [];
        $this->check(is_array($nodes) && count($nodes) > 0 && count($nodes) <= 200, '最多支持 200 个节点');
        $root = $document['rootId'] ?? '';
        $this->check(is_string($root) && ($nodes[$root]['kind'] ?? '') === 'container', '页面必须有根容器');
        $bindings = $document['bindings'] ?? null;
        $this->check(is_array($bindings) && is_array($document['parameters'] ?? null), '缺少数据绑定或参数');
        $this->check(count($document['parameters'])<=32,'页面参数最多 32 个');
        foreach($document['parameters'] as $name=>$parameter) {
            $this->check(is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/',$name) && is_array($parameter),'页面参数名称不合法');
            $type=$parameter['type'] ?? '';
            $value=$parameter['default'] ?? null;
            $this->check(($type==='string' && is_string($value) && strlen($value)<=1000) || ($type==='number' && (is_int($value)||is_float($value)) && is_finite((float)$value)) || ($type==='boolean' && is_bool($value)), '页面参数默认值不合法');
        }
        foreach ($bindings as $id => $binding) {
            $this->check(is_array($binding) && ($binding['id'] ?? null) === $id && preg_match('/^[A-Za-z0-9_-]{1,80}$/', (string) $id), '数据绑定 ID 不合法');
            $this->check(is_int($binding['datasetId'] ?? null) && $binding['datasetId'] > 0, '数据集 ID 不合法');
            $this->check(in_array($binding['refresh']['mode'] ?? '', ['manual', 'interval'], true), '刷新策略不合法');
            $this->number($binding['refresh']['seconds'] ?? null, 10, 3600);
            $this->check(is_array($binding['params'] ?? null), '绑定参数不合法');
            foreach($binding['params'] as $value) if(is_array($value)) $this->check(count($value)===1 && is_string($value['pageParameter'] ?? null) && isset($document['parameters'][$value['pageParameter']]),'页面参数引用不合法');
        }
        $visited = [];
        $visit = function (string $id, int $depth) use (&$visit, &$visited, $nodes, $bindings): void {
            $this->check($depth <= 12, '容器嵌套不能超过 12 层');
            $this->check(!isset($visited[$id]), '节点循环引用或属于多个容器');
            $visited[$id] = true;
            $node = $nodes[$id] ?? null;
            $this->check(is_array($node) && ($node['id'] ?? null) === $id && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id), '节点 ID 不合法');
            $this->check(is_string($node['name'] ?? null) && mb_strlen($node['name']) <= 120, '节点名称不合法');
            $this->check(is_bool($node['hidden'] ?? null) && is_bool($node['locked'] ?? null), '节点状态不合法');
            $p = $node['placement'] ?? [];
            foreach (['x','y'] as $key) $this->number($p[$key] ?? null, -100000, 100000);
            foreach (['width','height'] as $key) $this->number($p[$key] ?? null, 1, 16384);
            $this->number($p['zIndex'] ?? null, 0, 100000);
            foreach (['grow','shrink'] as $key) $this->number($p[$key] ?? null, 0, 100);
            if (($p['basis'] ?? null) !== 'auto') $this->number($p['basis'] ?? null, 0, 16384);
            foreach (['columnSpan' => 24, 'rowSpan' => 100] as $key => $max) {
                $this->check(is_int($p[$key] ?? null), '网格跨度必须为整数');
                $this->number($p[$key], 1, $max);
            }
            $a = $node['appearance'] ?? [];
            foreach (['background','color','borderColor'] as $key) $this->color($a[$key] ?? null);
            $this->number($a['opacity'] ?? null, 0, 1);
            $this->number($a['borderWidth'] ?? null, 0, 100);
            $this->number($a['borderRadius'] ?? null, 0, 1000);
            if (($node['kind'] ?? '') === 'container') {
                $l = $node['layout'] ?? [];
                $this->check(in_array($l['mode'] ?? '', ['free','flex','grid'], true), '容器布局不合法');
                $this->check(is_array($node['children'] ?? null) && !isset($node['widget']) && !isset($node['binding']), '容器结构不合法');
                $this->check(in_array($l['direction'] ?? '', ['row','column'], true) && in_array($l['align'] ?? '', ['start','center','end','stretch'], true) && in_array($l['justify'] ?? '', ['start','center','end','space-between'], true), '容器对齐不合法');
                foreach (['gap','padding'] as $key) $this->number($l[$key] ?? null, 0, 1000);
                $this->number($l['rowHeight'] ?? null, 1, 16384);
                $this->check(is_int($l['columns'] ?? null) && is_bool($l['wrap'] ?? null), '网格列数或换行不合法');
                $this->number($l['columns'], 1, 24);
                foreach ($node['children'] as $child) {
                    $this->check(is_string($child), '子节点 ID 不合法');
                    $visit($child, $depth + 1);
                }
            } else {
                $this->check(($node['kind'] ?? '') === 'widget' && !isset($node['children']) && !isset($node['layout']), '组件结构不合法');
                $widget = $node['widget'] ?? [];
                $this->check(is_string($widget['type'] ?? null) && ($widget['version'] ?? null) === 1 && is_array($widget['props'] ?? null), '组件定义不合法');
                if (isset($node['binding'])) $this->check(isset($bindings[$node['binding']['instanceId'] ?? '']), '组件引用了不存在的数据绑定');
            }
        };
        $visit($root, 0);
        $this->check(count($visited) === count($nodes), '文档包含游离节点');
        return $document;
    }

    private function number(mixed $value, int $min, int $max): void
    {
        $this->check((is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= $min && $value <= $max, '布局数值超出范围');
    }

    private function color(mixed $value): void
    {
        $this->check(is_string($value) && strlen($value) <= 128 && !preg_match('/url\s*\(|[;{}]/i', $value), '颜色格式不合法');
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) throw new ApiException($message);
    }
}
