<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/history_store.php';

try {
    configureMahjongTimezone();
} catch (RuntimeException $error) {
    error_log($error->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '时区配置无效，请检查 MAHJONG_TIMEZONE。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (($_GET['action'] ?? '') === 'history') {
            echo json_encode(['ok' => true, 'games' => MahjongHistory::listGames()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (($_GET['action'] ?? '') === 'history_detail') {
            $id = $_GET['id'] ?? '';
            if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => '牌局编号无效。'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $history = MahjongHistory::find($id);
            if ($history === null) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => '未找到该牌局记录。'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => true, 'game' => $history], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '不支持的查询操作。'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        error_log($error->__toString());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => '读取牌局历史时发生错误。'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => '仅支持 POST 请求。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = file_get_contents('php://input');
$request = json_decode($body === false ? '' : $body, true);
if (!is_array($request) || !isset($request['action']) || !is_string($request['action'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请求数据格式无效。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$game = $_SESSION['mahjong_game'] ?? null;
try {
    MahjongGame::handle($game, $request['action'], $request);
    if (($game['phase'] ?? null) === 'gameover' && empty($game['historyId'])) {
        $game['historyId'] = MahjongHistory::save($game);
    }
    $_SESSION['mahjong_game'] = $game;
    echo json_encode(['ok' => true, 'state' => MahjongGame::publicState($game)], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log($error->__toString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '服务器处理牌局操作时发生错误。'], JSON_UNESCAPED_UNICODE);
}
