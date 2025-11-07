<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '只允许POST请求'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 检查cURL是否可用
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '服务器不支持cURL'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 获取JSON输入
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('无效的JSON输入: ' . json_last_error_msg());
    }
    
    if (!$input) {
        throw new Exception('空的输入数据');
    }
    
    $url = isset($input['url']) ? trim($input['url']) : '';
    $cookies = isset($input['cookies']) ? trim($input['cookies']) : '';
    $headers = isset($input['headers']) ? $input['headers'] : array();
    
    // 验证URL
    if (empty($url)) {
        throw new Exception('URL不能为空');
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('无效的URL格式');
    }
    
    // 验证是否为HTTPS链接
    if (strpos($url, 'https://') !== 0) {
        throw new Exception('只支持HTTPS链接');
    }
    
    // 初始化cURL
    $ch = curl_init();
    
    // 设置cURL选项
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, isset($headers['User-Agent']) ? $headers['User-Agent'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    // 设置HTTP头
    $httpHeaders = array(
        'Accept: ' . (isset($headers['Accept']) ? $headers['Accept'] : 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'),
        'Accept-Language: ' . (isset($headers['Accept-Language']) ? $headers['Accept-Language'] : 'zh-CN,zh;q=0.9,en;q=0.8'),
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    
    // 设置Cookie
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    
    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // 检查cURL错误
    if ($response === false) {
        throw new Exception('cURL错误: ' . $error);
    }
    
    // 检查HTTP状态码
    if ($httpCode >= 400) {
        throw new Exception('HTTP错误: ' . $httpCode);
    }
    
    // 成功响应
    echo json_encode(array(
        'success' => true,
        'data' => $response,
        'http_code' => $httpCode,
        'url' => $url,
        'cookies_used' => !empty($cookies)
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // 错误响应
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    // PHP 7+ 错误处理
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'PHP错误: ' . $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
}
?>
