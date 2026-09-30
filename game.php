<?php
declare(strict_types=1);

final class MahjongGame
{
    private const SUITS = ['wan', 'tiao', 'tong'];
    private const SUIT_NAMES = ['wan' => '万', 'tiao' => '条', 'tong' => '筒'];
    private const PLAYER_NAMES = ['东家·你', '南家（AI）', '西家（AI）', '北家（AI）'];

    public static function handle(?array &$game, string $action, array $payload): void
    {
        if ($action === 'start') {
            $game = self::newGame();
            return;
        }

        if ($game === null) {
            throw new InvalidArgumentException('请先开始新牌局。');
        }

        switch ($action) {
            case 'set_que':
                self::setQue($game, $payload['suit'] ?? null);
                break;
            case 'discard':
                self::discard($game, $payload['tile'] ?? null);
                break;
            case 'respond':
                self::respond($game, $payload['response'] ?? null);
                break;
            default:
                throw new InvalidArgumentException('不支持的操作。');
        }

        self::advanceAi($game);
    }

    public static function publicState(?array $game): ?array
    {
        if ($game === null) {
            return null;
        }

        return [
            'startedAt' => $game['startedAt'],
            'phase' => $game['phase'],
            'hand' => $game['players'][0],
            'playerCounts' => array_map('count', $game['players']),
            'que' => $game['que'],
            'currentPlayer' => $game['currentPlayer'],
            'wallCount' => count($game['wall']),
            'discards' => $game['discards'],
            'melds' => $game['melds'],
            'huPlayers' => $game['huPlayers'],
            'currentTile' => $game['currentTile'],
            'lastDiscard' => $game['lastDiscard'],
            'waitingAction' => $game['waitingAction'],
            'actions' => $game['waitingAction']['offers'] ?? [],
            'records' => $game['records'],
            'status' => $game['status'],
            'endReason' => $game['endReason'],
            'flowerPlayers' => $game['flowerPlayers'],
        ];
    }

    private static function newGame(): array
    {
        $wall = range(0, 107);
        shuffle($wall);
        $game = [
            'startedAt' => gmdate('c'),
            'players' => [[], [], [], []],
            'que' => [null, null, null, null],
            'wall' => $wall,
            'currentPlayer' => 0,
            'currentTile' => -1,
            'phase' => 'que',
            'lastDiscard' => -1,
            'discards' => [[], [], [], []],
            'melds' => [[], [], [], []],
            'huPlayers' => [],
            'waitingAction' => null,
            'records' => [],
            'status' => '请选择定缺花色：',
            'endReason' => null,
            'flowerPlayers' => [],
        ];
        self::record($game, '新局开始，东家坐庄。');

        for ($i = 0; $i < 13; $i++) {
            for ($p = 0; $p < 4; $p++) {
                $game['players'][$p][] = array_pop($game['wall']);
            }
        }
        for ($p = 0; $p < 4; $p++) {
            self::sortHand($game['players'][$p]);
        }

        $game['currentTile'] = array_pop($game['wall']);
        $game['players'][0][] = $game['currentTile'];
        self::sortHand($game['players'][0]);
        self::record($game, self::PLAYER_NAMES[0] . '起手摸到' . self::tileDisplay($game['currentTile']) . '。');

        for ($p = 1; $p < 4; $p++) {
            $counts = array_fill_keys(self::SUITS, 0);
            foreach ($game['players'][$p] as $tile) {
                $counts[self::tileSuit($tile)]++;
            }
            $minSuit = self::SUITS[0];
            foreach (self::SUITS as $suit) {
                if ($counts[$suit] < $counts[$minSuit]) {
                    $minSuit = $suit;
                }
            }
            $game['que'][$p] = $minSuit;
            self::record($game, self::PLAYER_NAMES[$p] . '定缺' . self::SUIT_NAMES[$minSuit] . '。');
        }
        return $game;
    }

