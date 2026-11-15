# Board Studio

SaiAdmin 6 独立大屏插件。使用 `boardstudio` 命名空间，不修改原 `saiboard`。V2 将文档、容器布局、组件、数据源、数据集、发布快照与访问授权分开。

## 安装与升级

通过 SaiAdmin 插件管理上传 ZIP，安装并完成前端依赖安装，再重新构建管理端。ZIP 根包含 `info.ini`、`config.json`、`plugin/boardstudio`、`saiadmin-artd/src/views/plugin/boardstudio`。运行依赖为 ECharts 6、vue3-draggable-resizable；Vue 3、Element Plus、Axios 由宿主提供。

验证基线为 SaiAdmin 6.1.5、saipackage 6.1.0、PHP 8.4。支持 MySQL 8.4 / PostgreSQL 17 作为宿主存储；业务数据源支持 MySQL 与 HTTP。PostgreSQL 宿主支持不等于 PostgreSQL 业务取数连接器。

升级前分别备份数据库、插件文件和凭据密钥。迁移幂等，保留旧大屏及历史记录；旧文档在 V2 设计器中显式转换，不自动覆盖。预设模板随包只读发行；历史市场表归档为 `boardstudio_retired_templates`，不再提供模板市场。

卸载默认拒绝删除任何已有业务数据，包括软删除记录、数据集、发布历史和归档模板。先导出备份并明确清理后再卸载；卸载失败时核对迁移状态，不手工伪造已安装状态。凭据密钥不随卸载删除。

## 文档与编辑器

大屏管理的 V2 设计器使用 schemaVersion=2 文档：根容器、嵌套 free/flex/grid 容器、组件节点、共享数据绑定、页面参数。单屏最多 200 节点、12 层、2 MiB；最大设计尺寸 16384 像素。设计器和播放器共享布局渲染。

支持结构树移动和排序、自由布局拖动/缩放、Shift 多选、框选、整组移动、对齐、等距、批量隐藏/锁定/复制/删除。对齐与整组移动要求同一个自由布局父容器；Flex/Grid 位置由容器管理。操作通过命令历史原子撤销。Ctrl/Cmd+S 保存、Ctrl/Cmd+Z 撤销、Ctrl/Cmd+Shift+Z 重做、Ctrl/Cmd+D 复制、Delete 删除；表单输入和预览不截获编辑快捷键。

草稿保存有乐观锁。保存期间继续编辑不会丢失新改动；离开未保存页面需确认。预设可筛选、套用整屏或插入组件，均可撤销。JSON 导入先校验再确认替换；导出包含布局、业务参数和数据集引用，不含连接密钥。跨环境导入须重新核对数据集 ID。旧版编辑器独立保留，其快捷键与 V2 不完全相同。

## 数据源、数据集与刷新

数据源保存连接地址与凭据；数据集保存受限参数化 SQL、可视化关联查询或 HTTP 请求定义。SQL 以只读事务执行，生产连接务必使用专用只读数据库账号；查询编写者属于有权访问底层数据的可信人员。SQL 校验不是替代数据库权限的安全沙箱。可视化关联支持同一连接内表关联，需核对一对多导致的重复汇总。

数据集参数有类型、必填与默认值，绑定可使用固定值或同类型页面参数。可声明输出字段名、标签、类型和是否允许空；执行时检查契约并仅返回声明字段，空结果也返回字段信息。数据集修改生成新版本，发布固定引用版本；更改最新查询不会改变已发布快照。

单次最多 5000 行、结果 2 MiB、查询时限 100–10000ms。HTTP 支持有界 GET/POST 与数组路径，不自动分页。禁止跳转绕过目标校验；私网地址须部署管理员配置精确 `BOARDSTUDIO_HTTP_PRIVATE_ENDPOINTS=host:port`，可另配 `BOARDSTUDIO_HTTP_ALLOWED_HOSTS`。不允许任意 URL 或前端代码执行。

数据集默认刷新 10–3600 秒，组件绑定可独立覆盖或仅首次/手动刷新。缓存 0–3600 秒与前端轮询是不同设置，不刷新整页浏览器。相同绑定复用请求，单屏并发上限 4；完成后安排下一轮，失败退避上限 8 倍，页面隐藏暂停新请求。开启缓存时后端通过 Redis 锁合并相同查询；本地文件锁回退仅保证单机。多主机部署必须共享 Redis 和凭据密钥。

