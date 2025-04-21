# 数字人合成服务
# 1、循环拉取任务
# 2、根据根据任务的数据做数字人合成
# 3，把成片上传到火山引擎云储存
# 4、更新任务状态


import os
import time
import uuid
import json
import requests
import subprocess
import threading
import tos
from io import BytesIO
import shutil
import re
from datetime import datetime

# 合成API地址
SYNTHESIS_URL = "http://127.0.0.1:8383/easy/submit"
QUERY_URL = "http://127.0.0.1:8383/easy/query"

# TOS配置
TOS_AK = 'xxxxx'
TOS_SK = 'xxxxxx'
TOS_BUCKET = "xxxxx"
TOS_ENDPOINT = "tos-cn-shanghai.volces.com"
TOS_REGION = "cn-shanghai"

# API地址
GET_TASK_API = "https://xxxx/api/get_task.php"
UPDATE_TASK_API = "https://xxxx/api/up_task.php"

def download_file(url, save_path):
    """下载文件到指定路径"""
    try:
        print('download_file info')
        print('url',url)
        print('save_path',save_path)
        response = requests.get(url, stream=True)
        response.raise_for_status()
        
        # 确保目录存在
        os.makedirs(os.path.dirname(save_path), exist_ok=True)
        
        with open(save_path, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)
        return True
    except Exception as e:
        print(f"下载文件失败: {url}, 错误: {str(e)}")
        return False

def tos_put(file_path, object_key):
    """上传文件到火山引擎TOS"""
    try:
        with open(file_path, 'rb') as file:
            data = file.read()
        
        # 将文件数据加载到 BytesIO
        bytes_io = BytesIO(data)
        
        client = tos.TosClientV2(TOS_AK, TOS_SK, TOS_ENDPOINT, TOS_REGION)
        
        # 通过字符串方式添加 Object
        client.put_object(TOS_BUCKET, object_key, content=bytes_io, content_type=".mp4")
        
        # 生成URL
        url = f"https://{TOS_BUCKET}.{TOS_ENDPOINT}/{object_key}"
        
        # 记录日志
        if not os.path.exists("log"):
            os.makedirs("log")
            open("log/tos_put.log", 'w').close()
        
        with open("log/tos_put.log", 'a+') as f:
            f.write(f"{url}" + "\n")
        
        return url
    except Exception as e:
        print(f"上传文件失败: {file_path}, 错误: {str(e)}")
        
        # 确保错误日志目录存在
        if not os.path.exists("error"):
            os.makedirs("error")
        
        with open("error/tos_put_error.log", 'a+') as f:
            f.write(f"{file_path} 上传失败: {str(e)}" + "\n")
        
        return None

def update_task_status(task_id, status, output_url=None, task_note=None):
    """更新任务状态"""
    try:
        data = {
            "id": task_id,
            "task_status": status
        }
        
        if output_url:
            data["output_url"] = output_url
            
        if task_note:
            data["task_note"] = task_note
        
        response = requests.post(UPDATE_TASK_API, json=data)
        response.raise_for_status()
        
        result = response.json()
        if result.get("code") == 0:
            print(f"任务 {task_id} 状态更新成功: {status}")
            return True
        else:
            print(f"任务 {task_id} 状态更新失败: {result.get('message')}")
            return False
    except Exception as e:
        print(f"更新任务状态失败: {str(e)}")
        return False

