<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

/** Preserve JSON objects across PHP's associative request decoding. */
final class DocumentJson
{
    public static function encode(array $document): string
    {
        foreach ($document['nodes'] as &$node) {
            if (isset($node['widget'])) $node['widget']['props'] = (object) $node['widget']['props'];
            if (isset($node['binding'])) $node['binding']['mapping'] = (object) ($node['binding']['mapping'] ?? []);
        }
        unset($node);
        foreach ($document['bindings'] as &$binding) $binding['params'] = (object) $binding['params'];
        unset($binding);
        $document['nodes'] = (object) $document['nodes'];
        $document['bindings'] = (object) $document['bindings'];
        $document['parameters'] = (object) $document['parameters'];
        return json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
