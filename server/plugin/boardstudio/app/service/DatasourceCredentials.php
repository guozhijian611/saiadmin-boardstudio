<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use InvalidArgumentException;
use RuntimeException;

/** Encrypt complete connection configuration; plaintext exists only during execution. */
final class DatasourceCredentials
{
    public const MASK = '[已保存]';
    public function __construct(private readonly ?string $testKey = null) {}

    private function keyPath(): string
    {
        return runtime_path('boardstudio-credentials.key');
    }
    private function key(): string
    {
        $encoded = $this->testKey ?? (string) config('plugin.boardstudio.app.credentials_key', '');
        if ($encoded === '') {
            $path = $this->keyPath();
            if (!is_file($path)) throw new RuntimeException('数据源密钥缺失，请恢复 BOARDSTUDIO_CREDENTIALS_KEY 或密钥文件');
            $encoded = trim((string) file_get_contents($path));
        }
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('数据源密钥必须是 32 字节随机值的 Base64 编码');
        return $key;
    }
    /** Only installation / explicit initialization may create a key. Never auto-regenerate on reads. */
    public function initialize(): void
    {
        if ($this->testKey !== null || (string) config('plugin.boardstudio.app.credentials_key', '') !== '' || is_file($this->keyPath())) {
            $this->key();
            return;
        }
        $path = $this->keyPath();
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) throw new RuntimeException('无法创建密钥目录');
        $old = umask(0077);
        try {
            $file = @fopen($path, 'x');
            if (!$file) { $this->key(); return; }
            try {
                $encoded = base64_encode(random_bytes(32));
                if (fwrite($file, $encoded . "\n") !== strlen($encoded) + 1 || !fflush($file)) throw new RuntimeException('密钥写入失败');
            } finally { fclose($file); }
            chmod($path, 0600);
        } finally { umask($old); }
        $this->key();
    }
    public function seal(array $config): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, 'boardstudio:datasource:v1');
        if ($cipher === false) throw new RuntimeException('连接配置加密失败');
        return json_encode(['_boardstudio_encrypted' => 1, 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'data' => base64_encode($cipher)], JSON_THROW_ON_ERROR);
    }
    public function open(mixed $value): array
    {
        if (is_string($value)) $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('连接配置损坏');
        // Legacy plaintext is only accepted for migration and previously installed records.
        if (!isset($value['_boardstudio_encrypted'])) return $value;
        if ($value['_boardstudio_encrypted'] !== 1) throw new RuntimeException('未知的连接配置加密版本');
        $iv = base64_decode((string) ($value['iv'] ?? ''), true);
        $tag = base64_decode((string) ($value['tag'] ?? ''), true);
        $cipher = base64_decode((string) ($value['data'] ?? ''), true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $cipher === false) throw new RuntimeException('连接配置损坏');
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, 'boardstudio:datasource:v1');
        if ($plain === false) throw new RuntimeException('连接配置解密失败，请检查密钥');
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }
    public function mask(array $config): array
    {
        if (array_key_exists('password', $config)) $config['password'] = $config['password'] === '' ? '' : self::MASK;
        // Any header/default parameter may contain a custom secret; do not infer from its name.
        foreach (['headers', 'params'] as $field) if (isset($config[$field])) {
            foreach ($config[$field] as $name => $_) $config[$field][$name] = self::MASK;
        }
        return $config;
    }
    public function merge(array $input, array $stored, bool $clearPassword = false): array
    {
        if ($clearPassword) $input['password'] = '';
        elseif (isset($stored['password']) && (!isset($input['password']) || $input['password'] === '' || $input['password'] === self::MASK)) $input['password'] = $stored['password'];
        elseif (($input['password'] ?? null) === self::MASK) throw new InvalidArgumentException('没有可保留的密码');
        foreach (['headers', 'params'] as $field) if (isset($input[$field])) {
            if (!is_array($input[$field])) throw new InvalidArgumentException('连接配置必须为对象');
            foreach ($input[$field] as $name => $value) if ($value === self::MASK) {
                if (!array_key_exists($name, $stored[$field] ?? [])) throw new InvalidArgumentException('没有可保留的密钥字段');
                $input[$field][$name] = $stored[$field][$name];
            }
        }
        return $input;
    }
}
