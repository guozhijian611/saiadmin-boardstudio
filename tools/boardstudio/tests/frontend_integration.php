<?php

declare(strict_types=1);
require __DIR__.'/../../../server/plugin/boardstudio/app/service/FrontendIntegration.php';
use plugin\boardstudio\app\service\FrontendIntegration;
function check(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function fail(callable $run, string $message): void { try { $run(); } catch (RuntimeException $e) { return; } throw new RuntimeException($message); }
function fixture(string $root, string $nl = "\n"): array {
    $main = $root.'/src/main.ts';
    $header = $root.'/src/components/core/layouts/art-header-bar/index.vue';
    @mkdir(dirname($header), 0777, true);
    @mkdir($root.'/src/views/plugin/boardstudio/i18n', 0777, true);
    file_put_contents($root.'/src/views/plugin/boardstudio/i18n/host.ts', 'export {}');
    file_put_contents($main, "import App from './App.vue'".$nl."// user main".$nl);
    file_put_contents($header, implode($nl, ['const router = useRouter()', '  const changeLanguage = (lang: LanguageEnum): void => {', '    if (locale.value === lang) return', '    locale.value = lang', '    userStore.setLanguage(lang)', '    reload(50)', '  }', '// user header', '']));
    return [$main, $header];
}
function clean(string $root): void { if (!is_dir($root)) return; foreach (scandir($root) as $n) if ($n!=='.' && $n!=='..') { $p=$root.'/'.$n; if (is_dir($p)&&!is_link($p)) clean($p); else unlink($p); } rmdir($root); }
$root = sys_get_temp_dir().'/boardstudio-integration-'.bin2hex(random_bytes(6)).'-中文 含空格';
try {
    foreach (["\n", "\r\n"] as $index => $nl) {
        $front=$root.'/host-'.$index; [$main,$header]=fixture($front,$nl);
        if ($index===1) file_put_contents($main,"\xEF\xBB\xBF".file_get_contents($main));
        $before=[file_get_contents($main),file_get_contents($header)];
        $service=new FrontendIntegration($front,$root.'/state-'.$index);
        check(count($service->plan())===2,'Both host integrations must be planned');
        check([file_get_contents($main),file_get_contents($header)]===$before,'Planning must be read-only');
        $service->apply(); $installed=[file_get_contents($main),file_get_contents($header)];
        check(str_contains($installed[0],'import.meta.glob('),'Bootstrap missing');
        check(str_contains($installed[1],"startsWith('/boardstudio/')"),'Language refresh guard missing');
        $service->apply(); check([file_get_contents($main),file_get_contents($header)]===$installed,'Repeat installation changed host');
        file_put_contents($main,file_get_contents($main).'// later main edit'.$nl);
        file_put_contents($header,file_get_contents($header).'// later header edit'.$nl);
        $service->remove();
        check(file_get_contents($main)===$before[0].'// later main edit'.$nl,'Uninstall overwrote user main edits');
        check(file_get_contents($header)===$before[1].'// later header edit'.$nl,'Uninstall overwrote user header edits');
        $removed=[file_get_contents($main),file_get_contents($header)]; $service->remove();
        check([file_get_contents($main),file_get_contents($header)]===$removed,'Repeat removal is not idempotent');
    }
    [$main,$header]=fixture($root.'/manual');
    file_put_contents($main,"import './views/plugin/boardstudio/i18n/host'\n".file_get_contents($main));
    file_put_contents($header,str_replace('reload(50)',"if (!router.currentRoute.value.path.startsWith('/boardstudio/')) reload(50)",file_get_contents($header)));
    $service=new FrontendIntegration($root.'/manual',$root.'/manual-state');$service->apply();$service->apply();
    check(substr_count(file_get_contents($main),'import.meta.glob(')===1,'Manual bootstrap duplicated');
    check(substr_count(file_get_contents($header),"startsWith('/boardstudio/')")===1,'Manual refresh guard duplicated');
    $service->remove();check(!str_contains(file_get_contents($main),'boardstudio'),'Manual integration not adopted for cleanup');
    [$main,$header]=fixture($root.'/unsupported');file_put_contents($header,'custom unsupported header');$snapshot=file_get_contents($main);
    fail(fn()=>(new FrontendIntegration($root.'/unsupported',$root.'/bad-state'))->apply(),'Unsupported host accepted');check(file_get_contents($main)===$snapshot,'Partial unsupported patch');
    [$main,$header]=fixture($root.'/readonly');chmod($header,0444);$snapshot=file_get_contents($main);
    fail(fn()=>(new FrontendIntegration($root.'/readonly',$root.'/ro-state'))->apply(),'Read-only file accepted');check(file_get_contents($main)===$snapshot,'Read-only failure changed main');chmod($header,0644);
    [$main,$header]=fixture($root.'/failure');$snapshot=[file_get_contents($main),file_get_contents($header)];
    $service=new class($root.'/failure',$root.'/failure-state') extends FrontendIntegration {
        private int $writes=0;
        protected function writeAtomic(string $path,string $content):void { if(++$this->writes===2)throw new RuntimeException('injected second file failure');parent::writeAtomic($path,$content); }
    };
    fail(fn()=>$service->apply(),'Injected file failure accepted');check([file_get_contents($main),file_get_contents($header)]===$snapshot,'Failure did not restore both host files');
    [$main,$header]=fixture($root.'/edited');$service=new FrontendIntegration($root.'/edited',$root.'/edited-state');$service->apply();
    file_put_contents($header,str_replace("reload(50)","reload(99)",file_get_contents($header)));$snapshot=file_get_contents($main);
    fail(fn()=>$service->remove(),'Edited managed block silently removed');check(file_get_contents($main)===$snapshot,'Edited managed block caused partial removal');
    echo "PASS automatic frontend integration: plan, install, repeat, manual adoption, CRLF, uninstall, user edits, read-only and failure recovery\n";
} finally { clean($root); }
