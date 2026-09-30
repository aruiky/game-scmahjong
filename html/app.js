const SUITS = ['wan', 'tiao', 'tong'];
const SUIT_NAMES = {wan: '万', tiao: '条', tong: '筒'};
const PLAYER_NAMES = ['东家·你', '南家（AI）', '西家（AI）', '北家（AI）'];
const timeZone = document.querySelector('.container').dataset.timezone;

let game = null;
let selectedIdx = -1;
let selectedTile = -1;
let reviewShown = false;
let requestPending = false;

function tileRank(tile) {
  return Math.floor(tile / 4);
}

function tileSuit(tile) {
  return SUITS[Math.floor(tileRank(tile) / 9)];
}

function tileNum(tile) {
  return tileRank(tile) % 9 + 1;
}

function tileDisplay(tile) {
  return `${tileNum(tile)}${SUIT_NAMES[tileSuit(tile)]}`;
}

async function sendAction(action, payload = {}) {
  if (requestPending) return;
  requestPending = true;
  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action, ...payload})
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result.error || `请求失败（${response.status}）`);
    }
    game = result.state;
    selectedIdx = -1;
    selectedTile = -1;
    requestPending = false;
    render();
  } catch (error) {
    const message = error instanceof Error ? error.message : '连接服务器失败，请稍后重试。';
    if (action === 'start') {
      document.getElementById('startScreen').style.display = 'flex';
      document.querySelector('.start-note').textContent = message;
    } else {
      setStatus(message);
    }
  } finally {
    requestPending = false;
  }
}

function startGame() {
  reviewShown = false;
  document.getElementById('startScreen').style.display = 'none';
  sendAction('start');
}

function selectQue(suit) {
  sendAction('set_que', {suit});
}

function selectTile(index) {
  if (!game || game.phase !== 'playing' || game.currentPlayer !== 0 || game.waitingAction || requestPending) return;
  const tile = game.hand[index];
  if (tile === undefined) return;
  if (selectedIdx === index && selectedTile === tile) {
    playSelectedTile();
    return;
  }
  selectedIdx = index;
  selectedTile = tile;
  render();
}

function playSelectedTile() {
  if (!game || selectedIdx < 0 || selectedIdx >= game.hand.length) return;
  if (game.hand[selectedIdx] !== selectedTile) return;
  sendAction('discard', {tile: selectedTile});
}

function respondToAction(response) {
  sendAction('respond', {response});
}

function setStatus(message) {
  document.getElementById('statusBar').textContent = message;
}

function render() {
  if (!game) return;
  renderPlayers();
  renderDiscards();
  renderHand();
  renderGameLog();
  renderActions();
  setStatus(game.status);
  document.getElementById('roundInfo').textContent = '血战到底 · 东风局';
  if (game.phase === 'gameover' && !reviewShown) {
    reviewShown = true;
    showGameReview();
  }
}

function renderPlayers() {
  const area = document.getElementById('opponentArea');
  area.innerHTML = '';
  for (let p = 1; p <= 3; p++) {
    const card = document.createElement('div');
    card.className = `player-info${game.currentPlayer === p && game.phase === 'playing' ? ' active' : ''}${game.huPlayers.includes(p) ? ' hu' : ''}`;
    const header = document.createElement('div');
    header.className = 'player-header';
    const seat = document.createElement('span');
    seat.className = 'seat-mark';
    seat.textContent = ['南', '西', '北'][p - 1];
    const dot = document.createElement('span');
    dot.className = 'turn-dot';
    header.append(seat, dot);

    const name = document.createElement('div');
    name.className = 'player-name';
    name.append(document.createTextNode(PLAYER_NAMES[p]));
    name.append(createQueBadge(game.que[p]));
    if (game.huPlayers.includes(p)) name.append(document.createTextNode(' ✅胡'));

    const count = document.createElement('div');
    count.className = 'player-tiles';
    count.textContent = `牌数: ${game.playerCounts[p]}`;
    const backs = document.createElement('div');
    backs.className = 'opp-hand';
    for (let i = 0; i < Math.min(game.playerCounts[p], 13); i++) {
      const back = document.createElement('div');
      back.className = 'tile-back';
      backs.append(back);
    }
    card.append(header, name, count, backs);
    card.insertAdjacentHTML('beforeend', renderMelds(p));
    area.append(card);
  }
}

function createQueBadge(suit) {
  const badge = document.createElement('span');
  badge.className = `que-badge que-${suit || 'wan'}`;
  badge.textContent = `缺${SUIT_NAMES[suit] || '未选'}`;
  return badge;
}

function renderDiscards() {
  const area = document.getElementById('discardArea');
  area.innerHTML = '';
  game.discards.forEach((tiles, player) => {
    const pile = document.createElement('div');
    pile.className = 'discard-pile';
    pile.id = `disc-pile-${player}`;
    pile.setAttribute('aria-label', `${PLAYER_NAMES[player]}弃牌区`);
    pile.tabIndex = 0;
    tiles.forEach(tile => pile.insertAdjacentHTML('beforeend', renderTile(tile)));
    area.append(pile);
    pile.scrollTop = pile.scrollHeight;
  });
}

function renderHand() {
  const info = document.getElementById('handInfo');
  info.replaceChildren();
  const seat = document.createElement('span');
  seat.textContent = '东家';
  seat.style.marginRight = '8px';
  const badge = createQueBadge(game.que[0]);
  const wall = document.createElement('span');
  wall.textContent = `剩余牌数: ${game.wallCount}`;
  wall.style.marginLeft = '8px';
  info.append(seat, badge, wall);
  if (game.huPlayers.includes(0)) {
    const hu = document.createElement('span');
    hu.textContent = '✅已胡牌';
    hu.style.marginLeft = '8px';
    info.append(hu);
  }

  const hand = document.getElementById('handTiles');
  hand.innerHTML = renderMelds(0);
  game.hand.forEach((tile, index) => {
    const wrap = document.createElement('span');
    wrap.className = 'tile-wrap';
    wrap.addEventListener('click', () => selectTile(index));
    wrap.innerHTML = renderTile(tile, selectedIdx === index && selectedTile === tile);
    hand.append(wrap);
  });
}

