<?php
// 添加CORS头
header("Access-Control-Allow-Origin: *"); // 允许任何域名进行访问
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // 允许的HTTP方法
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type"); // 允许的请求头

require 'vendor/autoload.php';
use Tos\TosClient;
use Tos\Exception\TosClientException;
use Tos\Exception\TosServerException;
use Tos\Model\PutObjectInput;
use Tos\Model\Enum;
use Tos\Model\DeleteObjectInput;
use Tos\Model\DeleteMultiObjectsInput;
use Tos\Model\ObjectTobeDeleted;
#use PHPMailer\PHPMailer\PHPMailer;
#use PHPMailer\PHPMailer\Exception;

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
    'password'     => 'xxxxxx'
]);

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
    if (!isset($postData['wav']) || !isset($postData['mp4'])) {
        echo json_encode([
            'code' => 400,
            'message' => '缺少必要参数',
            'data' => null
        ]);
        exit;
    }
    
    $wav = $postData['wav'];
    $mp4 = $postData['mp4'];
    $currentTime = date('Y-m-d H:i:s');
    
    // 获取可选参数output_url
    $output_url = isset($postData['output_url']) ? $postData['output_url'] : null;
    $task_note = isset($postData['task_note']) ? $postData['task_note'] : null;
    
    // 插入任务到MySQL
    $sql = "INSERT INTO tasks (wav, mp4, task_status, task_note, output_url, add_time, update_time, status) 
            VALUES (?, ?, '待处理', ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $wav, $mp4, $task_note, $output_url, $currentTime, $currentTime);
    
    if ($stmt->execute()) {
        // 获取新插入的ID
        $taskId = $conn->insert_id;
        
        // 将任务添加到Redis队列
        $taskData = [
            'id' => $taskId,
            'wav' => $wav,
            'mp4' => $mp4,
            'task_note' => $task_note,
            'output_url' => $output_url,
            'add_time' => $currentTime
        ];
        
        // 使用Redis的LPUSH命令将任务添加到队列
        $redis->lpush('heygem_tsaks', json_encode($taskData));
        
        // 返回成功响应
        echo json_encode([
            'code' => 0,
            'message' => '任务添加成功',
            'data' => [
                'id' => $taskId
            ]
        ]);
    } else {
        // 返回错误响应
        echo json_encode([
            'code' => 500,
            'message' => '任务添加失败: ' . $stmt->error,
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
 * 添加任务API
 * 
 * 请求方法: POST
 * 
 * 请求参数:
 * - wav: 字符串，必填，音频文件路径
 * - mp4: 字符串，必填，视频文件路径
 * - task_note: 字符串，可选，任务执行描述
 * - output_url: 字符串，可选，最终合成的成片url
 * 
 * 返回格式: JSON
 * 
 * 成功返回示例:
 * {
 *     "code": 0,
 *     "message": "任务添加成功",
 *     "data": {
 *         "id": 123
 *     }
 * }
 * 
 * 错误返回示例:
 * {
 *     "code": 400/500,
 *     "message": "错误信息",
 *     "data": null
 * }
 * 
 * 请求示例:
 * curl -X POST https://xxx/api/add_task.php \
 *      -H "Content-Type: application/json" \
 *      -d '{
 *          "wav": "https://xxx/output_url/audio.wav",
 *          "mp4": "https://xxx/output_url/video.mp4",
 *          "task_note": "初始任务",
 *          "output_url": "https://xxx/output_url/final.mp4"
 *      }'
 */
