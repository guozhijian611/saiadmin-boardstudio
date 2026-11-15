# 贡献指南

先阅读 README 的范围、构建命令与 AGENTS.md。基于 main 创建分支；修改保持在插件目录，使用 Conventional Commits。

提交 PR 时描述问题、行为变化和验证结果。至少运行 `node tools/boardstudio/verify.mjs`、播放器构建与包检查。数据库迁移、权限和宿主路由变更必须补充独立 SaiAdmin 环境中的实际验证；保留非空卸载保护与旧数据。

不要提交凭据、数据库、运行目录或真实用户数据。示例使用虚构数据。新依赖说明用途及许可证，构建产物由 CI 生成，不提交 node_modules 或播放器压缩包。