def process_task():
    """处理任务的主函数"""
    # 循环次数
    run_num = 0
    while True:
        try:
            run_num += 1
            # 获取任务
            response = requests.get(GET_TASK_API)
            if response.status_code != 200:
                print(f"获取任务失败，状态码: {response.status_code}")
                time.sleep(5)  # 等待5秒后重试
                continue
            
            result = response.json()
            
            # 检查是否有任务
            if result.get("code") != 0 or not result.get("data"):
                if run_num % 30 == 0:
                    print("没有待处理的任务，等待中...")
                time.sleep(1)  # 等待10秒后重试
                continue
            
            # 获取任务数据
            task_data = result.get("data")
            task_id = task_data.get("id")
            wav_url = task_data.get("wav")
            mp4_url = task_data.get("mp4")
            
            print(f"获取到任务 {task_id}, 开始处理...")
            
            # 从wav_url中提取时间戳
            try:
                # 分割URL并提取时间戳部分
                url_parts = wav_url.split('/')
                timestamp = None
                for part in url_parts:
                    if len(part) == 14 and part.isdigit():  # 时间戳格式为14位数字
                        timestamp = part
                        break
                
                if not timestamp:
                    # 如果没有找到时间戳，则使用当前时间
                    timestamp = datetime.now().strftime("%Y%m%d%H%M%S")
                    print(f"未能从URL中提取时间戳，使用当前时间: {timestamp}")
            except Exception as e:
                # 出错时使用当前时间
                timestamp = datetime.now().strftime("%Y%m%d%H%M%S")
                print(f"提取时间戳出错: {str(e)}，使用当前时间: {timestamp}")
            
            # 创建目录路径
            input_dir = f"/input/{timestamp}"
            
            # 下载WAV文件
            wav_filename = os.path.basename(wav_url)
            wav_path = f"{input_dir}/{wav_filename}"
            if not download_file(wav_url, wav_path):
                update_task_status(task_id, "失败")
                continue
            
            # 下载视频文件
            video_filename = os.path.basename(mp4_url)
            video_path = f"{input_dir}/{video_filename}"
            if not download_file(mp4_url, video_path):
                update_task_status(task_id, "失败")
                continue
            
            # 检查视频格式，如果不是mp4则转换
            video_ext = os.path.splitext(video_filename)[1].lower()
            converted_video_path = video_path
            if video_ext != '.mp4':
                print(f"检测到非MP4格式视频: {video_ext}，正在转换为MP4格式...")
                converted_video_path = f"{input_dir}/{os.path.splitext(video_filename)[0]}.mp4"
                try:
                    subprocess.run([
                        './ffmpeg', '-i', video_path, '-c:v', 'libx264', converted_video_path
                    ], check=True)
                    print(f"视频格式转换成功: {converted_video_path}")
                except Exception as e:
                    print(f"视频格式转换失败: {str(e)}")
                    update_task_status(task_id, "失败", task_note="视频格式转换失败")
                    continue
            
            # 处理视频，去除音频
            silent_video_path = f"{input_dir}/{timestamp}.mp4"
            try:
                subprocess.run([
                    './ffmpeg', '-i', converted_video_path, '-c:v', 'libx264', '-an', silent_video_path
                ], check=True)
            except Exception as e:
                print(f"处理视频失败: {str(e)}")
                update_task_status(task_id, "失败")
                continue
            
            # 复制文件到指定目录
            temp_dir = "D:\\heygem_data\\face2face\\temp"
            os.makedirs(temp_dir, exist_ok=True)
            
            temp_video_path = f"{temp_dir}\\{timestamp}.mp4"
            temp_audio_path = f"{temp_dir}\\{timestamp}.wav"
            
            # 复制无声视频
            try:
                with open(silent_video_path, 'rb') as src, open(temp_video_path, 'wb') as dst:
                    dst.write(src.read())
            except Exception as e:
                print(f"复制视频文件失败: {str(e)}")
                update_task_status(task_id, "失败")
                continue
            
            # 复制音频文件
            try:
                with open(wav_path, 'rb') as src, open(temp_audio_path, 'wb') as dst:
                    dst.write(src.read())
            except Exception as e:
                print(f"复制音频文件失败: {str(e)}")
                update_task_status(task_id, "失败")
                continue
            
            # 生成唯一code
            unique_code = str(uuid.uuid4())
            
            # 准备合成请求参数
            payload = {
                "audio_url": f"{timestamp}.wav",
                "video_url": f"{timestamp}.mp4",
                "code": unique_code,
                "chaofen": 0,
                "watermark_switch": 0,
                "pn": 1
            }
            
            # 发送合成请求
            try:
                response = requests.post(SYNTHESIS_URL, json=payload)
                if response.status_code != 200:
                    print(f"合成请求失败，状态码: {response.status_code}")
                    update_task_status(task_id, "失败")
                    continue
                
                print("合成请求成功")
                print("响应内容:", response.json())
                
                # 开始查询进度
                while True:
                    try:
                        # 发送进度查询请求
                        query_response = requests.get(f"{QUERY_URL}?code={unique_code}")
                        
                        if query_response.status_code != 200:
                            print(f"进度查询失败，状态码: {query_response.status_code}")
                            time.sleep(2)
                            continue
                        
                        progress_data = query_response.json()
                        print("当前进度:", progress_data)
                        
                        # 检查任务是否完成
                        if progress_data.get("data", {}).get("status") == 2:
                            print("任务已完成")
                            result_file = progress_data.get("data", {}).get("result")
                            print("生成结果文件:", result_file)
                            
                            # 复制结果文件到指定目录
                            src_path = f"D:\\heygem_data\\face2face\\temp{result_file}"
                            dst_filename = os.path.basename(result_file)
                            dst_path = f"{input_dir}/{dst_filename}"
                            
                            try:
                                with open(src_path, 'rb') as src, open(dst_path, 'wb') as dst:
                                    dst.write(src.read())
                            except Exception as e:
                                print(f"复制结果文件失败: {str(e)}")
                                update_task_status(task_id, "失败")
                                break
                            
                            # 上传到TOS
                            object_key = f"input/{timestamp}/{dst_filename}"
                            output_url = tos_put(dst_path, object_key)
                            
                            if output_url:
                                # 更新任务状态
                                update_task_status(task_id, "已完成", output_url)
                            else:
                                update_task_status(task_id, "失败")
                            
                            break

                        # 检查任务是否处于进行中状态
                        if progress_data.get("data", {}).get("status") == 1:
                            # 获取当前进度信息
                            progress_msg = progress_data.get("data", {}).get("msg", "")
                            if progress_msg:
                                # 更新任务状态，将进度信息更新到task_note字段
                                update_task_status(task_id, "处理中", task_note=progress_msg)
                                print(f"已更新任务进度信息: {progress_msg}")

                        # 检查任务是否失败
                        if progress_data.get("data", {}).get("status") == 3:
                            print("任务失败")
                            error_msg = progress_data.get("data", {}).get("msg", "未知错误")
                            print(f"失败原因: {error_msg}")
                            
                            # 更新任务状态为失败，并记录失败原因
                            update_task_status(task_id, "失败", task_note=error_msg)
                            
                            # 记录错误日志
                            if not os.path.exists("error"):
                                os.makedirs("error")
                            
                            with open("error/synthesis_error.log", 'a+') as f:
                                f.write(f"{datetime.now()} - 任务ID: {task_id} - 失败原因: {error_msg}\n")
                            
                            break
                        
                        # 每2秒查询一次进度
                        time.sleep(2)
                        
                    except Exception as query_error:
                        print(f"进度查询发生错误: {str(query_error)}")
                        time.sleep(5)  # 出错后等待5秒再重试
            
            except Exception as e:
                print(f"合成请求发生错误: {str(e)}")
                update_task_status(task_id, "失败")
        
        except Exception as e:
            print(f"处理任务时发生错误: {str(e)}")
            time.sleep(5)  # 出错后等待5秒再重试

