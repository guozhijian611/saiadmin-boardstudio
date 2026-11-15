<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use RuntimeException;
use Throwable;

/** Install-time, owned-fragment frontend integration. No subprocesses or Python required. */
class FrontendIntegration
{
    private const MAIN_START = '// <boardstudio:bootstrap>';
    private const MAIN_END = '// </boardstudio:bootstrap>';
    private const HEADER_START = '// <boardstudio:language-refresh>';
    private const HEADER_END = '// </boardstudio:language-refresh>';
    private const GLOB = "import.meta.glob('./views/plugin/boardstudio/i18n/host.ts', { eager: true })";
    private const GUARD = "if (!router.currentRoute.value.path.startsWith('/boardstudio/')) reload(50)";
    private string $root;
    private string $stateDir;

    public function __construct(string $frontend, string $runtime)
    {
        $root = realpath($frontend);
        if ($root === false || !is_dir($root)) throw new RuntimeException('Board Studio 自动接入失败：宿主前端目录不存在：'.$frontend);
        $this->root = $root;
        $this->stateDir = rtrim($runtime, '/\\').'/frontend-integration/'.hash('sha256', $root);
    }

    public static function host(): self
    {
        // Same resolver used by saipackage to copy the frontend and update package.json.
        return new self(\plugin\saipackage\app\logic\InstallLogic::frontendDir(), runtime_path().'/boardstudio');
    }

    public function apply(): void { $this->synchronize(false); }
    public function remove(): void { $this->synchronize(true); }

    /** Compute all changes before touching any host file; useful for diagnostics. */
    public function plan(bool $remove = false): array
    {
        if (!$remove && !is_file($this->root.'/src/views/plugin/boardstudio/i18n/host.ts')) throw new RuntimeException('Board Studio 自动接入失败：插件前端语言入口未复制到宿主，请核对 FRONTEND_DIR 与安装包路径');
        $paths = [
            'main' => $this->root.'/src/main.ts',
            'header' => $this->root.'/src/components/core/layouts/art-header-bar/index.vue',
        ];
        $original = [];
        foreach ($paths as $key => $path) {
            if (!is_file($path) || is_link($path) || filesize($path) > 2 * 1024 * 1024) throw new RuntimeException('Board Studio 自动接入失败：不支持的宿主文件：'.$path);
            $content = file_get_contents($path);
            if ($content === false) throw new RuntimeException('Board Studio 无法读取宿主文件：'.$path);
            $original[$key] = $content;
        }
        $updated = [
            'main' => $this->main($original['main'], $remove),
            'header' => $this->header($original['header'], $remove),
        ];
        $changes = [];
        foreach ($paths as $key => $path) if ($original[$key] !== $updated[$key]) {
            if (!is_writable($path) || !is_writable(dirname($path)) || !(fileperms($path) & 0222) || !(fileperms(dirname($path)) & 0222)) throw new RuntimeException('Board Studio 自动接入失败：宿主文件或目录不可写：'.$path);
            $changes[$key] = ['path' => $path, 'before' => $original[$key], 'after' => $updated[$key]];
        }
        return $changes;
    }

