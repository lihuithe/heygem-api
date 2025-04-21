<?php
// 添加CORS头
header("Access-Control-Allow-Origin: *"); // 允许任何域名进行访问
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // 允许的HTTP方法
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type"); // 允许的请求头

require 'vendor/autoload.php';

// 连接数据库
$servername = "127.0.0.1";
$username = "xxxx";
$password = "xxxx";
$dbname = "xxxx";
$dk = "3306";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname, $dk);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $postData = json_decode(file_get_contents('php://input'), true);
    
    // 检查必要参数
    if (!isset($postData['id'])) {
        echo json_encode([
            'code' => 400,
            'message' => '缺少任务ID',
            'data' => null
        ]);
        exit;
    }
    
    $taskId = $postData['id'];
    $currentTime = date('Y-m-d H:i:s');
    
    // 构建更新SQL语句
    $sql = "UPDATE tasks SET update_time = ?";
    $params = [$currentTime];
    $types = "s";
    
    // 检查是否有其他字段需要更新
    if (isset($postData['wav'])) {
        $sql .= ", wav = ?";
        $params[] = $postData['wav'];
        $types .= "s";
    }
    
    if (isset($postData['mp4'])) {
        $sql .= ", mp4 = ?";
        $params[] = $postData['mp4'];
        $types .= "s";
    }
    
    if (isset($postData['task_status'])) {
        $sql .= ", task_status = ?";
        $params[] = $postData['task_status'];
        $types .= "s";
    }
    
    if (isset($postData['task_note'])) {
        $sql .= ", task_note = ?";
        $params[] = $postData['task_note'];
        $types .= "s";
    }
    
    if (isset($postData['output_url'])) {
        $sql .= ", output_url = ?";
        $params[] = $postData['output_url'];
        $types .= "s";
    }
    
    if (isset($postData['status'])) {
        $sql .= ", status = ?";
        $params[] = $postData['status'];
        $types .= "i";
    }
    
    // 添加WHERE条件
    $sql .= " WHERE id = ?";
    $params[] = $taskId;
    $types .= "i";
    
    // 准备并执行SQL语句
    $stmt = $conn->prepare($sql);
    
    // 动态绑定参数
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // 检查是否有行被更新
        if ($stmt->affected_rows > 0) {
            // 返回成功响应
            echo json_encode([
                'code' => 0,
                'message' => '任务更新成功',
                'data' => [
                    'id' => $taskId
                ]
            ]);
        } else {
            // 没有找到对应ID的任务
            echo json_encode([
                'code' => 404,
                'message' => '未找到指定任务',
                'data' => null
            ]);
        }
    } else {
        // 返回错误响应
        echo json_encode([
            'code' => 500,
            'message' => '任务更新失败: ' . $stmt->error,
            'data' => null
        ]);
    }
    
    $stmt->close();
} else {
    // 非POST请求返回错误
    echo json_encode([
        'code' => 405,
        'message' => '方法不允许',
        'data' => null
    ]);
}

// 关闭数据库连接
$conn->close();

/**
 * 更新任务状态API
 * 
 * 请求地址: https://xxxx/api/up_tsak.php
 * 
 * 请求方法: POST
 * 
 * 请求参数:
 * - id: 整数，必填，任务ID
 * - wav: 字符串，可选，音频文件路径
 * - mp4: 字符串，可选，视频文件路径
 * - task_status: 字符串，可选，合成状态（如"待处理"、"处理中"、"已完成"、"失败"等）
 * - task_note: 字符串，可选，任务执行描述
 * - output_url: 字符串，可选，最终合成的成片url
 * - status: 整数，可选，任务记录状态（1-正常，0-禁用，-1-删除）
 * 
 * 返回格式: JSON
 * 
 * 成功返回示例:
 * {
 *     "code": 0,
 *     "message": "任务更新成功",
 *     "data": {
 *         "id": 123
 *     }
 * }
 * 
 * 错误返回示例:
 * {
 *     "code": 400/404/500,
 *     "message": "错误信息",
 *     "data": null
 * }
 * 
 * 请求示例:
 * curl -X POST https://xxxx/api/up_tsak.php \
 *      -H "Content-Type: application/json" \
 *      -d '{
 *          "id": 123,
 *          "task_status": "已完成",
 *          "task_note": "任务处理成功",
 *          "output_url": "https://xxxx/output_url/final.mp4"
 *      }'
 */