    private static function setQue(array &$game, mixed $suit): void
    {
        if ($game['phase'] !== 'que' || !in_array($suit, self::SUITS, true)) {
            throw new InvalidArgumentException('当前无法选择该定缺花色。');
        }
        $game['que'][0] = $suit;
        $game['phase'] = 'playing';
        $game['currentPlayer'] = 0;
        $game['status'] = '轮到你出牌，请选择要打出的牌';
        self::record($game, '东家定缺' . self::SUIT_NAMES[$suit] . '。');
    }

    private static function discard(array &$game, mixed $tile): void
    {
        if ($game['phase'] !== 'playing' || $game['currentPlayer'] !== 0 || $game['waitingAction'] !== null) {
            throw new InvalidArgumentException('现在不是你的出牌回合。');
        }
        if (!is_int($tile)) {
            throw new InvalidArgumentException('出牌数据无效。');
        }
        $index = array_search($tile, $game['players'][0], true);
        if ($index === false) {
            throw new InvalidArgumentException('这张牌不在你的手牌中。');
        }
        $que = $game['que'][0];
        $hasQue = false;
        foreach ($game['players'][0] as $handTile) {
            if (is_int($handTile) && self::tileSuit($handTile) === $que) {
                $hasQue = true;
                break;
            }
        }
        if ($hasQue && self::tileSuit($tile) !== $que) {
            throw new InvalidArgumentException('你还有定缺花色的牌必须先打！');
        }

        array_splice($game['players'][0], (int)$index, 1);
        $game['discards'][0][] = $tile;
        $game['lastDiscard'] = $tile;
        $game['currentTile'] = -1;
        self::record($game, self::PLAYER_NAMES[0] . '打出' . self::tileDisplay($tile) . '。', 'discard');
        self::resolveDiscard($game, 0, $tile, []);
    }

    private static function respond(array &$game, mixed $response): void
    {
        $waiting = $game['waitingAction'];
        if (!is_array($waiting) || !is_string($response)) {
            throw new InvalidArgumentException('当前没有待响应的牌局操作。');
        }
        if ($response !== 'pass' && !in_array($response, $waiting['offers'], true)) {
            throw new InvalidArgumentException('该操作当前不可用。');
        }

        $game['waitingAction'] = null;
        if ($response === 'pass') {
            if ($waiting['kind'] === 'self') {
                $game['status'] = $waiting['replacement'] ? '杠后摸牌，请出牌' : '你摸到了牌，请出牌';
                return;
            }
            $waiting['declined'][] = $waiting['type'];
            self::resolveDiscard($game, $waiting['from'], $waiting['tile'], $waiting['declined']);
            return;
        }

        if ($response === 'hu') {
            self::doHu($game, 0, $waiting['from'], $waiting['tile'], $waiting['kind'] === 'self');
        } elseif ($response === 'peng') {
            self::doPeng($game, 0, $waiting['from'], $waiting['tile']);
        } elseif ($response === 'gang') {
            self::doGang($game, 0, $waiting['from'], $waiting['tile']);
        } else {
            throw new InvalidArgumentException('该操作当前不可用。');
        }
    }

    private static function resolveDiscard(array &$game, int $from, int $tile, array $declined): void
    {
        $players = self::nextPlayers($from, $game['huPlayers']);
        foreach (['hu', 'gang', 'peng'] as $type) {
            foreach ($players as $p) {
                if (in_array($type, $declined, true) && $p === 0) {
                    continue;
                }
                $eligible = match ($type) {
                    'hu' => self::canHu($game['players'][$p], $tile, $game['que'][$p], $game['melds'][$p]),
                    'gang' => self::canMingGang($game['players'][$p], $tile),
                    'peng' => self::canPeng($game['players'][$p], $tile),
                };
                if (!$eligible) {
                    continue;
                }
                if ($p === 0) {
                    $game['waitingAction'] = [
                        'kind' => 'discard',
                        'type' => $type,
                        'from' => $from,
                        'tile' => $tile,
                        'declined' => $declined,
                        'offers' => [$type, 'pass'],
                    ];
                    $game['status'] = match ($type) {
                        'hu' => '可以胡牌！',
                        'gang' => '可以杠！',
                        default => '可以碰！',
                    };
                    return;
                }
                $chance = ['hu' => 0.95, 'gang' => 0.85, 'peng' => 0.7][$type];
                if (mt_rand() / mt_getrandmax() < $chance) {
                    if ($type === 'hu') {
                        self::doHu($game, $p, $from, $tile, false);
                    } elseif ($type === 'gang') {
                        self::doGang($game, $p, $from, $tile);
                    } else {
                        self::doPeng($game, $p, $from, $tile);
                    }
                    return;
                }
            }
        }
        self::nextTurn($game, $from);
    }

