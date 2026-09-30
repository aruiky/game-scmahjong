<?php
declare(strict_types=1);

final class MahjongHistory
{
    public static function save(array $game): string
    {
        $id = bin2hex(random_bytes(16));
        $entry = [
            'id' => $id,
            'startedAt' => $game['startedAt'] ?? $game['finishedAt'],
            'finishedAt' => $game['finishedAt'],
            'endReason' => $game['endReason'],
            'huPlayers' => array_values($game['huPlayers']),
            'flowerPlayers' => array_values($game['flowerPlayers']),
            'records' => $game['records'],
        ];

        self::update(static function (array $games) use ($entry): array {
            array_unshift($games, $entry);
            return $games;
        });

        return $id;
    }

    public static function listGames(): array
    {
        return array_map(static function (array $game): array {
            return [
                'id' => $game['id'],
                'startedAt' => $game['startedAt'],
                'finishedAt' => $game['finishedAt'],
                'endReason' => $game['endReason'],
                'huPlayers' => $game['huPlayers'],
                'flowerPlayers' => $game['flowerPlayers'],
                'recordsCount' => count($game['records']),
            ];
        }, self::readAll());
    }

    public static function find(string $id): ?array
    {
        foreach (self::readAll() as $game) {
            if ($game['id'] === $id) {
                return $game;
            }
        }
        return null;
    }

    private static function readAll(): array
    {
        $path = self::filePath();
        if (!is_file($path)) {
            return [];
        }

        $file = fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('无法打开牌局历史文件。');
        }

        $locked = false;
        try {
            if (!flock($file, LOCK_SH)) {
                throw new RuntimeException('无法读取牌局历史文件。');
            }
            $locked = true;
            $contents = stream_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException('无法读取牌局历史文件。');
            }
        } finally {
            if ($locked) {
                flock($file, LOCK_UN);
            }
            fclose($file);
        }

        if ($contents === '') {
            return [];
        }
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['games']) || !is_array($data['games'])) {
            throw new RuntimeException('牌局历史文件格式无效。');
        }
        return $data['games'];
    }

    private static function update(callable $change): void
    {
        $directory = dirname(self::filePath());
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建牌局历史目录。');
        }

        $file = fopen(self::filePath(), 'c+b');
        if ($file === false) {
            throw new RuntimeException('无法打开牌局历史文件。');
        }

        $locked = false;
        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('无法锁定牌局历史文件。');
            }
            $locked = true;
            $contents = stream_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException('无法读取牌局历史文件。');
            }
            $data = $contents === '' ? ['games' => []] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['games']) || !is_array($data['games'])) {
                throw new RuntimeException('牌局历史文件格式无效。');
            }

            $encoded = json_encode(
                ['games' => $change($data['games'])],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
                throw new RuntimeException('无法保存牌局历史文件。');
            }
        } finally {
            if ($locked) {
                flock($file, LOCK_UN);
            }
            fclose($file);
        }
    }

    private static function filePath(): string
    {
        $directory = getenv('MAHJONG_DATA_DIR');
        if ($directory === false || $directory === '') {
            $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
        }
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'games.json';
    }
}
