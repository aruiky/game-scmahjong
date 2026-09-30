const PLAYER_NAMES = ['东家·你', '南家（AI）', '西家（AI）', '北家（AI）'];
const panel = document.getElementById('historyPanel');
const historyListButton = document.getElementById('historyListButton');
const timeZone = document.querySelector('.history-shell').dataset.timezone;

historyListButton.addEventListener('click', loadHistory);

function formatDate(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '时间未知' : date.toLocaleString('zh-CN', {timeZone});
}

function appendText(parent, tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  element.textContent = text;
  parent.append(element);
  return element;
}

async function loadHistory() {
  try {
    const response = await fetch('api.php?action=history', {cache: 'no-store'});
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || '读取牌局历史失败。');
    renderHistoryList(result.games);
  } catch (error) {
    showMessage(error instanceof Error ? error.message : '读取牌局历史失败。');
  }
}

function renderHistoryList(games) {
  historyListButton.hidden = true;
  panel.replaceChildren();
  if (!games.length) {
    appendText(panel, 'div', 'history-status', '还没有已结束的牌局。完成一局后，记录会自动保存在这里。');
    return;
  }

  const list = document.createElement('div');
  list.className = 'history-list';
  games.forEach((game, index) => {
    const button = document.createElement('button');
    button.className = 'history-item';
    button.type = 'button';
    const info = document.createElement('span');
    appendText(info, 'strong', '', `牌局 ${games.length - index}`);
    appendText(info, 'small', '', `${formatDate(game.finishedAt)} · ${game.recordsCount} 条记录`);
    const result = document.createElement('span');
    result.className = 'history-result';
    result.textContent = game.endReason || '牌局结束';
    button.append(info, result);
    button.addEventListener('click', () => loadHistoryDetail(game.id));
    list.append(button);
  });
  panel.append(list);
}

async function loadHistoryDetail(id) {
  showMessage('正在读取牌局详情…');
  try {
    const response = await fetch(`api.php?action=history_detail&id=${encodeURIComponent(id)}`, {cache: 'no-store'});
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || '读取牌局详情失败。');
    renderHistoryDetail(result.game);
  } catch (error) {
    showMessage(error instanceof Error ? error.message : '读取牌局详情失败。');
  }
}

function renderHistoryDetail(game) {
  historyListButton.hidden = false;
  panel.replaceChildren();
  appendText(panel, 'h2', 'detail-heading', game.endReason || '牌局详情');
  const summary = document.createElement('div');
  summary.className = 'detail-summary';
  const winners = game.huPlayers.map(player => PLAYER_NAMES[player]).join('、') || '无人胡牌';
  const flowers = game.flowerPlayers.map(player => PLAYER_NAMES[player]).join('、') || '无';
  summary.textContent = `开始：${formatDate(game.startedAt)}　结束：${formatDate(game.finishedAt)}\n胡牌：${winners}　花猪：${flowers}`;
  panel.append(summary);

  const log = document.createElement('div');
  log.className = 'history-log';
  log.setAttribute('aria-label', '牌局过程记录');
  const recordTimes = formatRecordTimes(game.records, game.startedAt, timeZone);
  game.records.forEach((record, index) => {
    const row = document.createElement('div');
    row.className = 'history-record';
    appendText(row, 'time', '', recordTimes[index]);
    const details = document.createElement('div');
    details.className = 'history-record-details';
    appendText(details, 'span', '', record.message);
    if (Array.isArray(record.winningHand)) {
      const hand = document.createElement('div');
      hand.className = 'history-winning-hand';
      appendText(hand, 'span', 'history-winning-hand-label', '胡牌后手牌：');
      const tiles = document.createElement('div');
      tiles.className = 'history-winning-hand-tiles';
      record.winningHand.forEach(tile => {
        if (!Number.isInteger(tile) || tile < 0 || tile > 107) return;
        const image = document.createElement('img');
        image.className = 'history-winning-tile';
        image.src = `paimian/${Math.floor(tile / 4) * 4}.png`;
        image.alt = `${Math.floor(tile / 4) % 9 + 1}${['万', '条', '筒'][Math.floor(tile / 36)]}`;
        image.title = image.alt;
        tiles.append(image);
      });
      hand.append(tiles);
      details.append(hand);
    }
    row.append(details);
    log.append(row);
  });
  panel.append(log);
}

function showMessage(message) {
  panel.replaceChildren();
  appendText(panel, 'div', 'history-status', message);
}

loadHistory();
