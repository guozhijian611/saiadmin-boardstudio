<?php
namespace plugin\boardstudio\app\model;
use plugin\saiadmin\basic\think\BaseModel;
final class DatasetVersion extends BaseModel
{
    protected $table = 'boardstudio_dataset_version';
    protected $pk = 'id';
    public function getDefinitionAttr(mixed $value): array { return is_array($value) ? $value : (json_decode((string) $value, true) ?: []); }
    public function setDefinitionAttr(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
}