function renderMelds(player) {
  const melds = game.melds[player] || [];
  let html = '<div style="display:flex;gap:4px;margin:4px 0;justify-content:center;align-items:center;flex-wrap:wrap;">';
  melds.forEach(meld => {
    html += '<div class="meld-tiles">';
    meld.tiles.forEach(tile => {
      if (meld.type === 'angang' && player !== 0) {
        html += '<div class="tile" style="background:linear-gradient(135deg,#1a237e,#0d47a1);border-color:#000;"></div>';
      } else {
        html += renderTile(tile);
      }
    });
    html += '</div>';
  });
  return `${html}</div>`;
}

function renderTile(tile, selected = false) {
  const suit = tileSuit(tile);
  const number = tileNum(tile);
  const label = `${number}${SUIT_NAMES[suit]}`;
  return `<div class="tile ${suit}${selected ? ' selected' : ''}" role="img" aria-label="${label}" title="${label}"><img class="tile-art" src="paimian/${tile}.png" alt="" draggable="false"></div>`;
}

function renderGameLog() {
  const list = document.getElementById('gameLogList');
  list.replaceChildren();
  document.getElementById('gameLogCount').textContent = `${game.records.length} 条`;
  const recordTimes = formatRecordTimes(game.records, game.startedAt, timeZone);
  game.records.forEach((record, index) => {
    const entry = document.createElement('div');
    entry.className = `game-log-entry ${record.type}${index === game.records.length - 1 ? ' latest' : ''}`;
    const time = document.createElement('span');
    time.className = 'game-log-time';
    time.textContent = recordTimes[index];
    const message = document.createElement('span');
    message.className = 'game-log-message';
    message.textContent = record.message;
    entry.append(time, message);
    list.append(entry);
  });
  list.scrollTop = list.scrollHeight;
}

function renderActions() {
  const buttons = document.getElementById('actionButtons');
  buttons.replaceChildren();
  if (game.phase === 'que') {
    SUITS.forEach(suit => addActionButton(buttons, `缺${SUIT_NAMES[suit]}`, '', () => selectQue(suit)));
  } else if (game.waitingAction) {
    game.actions.forEach(action => {
      if (action !== 'pass') {
        const labels = {hu: '胡', gang: '杠', peng: '碰'};
        addActionButton(buttons, labels[action], action, () => respondToAction(action));
      }
    });
    addActionButton(buttons, '过', 'pass', () => respondToAction('pass'));
  } else if (game.phase === 'playing' && game.currentPlayer === 0 && selectedIdx >= 0) {
    addActionButton(buttons, '出牌', '', playSelectedTile);
  }
}

function addActionButton(container, label, className, onClick) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = `action-btn${className ? ` ${className}` : ''}`;
  button.textContent = label;
  button.disabled = requestPending;
  button.addEventListener('click', onClick);
  container.append(button);
}

function showGameReview() {
  const modalContent = document.getElementById('modalContent');
  modalContent.className = 'modal-content review-content';
  modalContent.replaceChildren();

  const heading = document.createElement('header');
  heading.className = 'review-heading';
  const title = document.createElement('h2');
  title.textContent = '牌局记录';
  const subtitle = document.createElement('p');
  subtitle.textContent = `本局结束 · ${game.endReason} · 共 ${game.records.length} 条记录`;
  heading.append(title, subtitle);

  const summary = document.createElement('div');
  summary.className = 'review-summary';
  addReviewChip(summary, game.endReason);
  if (game.huPlayers.length) {
    game.huPlayers.forEach(player => addReviewChip(summary, `${PLAYER_NAMES[player]}胡牌`, 'hu'));
  } else {
    addReviewChip(summary, '本局无人胡牌');
  }
  game.flowerPlayers.forEach(player => addReviewChip(summary, `${PLAYER_NAMES[player]}是花猪`, 'flower'));

  const log = document.createElement('div');
  log.className = 'review-log';
  log.setAttribute('aria-label', '完整牌局记录');
  const recordTimes = formatRecordTimes(game.records, game.startedAt, timeZone);
  game.records.forEach((record, index) => {
    const entry = document.createElement('div');
    entry.className = `game-log-entry ${record.type}`;
    const time = document.createElement('span');
    time.className = 'game-log-time';
    time.textContent = recordTimes[index];
    const message = document.createElement('span');
    message.className = 'game-log-message';
    message.textContent = record.message;
    entry.append(time, message);
    log.append(entry);
  });

  const actions = document.createElement('div');
  actions.className = 'review-actions';
  const restart = document.createElement('button');
  restart.className = 'modal-btn';
  restart.type = 'button';
  restart.textContent = '再来一局';
  restart.addEventListener('click', () => {
    document.getElementById('modal').style.display = 'none';
    modalContent.className = 'modal-content';
    startGame();
  });
  actions.append(restart);
  modalContent.append(heading, summary, log, actions);
  document.getElementById('modal').style.display = 'flex';
}

function addReviewChip(container, label, kind = '') {
  const chip = document.createElement('span');
  chip.className = `review-result-chip${kind ? ` ${kind}` : ''}`;
  chip.textContent = label;
  container.append(chip);
}

document.getElementById('startScreen').style.display = 'flex';
