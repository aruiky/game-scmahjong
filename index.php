<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$mahjongTimezone = configureMahjongTimezone();
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="mahjong.png">
<title>四川麻将 · 血战到底</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; user-select: none; }
body {
  font-family: "Microsoft YaHei", sans-serif;
  background:
    radial-gradient(circle at top, rgba(255,255,255,0.18), transparent 32%),
    radial-gradient(circle at bottom right, rgba(108, 180, 114, 0.15), transparent 28%),
    linear-gradient(135deg, #0f5d34 0%, #0a4323 32%, #083c1f 100%);
  min-height: 100vh;
  color: #fff;
  overflow: hidden;
}
body::before {
  content: "";
  position: fixed;
  inset: 0;
  background: repeating-linear-gradient(
    135deg,
    rgba(255,255,255,0.02),
    rgba(255,255,255,0.02) 2px,
    transparent 2px,
    transparent 6px
  );
  pointer-events: none;
}
.container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  max-width: 1400px;
  margin: 0 auto;
  padding: 12px 16px 16px;
  position: relative;
  z-index: 1;
}
.header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  position: relative;
  text-align: center;
  padding: 14px 18px;
  background: linear-gradient(180deg, rgba(24, 40, 30, 0.8), rgba(9, 21, 15, 0.8));
  backdrop-filter: blur(6px);
  border: 1px solid rgba(212, 175, 55, 0.8);
  border-bottom: none;
  border-radius: 18px 18px 0 0;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
  font-size: clamp(20px, 2vw, 28px);
  font-weight: 800;
  letter-spacing: 1px;
  text-shadow: 0 2px 6px rgba(0,0,0,0.35);
}
.header-logo {
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  border-radius: 10px;
  object-fit: cover;
}
.history-link {
  position: absolute;
  top: 50%;
  right: 18px;
  transform: translateY(-50%);
  color: #ffe29a;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
}
.history-link:hover { color: #fff; }
.game-area {
  flex: 1;
  display: grid;
  grid-template-rows: 120px 1fr 200px;
  padding: 14px 12px 12px;
  gap: 12px;
  background: linear-gradient(180deg, rgba(17, 39, 27, 0.42), rgba(7, 18, 12, 0.55));
  border-radius: 0 0 18px 18px;
  border: 1px solid rgba(212, 175, 55, 0.35);
  border-top: none;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), inset 0 -12px 20px rgba(0,0,0,0.12);
}
.opponent-area {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: stretch;
}
.player-info {
  position: relative;
  background: linear-gradient(180deg, rgba(12, 26, 20, 0.72), rgba(8, 16, 12, 0.7));
  border-radius: 14px;
  padding: 10px 12px 12px;
  min-width: 190px;
  text-align: center;
  border: 1px solid rgba(212, 175, 55, 0.5);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), 0 10px 22px rgba(0,0,0,0.12);
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.player-info:hover { transform: translateY(-2px); }
.player-info.active {
  border-color: rgba(255, 105, 105, 0.9);
  box-shadow: 0 0 18px rgba(255, 107, 107, 0.35);
}
.player-info.hu { background: linear-gradient(180deg, rgba(66, 39, 8, 0.38), rgba(30, 15, 6, 0.62)); }
.player-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 6px;
}
.seat-mark {
  display: inline-flex;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  background: rgba(228, 191, 78, 0.18);
  border: 1px solid rgba(228, 191, 78, 0.75);
  font-size: 11px;
  color: #f3cf73;
  font-weight: 800;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
}
.turn-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #ff6b6b;
  box-shadow: 0 0 12px rgba(255, 107, 107, 0.9);
  opacity: 0;
  transition: opacity 0.2s ease;
}
.player-info.active .turn-dot { opacity: 1; }
.player-name {
  font-size: 14px;
  margin-bottom: 6px;
  color: #f9f1da;
  font-weight: 700;
}
.player-tiles {
  font-size: 12px;
  color: #d9d9d9;
  margin-bottom: 4px;
}
.player-score {
  font-size: 16px;
  color: #ffd700;
  font-weight: bold;
  margin-bottom: 4px;
}
.table-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  padding: 22px 18px 14px;
  border-radius: 26px;
  background: radial-gradient(circle at center, rgba(36, 98, 61, 0.9) 0%, rgba(20, 58, 39, 0.95) 32%, rgba(8, 35, 22, 0.97) 100%);
  border: 2px solid rgba(214, 180, 75, 0.4);
  box-shadow: inset 0 0 50px rgba(145, 214, 110, 0.09), 0 18px 36px rgba(0,0,0,0.18);
}
.table-center::before {
  content: "";
  position: absolute;
  inset: 12px;
  border-radius: 18px;
  border: 1px solid rgba(255,255,255,0.06);
  pointer-events: none;
}
.table-center::after {
  content: "";
  position: absolute;
  width: 74%;
  height: 70%;
  border-radius: 50%;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  box-shadow: inset 0 0 30px rgba(255,255,255,0.02);
}
.discard-area {
  width: 100%;
  max-width: 1280px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 12px;
  position: relative;
  z-index: 1;
}
.discard-pile {
  min-width: 0;
  height: clamp(58px, 12vh, 104px);
  background: rgba(14, 36, 24, 0.52);
  border: 1px solid rgba(212, 175, 55, 0.25);
  border-radius: 10px;
  padding: 5px;
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  align-content: flex-start;
  justify-content: flex-start;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: rgba(212, 175, 55, 0.45) transparent;
  box-shadow: inset 0 1px 4px rgba(0,0,0,0.15);
}
.discard-pile .tile {
  width: 28px;
  height: 36px;
  font-size: 11px;
  margin: 0;
}
.game-log {
  position: absolute;
  left: 18px;
  bottom: 14px;
  width: min(320px, calc(100% - 36px));
  max-height: 96px;
  margin: 0;
  overflow: hidden;
  z-index: 2;
  border: 1px solid rgba(212, 175, 55, 0.24);
  border-radius: 10px;
  background: rgba(5, 20, 12, 0.34);
}
.game-log-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 5px 10px;
  color: #e7ca7b;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  border-bottom: 1px solid rgba(212, 175, 55, 0.14);
}
.game-log-count { color: rgba(255,255,255,0.55); font-weight: 400; letter-spacing: 0; }
.game-log-list {
  max-height: 66px;
  padding: 3px 10px 5px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(212, 175, 55, 0.45) transparent;
}
.game-log-entry {
  display: flex;
  gap: 8px;
  align-items: baseline;
  min-height: 18px;
  color: rgba(255,255,255,0.8);
  font-size: 11px;
  line-height: 1.5;
}
.game-log-entry.latest { color: #fff0bd; }
.game-log-entry.system { color: #d5e4d2; }
.game-log-entry.action { color: #ffe29a; }
.game-log-time {
  flex: 0 0 38px;
  color: rgba(255,255,255,0.42);
  font-variant-numeric: tabular-nums;
}
.game-log-message { min-width: 0; }
.status-bar {
  position: relative;
  z-index: 1;
  font-size: 16px;
  margin-bottom: 12px;
  min-height: 30px;
  text-align: center;
  color: #ffe29a;
  font-weight: 700;
  text-shadow: 0 2px 8px rgba(0,0,0,0.25);
  padding: 7px 16px;
  border-radius: 999px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
}
.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  min-height: 42px;
  position: relative;
  z-index: 1;
}
.action-btn {
  padding: 10px 18px;
  border: 1px solid rgba(255,255,255,0.5);
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
  font-weight: bold;
  transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
  background: linear-gradient(180deg, #52b65e, #2d8a3d);
  color: white;
  box-shadow: 0 6px 16px rgba(41, 126, 62, 0.35);
}
.action-btn:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18);
}
.action-btn:active { transform: translateY(0); }
.action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.action-btn.peng { background: linear-gradient(180deg, #3aa0ff, #1b72d6); }
.action-btn.gang { background: linear-gradient(180deg, #ffb14a, #e77a12); }
.action-btn.hu { background: linear-gradient(180deg, #ff5a5a, #d92c2c); }
.action-btn.pass { background: linear-gradient(180deg, #7d7d7d, #4d4d4d); }
.hand-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 14px 12px 10px;
  background: linear-gradient(180deg, rgba(18, 30, 22, 0.7), rgba(12, 20, 16, 0.8));
  border-radius: 16px;
  border: 1px solid rgba(212, 175, 55, 0.25);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.04), 0 8px 18px rgba(0,0,0,0.12);
}
.hand-info {
  margin-bottom: 10px;
  font-size: 14px;
  color: #dfe7dc;
  letter-spacing: 0.4px;
}
.hand-tiles {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 4px;
  min-height: 66px;
}
.tile-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.tile {
  --suit-border: #c8b99a;
  width: 42px;
  height: 60px;
  background:
    linear-gradient(135deg, #fff 0%, #f8f4e9 62%, #e5d9bf 100%);
  border: 1px solid var(--suit-border);
  border-radius: 6px 7px 7px 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
  position: relative;
  box-shadow: 0 2px 0 #c4b698, 0 4px 7px rgba(0,0,0,0.24), inset 0 1px 0 #fff;
  overflow: hidden;
}
.tile-art {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: 100%;
  padding: 3px;
  object-fit: contain;
  pointer-events: none;
}
.tile.selected {
  transform: translateY(-12px);
  box-shadow: 0 12px 18px rgba(0,0,0,0.25);
  filter: drop-shadow(0 0 10px rgba(255, 210, 88, 0.7));
}
.tile:hover { transform: translateY(-6px); }
.tile.selected:hover { transform: translateY(-12px); }
.tile.dora { border: 2px solid #ffd700; }
.meld-tiles { display: flex; gap: 2px; margin: 0 4px; }
.meld-tiles .tile {
  width: 32px;
  height: 46px;
  font-size: 18px;
  cursor: default;
}
.meld-tiles .tile:hover { transform: none; }
.opp-hand { display: flex; gap: 2px; justify-content: center; margin-top: 4px; flex-wrap: wrap; }
.opp-hand .tile-back {
  width: 24px;
  height: 32px;
  background: linear-gradient(135deg, #173d8a, #0d2d67 50%, #132a54);
  border: 1px solid rgba(12, 10, 18, 0.9);
  border-radius: 5px;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
}
.que-badge {
  display: inline-block;
  padding: 3px 7px;
  border-radius: 999px;
  font-size: 11px;
  margin-left: 4px;
  font-weight: 700;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2);
}
.que-wan { background: #d9534f; }
.que-tiao { background: #4caf50; }
.que-tong { background: #4a90e2; }
.round-info {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: 12px;
  background: rgba(6, 12, 13, 0.56);
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(212, 175, 55, 0.5);
  color: #f5d67a;
  font-weight: 700;
}
.modal {
  position: fixed;
  top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.72);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
  backdrop-filter: blur(4px);
}
.modal-content {
  background: linear-gradient(180deg, #fff, #f4f6f7);
  color: #000;
  padding: 30px 26px 22px;
  border-radius: 16px;
  text-align: center;
  max-width: 500px;
  box-shadow: 0 18px 48px rgba(0,0,0,0.32);
  border: 1px solid rgba(0,0,0,0.08);
}
.modal-content.review-content {
  display: flex;
  flex-direction: column;
  width: min(720px, calc(100vw - 32px));
  max-width: 720px;
  max-height: min(82vh, 760px);
  padding: 26px;
  color: #fff;
  text-align: left;
  background: linear-gradient(155deg, #173b29, #092316 72%);
  border: 1px solid rgba(212, 175, 55, 0.52);
}
.review-heading { text-align: center; flex: 0 0 auto; }
.review-heading h2 {
  margin: 0 0 6px;
  color: #ffe29a;
  font-size: 24px;
  letter-spacing: 2px;
}
.review-heading p {
  margin: 0;
  color: rgba(255,255,255,0.62);
  font-size: 13px;
}
.review-summary {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 16px 0;
  flex: 0 0 auto;
}
.review-result-chip {
  padding: 6px 12px;
  border: 1px solid rgba(212, 175, 55, 0.28);
  border-radius: 999px;
  color: #f7e5ae;
  background: rgba(255,255,255,0.06);
  font-size: 12px;
}
.review-result-chip.hu { color: #ffe19a; background: rgba(212, 175, 55, 0.16); }
.review-result-chip.flower { color: #ffcccc; border-color: rgba(255, 107, 107, 0.32); }
.review-log {
  min-height: 0;
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 8px 12px;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  background: rgba(0,0,0,0.2);
  scrollbar-width: thin;
  scrollbar-color: rgba(212, 175, 55, 0.45) transparent;
}
.review-log:empty::after {
  content: "本局暂无记录";
  display: block;
  padding: 20px;
  color: rgba(255,255,255,0.5);
  text-align: center;
}
.review-log .game-log-entry {
  min-height: 28px;
  align-items: flex-start;
  padding: 5px 0;
  border-bottom: 1px solid rgba(255,255,255,0.045);
  font-size: 13px;
}
.review-log .game-log-time { flex-basis: 58px; padding-top: 1px; }
.review-actions {
  display: flex;
  justify-content: center;
  padding-top: 16px;
  flex: 0 0 auto;
}
.review-actions .modal-btn { margin-top: 0; min-width: 190px; font-weight: 700; }
.modal-content h2 { margin-bottom: 18px; color: #c62828; }
.modal-content p { margin-bottom: 10px; font-size: 16px; line-height: 1.8; }
.modal-btn {
  margin-top: 20px; padding: 10px 30px; font-size: 16px;
  background: linear-gradient(180deg, #4CAF50, #2e7d32);
  color: white; border: none; border-radius: 8px;
  cursor: pointer;
  box-shadow: 0 8px 16px rgba(76,175,80,0.25);
}
.start-screen {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 24px;
  background:
    radial-gradient(ellipse at 50% 42%, rgba(39, 107, 66, 0.72), transparent 52%),
    linear-gradient(145deg, rgba(3, 20, 12, 0.97), rgba(7, 47, 27, 0.98) 50%, rgba(3, 23, 15, 0.99));
}
.start-screen::before,
.start-screen::after {
  content: "";
  position: absolute;
  width: min(70vw, 720px);
  aspect-ratio: 1;
  border: 1px solid rgba(226, 194, 105, 0.1);
  border-radius: 50%;
  pointer-events: none;
}
.start-screen::after {
  width: min(54vw, 560px);
  border-color: rgba(226, 194, 105, 0.08);
}
.start-card {
  position: relative;
  z-index: 1;
  width: min(100%, 560px);
  padding: clamp(32px, 7vw, 62px) 28px 38px;
  text-align: center;
  background: linear-gradient(145deg, rgba(20, 55, 37, 0.76), rgba(5, 26, 17, 0.82));
  border: 1px solid rgba(218, 184, 85, 0.52);
  border-radius: 28px;
  box-shadow: 0 28px 90px rgba(0,0,0,0.36), inset 0 1px 0 rgba(255,255,255,0.1);
  backdrop-filter: blur(12px);
}
.start-mark {
  display: block;
  width: 76px;
  height: 76px;
  margin: 0 auto 18px;
  border-radius: 19px;
  object-fit: cover;
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.start-title {
  color: #fff4d3;
  font-size: clamp(30px, 6vw, 46px);
  font-weight: 900;
  letter-spacing: 4px;
  text-shadow: 0 4px 18px rgba(0,0,0,0.35);
}
.start-subtitle {
  margin-top: 10px;
  color: #d5c58e;
  font-size: 14px;
  letter-spacing: 5px;
}
.start-divider {
  width: 74px;
  height: 1px;
  margin: 28px auto 22px;
  background: linear-gradient(90deg, transparent, #d4af37, transparent);
}
.start-description {
  color: rgba(241, 242, 225, 0.78);
  font-size: 14px;
  line-height: 1.9;
}
.start-features {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 22px 0 28px;
}
.start-feature {
  padding: 6px 12px;
  border: 1px solid rgba(222, 197, 126, 0.24);
  border-radius: 999px;
  color: #e9d99f;
  background: rgba(255,255,255,0.045);
  font-size: 12px;
}
.start-button {
  min-width: 210px;
  padding: 14px 32px;
  border: 1px solid rgba(255, 239, 185, 0.7);
  border-radius: 12px;
  color: #3b2a0b;
  background: linear-gradient(180deg, #f4d988, #d6ad4e);
  box-shadow: 0 10px 26px rgba(182, 139, 44, 0.26), inset 0 1px 0 rgba(255,255,255,0.6);
  font: inherit;
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 2px;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}
.start-button:hover {
  transform: translateY(-2px);
  filter: brightness(1.06);
  box-shadow: 0 14px 30px rgba(182, 139, 44, 0.34), inset 0 1px 0 rgba(255,255,255,0.65);
}
.start-button:active { transform: translateY(0); }
.start-button:focus-visible { outline: 3px solid #fff1be; outline-offset: 4px; }
.start-note {
  margin-top: 18px;
  color: rgba(255,255,255,0.42);
  font-size: 11px;
  letter-spacing: 1px;
}
.start-history-link {
  display: inline-block;
  margin-top: 16px;
  color: #ffe29a;
  font-size: 13px;
  text-decoration: none;
}
.start-history-link:hover { color: #fff; }
@media (max-width: 900px) {
  body { overflow: auto; }
  .container { height: auto; min-height: 100vh; }
  .game-area { grid-template-rows: auto auto auto; }
  .opponent-area {
    flex-wrap: wrap;
    justify-content: center;
  }
  .player-info { min-width: 140px; flex: 1 1 40%; }
  .discard-area {
    max-width: 100%;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }
  .game-log {
    position: relative;
    left: auto;
    bottom: auto;
    width: 100%;
    max-width: 700px;
    margin: 0 0 12px;
    align-self: center;
  }
  .tile { width: 36px; height: 52px; }
}
@media (max-width: 480px) {
  .start-screen { padding: 16px; }
  .start-card { border-radius: 22px; }
  .start-subtitle { letter-spacing: 3px; }
  .start-features { gap: 6px; }
  .modal-content.review-content { padding: 20px 14px 14px; max-height: 90vh; }
  .review-heading h2 { font-size: 21px; }
  .review-log .game-log-entry { font-size: 12px; }
}
</style>
</head>
<body>
<div class="container" data-timezone="<?= htmlspecialchars($mahjongTimezone, ENT_QUOTES, 'UTF-8') ?>">
  <div class="header">
    <img class="header-logo" src="mahjong.png" alt="">
    <span>四川麻将 · 血战到底</span>
    <a class="history-link" href="history.php">历史牌局</a>
  </div>
  <div class="game-area">
    <div class="opponent-area" id="opponentArea"></div>
    <div class="table-center">
      <div class="round-info" id="roundInfo"></div>
      <div class="status-bar" id="statusBar">游戏加载中...</div>
      <div class="discard-area" id="discardArea"></div>
      <section class="game-log" aria-label="牌局记录">
        <div class="game-log-header">
          <span>牌局记录</span>
          <span class="game-log-count" id="gameLogCount">0 条</span>
        </div>
        <div class="game-log-list" id="gameLogList" aria-live="polite"></div>
      </section>
      <div class="action-buttons" id="actionButtons"></div>
    </div>
    <div class="hand-area" id="handArea">
      <div class="hand-info" id="handInfo"></div>
      <div class="hand-tiles" id="handTiles"></div>
    </div>
  </div>
</div>
<div id="modal" class="modal" style="display:none;">
  <div class="modal-content" id="modalContent"></div>
</div>
<main class="start-screen" id="startScreen">
  <section class="start-card" aria-labelledby="startTitle">
    <img class="start-mark" src="mahjong.png" alt="">
    <h1 class="start-title" id="startTitle">四川麻将</h1>
    <p class="start-subtitle">血 战 到 底</p>
    <div class="start-divider"></div>
    <p class="start-description">牌局已备好，邀你入座。<br>与三位牌友切磋牌技，体验川麻血战到底。</p>
    <div class="start-features" aria-label="玩法特色">
      <span class="start-feature">三人 AI 对战</span>
      <span class="start-feature">定缺玩法</span>
      <span class="start-feature">碰 · 杠 · 胡</span>
    </div>
    <button class="start-button" type="button" onclick="startGame()">开始游戏</button>
    <p class="start-note">准备好后点击开始，祝你手气旺</p>
    <a class="start-history-link" href="history.php">查阅历史牌局 →</a>
  </section>
</main>

<script src="time.js" defer></script>
<script src="app.js" defer></script>
</body>
</html>
