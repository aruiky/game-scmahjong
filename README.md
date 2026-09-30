# 🀄 四川麻将 · PHP + Docker 部署

这是一个完整可玩的四川麻将（血战到底）网页游戏。PHP 服务端管理牌墙、手牌、AI 行动和牌局记录，浏览器端负责界面交互。

## 游戏特色
- ✅ **四川血战到底规则**：定缺、碰杠、胡牌后继续
- ✅ **108 张麻将牌图片**：万、索、筒各 1–9，每种牌四张
- ✅ **刮风下雨（杠牌）**：明杠/暗杠机制
- ✅ **查花猪查大叫**：流局结算
- ✅ **AI电脑对手**：3个AI陪打
- ✅ **PHP 服务端游戏逻辑**：对局状态保存在 PHP Session 中
- ✅ **历史牌局**：已结束牌局及完整操作记录保存在 `./data/games.json`，可从游戏页查阅
- ✅ **无需数据库**：适合单人对战和本地部署
- ✅ **手机/电脑浏览器适配**

## 快速启动

### 方式一：Docker Compose 一键启动（推荐）

```bash
cd sichuan-mahjong
docker-compose up -d
```

启动后浏览器访问：`http://你的服务器IP:7180`

已结束牌局会保存到项目目录的 `data` 文件夹；历史记录页面可从游戏页右上角或开始页面进入。

### 时区配置

默认时区为 `Asia/Shanghai`。使用 Docker Compose 时，可在项目根目录的 `.env` 文件中设置时区，例如：

```env
MAHJONG_TIMEZONE=America/Los_Angeles
```

使用 PHP 内置开发服务器时，也可通过环境变量 `MAHJONG_TIMEZONE` 设置。请使用 PHP 支持的时区标识（如 `Asia/Tokyo` 或 `UTC`）；该设置会统一影响牌局操作记录和历史页面的日期时间显示。

网页目录以只读方式挂载到容器的 `/project-html`。容器启动时会将网页复制到容器内的 `/var/www/html` 并设置 Apache 可读的权限，因此无需构建项目镜像。修改网页文件后，重新创建或重启容器以复制最新文件。

如果飞牛出现 `Failed opening required '/var/www/html/index.php'` 或 `Permission denied`，请确认 Compose 项目目录中的 `html/index.php` 可被容器读取，并重新创建容器；无需重新构建镜像。

### 方式二：Docker 命令直接运行

```bash
cd sichuan-mahjong
docker build -t sichuan-mahjong .
docker run -d -p 7180:80 -v "$(pwd)/data:/app/data" --name sichuan-mahjong --restart always sichuan-mahjong
```

### 方式三：使用 PHP 内置开发服务器

需要安装 PHP 8.1 或更高版本，在项目根目录运行：

```bash
php -S 127.0.0.1:8080 -t html
```

浏览器访问：`http://127.0.0.1:8080`

不能直接用 `file://` 打开页面，因为游戏操作需要请求 PHP 接口。

## 游戏规则说明

### 核心规则
1. **定缺**：开局必须选择一门花色定缺，整局不能有该花色的牌才能胡牌
2. **血战到底**：一家胡牌后不结束，剩下玩家继续打，直到3家胡或牌墙打空
3. **碰杠**：可以碰任意一家的牌，可以明杠、暗杠（四川麻将不能吃牌）
4. **胡牌牌型**：平胡、对对胡、清一色、七对、杠上花等
5. **流局结算**：查花猪（没打缺的包赔）、查大叫

### 操作方式
- **点击手牌**选中牌，再次点击或点击"出牌"按钮打出
- 可碰/杠/胡时会弹出对应按钮
- 有定缺牌时必须先打光定缺才能打其他牌

## 端口修改
如需修改访问端口，编辑 `docker-compose.yml` 中的端口映射：
```yaml
ports:
  - "你要的端口:80"
```

## 停止游戏
```bash
docker-compose down
```

## 文件结构
```
sichuan-mahjong/
├── Dockerfile           # PHP Apache 镜像构建文件
├── docker-compose.yml   # Docker Compose 配置
├── html/
│   ├── index.php        # 游戏页面
│   ├── app.js           # 浏览器界面与交互
│   ├── api.php          # 游戏接口
│   ├── game.php         # 服务端规则与 AI
│   ├── config.php       # 时区等运行配置
│   ├── time.js          # 统一时间格式化
│   ├── history.php      # 历史牌局页面
│   ├── history.js       # 历史牌局页面交互
│   ├── history_store.php # 历史牌局持久化
│   └── paimian/         # 108 张牌面图片及 CC0 许可
├── data/                # Docker 挂载的数据目录
└── README.md            # 本说明文件
```

## 注意事项
- 本游戏使用 PHP Session 保存当前牌局状态；已结束牌局写入 Docker 挂载目录 `./data`，无需数据库
- AI 对手和规则由 PHP 服务端处理；刷新页面后点击“开始游戏”会开启新牌局
- 本项目是单人对战版本；多人联机对战还需增加账号、房间和实时通信服务