    private static function nextPlayers(int $from, array $winners): array
    {
        $players = [];
        for ($step = 1; $step < 4; $step++) {
            $p = ($from + $step) % 4;
            if (!in_array($p, $winners, true)) {
                $players[] = $p;
            }
        }
        return $players;
    }

    private static function doPeng(array &$game, int $p, int $from, int $tile): void
    {
        self::removeLastDiscard($game, $from, $tile);
        $rank = self::tileRank($tile);
        $meldTiles = [...self::extractRank($game['players'][$p], $rank, 2), $tile];
        $game['melds'][$p][] = ['type' => 'peng', 'tiles' => $meldTiles, 'from' => $from];
        $game['lastDiscard'] = -1;
        $game['currentTile'] = -1;
        $game['currentPlayer'] = $p;
        $game['status'] = $p === 0 ? '碰牌后请出牌' : self::PLAYER_NAMES[$p] . ' 碰';
        self::record($game, self::PLAYER_NAMES[$p] . '碰' . self::tileDisplay($tile) . '（' . self::PLAYER_NAMES[$from] . '打出）。', 'action');
    }

    private static function doGang(array &$game, int $p, int $from, int $tile): void
    {
        $rank = self::tileRank($tile);
        $meldTiles = [];
        if ($from !== -1) {
            self::removeLastDiscard($game, $from, $tile);
            $meldTiles = self::extractRank($game['players'][$p], $rank, 3);
            $meldTiles[] = $tile;
            $type = 'minggang';
            self::record($game, self::PLAYER_NAMES[$p] . '明杠' . self::tileDisplay($tile) . '（' . self::PLAYER_NAMES[$from] . '打出）。', 'action');
            $game['status'] = self::PLAYER_NAMES[$p] . ' 明杠（刮风）';
        } else {
            $meldTiles = self::extractRank($game['players'][$p], $rank, 4);
            $type = 'angang';
            self::record($game, self::PLAYER_NAMES[$p] . '暗杠' . self::tileDisplay($tile) . '。', 'action');
            $game['status'] = self::PLAYER_NAMES[$p] . ' 暗杠（下雨）';
        }
        if (count($meldTiles) !== 4) {
            throw new RuntimeException('杠牌时手牌状态不一致。');
        }
        $game['melds'][$p][] = ['type' => $type, 'tiles' => $meldTiles, 'from' => $from];
        $game['lastDiscard'] = -1;
        $game['currentPlayer'] = $p;
        self::draw($game, $p, true);
    }

    private static function doHu(array &$game, int $p, int $from, int $tile, bool $selfDraw): void
    {
        if (!in_array($p, $game['huPlayers'], true)) {
            $game['huPlayers'][] = $p;
        }
        $fan = self::calculateFan($game, $p, $tile, $selfDraw);
        $game['currentPlayer'] = $p;
        $game['status'] = self::PLAYER_NAMES[$p] . ($selfDraw ? ' 自摸！' : ' 胡牌！') . $fan . '番';
        $winningTile = self::tileDisplay($tile);
        $winningHand = $game['players'][$p];
        if (!$selfDraw) {
            $winningHand[] = $tile;
        }
        sort($winningHand, SORT_NUMERIC);
        foreach ($game['melds'][$p] as $meld) {
            array_push($winningHand, ...$meld['tiles']);
        }
        self::record(
            $game,
            self::PLAYER_NAMES[$p] . ($selfDraw ? '自摸' . $winningTile : '胡' . $winningTile . '（' . self::PLAYER_NAMES[$from] . '点炮）') . '，' . $fan . '番。',
            'action',
            $winningHand
        );
        if (count($game['huPlayers']) >= 3 || count($game['wall']) === 0) {
            self::finish($game);
            return;
        }
        self::nextTurn($game, $selfDraw ? $p : $from);
    }

