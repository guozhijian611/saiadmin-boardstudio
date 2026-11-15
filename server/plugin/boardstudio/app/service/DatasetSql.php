<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use InvalidArgumentException;

/** Bounded relational query compiler. Identifiers never come from runtime values. */
final class DatasetSql
{
    private array $schema;
    private array $aliases = [];
    private array $bindings = [];
    private array $params;

    public function visual(array $config, array $schema, array $params, ?array $scope = null): array
    {
        $this->schema = $schema;
        $this->aliases = [];
        $this->bindings = [];
        $this->params = $params;
        $main = $config['table'] ?? '';
        $sql = ' FROM ' . $this->table($main, 't0');
        $joins = $config['joins'] ?? [];
        if (!is_array($joins) || count($joins) > 5) throw new InvalidArgumentException('最多关联 5 张表');
        foreach ($joins as $index => $join) {
            $kind = strtoupper((string) ($join['type'] ?? 'LEFT'));
            if (!in_array($kind, ['LEFT', 'INNER'], true)) throw new InvalidArgumentException('仅支持 LEFT/INNER JOIN');
            $alias = 't' . ($index + 1);
            $table = $this->table($join['table'] ?? '', $alias);
            $sql .= ' ' . $kind . ' JOIN ' . $table . ' ON ' . $this->field($join['left'] ?? '') . ' = ' . $this->field($join['right'] ?? '');
        }
        $columns = $config['columns'] ?? [];
        if (!is_array($columns) || !$columns || count($columns) > 50) throw new InvalidArgumentException('请选择 1 到 50 个输出字段');
        $select = []; $output = [];
        foreach ($columns as $column) {
            $alias = $this->identifier($column['alias'] ?? '');
            if (isset($output[$alias])) throw new InvalidArgumentException('输出字段别名重复');
            $output[$alias] = true;
            $field = ($column['field'] ?? '') === '*' ? '*' : $this->field($column['field'] ?? '');
            $aggregate = strtoupper((string) ($column['aggregate'] ?? ''));
            if ($aggregate !== '') {
                if (!in_array($aggregate, ['COUNT','SUM','AVG','MIN','MAX'], true) || ($field === '*' && $aggregate !== 'COUNT')) throw new InvalidArgumentException('聚合函数不合法');
                $field = $aggregate . '(' . (!empty($column['distinct']) ? 'DISTINCT ' : '') . $field . ')';
            } elseif ($field === '*') throw new InvalidArgumentException('请选择具体字段');
            $select[] = $field . ' AS `' . $alias . '`';
        }
        $conditions = $config['conditions'] ?? [];
        if (count($conditions) > 30) throw new InvalidArgumentException('筛选条件过多');
        $where = [];
        foreach ($conditions as $condition) {
            $field = $this->field($condition['field'] ?? '');
            $op = strtoupper((string) ($condition['operator'] ?? '='));
            if (in_array($op, ['IS NULL','IS NOT NULL'], true)) { $where[] = $field . ' ' . $op; continue; }
            if (!in_array($op, ['=','!=','>','>=','<','<=','LIKE'], true)) throw new InvalidArgumentException('筛选运算符不合法');
            if (isset($condition['parameter'])) {
                $name = $condition['parameter'];
                if (!array_key_exists($name, $params)) throw new InvalidArgumentException('筛选引用未声明参数');
                $value = $params[$name];
            } else $value = $condition['value'] ?? null;
            if ($value !== null && !is_scalar($value)) throw new InvalidArgumentException('筛选值必须是标量');
            $this->bindings[] = $value;
            $where[] = $field . ' ' . $op . ' ?';
        }
        if ($scope) {
            $where[]=$this->field($scope['field']).' = ?';
            $this->bindings[]=$scope['value'];
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $groups = $config['groupBy'] ?? [];
        if ($groups) $sql .= ' GROUP BY ' . implode(', ', array_map(fn($field) => $this->field($field), $groups));
        $orders = [];
        foreach (array_slice($config['orderBy'] ?? [], 0, 10) as $order) {
            $field = $this->identifier($order['field'] ?? '');
            if (!isset($output[$field])) throw new InvalidArgumentException('排序字段必须是输出别名');
            $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC','DESC'], true)) throw new InvalidArgumentException('排序方向不合法');
            $orders[] = '`' . $field . '` ' . $direction;
        }
        if ($orders) $sql .= ' ORDER BY ' . implode(', ', $orders);
        return ['SELECT ' . implode(', ', $select) . $sql, $this->bindings];
    }

    public function custom(string $sql, array $params, ?array $scope = null): array
    {
        if (strlen($sql) > 20000) throw new InvalidArgumentException('SQL 超过长度限制');
        // A deliberately restricted SELECT dialect: no comments, variables, compound
        // statements, subqueries, arbitrary functions, stored routines or file access.
        if (preg_match('/--|\/\*|\*\//', $sql)) throw new InvalidArgumentException('SQL 不允许注释');
        $offset = 0; $tokens = []; $bindings = [];
        while ($offset < strlen($sql)) {
            if (!preg_match('/\G(?:\s+|\'(?:\'\'|[^\'\\\\])*\'|`[A-Za-z_][A-Za-z0-9_]*`|:[A-Za-z][A-Za-z0-9_]*|[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|<=|>=|<>|!=|[(),.*+\/=<>-])/A', $sql, $match, 0, $offset)) throw new InvalidArgumentException('SQL 包含不支持的语法');
            $token = $match[0]; $offset += strlen($token);
            if (trim($token) === '') continue;
            if ($token[0] === ':') {
                $key = substr($token, 1);
                if (!array_key_exists($key, $params)) throw new InvalidArgumentException('SQL 引用了未声明参数：' . $key);
                $bindings[] = $params[$key]; $tokens[] = '?';
            } else $tokens[] = $token;
        }
        if (strtoupper($tokens[0] ?? '') !== 'SELECT') throw new InvalidArgumentException('仅允许 SELECT 查询');
        $forbidden = ['INSERT','UPDATE','DELETE','DROP','ALTER','CREATE','TRUNCATE','REPLACE','GRANT','REVOKE','CALL','DO','SET','INTO','OUTFILE','DUMPFILE','LOAD','LOCK','FOR','PROCEDURE','UNION','WITH','HANDLER'];
        $functions = ['COUNT','SUM','AVG','MIN','MAX','ROUND','COALESCE','IFNULL','NULLIF','DATE','DATE_FORMAT','YEAR','MONTH','DAY','LOWER','UPPER','CONCAT','CAST','ABS'];
        $selectCount = 0; $depth = 0;
        foreach ($tokens as $index => $token) {
            $upper = strtoupper(trim($token, '`'));
            if (($tokens[$index + 1] ?? '') === '(' && (($tokens[$index - 1] ?? '') === '.')) throw new InvalidArgumentException('不允许调用数据库例程');
            if ($upper === 'SELECT') $selectCount++;
            if (in_array($upper, $forbidden, true)) throw new InvalidArgumentException('不允许的 SQL 关键字：' . $upper);
            if ($token === '(') $depth++;
            if ($token === ')' && --$depth < 0) throw new InvalidArgumentException('SQL 括号不匹配');
            if (($tokens[$index + 1] ?? '') === '(' && preg_match('/^[A-Za-z_`]/', $token) && !in_array($upper, array_merge($functions, ['IN','AS']), true)) throw new InvalidArgumentException('不允许的 SQL 函数：' . $token);
        }
        if ($selectCount !== 1 || $depth !== 0) throw new InvalidArgumentException('仅允许单层 SELECT 查询');
        if ($scope) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}\.[A-Za-z_][A-Za-z0-9_]{0,63}$/',$scope['field'])) throw new InvalidArgumentException('范围字段不合法');
            $depth=0;$where=null;$boundary=count($tokens);$from=false;
            foreach($tokens as $index=>$token){
                if($token==='(')$depth++;
                if($token===')')$depth--;
                if($depth!==0)continue;
                $word=strtoupper($token);
                if($word==='FROM')$from=true;
                if($word==='WHERE')$where=$index;
                if(in_array($word,['GROUP','HAVING','ORDER','LIMIT','OFFSET'],true)){$boundary=$index;break;}
            }
            if(!$from)throw new InvalidArgumentException('观看者范围 SQL 必须查询数据表');
            $head=array_slice($tokens,0,$where ?? $boundary);
            $tail=array_slice($tokens,$boundary);
            $scopeField=implode('.',array_map(fn($part)=>'`'.$part.'`',explode('.',$scope['field'])));
            $predicate=[$scopeField,'=','?'];
            if($where!==null)$predicate=array_merge(['('],array_slice($tokens,$where+1,$boundary-$where-1),[')','AND'],$predicate);
            $insertIndex=count(array_filter(array_slice($tokens,0,$boundary),fn($token)=>$token==='?'));
            array_splice($bindings,$insertIndex,0,[$scope['value']]);
            $tokens=array_merge($head,['WHERE'],$predicate,$tail);
        }
        $compiled = '';
        foreach ($tokens as $index => $token) {
            $previous = strtoupper(trim($tokens[$index - 1] ?? '', '`'));
            $compiled .= ($index && !($token === '(' && in_array($previous, $functions, true)) ? ' ' : '') . $token;
        }
        return [$compiled, $bindings];
    }

    private function table(string $name, string $alias): string
    {
        $this->identifier($name);
        if (!isset($this->schema[$name])) throw new InvalidArgumentException('数据表不存在或未授权：' . $name);
        $this->aliases[$alias] = $name;
        return '`' . $name . '` AS `' . $alias . '`';
    }
    private function field(string $field): string
    {
        $parts = explode('.', $field);
        if (count($parts) !== 2) throw new InvalidArgumentException('字段须包含表别名，例如 t0.id');
        [$alias,$name] = $parts;
        $this->identifier($alias); $this->identifier($name);
        if (!isset($this->aliases[$alias]) || !in_array($name, $this->schema[$this->aliases[$alias]], true)) throw new InvalidArgumentException('字段不存在或未授权：' . $field);
        return '`' . $alias . '`.`' . $name . '`';
    }
    private function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name)) throw new InvalidArgumentException('标识符不合法');
        return $name;
    }
}
