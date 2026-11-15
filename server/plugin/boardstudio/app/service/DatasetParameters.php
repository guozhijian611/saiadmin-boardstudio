<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use InvalidArgumentException;

final class DatasetParameters
{
    public function resolve(array $definitions, array $values): array
    {
        if (count($definitions) > 32 || count($values) > 32) throw new InvalidArgumentException('参数最多 32 个');
        $result = [];
        foreach ($definitions as $name => $definition) {
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $name) || !is_array($definition)) throw new InvalidArgumentException('参数定义不合法');
            $value = array_key_exists($name, $values) ? $values[$name] : ($definition['default'] ?? null);
            if ($value === null) {
                if ($definition['required'] ?? false) throw new InvalidArgumentException('缺少必填参数：' . $name);
                $result[$name] = null;
                continue;
            }
            $type = $definition['type'] ?? 'string';
            if ($type === 'number') {
                if (!is_numeric($value) || !is_finite((float) $value)) throw new InvalidArgumentException('参数必须是数字：' . $name);
                $value = $value + 0;
            } elseif ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) throw new InvalidArgumentException('参数必须是布尔值：' . $name);
            } elseif ($type === 'string') {
                if (!is_scalar($value) || strlen((string) $value) > 1000) throw new InvalidArgumentException('参数字符串过长：' . $name);
                $value = (string) $value;
            } else throw new InvalidArgumentException('未知参数类型：' . $type);
            $result[$name] = $value;
        }
        foreach ($values as $name => $_) if (!array_key_exists($name, $definitions)) throw new InvalidArgumentException('未声明的参数：' . $name);
        return $result;
    }
}