暂时错误保留同查询最后成功结果并标过期；更换参数清空旧数据。401/403/404/409 清空失效结果并停止自动重试，可手动重试或重新加载发布版本。

## 发布与访问

发布创建不可变快照，切换历史版本不覆盖草稿。访问方式为公开、登录并获授权、访问令牌。公开和令牌模式使用所有者有权使用的共享结果；观看者用户/部门行级筛选只允许登录模式，从服务器账号记录注入身份，缓存按范围隔离。页面参数不能覆盖授权身份。HTTP 上游行级隔离由上游实施。租户范围不从页面参数猜测。

资源授权与菜单权限同时生效：数据源 use 用于查询编写、manage 用于连接管理；数据集 use 用于执行（不暴露 SQL）、edit 用于改版本、manage 用于授权；大屏 view 用于已发布内容、manage 用于观看授权。观看权限不授予草稿和连接管理权限。

V2 播放地址 `/app/boardstudio/player/?v=2&code=<编码>`，反向代理有 `/api` 前缀时保留前缀。令牌链接使用 fragment 传入后移除；分享链接即授予相应大屏读取能力。登录模式需有效 SaiAdmin JWT 及观看授权。

## 凭据与备份

连接配置通过 AES-256-GCM 整体加密存储。首次迁移初始化 `runtime/boardstudio-credentials.key`（0600）；也可提供 `BOARDSTUDIO_CREDENTIALS_KEY`，内容为 32 字节随机值的 Base64。环境变量优先，更改后重载 Webman。密钥与数据库分别备份，多实例使用相同密钥。已有密文时缺失密钥将失败，不重建覆盖；当前不提供在线密钥轮换。

读取接口脱敏密码、全部 HTTP 请求头和默认参数。密码空白保留，勾选清空才移除；`[已保存]` 保留旧值，删除 HTTP 键则移除。操作日志不记录请求正文。服务根执行 `php plugin/boardstudio/bin/check-credentials.php` 检查密文可解密，不输出秘密。

## 从源码构建

在仓库根目录运行，脚本不依赖某次临时目录：

```sh
pnpm --dir saiadmin-artd install --frozen-lockfile
node tools/boardstudio/verify.mjs
node tools/boardstudio/build-player.mjs
python3 tools/boardstudio/package.py --output ./dist/boardstudio-2.1.1.zip
```

播放器构建产物随包提供。PHP 语法、管理端类型检查、真实 HTTP、浏览器及双库安装生命周期须另行验证；测试脚本位于 `tools/boardstudio/tests`。测试会创建标记数据，带 `--disposable-fixture` 的脚本仅用于独立临时环境。实际发行验收证据见源码包 `docs/boardstudio-v2.md`，开发构建不等于通过最终发行验收。

## 自动主题与语言接入（2.1.1）

安装器复制文件后执行第九个 Phinx 迁移，由 PHP 服务自动接入宿主 `src/main.ts` 的语言注册与 `changeLanguage` 的插件路由刷新保护。前端目录使用 saipackage 的 `FRONTEND_DIR` 解析结果，不依赖临时路径、Python 或额外 shell 命令。正常安装与升级无需手动运行接入脚本；依赖安装与管理端重建仍走宿主原流程。

后台复用宿主 `--el-*` 语义 token 与 vue-i18n 的 zh/en 全局 Composer。接入只合并插件命名空间，保留宿主默认语言、fallback 以及其它页面刷新行为；用户配置的画布颜色、标题和业务内容独立。播放器使用链接的 lang/theme 初始化界面。

修改前检查支持的宿主结构与写权限，并在 `server/runtime/boardstudio/frontend-integration/` 内备份源文件。重复安装不会插入重复片段；升级会接管 2.1.0 的已知手动接入。接入本身失败时回退本次已改的宿主文件，安装明确失败，不伪造已安装状态。

非空业务库拒绝卸载时，不先修改宿主文件。空库卸载仅移除插件管理的片段，保留其它用户改动；用户自行修改了管理片段时会拒绝自动覆盖。备份保留在 runtime，供排查恢复，不用整份旧备份覆盖宿主新代码。

仅在需要诊断或修复时，可在 server 目录预览实际接入差异：

```sh
php plugin/boardstudio/bin/integrate-frontend.php --frontend ../saiadmin-artd
# 明确需要写入时加 --apply；预览卸载清理时加 --remove
```

不识别的自定义宿主或不可写目录会使安装失败，需核对宿主实现与部署权限；不要跳过失败迁移直接标记已安装。完整说明见源码包 `docs/auto-integration.md`。
