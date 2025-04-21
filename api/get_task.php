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

// 创建Redis连接
$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'password'     => 'xxxx'
]);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 处理GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 从Redis队列中获取一个任务
    $taskJson = $redis->rpop('heygem_tsaks');
    
    if ($taskJson) {
        // 解析任务数据
        $taskData = json_decode($taskJson, true);
        
        // 更新任务状态为处理中
        $taskId = $taskData['id'];
        $currentTime = date('Y-m-d H:i:s');
        
        $sql = "UPDATE tasks SET task_status = '处理中', update_time = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $currentTime, $taskId);
        $stmt->execute();
        $stmt->close();
        
        // 返回任务数据
        echo json_encode([
            'code' => 0,
            'message' => '获取任务成功',
            'data' => $taskData
        ]);
    } else {
        // 没有任务可处理
        echo json_encode([
            'code' => 404,
            'message' => '没有待处理的任务',
            'data' => null
        ]);
    }
} else {
    // 非GET请求返回错误
    echo json_encode([
        'code' => 405,
        'message' => '方法不允许',
        'data' => null
    ]);
}

// 关闭数据库连接
$conn->close();

/**
 * 获取任务API
 * 
 * 请求地址: https://xxxx/api/get_task.php
 * 
 * 请求方法: GET
 * 
 * 请求参数: 无
 * 
 * 返回格式: JSON
 * 
 * 成功返回示例:
 * {
 *     "code": 0,
 *     "message": "获取任务成功",
 *     "data": {
 *         "id": 123,
 *         "wav": "https://xxxx/output_url/audio.wav",
 *         "mp4": "https://xxxx/output_url/video.mp4",
 *         "task_note": "初始任务",
 *         "output_url": "https://xxxx/output_url/final.mp4",
 *         "add_time": "2023-01-01 12:00:00"
 *     }
 * }
 * 
 * 错误返回示例:
 * {
 *     "code": 404/405,
 *     "message": "错误信息",
 *     "data": null
 * }
 * 
 * 请求示例:
 * curl -X GET https://xxxx/api/get_task.php
 */
