# 安全报告

不要在公开 Issue 中粘贴凭据、访问令牌、私有 SQL、数据库转储或可直接利用的漏洞细节。

请优先使用 GitHub 仓库 Security → Advisories → Report a vulnerability 私密报告，附受影响版本、脱敏复现步骤与影响范围。本项目尚未承诺固定响应 SLA。报告时不需要提供生产系统访问权限。

生产部署应使用数据库只读账号、最小资源授权、HTTPS，并独立备份 `runtime/boardstudio-credentials.key`。SQL 解析限制不替代数据库权限隔离。
