from pathlib import Path
import tempfile,subprocess,sys
script=Path(__file__).resolve().parents[3]/'server/plugin/boardstudio/bin/integrate-frontend.py'
with tempfile.TemporaryDirectory() as temp:
 root=Path(temp);main=root/'src/main.ts';header=root/'src/components/core/layouts/art-header-bar/index.vue';header.parent.mkdir(parents=True)
 main.write_text("import App from './App.vue'\n")
 header.write_text("const router = useRouter()\n  const changeLanguage = (lang: string): void => {\n    locale.value = lang\n    userStore.setLanguage(lang)\n    reload(50)\n  }\n")
 before=[x.read_bytes() for x in [main,header]]
 def run(*args):return subprocess.run([sys.executable,str(script),'--frontend',str(root),*args],capture_output=True)
 assert run().returncode==0 and [x.read_bytes() for x in [main,header]]==before
 assert run('--apply').returncode==0
 after=[x.read_bytes() for x in [main,header]]
 assert b"/boardstudio/" in after[1] and b'userStore.setLanguage(lang)' in after[1]
 assert run('--apply').returncode==0 and [x.read_bytes() for x in [main,header]]==after
 header.write_text('unsupported source')
 snapshot=main.read_bytes()
 assert run('--apply').returncode!=0 and main.read_bytes()==snapshot
print('PASS host integration dry-run, idempotence, retained host language state and unsupported-host refusal')
