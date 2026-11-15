# Repository instructions

- 使用中文说明和 Conventional Commits；提交前检查 git status，仅暂存本次相关文件。
- 此仓库仅包含 Board Studio 插件，不引入完整 SaiAdmin 宿主或修改原 saiboard。
- 插件业务源为 server/plugin/boardstudio 与 saiadmin-artd/src/views/plugin/boardstudio。
- 运行 node tools/boardstudio/verify.mjs、node tools/boardstudio/build-player.mjs 和打包校验；宿主 API/数据库修改另需隔离环境集成测试。
- VERSION 是唯一版本源；vX.Y.Z 标签必须与 VERSION 一致。打包使用 python3 tools/boardstudio/package.py --output dist/boardstudio-X.Y.Z.zip。
- 不提交凭据、JWT、数据库转储、运行目录或 node_modules。保留上游版权与构建生成的依赖许可证。
