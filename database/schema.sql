SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `kuplyuta_db` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `kuplyuta_db`;

CREATE TABLE IF NOT EXISTS `analytics_bot_visits` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_slug` VARCHAR(255) NOT NULL,
  `language` VARCHAR(5) NOT NULL,
  `bot_type` VARCHAR(50) NOT NULL DEFAULT 'unknown',
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `visit_date` DATE NOT NULL,
  `visits` INT(11) UNSIGNED NOT NULL DEFAULT 1,
  `last_visit` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bot_visit` (`page_slug`, `language`, `bot_type`, `visit_date`),
  KEY `idx_page_date` (`page_slug`, `visit_date`),
  KEY `idx_bot_type` (`bot_type`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_bot_crawl_frequency` (`page_slug`, `visit_date`, `bot_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 

CREATE TABLE `analytics` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `visits` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `bounce_rate` decimal(5,2) DEFAULT 0.00,
  `avg_time_seconds` int(11) DEFAULT 0,
  `date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `year` int(11) GENERATED ALWAYS AS (year(`date`)) STORED,
  `month` int(11) GENERATED ALWAYS AS (month(`date`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `analytics_link_clicks` (
  `id` int(11) NOT NULL,
  `from_slug` varchar(100) DEFAULT NULL,
  `to_slug` varchar(100) DEFAULT NULL,
  `link_text` varchar(255) DEFAULT NULL,
  `clicks` int(11) DEFAULT 1,
  `language` varchar(5) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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

CREATE TABLE `content_rotations` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `title_ru` varchar(200) DEFAULT NULL,
  `title_uz` varchar(200) DEFAULT NULL,
  `content_ru` longtext DEFAULT NULL,
  `content_uz` longtext DEFAULT NULL,
  `description_ru` text DEFAULT NULL,
  `description_uz` text DEFAULT NULL,
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

CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `show_link_widget` tinyint(1) DEFAULT 1,
  `widget_title_ru` varchar(100) DEFAULT 'Полезные страницы',
  `widget_title_uz` varchar(100) DEFAULT 'Foydali sahifalar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `page_link_widgets` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `link_to_page_id` int(11) NOT NULL,
  `position` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `organization_schema` text DEFAULT NULL,
  `website_schema` text DEFAULT NULL,
  `service_schema` text DEFAULT NULL,
  `breadcrumb_schema` text DEFAULT NULL,
  `article_schema` text DEFAULT NULL,
  `image_schema` text DEFAULT NULL,
  `org_type` varchar(50) DEFAULT 'LocalBusiness',
  `org_logo` varchar(500) DEFAULT NULL,
  `org_description_ru` text DEFAULT NULL,
  `org_description_uz` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(10) DEFAULT 'UZ',
  `opening_hours` text DEFAULT NULL,
  `price_range` varchar(20) DEFAULT NULL,
  `social_facebook` varchar(500) DEFAULT NULL,
  `social_instagram` varchar(500) DEFAULT NULL,
  `social_twitter` varchar(500) DEFAULT NULL,
  `social_youtube` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `v_crawl_frequency` (
`page_slug` varchar(100)
,`total_days_tracked` bigint(21)
,`total_visits` decimal(32,0)
,`avg_daily_visits` decimal(14,4)
,`last_crawl_date` date
,`days_since_last_crawl` int(7)
,`crawl_frequency_category` varchar(7)
);
CREATE TABLE `v_navigation_flow` (
`from_slug` varchar(100)
,`to_slug` varchar(100)
,`language` varchar(5)
,`total_clicks` decimal(32,0)
,`active_days` bigint(21)
,`last_click_date` date
,`days_since_last_click` int(7)
);
CREATE TABLE `v_popular_paths` (
`from_slug` varchar(100)
,`to_slug` varchar(100)
,`clicks` decimal(32,0)
,`active_months` bigint(21)
);
CREATE TABLE `v_rotation_effectiveness` (
`slug` varchar(100)
,`title_ru` varchar(200)
,`enable_rotation` tinyint(1)
,`months_with_content` bigint(21)
,`active_rotations` decimal(22,0)
,`total_times_shown` decimal(32,0)
,`last_rotation_shown` date
);
DROP TABLE IF EXISTS `v_crawl_frequency`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_crawl_frequency`  AS SELECT `analytics`.`page_slug` AS `page_slug`, count(distinct `analytics`.`date`) AS `total_days_tracked`, sum(`analytics`.`visits`) AS `total_visits`, avg(`analytics`.`visits`) AS `avg_daily_visits`, max(`analytics`.`date`) AS `last_crawl_date`, to_days(curdate()) - to_days(max(`analytics`.`date`)) AS `days_since_last_crawl`, CASE WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 1 THEN 'Daily' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 7 THEN 'Weekly' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 30 THEN 'Monthly' ELSE 'Rare' END AS `crawl_frequency_category` FROM `analytics` WHERE `analytics`.`date` >= curdate() - interval 90 day GROUP BY `analytics`.`page_slug` ;
DROP TABLE IF EXISTS `v_navigation_flow`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_navigation_flow`  AS SELECT `analytics_internal_links`.`from_slug` AS `from_slug`, `analytics_internal_links`.`to_slug` AS `to_slug`, `analytics_internal_links`.`language` AS `language`, sum(`analytics_internal_links`.`clicks`) AS `total_clicks`, count(distinct `analytics_internal_links`.`date`) AS `active_days`, max(`analytics_internal_links`.`date`) AS `last_click_date`, to_days(curdate()) - to_days(max(`analytics_internal_links`.`date`)) AS `days_since_last_click` FROM `analytics_internal_links` WHERE `analytics_internal_links`.`date` >= curdate() - interval 90 day GROUP BY `analytics_internal_links`.`from_slug`, `analytics_internal_links`.`to_slug`, `analytics_internal_links`.`language` ORDER BY sum(`analytics_internal_links`.`clicks`) DESC ;
DROP TABLE IF EXISTS `v_popular_paths`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_popular_paths`  AS SELECT `analytics_internal_links_monthly`.`from_slug` AS `from_slug`, `analytics_internal_links_monthly`.`to_slug` AS `to_slug`, sum(`analytics_internal_links_monthly`.`total_clicks`) AS `clicks`, count(distinct concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`)) AS `active_months` FROM `analytics_internal_links_monthly` WHERE cast(concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`,'-01') as date) >= curdate() - interval 6 month GROUP BY `analytics_internal_links_monthly`.`from_slug`, `analytics_internal_links_monthly`.`to_slug` HAVING `clicks` > 5 ORDER BY sum(`analytics_internal_links_monthly`.`total_clicks`) DESC LIMIT 0, 50 ;
DROP TABLE IF EXISTS `v_rotation_effectiveness`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_rotation_effectiveness`  AS SELECT `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`enable_rotation` AS `enable_rotation`, count(distinct `cr`.`active_month`) AS `months_with_content`, sum(case when `cr`.`is_active` = 1 then 1 else 0 end) AS `active_rotations`, coalesce(sum(`ar`.`times_shown`),0) AS `total_times_shown`, max(`ar`.`last_shown`) AS `last_rotation_shown` FROM ((`pages` `p` left join `content_rotations` `cr` on(`p`.`id` = `cr`.`page_id`)) left join `analytics_rotations` `ar` on(`p`.`slug` = `ar`.`page_slug`)) WHERE `p`.`enable_rotation` = 1 GROUP BY `p`.`id`, `p`.`slug`, `p`.`title_ru`, `p`.`enable_rotation` ;


ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily` (`page_slug`,`language`,`date`),
  ADD KEY `idx_analytics_date_slug` (`date`,`page_slug`),
  ADD KEY `idx_year_month` (`year`,`month`);

ALTER TABLE `analytics_internal_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_link` (`from_slug`,`to_slug`,`language`,`date`),
  ADD KEY `idx_from_slug` (`from_slug`),
  ADD KEY `idx_to_slug` (`to_slug`),
  ADD KEY `idx_date` (`date`);

ALTER TABLE `analytics_internal_links_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_monthly_link` (`from_slug`,`to_slug`,`language`,`year`,`month`),
  ADD KEY `idx_year_month` (`year`,`month`);

ALTER TABLE `analytics_link_clicks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_link_click` (`from_slug`,`to_slug`,`link_text`,`language`,`date`);

ALTER TABLE `analytics_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_monthly` (`page_slug`,`language`,`year`,`month`),
  ADD KEY `idx_year_month` (`year`,`month`),
  ADD KEY `idx_analytics_monthly_date` (`year`,`month`);

ALTER TABLE `analytics_rotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rotation_tracking` (`page_slug`,`year`,`rotation_month`,`language`),
  ADD KEY `idx_page_date` (`page_slug`,`year`,`rotation_month`),
  ADD KEY `idx_last_shown` (`last_shown`);

ALTER TABLE `content_rotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_month` (`page_id`,`active_month`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slug` (`page_slug`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_filename` (`filename`);

ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_published` (`is_published`);

ALTER TABLE `page_link_widgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_link` (`page_id`,`link_to_page_id`),
  ADD KEY `link_to_page_id` (`link_to_page_id`);

ALTER TABLE `seo_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_internal_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_internal_links_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_link_clicks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `content_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `page_link_widgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `seo_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `content_rotations`
  ADD CONSTRAINT `content_rotations_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

ALTER TABLE `page_link_widgets`
  ADD CONSTRAINT `page_link_widgets_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `page_link_widgets_ibfk_2` FOREIGN KEY (`link_to_page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
