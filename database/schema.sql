
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(100) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `visits` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `bounce_rate` decimal(5,2) DEFAULT 0.00,
  `avg_time_seconds` int(11) DEFAULT 0,
  `date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `year` int(11) GENERATED ALWAYS AS (year(`date`)) STORED,
  `month` int(11) GENERATED ALWAYS AS (month(`date`)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily` (`page_slug`,`language`,`date`),
  KEY `idx_analytics_date_slug` (`date`,`page_slug`),
  KEY `idx_year_month` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=1271 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_bot_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_bot_visits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(255) NOT NULL,
  `language` varchar(5) NOT NULL,
  `bot_type` varchar(50) NOT NULL DEFAULT 'unknown',
  `user_agent` varchar(255) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visits` int(11) unsigned NOT NULL DEFAULT 1,
  `last_visit` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bot_visit` (`page_slug`,`language`,`bot_type`,`visit_date`),
  KEY `idx_page_date` (`page_slug`,`visit_date`),
  KEY `idx_bot_type` (`bot_type`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_bot_crawl_frequency` (`page_slug`,`visit_date`,`bot_type`)
) ENGINE=InnoDB AUTO_INCREMENT=467 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_internal_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_internal_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_slug` varchar(100) NOT NULL,
  `to_slug` varchar(100) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `clicks` int(11) DEFAULT 1,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_link` (`from_slug`,`to_slug`,`language`,`date`),
  KEY `idx_from_slug` (`from_slug`),
  KEY `idx_to_slug` (`to_slug`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_internal_links_monthly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_internal_links_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_slug` varchar(100) NOT NULL,
  `to_slug` varchar(100) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `total_clicks` int(11) DEFAULT 0,
  `unique_days` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_monthly_link` (`from_slug`,`to_slug`,`language`,`year`,`month`),
  KEY `idx_year_month` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_link_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_link_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_slug` varchar(100) DEFAULT NULL,
  `to_slug` varchar(100) DEFAULT NULL,
  `link_text` varchar(255) DEFAULT NULL,
  `clicks` int(11) DEFAULT 1,
  `language` varchar(5) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link_click` (`from_slug`,`to_slug`,`link_text`,`language`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_monthly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(100) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `total_visits` int(11) DEFAULT 0,
  `total_clicks` int(11) DEFAULT 0,
  `avg_time_seconds` int(11) DEFAULT 0,
  `unique_days` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_monthly` (`page_slug`,`language`,`year`,`month`),
  KEY `idx_year_month` (`year`,`month`),
  KEY `idx_analytics_monthly_date` (`year`,`month`)
) ENGINE=InnoDB AUTO_INCREMENT=1015 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analytics_rotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_rotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `rotation_month` int(11) NOT NULL,
  `language` varchar(5) DEFAULT 'ru',
  `times_shown` int(11) DEFAULT 1,
  `unique_days` int(11) DEFAULT 1,
  `last_shown` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rotation_tracking` (`page_slug`,`year`,`rotation_month`,`language`),
  KEY `idx_page_date` (`page_slug`,`year`,`rotation_month`),
  KEY `idx_last_shown` (`last_shown`)
) ENGINE=InnoDB AUTO_INCREMENT=334 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_rotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_rotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_page_month` (`page_id`,`active_month`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=656 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(100) NOT NULL,
  `question_ru` text NOT NULL,
  `question_uz` text NOT NULL,
  `answer_ru` text NOT NULL,
  `answer_uz` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`page_slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `usage_count` int(11) DEFAULT 0 COMMENT 'How many pages use this media',
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filename` (`filename`),
  KEY `idx_usage` (`usage_count`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_link_widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_link_widgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `link_to_page_id` int(11) NOT NULL,
  `position` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_page_link` (`page_id`,`link_to_page_id`),
  KEY `link_to_page_id` (`link_to_page_id`)
) ENGINE=InnoDB AUTO_INCREMENT=804 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_page_media_section` (`page_id`,`media_id`,`section`),
  KEY `idx_page_id` (`page_id`),
  KEY `idx_media_id` (`media_id`),
  KEY `idx_section` (`section`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `widget_title_uz` varchar(100) DEFAULT 'Foydali sahifalar',
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_published` (`is_published`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_depth` (`depth`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `org_sameas_extra` text DEFAULT NULL,
  `service_type` varchar(100) DEFAULT 'Service',
  `area_served` varchar(100) DEFAULT NULL,
  `org_latitude` varchar(50) DEFAULT NULL,
  `org_longitude` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_media_usage`;
/*!50001 DROP VIEW IF EXISTS `v_media_usage`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_media_usage` AS SELECT
 1 AS `id`,
  1 AS `filename`,
  1 AS `original_name`,
  1 AS `file_size`,
  1 AS `mime_type`,
  1 AS `uploaded_at`,
  1 AS `usage_count`,
  1 AS `pages_count`,
  1 AS `used_in_pages`,
  1 AS `page_titles_ru`,
  1 AS `last_used_on_page` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_popular_paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_popular_paths` (
  `from_slug` varchar(100) DEFAULT NULL,
  `to_slug` varchar(100) DEFAULT NULL,
  `clicks` decimal(32,0) DEFAULT NULL,
  `active_months` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_recent_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_recent_submissions` (
  `page_slug` varchar(100) DEFAULT NULL,
  `search_engine` enum('google','bing','yandex') DEFAULT NULL,
  `submission_type` enum('create','update','rotation','manual','sitemap_ping') DEFAULT NULL,
  `status` enum('pending','success','failed','rate_limited') DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `response_code` int(11) DEFAULT NULL,
  `title_ru` varchar(200) DEFAULT NULL,
  `title_uz` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_rotation_effectiveness`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_rotation_effectiveness` (
  `slug` varchar(100) DEFAULT NULL,
  `title_ru` varchar(200) DEFAULT NULL,
  `enable_rotation` tinyint(1) DEFAULT NULL,
  `months_with_content` bigint(21) DEFAULT NULL,
  `active_rotations` decimal(22,0) DEFAULT NULL,
  `total_times_shown` decimal(32,0) DEFAULT NULL,
  `last_rotation_shown` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_submission_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_submission_stats` (
  `search_engine` enum('google','bing','yandex') DEFAULT NULL,
  `total_submissions` bigint(21) DEFAULT NULL,
  `successful` decimal(22,0) DEFAULT NULL,
  `failed` decimal(22,0) DEFAULT NULL,
  `rate_limited` decimal(22,0) DEFAULT NULL,
  `success_rate` decimal(28,2) DEFAULT NULL,
  `last_submission` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_unsubmitted_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_unsubmitted_pages` (
  `slug` varchar(100) DEFAULT NULL,
  `title_ru` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `days_since_update` int(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `v_media_usage`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`kuplyuta`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_media_usage` AS select `m`.`id` AS `id`,`m`.`filename` AS `filename`,`m`.`original_name` AS `original_name`,`m`.`file_size` AS `file_size`,`m`.`mime_type` AS `mime_type`,`m`.`uploaded_at` AS `uploaded_at`,`m`.`usage_count` AS `usage_count`,count(distinct `pm`.`page_id`) AS `pages_count`,group_concat(distinct `p`.`slug` order by `p`.`slug` ASC separator ', ') AS `used_in_pages`,group_concat(distinct `p`.`title_ru` order by `p`.`title_ru` ASC separator ' | ') AS `page_titles_ru`,max(`pm`.`updated_at`) AS `last_used_on_page` from ((`media` `m` left join `page_media` `pm` on(`m`.`id` = `pm`.`media_id`)) left join `pages` `p` on(`pm`.`page_id` = `p`.`id`)) group by `m`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

