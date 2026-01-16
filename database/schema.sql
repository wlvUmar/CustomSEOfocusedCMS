-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 16, 2026 at 01:00 AM
-- Server version: 10.3.39-MariaDB-log-cll-lve
-- PHP Version: 8.1.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `year` int(11) GENERATED ALWAYS AS (year(`date`)) STORED,
  `month` int(11) GENERATED ALWAYS AS (month(`date`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_bot_visits`
--

CREATE TABLE `analytics_bot_visits` (
  `id` int(11) UNSIGNED NOT NULL,
  `page_slug` varchar(255) NOT NULL,
  `language` varchar(5) NOT NULL,
  `bot_type` varchar(50) NOT NULL DEFAULT 'unknown',
  `user_agent` varchar(255) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visits` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `last_visit` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `analytics_link_clicks`
--

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
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `usage_count` int(11) DEFAULT 0 COMMENT 'How many pages use this media',
  `last_used` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `depth` int(11) NOT NULL DEFAULT 0,
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

-- --------------------------------------------------------

--
-- Table structure for table `page_link_widgets`
--

CREATE TABLE `page_link_widgets` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `link_to_page_id` int(11) NOT NULL,
  `position` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_media`
--

CREATE TABLE `page_media` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `section` varchar(50) DEFAULT 'content' COMMENT 'hero, content, gallery, banner',
  `position` int(11) DEFAULT 0 COMMENT 'Order within section',
  `alt_text_ru` varchar(255) DEFAULT NULL,
  `alt_text_uz` varchar(255) DEFAULT NULL,
  `caption_ru` text DEFAULT NULL,
  `caption_uz` text DEFAULT NULL,
  `width` int(11) DEFAULT NULL COMMENT 'Display width in pixels',
  `alignment` enum('left','center','right','full') DEFAULT 'center',
  `css_class` varchar(100) DEFAULT NULL,
  `lazy_load` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_engine_config`
--

CREATE TABLE `search_engine_config` (
  `id` int(11) NOT NULL,
  `engine` enum('bing','yandex','google') NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `api_key` varchar(255) DEFAULT NULL COMMENT 'For Bing IndexNow',
  `api_endpoint` varchar(500) DEFAULT NULL,
  `rate_limit_per_day` int(11) DEFAULT 100,
  `submissions_today` int(11) DEFAULT 0,
  `last_reset_date` date DEFAULT NULL,
  `auto_submit_on_create` tinyint(1) DEFAULT 1,
  `auto_submit_on_update` tinyint(1) DEFAULT 1,
  `auto_submit_on_rotation` tinyint(1) DEFAULT 1,
  `ping_sitemap` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_submissions`
--

CREATE TABLE `search_submissions` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `search_engine` enum('google','bing','yandex') NOT NULL,
  `submission_type` enum('create','update','rotation','manual','sitemap_ping') NOT NULL,
  `status` enum('pending','success','failed','rate_limited') DEFAULT 'pending',
  `response_code` int(11) DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `rotation_month` int(11) DEFAULT NULL COMMENT 'If triggered by rotation',
  `user_id` int(11) DEFAULT NULL COMMENT 'If manual submission'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_submission_status`
--

CREATE TABLE `search_submission_status` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `search_engine` enum('google','bing','yandex') NOT NULL,
  `last_submitted_at` timestamp NULL DEFAULT NULL,
  `last_success_at` timestamp NULL DEFAULT NULL,
  `total_submissions` int(11) DEFAULT 0,
  `successful_submissions` int(11) DEFAULT 0,
  `failed_submissions` int(11) DEFAULT 0,
  `last_status` enum('success','failed','rate_limited') DEFAULT NULL,
  `last_response` text DEFAULT NULL,
  `can_resubmit_at` timestamp NULL DEFAULT NULL COMMENT 'Rate limit cooldown',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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
  `google_review_url` varchar(500) DEFAULT NULL,
  `organization_schema` text DEFAULT NULL,
  `website_schema` text DEFAULT NULL,
  `service_schema` text DEFAULT NULL,
  `breadcrumb_schema` text DEFAULT NULL,
  `article_schema` text DEFAULT NULL,
  `image_schema` text DEFAULT NULL,
  `org_type` varchar(50) DEFAULT 'LocalBusiness',
  `org_name_ru` varchar(255) DEFAULT NULL,
  `org_name_uz` varchar(255) DEFAULT NULL,
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
  `service_type` varchar(100) DEFAULT 'Service',
  `area_served` varchar(100) DEFAULT NULL,
  `org_latitude` varchar(50) DEFAULT NULL,
  `org_longitude` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitemap_history`
--

CREATE TABLE `sitemap_history` (
  `id` int(11) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pages_count` int(11) DEFAULT 0,
  `file_size` int(11) DEFAULT 0,
  `generation_time_ms` int(11) DEFAULT NULL,
  `trigger` enum('auto','manual','cron') DEFAULT 'auto',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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
-- Stand-in structure for view `v_media_usage`
-- (See below for the actual view)
--
CREATE TABLE `v_media_usage` (
`id` int(11)
,`filename` varchar(255)
,`original_name` varchar(255)
,`file_size` int(11)
,`mime_type` varchar(100)
,`uploaded_at` timestamp
,`usage_count` int(11)
,`pages_count` bigint(21)
,`used_in_pages` mediumtext
,`page_titles_ru` mediumtext
,`last_used_on_page` timestamp
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
-- Stand-in structure for view `v_pages_due_resubmit`
-- (See below for the actual view)
--
CREATE TABLE `v_pages_due_resubmit` (
`slug` varchar(100)
,`title_ru` varchar(200)
,`updated_at` timestamp
,`last_submitted_at` timestamp
,`days_since_submission` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_page_breadcrumbs`
-- (See below for the actual view)
--
CREATE TABLE `v_page_breadcrumbs` (
`id` int(11)
,`slug` varchar(100)
,`title_ru` varchar(200)
,`title_uz` varchar(200)
,`parent_id` int(11)
,`total_levels` int(1)
,`path_ids` varchar(1000)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_page_hierarchy`
-- (See below for the actual view)
--
CREATE TABLE `v_page_hierarchy` (
`id` int(11)
,`slug` varchar(100)
,`title_ru` varchar(200)
,`title_uz` varchar(200)
,`parent_id` int(11)
,`depth` int(11)
,`is_published` tinyint(4)
,`path_ru` varchar(1000)
,`path_uz` varchar(1000)
,`id_path` varchar(1000)
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
-- Stand-in structure for view `v_recent_submissions`
-- (See below for the actual view)
--
CREATE TABLE `v_recent_submissions` (
`page_slug` varchar(100)
,`search_engine` enum('google','bing','yandex')
,`submission_type` enum('create','update','rotation','manual','sitemap_ping')
,`status` enum('pending','success','failed','rate_limited')
,`submitted_at` timestamp
,`response_code` int(11)
,`title_ru` varchar(200)
,`title_uz` varchar(200)
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
-- Stand-in structure for view `v_submission_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_submission_stats` (
`search_engine` enum('google','bing','yandex')
,`total_submissions` bigint(21)
,`successful` decimal(22,0)
,`failed` decimal(22,0)
,`rate_limited` decimal(22,0)
,`success_rate` decimal(28,2)
,`last_submission` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_unsubmitted_pages`
-- (See below for the actual view)
--
CREATE TABLE `v_unsubmitted_pages` (
`slug` varchar(100)
,`title_ru` varchar(200)
,`created_at` timestamp
,`updated_at` timestamp
,`days_since_update` int(7)
);

-- --------------------------------------------------------

--
-- Structure for view `v_crawl_frequency`
--
DROP TABLE IF EXISTS `v_crawl_frequency`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_crawl_frequency`  AS SELECT `analytics`.`page_slug` AS `page_slug`, count(distinct `analytics`.`date`) AS `total_days_tracked`, sum(`analytics`.`visits`) AS `total_visits`, avg(`analytics`.`visits`) AS `avg_daily_visits`, max(`analytics`.`date`) AS `last_crawl_date`, to_days(curdate()) - to_days(max(`analytics`.`date`)) AS `days_since_last_crawl`, CASE WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 1 THEN 'Daily' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 7 THEN 'Weekly' WHEN to_days(curdate()) - to_days(max(`analytics`.`date`)) <= 30 THEN 'Monthly' ELSE 'Rare' END AS `crawl_frequency_category` FROM `analytics` WHERE `analytics`.`date` >= curdate() - interval 90 day GROUP BY `analytics`.`page_slug` ;

-- --------------------------------------------------------

--
-- Structure for view `v_media_usage`
--
DROP TABLE IF EXISTS `v_media_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_media_usage`  AS SELECT `m`.`id` AS `id`, `m`.`filename` AS `filename`, `m`.`original_name` AS `original_name`, `m`.`file_size` AS `file_size`, `m`.`mime_type` AS `mime_type`, `m`.`uploaded_at` AS `uploaded_at`, `m`.`usage_count` AS `usage_count`, count(distinct `pm`.`page_id`) AS `pages_count`, group_concat(distinct `p`.`slug` order by `p`.`slug` ASC separator ', ') AS `used_in_pages`, group_concat(distinct `p`.`title_ru` order by `p`.`title_ru` ASC separator ' | ') AS `page_titles_ru`, max(`pm`.`updated_at`) AS `last_used_on_page` FROM ((`media` `m` left join `page_media` `pm` on(`m`.`id` = `pm`.`media_id`)) left join `pages` `p` on(`pm`.`page_id` = `p`.`id`)) GROUP BY `m`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_navigation_flow`
--
DROP TABLE IF EXISTS `v_navigation_flow`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_navigation_flow`  AS SELECT `analytics_internal_links`.`from_slug` AS `from_slug`, `analytics_internal_links`.`to_slug` AS `to_slug`, `analytics_internal_links`.`language` AS `language`, sum(`analytics_internal_links`.`clicks`) AS `total_clicks`, count(distinct `analytics_internal_links`.`date`) AS `active_days`, max(`analytics_internal_links`.`date`) AS `last_click_date`, to_days(curdate()) - to_days(max(`analytics_internal_links`.`date`)) AS `days_since_last_click` FROM `analytics_internal_links` WHERE `analytics_internal_links`.`date` >= curdate() - interval 90 day GROUP BY `analytics_internal_links`.`from_slug`, `analytics_internal_links`.`to_slug`, `analytics_internal_links`.`language` ORDER BY sum(`analytics_internal_links`.`clicks`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_pages_due_resubmit`
--
DROP TABLE IF EXISTS `v_pages_due_resubmit`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_pages_due_resubmit`  AS SELECT `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`updated_at` AS `updated_at`, max(`s`.`submitted_at`) AS `last_submitted_at`, to_days(current_timestamp()) - to_days(max(`s`.`submitted_at`)) AS `days_since_submission` FROM (`pages` `p` left join `search_submissions` `s` on(`p`.`slug` = `s`.`page_slug` and `s`.`status` = 'success')) WHERE `p`.`is_published` = 1 GROUP BY `p`.`slug`, `p`.`title_ru`, `p`.`updated_at` HAVING `last_submitted_at` is null OR to_days(current_timestamp()) - to_days(`last_submitted_at`) > 30 ORDER BY to_days(current_timestamp()) - to_days(max(`s`.`submitted_at`)) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_page_breadcrumbs`
--
DROP TABLE IF EXISTS `v_page_breadcrumbs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_page_breadcrumbs`  AS WITH breadcrumb_path AS (SELECT `pages`.`id` AS `id`, `pages`.`slug` AS `slug`, `pages`.`title_ru` AS `title_ru`, `pages`.`title_uz` AS `title_uz`, `pages`.`parent_id` AS `parent_id`, 0 AS `level`, cast(`pages`.`id` as char(1000) charset utf8mb4) AS `path_ids` FROM `pages` UNION ALL SELECT `bp`.`id` AS `id`, `bp`.`slug` AS `slug`, `bp`.`title_ru` AS `title_ru`, `bp`.`title_uz` AS `title_uz`, `p`.`parent_id` AS `parent_id`, `bp`.`level`+ 1 AS `bp.level + 1`, concat(cast(`p`.`id` as char charset utf8mb4),',',`bp`.`path_ids`) AS `CONCAT(CAST(p.id AS CHAR), ',', bp.path_ids)` FROM (`breadcrumb_path` `bp` join `pages` `p` on(`bp`.`parent_id` = `p`.`id`))) SELECT `breadcrumb_path`.`id` AS `id`, `breadcrumb_path`.`slug` AS `slug`, `breadcrumb_path`.`title_ru` AS `title_ru`, `breadcrumb_path`.`title_uz` AS `title_uz`, `breadcrumb_path`.`parent_id` AS `parent_id`, max(`breadcrumb_path`.`level`) AS `total_levels`, `breadcrumb_path`.`path_ids` AS `path_ids` FROM `breadcrumb_path` GROUP BY `breadcrumb_path`.`id`, `breadcrumb_path`.`slug`, `breadcrumb_path`.`title_ru`, `breadcrumb_path`.`title_uz`, `breadcrumb_path`.`parent_id`, `breadcrumb_path`.`path_ids``path_ids`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_page_hierarchy`
--
DROP TABLE IF EXISTS `v_page_hierarchy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_page_hierarchy`  AS WITH page_tree AS (SELECT `pages`.`id` AS `id`, `pages`.`slug` AS `slug`, `pages`.`title_ru` AS `title_ru`, `pages`.`title_uz` AS `title_uz`, `pages`.`parent_id` AS `parent_id`, `pages`.`depth` AS `depth`, `pages`.`is_published` AS `is_published`, cast(`pages`.`title_ru` as char(1000) charset utf8mb4) AS `path_ru`, cast(`pages`.`title_uz` as char(1000) charset utf8mb4) AS `path_uz`, cast(`pages`.`id` as char(1000) charset utf8mb4) AS `id_path` FROM `pages` WHERE `pages`.`parent_id` is null UNION ALL SELECT `p`.`id` AS `id`, `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`title_uz` AS `title_uz`, `p`.`parent_id` AS `parent_id`, `p`.`depth` AS `depth`, `p`.`is_published` AS `is_published`, concat(`pt`.`path_ru`,' > ',`p`.`title_ru`) AS `path_ru`, concat(`pt`.`path_uz`,' > ',`p`.`title_uz`) AS `path_uz`, concat(`pt`.`id_path`,',',`p`.`id`) AS `id_path` FROM (`pages` `p` join `page_tree` `pt` on(`p`.`parent_id` = `pt`.`id`))) SELECT `page_tree`.`id` AS `id`, `page_tree`.`slug` AS `slug`, `page_tree`.`title_ru` AS `title_ru`, `page_tree`.`title_uz` AS `title_uz`, `page_tree`.`parent_id` AS `parent_id`, `page_tree`.`depth` AS `depth`, `page_tree`.`is_published` AS `is_published`, `page_tree`.`path_ru` AS `path_ru`, `page_tree`.`path_uz` AS `path_uz`, `page_tree`.`id_path` AS `id_path` FROM `page_tree``page_tree`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_popular_paths`
--
DROP TABLE IF EXISTS `v_popular_paths`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_popular_paths`  AS SELECT `analytics_internal_links_monthly`.`from_slug` AS `from_slug`, `analytics_internal_links_monthly`.`to_slug` AS `to_slug`, sum(`analytics_internal_links_monthly`.`total_clicks`) AS `clicks`, count(distinct concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`)) AS `active_months` FROM `analytics_internal_links_monthly` WHERE cast(concat(`analytics_internal_links_monthly`.`year`,'-',`analytics_internal_links_monthly`.`month`,'-01') as date) >= curdate() - interval 6 month GROUP BY `analytics_internal_links_monthly`.`from_slug`, `analytics_internal_links_monthly`.`to_slug` HAVING `clicks` > 5 ORDER BY sum(`analytics_internal_links_monthly`.`total_clicks`) DESC LIMIT 0, 50 ;

-- --------------------------------------------------------

--
-- Structure for view `v_recent_submissions`
--
DROP TABLE IF EXISTS `v_recent_submissions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_recent_submissions`  AS SELECT `s`.`page_slug` AS `page_slug`, `s`.`search_engine` AS `search_engine`, `s`.`submission_type` AS `submission_type`, `s`.`status` AS `status`, `s`.`submitted_at` AS `submitted_at`, `s`.`response_code` AS `response_code`, `p`.`title_ru` AS `title_ru`, `p`.`title_uz` AS `title_uz` FROM (`search_submissions` `s` left join `pages` `p` on(`s`.`page_slug` = `p`.`slug`)) ORDER BY `s`.`submitted_at` DESC LIMIT 0, 100 ;

-- --------------------------------------------------------

--
-- Structure for view `v_rotation_effectiveness`
--
DROP TABLE IF EXISTS `v_rotation_effectiveness`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_rotation_effectiveness`  AS SELECT `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`enable_rotation` AS `enable_rotation`, count(distinct `cr`.`active_month`) AS `months_with_content`, sum(case when `cr`.`is_active` = 1 then 1 else 0 end) AS `active_rotations`, coalesce(sum(`ar`.`times_shown`),0) AS `total_times_shown`, max(`ar`.`last_shown`) AS `last_rotation_shown` FROM ((`pages` `p` left join `content_rotations` `cr` on(`p`.`id` = `cr`.`page_id`)) left join `analytics_rotations` `ar` on(`p`.`slug` = `ar`.`page_slug`)) WHERE `p`.`enable_rotation` = 1 GROUP BY `p`.`id`, `p`.`slug`, `p`.`title_ru`, `p`.`enable_rotation` ;

-- --------------------------------------------------------

--
-- Structure for view `v_submission_stats`
--
DROP TABLE IF EXISTS `v_submission_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_submission_stats`  AS SELECT `search_submissions`.`search_engine` AS `search_engine`, count(0) AS `total_submissions`, sum(case when `search_submissions`.`status` = 'success' then 1 else 0 end) AS `successful`, sum(case when `search_submissions`.`status` = 'failed' then 1 else 0 end) AS `failed`, sum(case when `search_submissions`.`status` = 'rate_limited' then 1 else 0 end) AS `rate_limited`, round(sum(case when `search_submissions`.`status` = 'success' then 1 else 0 end) * 100.0 / count(0),2) AS `success_rate`, max(`search_submissions`.`submitted_at`) AS `last_submission` FROM `search_submissions` GROUP BY `search_submissions`.`search_engine` ;

-- --------------------------------------------------------

--
-- Structure for view `v_unsubmitted_pages`
--
DROP TABLE IF EXISTS `v_unsubmitted_pages`;

CREATE ALGORITHM=UNDEFINED DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER VIEW `v_unsubmitted_pages`  AS SELECT `p`.`slug` AS `slug`, `p`.`title_ru` AS `title_ru`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, to_days(current_timestamp()) - to_days(`p`.`updated_at`) AS `days_since_update` FROM `pages` AS `p` WHERE `p`.`is_published` = 1 AND !exists(select 1 from `search_submissions` `s` where `s`.`page_slug` = `p`.`slug` AND `s`.`status` = 'success' limit 1) ORDER BY `p`.`updated_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily` (`page_slug`,`language`,`date`),
  ADD KEY `idx_analytics_date_slug` (`date`,`page_slug`),
  ADD KEY `idx_year_month` (`year`,`month`);

--
-- Indexes for table `analytics_bot_visits`
--
ALTER TABLE `analytics_bot_visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bot_visit` (`page_slug`,`language`,`bot_type`,`visit_date`),
  ADD KEY `idx_page_date` (`page_slug`,`visit_date`),
  ADD KEY `idx_bot_type` (`bot_type`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `idx_bot_crawl_frequency` (`page_slug`,`visit_date`,`bot_type`);

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
-- Indexes for table `analytics_link_clicks`
--
ALTER TABLE `analytics_link_clicks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_link_click` (`from_slug`,`to_slug`,`link_text`,`language`,`date`);

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
  ADD KEY `idx_filename` (`filename`),
  ADD KEY `idx_usage` (`usage_count`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_published` (`is_published`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_depth` (`depth`);

--
-- Indexes for table `page_link_widgets`
--
ALTER TABLE `page_link_widgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_link` (`page_id`,`link_to_page_id`),
  ADD KEY `link_to_page_id` (`link_to_page_id`);

--
-- Indexes for table `page_media`
--
ALTER TABLE `page_media`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_media_section` (`page_id`,`media_id`,`section`),
  ADD KEY `idx_page_id` (`page_id`),
  ADD KEY `idx_media_id` (`media_id`),
  ADD KEY `idx_section` (`section`);

--
-- Indexes for table `search_engine_config`
--
ALTER TABLE `search_engine_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_engine` (`engine`);

--
-- Indexes for table `search_submissions`
--
ALTER TABLE `search_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_slug` (`page_slug`),
  ADD KEY `idx_search_engine` (`search_engine`),
  ADD KEY `idx_submitted_at` (`submitted_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_engine_status` (`search_engine`,`status`,`submitted_at`);

--
-- Indexes for table `search_submission_status`
--
ALTER TABLE `search_submission_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_engine` (`page_slug`,`search_engine`),
  ADD KEY `idx_last_submitted` (`last_submitted_at`),
  ADD KEY `idx_can_resubmit` (`can_resubmit_at`),
  ADD KEY `idx_page_status` (`page_slug`,`last_status`);

--
-- Indexes for table `seo_settings`
--
ALTER TABLE `seo_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sitemap_history`
--
ALTER TABLE `sitemap_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generated_at` (`generated_at`);

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
-- AUTO_INCREMENT for table `analytics_bot_visits`
--
ALTER TABLE `analytics_bot_visits`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `analytics_link_clicks`
--
ALTER TABLE `analytics_link_clicks`
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
-- AUTO_INCREMENT for table `page_link_widgets`
--
ALTER TABLE `page_link_widgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_media`
--
ALTER TABLE `page_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_engine_config`
--
ALTER TABLE `search_engine_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_submissions`
--
ALTER TABLE `search_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_submission_status`
--
ALTER TABLE `search_submission_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seo_settings`
--
ALTER TABLE `seo_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sitemap_history`
--
ALTER TABLE `sitemap_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Add org_sameas_extra to seo_settings for backward compatible migrations
ALTER TABLE `seo_settings`
  ADD COLUMN `org_sameas_extra` text DEFAULT NULL AFTER `social_youtube`;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `content_rotations`
--
ALTER TABLE `content_rotations`
  ADD CONSTRAINT `content_rotations_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `page_link_widgets`
--
ALTER TABLE `page_link_widgets`
  ADD CONSTRAINT `page_link_widgets_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `page_link_widgets_ibfk_2` FOREIGN KEY (`link_to_page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `page_media`
--
ALTER TABLE `page_media`
  ADD CONSTRAINT `fk_page_media_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_page_media_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;
COMMIT;