    private static function nextTurn(array &$game, int $lastPlayer): void
    {
        if (count($game['wall']) === 0) {
            self::finish($game);
            return;
        }
        $next = ($lastPlayer + 1) % 4;
        while (in_array($next, $game['huPlayers'], true)) {
            $next = ($next + 1) % 4;
        }
        $game['currentPlayer'] = $next;
        self::draw($game, $next, false);
    }

    private static function draw(array &$game, int $p, bool $replacement): void
    {
        if (count($game['wall']) === 0) {
            self::finish($game);
            return;
        }
        $tile = array_pop($game['wall']);
        $game['currentTile'] = $tile;
        $game['players'][$p][] = $tile;
        self::sortHand($game['players'][$p]);
        self::record(
            $game,
            self::PLAYER_NAMES[$p] . ($replacement ? '杠后摸到' : '摸到') . self::tileDisplay($tile) . '。'
        );
        if ($p === 0) {
            if (self::canHuDrawnHand($game['players'][$p], $tile, $game['que'][$p], $game['melds'][$p])) {
                $game['waitingAction'] = [
                    'kind' => 'self',
                    'type' => 'hu',
                    'from' => -1,
                    'tile' => $tile,
                    'replacement' => $replacement,
                    'declined' => [],
                    'offers' => ['hu', 'pass'],
                ];
                $game['status'] = '自摸！可以胡牌！';
            } else {
                $game['status'] = $replacement ? '杠后摸牌，请出牌' : '你摸到了牌，请出牌';
            }
            return;
        }
        $game['status'] = '轮到' . self::PLAYER_NAMES[$p] . '...';
    }

    private static function advanceAi(array &$game): void
    {
        $steps = 0;
        while ($game['phase'] === 'playing' && $game['waitingAction'] === null && $game['currentPlayer'] !== 0) {
            if (++$steps > 500) {
                throw new RuntimeException('牌局推进超过安全步数。');
            }
            $p = $game['currentPlayer'];
            $tile = $game['currentTile'];
            if (self::canHuDrawnHand($game['players'][$p], $tile, $game['que'][$p], $game['melds'][$p])
                && mt_rand() / mt_getrandmax() < 0.95) {
                self::doHu($game, $p, -1, $tile, true);
                continue;
            }
            $gangTile = self::findAnGang($game['players'][$p]);
            if ($gangTile !== null && mt_rand() / mt_getrandmax() < 0.8) {
                self::doGang($game, $p, -1, $gangTile);
                continue;
            }
            self::aiDiscard($game, $p);
        }
    }

