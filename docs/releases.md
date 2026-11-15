# 构建与发布

`VERSION` 控制 ZIP 元数据和发行文件名；标签必须为 `v` 加完整版本。`saiadmin-artd/package.json` 的版本仅描述工具链，不作为插件版本源。

CI 使用锁定依赖，检查 PHP 语法、24 项核心测试、PHP 校验、ESLint，然后构建播放器、收集其实际依赖许可证，生成安装包和独立源码 ZIP。打包器固定 ZIP 时间和权限，按文件名排序；同一文件内容和 `SOURCE_DATE_EPOCH` 产生相同 ZIP。不同操作系统构建产物可能不同，不把跨平台构建误称为字节级可重现。

CI 仅有 contents:read。标签发布使用独立 job、最小 contents:write 权限和仓库 GITHUB_TOKEN；无需个人 token 或额外 secrets。Action 依赖固定提交 SHA。PR 不获得发布权限，不使用 pull_request_target。

发布 job 下载测试通过的产物，不重新构建；先检查 SHA256，再创建 Release。生成安装 ZIP、独立源码 ZIP、SHA256SUMS.txt；GitHub 自动生成的 source archive 仅供查源码，不能直接导入 SaiAdmin。

本轮开源工具链调整没有改变业务 PHP/TS 代码；增加依赖许可证、仓库地址、版本来源和确定性归档，因此 GitHub Release 的 ZIP 校验值将不同于此前本地交付包。历史业务验证见 boardstudio-v2.md。CI 不运行完整宿主、浏览器和数据库生命周期；安装/迁移及发布授权改动仍需单独验收。

手动重跑 Release 适用于失败且尚未创建 Release 的标签。已有 Release 不自动删除或覆盖，避免改变已经分发的文件。
