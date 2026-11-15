# 构建与发布

`VERSION` 控制 ZIP 元数据和发行文件名；标签必须为 `v` 加完整版本。`saiadmin-artd/package.json` 的版本仅描述工具链，不作为插件版本源。

CI 使用锁定依赖，检查 PHP 语法、30 项 TypeScript 测试、PHP 校验、自动接入回归、ESLint，然后构建播放器、收集其实际依赖许可证，生成安装包和独立源码 ZIP。打包器固定 ZIP 时间和权限，按文件名排序；同一文件内容和 `SOURCE_DATE_EPOCH` 产生相同 ZIP。不同操作系统构建产物可能不同，不把跨平台构建误称为字节级可重现。

CI 仅有 contents:read。标签发布使用独立 job、最小 contents:write 权限和仓库 GITHUB_TOKEN；无需个人 token 或额外 secrets。Action 依赖固定提交 SHA。PR 不获得发布权限，不使用 pull_request_target。

发布 job 下载测试通过的产物，不重新构建；先检查 SHA256，再创建 Release。生成安装 ZIP、独立源码 ZIP、SHA256SUMS.txt；GitHub 自动生成的 source archive 仅供查源码，不能直接导入 SaiAdmin。

业务验证见 [V2 验收](boardstudio-v2.md)、[主题与多语言](theme-i18n.md)和 [2.1.1 自动接入](auto-integration.md)。CI 不运行完整宿主、浏览器和数据库生命周期；安装/迁移及发布授权改动仍需单独验收。每次交付以 Release 自带校验值为准，不复用历史本地包的校验值。

手动重跑 Release 适用于失败且尚未创建 Release 的标签。已有 Release 不自动删除或覆盖，避免改变已经分发的文件。
