<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$mahjongTimezone = configureMahjongTimezone();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="mahjong.png">
<title>历史牌局 · 四川麻将</title>
<style>
* { box-sizing: border-box; }
body {
  min-height: 100vh;
  margin: 0;
  padding: 116px 16px 28px;
  color: #fff;
  font-family: "Microsoft YaHei", sans-serif;
  background:
    radial-gradient(circle at top, rgba(255,255,255,.16), transparent 34%),
    linear-gradient(145deg, #0f5d34, #083c1f 72%);
}
.history-shell { width: min(900px, 100%); margin: 0 auto; }
.history-header {
  position: fixed;
  top: 12px;
  left: 50%;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  width: min(calc(100% - 32px), 900px);
  margin: 0;
  padding: 18px 22px;
  transform: translateX(-50%);
  border: 1px solid rgba(212,175,55,.65);
  border-radius: 16px;
  background: rgba(8,24,15,.78);
}
.history-header h1 { display: flex; align-items: center; gap: 10px; margin: 0; color: #ffe29a; font-size: 23px; }
.history-logo { width: 36px; height: 36px; border-radius: 9px; object-fit: cover; }
.history-header-actions { display: flex; align-items: center; gap: 14px; }
.history-header-actions a, .back-button {
  color: #ffe29a;
  text-decoration: none;
}
.history-header-actions a:hover, .back-button:hover { color: #fff; }
.history-header-actions [hidden] { display: none; }
.back-button {
  padding: 0;
  border: 0;
  font: inherit;
  background: none;
  cursor: pointer;
}
.history-panel {
  padding: 20px;
  border: 1px solid rgba(212,175,55,.35);
  border-radius: 16px;
  background: rgba(8,24,15,.7);
  box-shadow: 0 12px 30px rgba(0,0,0,.18);
}
.history-status { padding: 28px 12px; color: rgba(255,255,255,.7); text-align: center; }
.history-list { display: grid; gap: 10px; }
.history-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
  padding: 16px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 12px;
  color: inherit;
  text-align: left;
  background: rgba(255,255,255,.045);
  cursor: pointer;
}
.history-item:hover { border-color: rgba(212,175,55,.65); background: rgba(212,175,55,.08); }
.history-item strong { display: block; margin-bottom: 6px; color: #f8e6b0; }
.history-item small { color: rgba(255,255,255,.58); }
.history-result { flex: 0 0 auto; color: #ffe29a; font-size: 13px; }
.detail-heading { margin: 0 0 14px; color: #ffe29a; }
.detail-summary { margin-bottom: 18px; color: rgba(255,255,255,.7); font-size: 14px; line-height: 1.8; white-space: pre-line; }
.history-log { display: grid; gap: 8px; }
.history-record {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr);
  gap: 12px;
  padding: 9px 10px;
  border-bottom: 1px solid rgba(255,255,255,.07);
  font-size: 13px;
  line-height: 1.6;
}
.history-record time { color: #d9c37e; }
.history-record-details { min-width: 0; color: rgba(255,255,255,.88); }
.history-winning-hand {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.history-winning-hand-label { color: #ffe29a; }
.history-winning-hand-tiles { display: flex; flex-wrap: wrap; gap: 3px; }
.history-winning-tile {
  display: block;
  width: 28px;
  height: 38px;
  object-fit: contain;
  border-radius: 4px;
  background: #f8f4e9;
}
@media (max-width: 520px) {
  body { padding: 132px 10px 16px; }
  .history-header { top: 10px; width: calc(100% - 20px); padding: 15px; }
  .history-header h1 { font-size: 19px; }
  .history-header-actions { gap: 10px; font-size: 13px; }
  .history-panel { padding: 12px; }
  .history-item { align-items: flex-start; padding: 13px; }
  .history-result { font-size: 12px; }
}
</style>
</head>
<body>
<main class="history-shell" data-timezone="<?= htmlspecialchars($mahjongTimezone, ENT_QUOTES, 'UTF-8') ?>">
  <header class="history-header">
    <h1><img class="history-logo" src="mahjong.png" alt="">历史牌局</h1>
    <nav class="history-header-actions" aria-label="历史牌局导航">
      <button class="back-button" id="historyListButton" type="button" hidden>← 返回列表</button>
      <a href="index.php">返回游戏</a>
    </nav>
  </header>
  <section class="history-panel" id="historyPanel" aria-live="polite">
    <div class="history-status">正在读取牌局历史…</div>
  </section>
</main>
<script src="time.js" defer></script>
<script src="history.js" defer></script>
</body>
</html>
