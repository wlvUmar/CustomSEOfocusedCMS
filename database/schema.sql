-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 01, 2026 at 09:32 PM
-- Server version: 10.3.39-MariaDB-log-cll-lve
-- PHP Version: 8.1.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kuplyuta_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics`
--

CREATE TABLE `analytics` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `visits` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `bounce_rate` decimal(5,2) DEFAULT 0.00,
  `avg_time_seconds` int(11) DEFAULT 0,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_internal_links`
--

CREATE TABLE `analytics_internal_links` (
  `id` int(11) NOT NULL,
  `from_slug` varchar(100) NOT NULL,
  `to_slug` varchar(100) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `clicks` int(11) DEFAULT 1,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_internal_links_monthly`
--

CREATE TABLE `analytics_internal_links_monthly` (
  `id` int(11) NOT NULL,
  `from_slug` varchar(100) NOT NULL,
  `to_slug` varchar(100) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `total_clicks` int(11) DEFAULT 0,
  `unique_days` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_monthly`
--

CREATE TABLE `analytics_monthly` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `total_visits` int(11) DEFAULT 0,
  `total_clicks` int(11) DEFAULT 0,
  `avg_time_seconds` int(11) DEFAULT 0,
  `unique_days` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_rotations`
--

CREATE TABLE `analytics_rotations` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `rotation_month` int(11) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `times_shown` int(11) DEFAULT 1,
  `unique_days` int(11) DEFAULT 1,
  `last_shown` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_rotations`
--

CREATE TABLE `content_rotations` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `content_ru` longtext DEFAULT NULL,
  `content_uz` longtext DEFAULT NULL,
  `meta_title_ru` varchar(200) DEFAULT NULL,
  `meta_title_uz` varchar(200) DEFAULT NULL,
  `meta_description_ru` text DEFAULT NULL,
  `meta_description_uz` text DEFAULT NULL,
  `meta_keywords_ru` text DEFAULT NULL,
  `meta_keywords_uz` text DEFAULT NULL,
  `og_title_ru` varchar(200) DEFAULT NULL,
  `og_title_uz` varchar(200) DEFAULT NULL,
  `og_description_ru` text DEFAULT NULL,
  `og_description_uz` text DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `jsonld_ru` longtext DEFAULT NULL,
  `jsonld_uz` longtext DEFAULT NULL,
  `active_month` int(11) NOT NULL COMMENT '1-12 for Jan-Dec',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `question_ru` text NOT NULL,
  `question_uz` text NOT NULL,
  `answer_ru` text NOT NULL,
  `answer_uz` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title_ru` varchar(200) NOT NULL,
  `title_uz` varchar(200) NOT NULL,
  `content_ru` longtext DEFAULT NULL,
  `content_uz` longtext DEFAULT NULL,
  `meta_title_ru` varchar(200) DEFAULT NULL,
  `meta_title_uz` varchar(200) DEFAULT NULL,
  `meta_keywords_ru` text DEFAULT NULL,
  `meta_keywords_uz` text DEFAULT NULL,
  `meta_description_ru` text DEFAULT NULL,
  `meta_description_uz` text DEFAULT NULL,
  `og_title_ru` varchar(200) DEFAULT NULL,
  `og_title_uz` varchar(200) DEFAULT NULL,
  `og_description_ru` text DEFAULT NULL,
  `og_description_uz` text DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `canonical_url` varchar(500) DEFAULT NULL,
  `enable_rotation` tinyint(1) DEFAULT 0,
  `jsonld_ru` longtext DEFAULT NULL,
  `jsonld_uz` longtext DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seo_settings`
--

CREATE TABLE `seo_settings` (
  `id` int(11) NOT NULL,
  `site_name_ru` varchar(200) NOT NULL,
  `site_name_uz` varchar(200) NOT NULL,
  `meta_keywords_ru` text DEFAULT NULL,
  `meta_keywords_uz` text DEFAULT NULL,
  `meta_description_ru` text DEFAULT NULL,
  `meta_description_uz` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address_ru` text DEFAULT NULL,
  `address_uz` text DEFAULT NULL,
  `working_hours_ru` varchar(200) DEFAULT NULL,
  `working_hours_uz` varchar(200) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_crawl_frequency`
-- (See below for the actual view)
--
CREATE TABLE `v_crawl_frequency` (
`page_slug` varchar(100)
,`total_days_tracked` bigint(21)
,`total_visits` decimal(32,0)
,`avg_daily_visits` decimal(14,4)
,`last_crawl_date` date
,`days_since_last_crawl` int(7)
,`crawl_frequency_category` varchar(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_navigation_flow`
-- (See below for the actual view)
--
CREATE TABLE `v_navigation_flow` (
`from_slug` varchar(100)
,`to_slug` varchar(100)
,`language` varchar(5)
,`total_clicks` decimal(32,0)
,`active_days` bigint(21)
,`last_click_date` date
,`days_since_last_click` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_popular_paths`
-- (See below for the actual view)
--
CREATE TABLE `v_popular_paths` (
`from_slug` varchar(100)
,`to_slug` varchar(100)
,`clicks` decimal(32,0)
,`active_months` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_rotation_effectiveness`
-- (See below for the actual view)
--
CREATE TABLE `v_rotation_effectiveness` (
`slug` varchar(100)
,`title_ru` varchar(200)
,`enable_rotation` tinyint(1)
,`months_with_content` bigint(21)
,`active_rotations` decimal(22,0)
,`total_times_shown` decimal(32,0)
,`last_rotation_shown` date
);

-- --------------------------------------------------------

--
-- Structure for view `v_crawl_frequency`
--
DROP TABLE IF EXISTS `v_crawl_frequency`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_crawl_frequency`  AS SELECT `analytics`.`page_slug` AS `page_slug`, count(distinct `analytics`.`date`) AS `total_days_tracked`, sum(`analytics`.`visits`) AS `total_visits`, avg(`analytics`.`visits`) AS `avg_daily_visits`, max(`analytics`.`date`) AS `last_crawl_date`, to_days(curdate()) - to_days(max(`analytics`.`date`)) AS `days_since_last_crawl`, CASE WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 1 THEN 'Daily' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 7 THEN 'Weekly' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 30 THEN 'Monthly' ELSE 'Rare' END AS `crawl_frequency_category` FROM `analytics` WHERE `analytics`.`date` >= curdate() - interval 90 day GROUP BY `analytics`.`page_slug` ;

-- --------------------------------------------------------

--
-- Structure for view `v_navigation_flow`
--
DROP TABLE IF EXISTS `v_navigation_flow`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_navigation_flow`  AS SELECT `analytics_internal_links`.`from_slug` AS `from_slug`, `analytics_internal_links`.`to_slug` AS `to_slug`, `analytics_internal_links`.`language` AS `language`, sum(`analytics_internal_links`.`clicks`) AS `total_clicks`, count(distinct `analytics_internal_links`.`date`) AS `active_days`, max(`analytics_internal_links`.`date`) AS `last_click_date`, to_days(curdate()) - to_days(max(`analytics_internal_links`.`date`)) AS `days_since_last_click` FROM `analytics_internal_links` WHERE `analytics_internal_links`.`date` >= curdate() - interval 90 day GROUP BY `analytics_internal_links`.`from_slug`, `analytics_internal_links`.`to_slug`, `analytics_internal_links`.`language` ORDER BY sum(`analytics_internal_links`.`clicks`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_popular_paths`
--
DROP TABLE IF EXISTS `v_popular_paths`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_popular_paths`  AS SELECT `analytics_internal_links_monthly`.`from_slug` AS `from_slug`, `analytics_internal_links_monthly`.`to_slug` AS `to_slug`, sum(`analytics_internal_links_monthly`.`total_clicks`) AS `clicks`, count(distinct concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`)) AS `active_months` FROM `analytics_internal_links_monthly` WHERE cast(concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`,'-01') as date) >= curdate() - interval 6 month GROUP BY `analytics_internal_links_monthly`.`from_slug`, `analytics_internal_links_monthly`.`to_slug` HAVING `clicks` > 5 ORDER BY sum(`analytics_internal_links_monthly`.`total_clicks`) DESC LIMIT 0, 50 ;

-- --------------------------------------------------------

--
-- Structure for view `v_rotation_effectiveness`
--
DROP TABLE IF EXISTS `v_rotation_effectiveness`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_rotation_effectiveness`  AS SELECT `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`enable_rotation` AS `enable_rotation`, count(distinct `cr`.`active_month`) AS `months_with_content`, sum(case when `cr`.`is_active` = 1 then 1 else 0 end) AS `active_rotations`, coalesce(sum(`ar`.`times_shown`),0) AS `total_times_shown`, max(`ar`.`last_shown`) AS `last_rotation_shown` FROM ((`pages` `p` left join `content_rotations` `cr` on(`p`.`id` = `cr`.`page_id`)) left join `analytics_rotations` `ar` on(`p`.`slug` = `ar`.`page_slug`)) WHERE `p`.`enable_rotation` = 1 GROUP BY `p`.`id`, `p`.`slug`, `p`.`title_ru`, `p`.`enable_rotation` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily` (`page_slug`,`language`,`date`),
  ADD KEY `idx_analytics_date_slug` (`date`,`page_slug`);

--
-- Indexes for table `analytics_internal_links`
--
ALTER TABLE `analytics_internal_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_link` (`from_slug`,`to_slug`,`language`,`date`),
  ADD KEY `idx_from_slug` (`from_slug`),
  ADD KEY `idx_to_slug` (`to_slug`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `analytics_internal_links_monthly`
--
ALTER TABLE `analytics_internal_links_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_monthly_link` (`from_slug`,`to_slug`,`language`,`year`,`month`),
  ADD KEY `idx_year_month` (`year`,`month`);

--
-- Indexes for table `analytics_monthly`
--
ALTER TABLE `analytics_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_monthly` (`page_slug`,`language`,`year`,`month`),
  ADD KEY `idx_year_month` (`year`,`month`),
  ADD KEY `idx_analytics_monthly_date` (`year`,`month`);

--
-- Indexes for table `analytics_rotations`
--
ALTER TABLE `analytics_rotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rotation_tracking` (`page_slug`,`year`,`rotation_month`,`language`),
  ADD KEY `idx_page_date` (`page_slug`,`year`,`rotation_month`),
  ADD KEY `idx_last_shown` (`last_shown`);

--
-- Indexes for table `content_rotations`
--
ALTER TABLE `content_rotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_month` (`page_id`,`active_month`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slug` (`page_slug`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_filename` (`filename`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_published` (`is_published`);

--
-- Indexes for table `seo_settings`
--
ALTER TABLE `seo_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics`
--
ALTER TABLE `analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_internal_links`
--
ALTER TABLE `analytics_internal_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_internal_links_monthly`
--
ALTER TABLE `analytics_internal_links_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_monthly`
--
ALTER TABLE `analytics_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_rotations`
--
ALTER TABLE `analytics_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_rotations`
--
ALTER TABLE `content_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seo_settings`
--
ALTER TABLE `seo_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `content_rotations`
--
ALTER TABLE `content_rotations`
  ADD CONSTRAINT `content_rotations_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
