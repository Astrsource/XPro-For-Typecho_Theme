<?php
declare(strict_types=1);

/**
 * Bilibili 视频信息 API
 *
 * 输出 JSON，供前端 BiliCardManager fetch。
 *
 * 调用方式：
 *   /libs/BiliBili.php?bv=BV1xx411c7mD
 *   /libs/BiliBili.php?av=170001
 *
 * @package XPro
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

$bvid = isset($_GET['bv']) ? trim($_GET['bv']) : '';
$aid  = isset($_GET['av']) ? (int) $_GET['av'] : 0;

if (empty($bvid) && empty($aid)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => '请提供 bv 或 av 参数']);
    exit;
}
if ($bvid && !preg_match('/^BV[a-zA-Z0-9]{10}$/', $bvid)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => 'BV 号格式错误']);
    exit;
}
if ($aid && $aid <= 0) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => 'AV 号格式错误']);
    exit;
}

$cacheDir  = __DIR__ . '/cache/';
$cacheTime = 36000;

$readCache = function (string $key) use ($cacheDir, $cacheTime): ?array {
    $cacheFile = $cacheDir . md5($key) . '.json';
    $cacheMeta = $cacheDir . md5($key) . '.meta';
    if (!is_file($cacheFile) || !is_file($cacheMeta)) {
        return null;
    }
    $meta = json_decode(file_get_contents($cacheMeta), true);
    if (($meta['expires_at'] ?? 0) <= time()) {
        return null;
    }
    $data = json_decode(file_get_contents($cacheFile), true);
    if (!$data) {
        return null;
    }
    $data['pic']     = !empty($data['pic']) ? str_replace('http://', 'https://', $data['pic']) : '';
    $data['up_face'] = !empty($data['up_face']) ? str_replace('http://', 'https://', $data['up_face']) : '';
    return $data;
};

$sendCache = static function (array $data): void {
    echo json_encode([
        'code' => 0,
        'message' => 'ok',
        'data' => $data,
        'cached' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

$writeCache = function (string $key, array $data) use ($cacheDir, $cacheTime): void {
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . md5($key) . '.json';
    $cacheMeta = $cacheDir . md5($key) . '.meta';
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    file_put_contents($cacheMeta, json_encode([
        'created_at' => time(),
        'expires_at' => time() + $cacheTime,
    ], JSON_UNESCAPED_UNICODE));
};

if ($bvid) {
    $cached = $readCache('bili_video_bv_' . $bvid);
    if ($cached) {
        $sendCache($cached);
    }
}

if ($aid) {
    $cached = $readCache('bili_video_av_' . $aid);
    if ($cached) {
        $sendCache($cached);
    }
}

$api = 'https://api.bilibili.com/x/web-interface/view?' .
       ($bvid ? 'bvid=' . urlencode($bvid) : 'aid=' . urlencode((string)$aid));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Typecho/1.3)',
    CURLOPT_REFERER        => 'https://www.bilibili.com/',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($resp === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['code' => 502, 'message' => 'B 站 API 连接失败']);
    exit;
}

$biliData = json_decode($resp, true);
if (!$biliData || ($biliData['code'] ?? -1) !== 0) {
    http_response_code(502);
    echo json_encode([
        'code' => 502,
        'message' => 'B 站 API 错误: ' . ($biliData['message'] ?? '未知错误'),
    ]);
    exit;
}

$video = [
    'bvid'     => $biliData['data']['bvid'] ?? $bvid,
    'aid'      => $biliData['data']['aid'] ?? ($aid ?: null),
    'title'    => $biliData['data']['title'] ?? '',
    'desc'     => $biliData['data']['desc'] ?? '',
    'pic'      => !empty($biliData['data']['pic']) ? str_replace('http://', 'https://', $biliData['data']['pic']) : '',
    'duration' => $biliData['data']['duration'] ?? 0,
    'pubdate'  => $biliData['data']['pubdate'] ?? 0,
    'view'     => $biliData['data']['stat']['view'] ?? 0,
    'danmaku'  => $biliData['data']['stat']['danmaku'] ?? 0,
    'like'     => $biliData['data']['stat']['like'] ?? 0,
    'coin'     => $biliData['data']['stat']['coin'] ?? 0,
    'up_name'  => $biliData['data']['owner']['name'] ?? '',
    'up_face'  => !empty($biliData['data']['owner']['face']) ? str_replace('http://', 'https://', $biliData['data']['owner']['face']) : '',
];

if ($video['bvid'] ?? false) {
    $writeCache('bili_video_bv_' . $video['bvid'], $video);
}
if ($video['aid'] ?? false) {
    $writeCache('bili_video_av_' . $video['aid'], $video);
}

echo json_encode([
    'code' => 0,
    'message' => 'ok',
    'data' => $video,
    'cached' => false,
], JSON_UNESCAPED_UNICODE);
