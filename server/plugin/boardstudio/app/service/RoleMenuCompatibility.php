<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

/** PostgreSQL cannot infer bigint parameters in the host's INSERT ... SELECT ... UNION batch. */
final class RoleMenuCompatibility
{
    public static function transform(string $source, bool $remove): string
    {
        $nl = str_contains($source,"\r\n") ? "\r\n" : "\n";
        $start='// <boardstudio:role-menu-insert>';$end='// </boardstudio:role-menu-insert>';
        $known=["Db::name('sa_system_role_menu')->insertAll(\$data);", "Db::name('sa_system_role_menu')->limit(100)->insertAll(\$data);"];
        $safe="foreach (\$data as \$row) Db::name('sa_system_role_menu')->insert(\$row);";
        $pattern='~^([\t ]*)'.preg_quote($start,'~').'\R(.*?)^[\t ]*'.preg_quote($end,'~').'\R?~ms';
        if (preg_match($pattern,$source,$match)) {
            $lines=array_map('trim',preg_split('/\R/',trim($match[2])));
            $original=isset($lines[0]) && str_starts_with($lines[0],'// original: ') ? substr($lines[0],strlen('// original: ')) : '';
            if (count($lines)!==2 || !in_array($original,$known,true) || $lines[1]!==$safe || substr_count($source,$start)!==1 || substr_count($source,$end)!==1)
                throw new \RuntimeException('角色菜单 PostgreSQL 兼容片段已被修改，拒绝覆盖');
            return $remove ? str_replace($match[0],$match[1].$original.$nl,$source) : $source;
        }
        if (str_contains($source,$start)||str_contains($source,$end)) throw new \RuntimeException('角色菜单兼容标记不完整');
        if ($remove) return $source;
        $method=strpos($source,'function saveMenuPermission(');
        $following=$method===false ? false : strpos($source,'public function',$method+strlen('function saveMenuPermission('));
        $originals=array_values(array_filter($known,static fn($line)=>str_contains($source,$line)));
        if (!$originals && $method!==false && preg_match('~^[\t ]*'.preg_quote($safe,'~').'[\t ]*(?=\r?$)~m',$source,$native,PREG_OFFSET_CAPTURE)
            && $native[0][1]>$method && ($following===false || $native[0][1]<$following)) return $source;
        if ($method===false || count($originals)!==1) throw new \RuntimeException('不支持的 PostgreSQL 角色菜单保存实现，请核对宿主兼容性');
        $original=$originals[0];$position=strpos($source,$original);
        if ($position<$method || ($following!==false && $position>$following) || substr_count($source,$original)!==1)
            throw new \RuntimeException('不支持的 PostgreSQL 角色菜单保存实现，请核对宿主兼容性');
        $result=preg_replace_callback('~^([\t ]*)'.preg_quote($original,'~').'[\t ]*(?=\r?$)~m',
            static fn($m)=>$m[1].$start.$nl.$m[1].'// original: '.$original.$nl.$m[1].$safe.$nl.$m[1].$end,$source,1,$count);
        if ($result===null || $count!==1) throw new \RuntimeException('无法生成角色菜单兼容片段');
        return $result;
    }
}
