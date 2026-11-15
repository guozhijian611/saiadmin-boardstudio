<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use InvalidArgumentException;
use PDO;
use plugin\boardstudio\app\model\Datasource;

final class DatasetExecutor
{
    public function __construct(private readonly DataSourceExecutor $sources = new DataSourceExecutor()) {}

    public function execute(Datasource $source, array $definition, array $input, ?array $scope = null): array
    {
        $params = (new DatasetParameters())->resolve($definition['parameters'] ?? [], $input);
        $query = $definition['query'] ?? [];
        $limit = min(5000, max(1, (int) ($definition['limit'] ?? 500)));
        $started = microtime(true);
        if (($definition['mode'] ?? '') === 'http') {
            if ($source->type !== 'http') throw new InvalidArgumentException('HTTP 数据集必须使用 HTTP 数据源');
            $result = $this->sources->queryHttp($source->config, $query, $params, (int)($definition['timeoutMs'] ?? 5000));
            $rows = array_slice($result['rows'], 0, $limit + 1);
        } else {
            if ($source->type !== 'mysql') throw new InvalidArgumentException('SQL 数据集必须使用 MySQL 数据源');
            $pdo = $this->sources->pdo($source->config);
            $compiler = new DatasetSql();
            if (($definition['mode'] ?? '') === 'visual') {
                $schema = [];
                $tables = array_merge([$query['table'] ?? ''], array_column($query['joins'] ?? [], 'table'));
                $allowed = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                foreach (array_unique($tables) as $table) {
                    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || !in_array($table, $allowed, true)) throw new InvalidArgumentException('数据表不存在或未授权');
                    $schema[$table] = array_column($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
                }
                [$sql, $bindings] = $compiler->visual($query, $schema, $params, $scope);
            } elseif (($definition['mode'] ?? '') === 'sql') {
                [$sql, $bindings] = $compiler->custom((string) ($query['sql'] ?? ''), $params, $scope);
            } elseif (($definition['mode'] ?? '') === 'legacy') {
                // Imported 1.x datasets retain their bounded query compiler.
                [$sql, $bindings] = (new SqlBuilder())->build($pdo, $query['dataset_type'], $query['config'], $params);
            } else throw new InvalidArgumentException('未知数据集模式');
            $timeout = min(10000, max(100, (int) ($definition['timeoutMs'] ?? 5000)));
            $pdo->exec('SET SESSION MAX_EXECUTION_TIME = ' . $timeout);
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare('SELECT * FROM (' . $sql . ') AS boardstudio_result LIMIT ' . ($limit + 1));
                $statement->execute($bindings);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            } finally {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
        $truncated = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        if (strlen(json_encode($rows, JSON_THROW_ON_ERROR)) > 2 * 1024 * 1024) throw new InvalidArgumentException('查询结果超过 2 MB，请缩小范围');
        $contract=(new DatasetFields())->validate($definition['fields'] ?? []);
        $rows=(new DatasetFields())->apply($rows,$contract);
        $fields = [];
        foreach ($rows as $row) {
            foreach ($row as $name => $value) {
                if (!isset($fields[$name])) $fields[$name] = ['name' => $name, 'type' => 'unknown'];
                if ($value !== null && $fields[$name]['type'] === 'unknown') {
                    $fields[$name]['type'] = is_numeric($value) ? 'number' : (is_bool($value) ? 'boolean' : 'string');
                }
            }
        }
        return ['rows' => $rows, 'fields' => $contract ?: array_values($fields), 'total' => count($rows), 'truncated' => $truncated,
            'fetchedAt' => gmdate('c'), 'durationMs' => (int) round((microtime(true) - $started) * 1000), 'stale' => false];
    }
}