    private function main(string $source, bool $remove): string
    {
        $nl = str_contains($source, "\r\n") ? "\r\n" : "\n";
        $bom = str_starts_with($source, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
        $source = substr($source, strlen($bom));
        $pattern = '~^'.preg_quote(self::MAIN_START, '~').'\R(.*?)^'.preg_quote(self::MAIN_END, '~').'\R?~ms';
        if (preg_match($pattern, $source, $match)) {
            if (substr_count($source, self::MAIN_START) !== 1 || trim($match[1]) !== self::GLOB) throw new RuntimeException('Board Studio 启动接入片段已被修改，拒绝覆盖；请检查宿主 src/main.ts');
            return $bom.($remove ? str_replace($match[0], '', $source) : $source);
        }
        if (str_contains($source, self::MAIN_START) || str_contains($source, self::MAIN_END)) throw new RuntimeException('Board Studio 启动接入标记不完整，拒绝覆盖');
        // Adopt the exact integrations shipped by 2.1.0, while preserving unrelated imports.
        $legacy = '~^[\t ]*(?:import[\t ]+[\'"]\./views/plugin/boardstudio/i18n/host[\'"]|import\.meta\.glob\([\'"]\./views/plugin/boardstudio/i18n/host\.ts[\'"],[\t ]*\{[\t ]*eager:[\t ]*true[\t ]*\}\))[\t ]*;?[\t ]*(?:\R|$)~m';
        $count = 0;
        $source = preg_replace($legacy, '', $source, -1, $count);
        if ($count > 1) throw new RuntimeException('Board Studio 启动接入重复，拒绝自动覆盖');
        return $bom.($remove ? $source : self::MAIN_START.$nl.self::GLOB.$nl.self::MAIN_END.$nl.$source);
    }

    private function header(string $source, bool $remove): string
    {
        $nl = str_contains($source, "\r\n") ? "\r\n" : "\n";
        $pattern = '~^([\t ]*)'.preg_quote(self::HEADER_START, '~').'\R(.*?)^[\t ]*'.preg_quote(self::HEADER_END, '~').'\R?~ms';
        if (preg_match($pattern, $source, $match)) {
            if (substr_count($source, self::HEADER_START) !== 1 || trim($match[2]) !== self::GUARD) throw new RuntimeException('Board Studio 语言接入片段已被修改，拒绝覆盖；请检查宿主语言处理器');
            return $remove ? str_replace($match[0], $match[1].'reload(50)'.$nl, $source) : $source;
        }
        if (str_contains($source, self::HEADER_START) || str_contains($source, self::HEADER_END)) throw new RuntimeException('Board Studio 语言接入标记不完整，拒绝覆盖');
        if (!preg_match('~const changeLanguage\s*=.*?\{\R(.*?)\R  \}~s', $source, $function, PREG_OFFSET_CAPTURE)) throw new RuntimeException('Board Studio 自动接入失败：不识别的 changeLanguage 处理器');
        $body = $function[1][0];
        if (!str_contains($source, 'const router = useRouter()') || !preg_match('~locale\.value\s*=~', $body) || !str_contains($body, 'userStore.setLanguage(')) throw new RuntimeException('Board Studio 自动接入失败：不支持的宿主语言切换实现');
        $legacy = preg_quote(self::GUARD, '~');
        $guardCount = preg_match_all('~'.$legacy.'~', $body);
        if ($guardCount > 1) throw new RuntimeException('Board Studio 语言刷新保护重复，拒绝自动覆盖');
        if ($remove) {
            if (!$guardCount) return $source;
            $updated = str_replace(self::GUARD, 'reload(50)', $body);
        } else {
            if ($guardCount) $body = str_replace(self::GUARD, 'reload(50)', $body);
            $count = preg_match_all('~^[\t ]*reload\(50\);?[\t ]*$~m', $body);
            if (!$count && !preg_match('~\breload\s*\(~', $body)) return $source; // Already reactive host; no refresh guard needed.
            if ($count !== 1 || preg_match_all('~\breload\s*\(~', $body) !== 1) throw new RuntimeException('Board Studio 自动接入失败：不支持的宿主刷新实现');
            $updated = preg_replace_callback('~^([\t ]*)reload\(50\);?[\t ]*$~m', static fn ($m) => $m[1].self::HEADER_START.$nl.$m[1].self::GUARD.$nl.$m[1].self::HEADER_END, $body);
        }
        return substr_replace($source, $updated, $function[1][1], strlen($function[1][0]));
    }

    private function synchronize(bool $remove): void
    {
        if (!is_dir($this->stateDir) && !mkdir($this->stateDir, 0700, true) && !is_dir($this->stateDir)) throw new RuntimeException('Board Studio 无法创建接入备份目录');
        $lock = fopen($this->stateDir.'/lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('Board Studio 无法锁定前端接入');
        try {
            $changes = $this->plan($remove);
            foreach ($changes as $key => $change) {
                // Content-addressed backups survive plugin upgrades and are never restored over user edits.
                $backup = $this->stateDir.'/'.$key.'-'.hash('sha256', $change['before']).'.bak';
                if (!is_file($backup) && file_put_contents($backup, $change['before'], LOCK_EX) !== strlen($change['before'])) throw new RuntimeException('Board Studio 无法备份宿主文件');
                chmod($backup, 0600);
            }
            $applied = [];
            try {
                foreach ($changes as $key => $change) {
                    // Detect an editor/deployer changing a file after planning.
                    if (file_get_contents($change['path']) !== $change['before']) throw new RuntimeException('Board Studio 接入期间宿主文件发生变化，请重试安装');
                    $this->writeAtomic($change['path'], $change['after']);
                    $applied[] = $key;
                }
                $state = ['frontend' => $this->root, 'active' => !$remove, 'files' => array_keys($changes)];
                $this->writeAtomic($this->stateDir.'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } catch (Throwable $error) {
                $failures = [];
                foreach (array_reverse($applied) as $key) {
                    try {
                        if (file_get_contents($changes[$key]['path']) !== $changes[$key]['after']) throw new RuntimeException('宿主文件在回退前被其它进程修改');
                        $this->writeAtomic($changes[$key]['path'], $changes[$key]['before']);
                    }
                    catch (Throwable $restore) { $failures[] = $changes[$key]['path']; }
                }
                throw new RuntimeException('Board Studio 自动接入失败：'.$error->getMessage().($failures ? '；恢复失败，请使用备份检查：'.implode(', ', $failures) : '；已恢复本次宿主文件修改'), 0, $error);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function writeAtomic(string $path, string $content): void
    {
        $temp = tempnam(dirname($path), '.boardstudio-');
        if ($temp === false) throw new RuntimeException('无法创建宿主文件临时副本：'.$path);
        try {
            if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) throw new RuntimeException('无法写入宿主文件：'.$path);
            chmod($temp, is_file($path) ? fileperms($path) & 0777 : 0600);
            if (is_file($path)) {
                if (fileowner($temp) !== fileowner($path)) @chown($temp, fileowner($path));
                if (filegroup($temp) !== filegroup($path)) @chgrp($temp, filegroup($path));
            }
            if (!rename($temp, $path)) throw new RuntimeException('无法原子替换宿主文件：'.$path);
        } finally { if (is_file($temp)) unlink($temp); }
    }
}
