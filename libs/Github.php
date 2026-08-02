<?php
declare(strict_types=1);

/**
 * GitHub 仓库信息 API 中转
 *
 * 输出 JSON，供前端 GitHubCardManager fetch。
 *
 * 调用方式：/libs/Github.php?repo=owner/repo
 *
 * @package XPro
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

$repo = isset($_GET['repo']) ? trim($_GET['repo']) : '';

if (empty($repo)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => '请提供 repo 参数']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repo)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => 'repo 格式错误，应为 owner/repo']);
    exit;
}

$cacheDir  = __DIR__ . '/cache/';
$cacheTime = 3600000;

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
    return is_array($data) ? $data : null;
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

$cacheKey = 'github_repo_' . $repo;

$cached = $readCache($cacheKey);
if ($cached) {
    $sendCache($cached);
}

$api = 'https://api.github.com/repos/' . $repo;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Typecho/1.3)',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($resp === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode([
        'code' => 502,
        'message' => 'GitHub API 连接失败: HTTP ' . $httpCode,
    ]);
    exit;
}

$githubData = json_decode($resp, true);
if (!$githubData || isset($githubData['message'])) {
    http_response_code(502);
    echo json_encode([
        'code' => 502,
        'message' => 'GitHub API 错误: ' . ($githubData['message'] ?? '未知错误'),
    ]);
    exit;
}

$data = [
    'name'             => $githubData['name'],
    'owner'            => $githubData['owner']['login'],
    'description'      => $githubData['description'] ?? '',
    'stargazers_count' => (int) ($githubData['stargazers_count'] ?? 0),
    'forks_count'      => (int) ($githubData['forks_count'] ?? 0),
    'language'         => $githubData['language'] ?? '',
];

$writeCache($cacheKey, $data);

echo json_encode([
    'code' => 0,
    'message' => 'ok',
    'data' => $data,
    'cached' => false,
], JSON_UNESCAPED_UNICODE);
