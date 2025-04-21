-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2025-04-21 16:15:40
-- 服务器版本： 5.6.50-log
-- PHP 版本： 7.2.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `heygemdemo`
--

-- --------------------------------------------------------

--
-- 表的结构 `tasks`
--

CREATE TABLE `tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `wav` varchar(255) DEFAULT NULL COMMENT '音频文件',
  `mp4` varchar(255) DEFAULT NULL COMMENT '视频文件',
  `task_status` varchar(255) DEFAULT NULL COMMENT '合成状态',
  `task_note` varchar(255) DEFAULT NULL COMMENT '任务执行描述',
  `output_url` varchar(255) DEFAULT NULL COMMENT '最终合成的成片url',
  `add_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  `status` int(11) DEFAULT '1' COMMENT '1正常，-1删除，0禁用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `tasks`
--

INSERT INTO `tasks` (`id`, `wav`, `mp4`, `task_status`, `task_note`, `output_url`, `add_time`, `update_time`, `status`) VALUES
(1, 'https://xxxx/input/20250408143610/原始2.WAV', 'https://xxxx/input/20250408143611/原始5.mp4', '失败', NULL, NULL, '2025-04-08 14:36:11', '2025-04-08 15:49:49', 1),

--
-- 转储表的索引
--

--
-- 表的索引 `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