def start_worker():
    """启动工作线程"""
    try:
        process_task()
    except Exception as e:
        print(f"工作线程发生错误: {str(e)}")
        # 重新启动一个线程
        threading.Thread(target=start_worker).start()

# 启动工作线程
threading.Thread(target=start_worker).start()

print("数字人合成服务已启动...")
# 版本号
VERSION = "v0.0.1"

# 打印版本号和ASCII艺术Logo
print(f"版本: {VERSION}")

# 打印tingwu文字Logo
print("""
████████╗██╗███╗   ██╗ ██████╗ ██╗    ██╗██╗   ██╗     █████╗ ██╗
╚══██╔══╝██║████╗  ██║██╔════╝ ██║    ██║██║   ██║    ██╔══██╗██║
   ██║   ██║██╔██╗ ██║██║  ███╗██║ █╗ ██║██║   ██║    ███████║██║
   ██║   ██║██║╚██╗██║██║   ██║██║███╗██║██║   ██║    ██╔══██║██║
   ██║   ██║██║ ╚████║╚██████╔╝╚███╔███╔╝╚██████╔╝    ██║  ██║██║
   ╚═╝   ╚═╝╚═╝  ╚═══╝ ╚═════╝  ╚══╝╚══╝  ╚═════╝     ╚═╝  ╚═╝╚═╝
""")