    private static function aiDiscard(array &$game, int $p): void
    {
        $hand = $game['players'][$p];
        $queTiles = array_values(array_filter($hand, static fn(int $tile): bool => self::tileSuit($tile) === $game['que'][$p]));
        if ($queTiles !== []) {
            $tile = $queTiles[array_rand($queTiles)];
        } else {
            $counts = array_fill(0, 27, 0);
            foreach ($hand as $handTile) {
                $counts[self::tileRank($handTile)]++;
            }
            $bestScore = INF;
            $tile = $hand[0];
            foreach ($hand as $handTile) {
                $rank = self::tileRank($handTile);
                $score = $counts[$rank];
                if ($rank % 9 > 0) {
                    $score += $counts[$rank - 1] * 0.5;
                }
                if ($rank % 9 < 8) {
                    $score += $counts[$rank + 1] * 0.5;
                }
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $tile = $handTile;
                }
            }
        }
        $index = array_search($tile, $game['players'][$p], true);
        array_splice($game['players'][$p], (int)$index, 1);
        $game['discards'][$p][] = $tile;
        $game['lastDiscard'] = $tile;
        $game['currentTile'] = -1;
        $game['status'] = self::PLAYER_NAMES[$p] . ' 打出 ' . self::tileDisplay($tile);
        self::record($game, self::PLAYER_NAMES[$p] . '打出' . self::tileDisplay($tile) . '。', 'discard');
        self::resolveDiscard($game, $p, $tile, []);
    }

    private static function canHu(array $hand, int $tile, ?string $que, array $melds): bool
    {
        if ($que === null) {
            return false;
        }
        $tiles = [...$hand, $tile];
        foreach ($tiles as $handTile) {
            if (self::tileSuit($handTile) === $que) {
                return false;
            }
        }
        return self::isWinningHand($tiles, count($melds));
    }

    private static function canHuDrawnHand(array $hand, int $tile, ?string $que, array $melds): bool
    {
        $index = array_search($tile, $hand, true);
        if ($index === false) {
            return false;
        }
        array_splice($hand, (int)$index, 1);
        return self::canHu($hand, $tile, $que, $melds);
    }

    private static function isWinningHand(array $tiles, int $meldCount): bool
    {
        if (count($tiles) !== 3 * (4 - $meldCount) + 2) {
            return false;
        }
        $counts = array_fill(0, 27, 0);
        foreach ($tiles as $tile) {
            $counts[self::tileRank($tile)]++;
        }
        if ($meldCount === 0 && self::isSevenPairs($counts)) {
            return true;
        }
        for ($i = 0; $i < 27; $i++) {
            if ($counts[$i] >= 2) {
                $copy = $counts;
                $copy[$i] -= 2;
                if (self::canFormMelds($copy)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function isSevenPairs(array $counts): bool
    {
        $pairs = 0;
        foreach ($counts as $count) {
            if ($count === 2) {
                $pairs++;
            } elseif ($count === 4) {
                $pairs += 2;
            } elseif ($count !== 0) {
                return false;
            }
        }
        return $pairs === 7;
    }

    private static function canFormMelds(array $counts): bool
    {
        $first = null;
        foreach ($counts as $index => $count) {
            if ($count > 0) {
                $first = $index;
                break;
            }
        }
        if ($first === null) {
            return true;
        }
        if ($counts[$first] >= 3) {
            $counts[$first] -= 3;
            if (self::canFormMelds($counts)) {
                return true;
            }
            $counts[$first] += 3;
        }
        if ($first % 9 <= 6 && $counts[$first + 1] > 0 && $counts[$first + 2] > 0) {
            $counts[$first]--;
            $counts[$first + 1]--;
            $counts[$first + 2]--;
            if (self::canFormMelds($counts)) {
                return true;
            }
        }
        return false;
    }

    private static function canPeng(array $hand, int $tile): bool
    {
        return self::countRank($hand, self::tileRank($tile)) >= 2;
    }

    private static function canMingGang(array $hand, int $tile): bool
    {
        return self::countRank($hand, self::tileRank($tile)) >= 3;
    }

    private static function countRank(array $hand, int $rank): int
    {
        return count(array_filter($hand, static fn(int $tile): bool => self::tileRank($tile) === $rank));
    }

    private static function extractRank(array &$hand, int $rank, int $required): array
    {
        $tiles = [];
        for ($i = count($hand) - 1; $i >= 0 && count($tiles) < $required; $i--) {
            if (self::tileRank($hand[$i]) === $rank) {
                $tiles[] = $hand[$i];
                array_splice($hand, $i, 1);
            }
        }
        if (count($tiles) !== $required) {
            throw new RuntimeException('副露时手牌状态不一致。');
        }
        return $tiles;
    }

    private static function findAnGang(array $hand): ?int
    {
        $counts = array_fill(0, 27, 0);
        foreach ($hand as $tile) {
            $counts[self::tileRank($tile)]++;
        }
        foreach ($counts as $rank => $count) {
            if ($count === 4) {
                return $rank * 4;
            }
        }
        return null;
    }

    private static function calculateFan(array $game, int $p, int $tile, bool $selfDraw): int
    {
        $hand = $game['players'][$p];
        if ($selfDraw) {
            $index = array_search($tile, $hand, true);
            if ($index !== false) {
                array_splice($hand, (int)$index, 1);
            }
        }
        $allTiles = [...$hand, $tile];
        $suits = array_unique(array_map(self::tileSuit(...), $allTiles));
        foreach ($game['melds'][$p] as $meld) {
            foreach ($meld['tiles'] as $meldTile) {
                $suits[] = self::tileSuit($meldTile);
            }
        }
        $fan = 1 + (count(array_unique($suits)) === 1 ? 3 : 0);
        $counts = array_fill(0, 27, 0);
        foreach ($allTiles as $handTile) {
            $counts[self::tileRank($handTile)]++;
        }
        $duidui = true;
        foreach ($counts as $count) {
            if ($count > 0 && $count !== 2 && $count !== 3) {
                $duidui = false;
                break;
            }
        }
        if ($duidui) {
            $fan++;
        }
        foreach ($game['melds'][$p] as $meld) {
            if (str_contains($meld['type'], 'gang')) {
                $fan++;
            }
        }
        return $fan;
    }

    private static function removeLastDiscard(array &$game, int $from, int $tile): void
    {
        for ($i = count($game['discards'][$from]) - 1; $i >= 0; $i--) {
            if ($game['discards'][$from][$i] === $tile) {
                array_splice($game['discards'][$from], $i, 1);
                return;
            }
        }
    }

    private static function finish(array &$game): void
    {
        if ($game['phase'] === 'gameover') {
            return;
        }
        $game['phase'] = 'gameover';
        $game['finishedAt'] = gmdate('c');
        $game['waitingAction'] = null;
        $game['endReason'] = count($game['huPlayers']) >= 3 ? '三家胡牌' : '牌墙摸完';
        $game['flowerPlayers'] = [];
        for ($p = 0; $p < 4; $p++) {
            if (in_array($p, $game['huPlayers'], true)) {
                continue;
            }
            foreach ($game['players'][$p] as $tile) {
                if (self::tileSuit($tile) === $game['que'][$p]) {
                    $game['flowerPlayers'][] = $p;
                    break;
                }
            }
            if (!in_array($p, $game['flowerPlayers'], true)) {
                foreach ($game['melds'][$p] as $meld) {
                    foreach ($meld['tiles'] as $tile) {
                        if (self::tileSuit($tile) === $game['que'][$p]) {
                            $game['flowerPlayers'][] = $p;
                            break 2;
                        }
                    }
                }
            }
        }
        $game['status'] = '本局结束：' . $game['endReason'];
        self::record($game, '本局结束：' . $game['endReason'] . '。', 'action');
    }

    private static function sortHand(array &$hand): void
    {
        sort($hand, SORT_NUMERIC);
    }

    private static function tileRank(int $tile): int
    {
        return intdiv($tile, 4);
    }

    private static function tileSuit(int $tile): string
    {
        return self::SUITS[intdiv(self::tileRank($tile), 9)];
    }

    private static function tileDisplay(int $tile): string
    {
        $rank = self::tileRank($tile);
        return (($rank % 9) + 1) . self::SUIT_NAMES[self::SUITS[intdiv($rank, 9)]];
    }

    private static function record(array &$game, string $message, string $type = 'system', ?array $winningHand = null): void
    {
        $record = ['time' => date('c'), 'message' => $message, 'type' => $type];
        if ($winningHand !== null) {
            $record['winningHand'] = $winningHand;
        }
        $game['records'][] = $record;
    }
}
