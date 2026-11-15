# Third-party notices

Board Studio originated in [B8AIAdmin](https://github.com/guozhijian611/B8AIAdmin), based on the SaiAdmin/Webman ecosystem. The extracted plugin baseline is B8AIAdmin commit `159535b`; that upstream checkout was developed on branch `codex/boardstudio-refactor`.

- Webman plugin scaffolding: MIT, original notice retained in `server/plugin/boardstudio/LICENSE`.
- Frontend chart code adapted from Art Design Pro: MIT, Copyright (c) 2025 SuperManTT, original notice retained in `server/plugin/boardstudio/FRONTEND-LICENSE`.
- Vue, Vue Router, Element Plus, Axios, ECharts and their bundled dependencies retain their respective licenses. The player build generates `server/plugin/boardstudio/public/player/THIRD-PARTY-LICENSES.txt` from the actual Rollup module graph. This file is included in the install ZIP. ECharts is Apache-2.0; its license and notice are preserved.
- Element Plus 2.11.4 omits its MIT license text from the npm package. The exact upstream notice from https://github.com/element-plus/element-plus/blob/2.11.4/LICENSE is retained in `tools/boardstudio/licenses/element-plus-2.11.4.txt` and used by the bundle notice generator.
- SaiAdmin and other host dependencies are not redistributed as a complete server. Their own licensing requirements still apply to your host installation.

New Board Studio work is provided under the root MIT license. This does not replace the licenses of bundled third-party components.
