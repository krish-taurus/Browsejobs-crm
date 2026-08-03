-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobsbackendlaravel
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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

--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_recipients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email_status` enum('skipped','pending','sent','failed') NOT NULL DEFAULT 'skipped',
  `whatsapp_status` enum('skipped','pending','sent','failed') NOT NULL DEFAULT 'skipped',
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_recipients_student_id_foreign` (`student_id`),
  KEY `campaign_recipients_campaign_id_index` (`campaign_id`),
  CONSTRAINT `campaign_recipients_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_recipients_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `channel` enum('email','whatsapp','both') NOT NULL DEFAULT 'email',
  `audience` enum('all','active') NOT NULL DEFAULT 'active',
  `subject` varchar(255) DEFAULT NULL,
  `body` longtext NOT NULL,
  `whatsapp_template` varchar(255) DEFAULT NULL,
  `whatsapp_template_lang` varchar(255) NOT NULL DEFAULT 'en_US',
  `status` enum('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
  `total_recipients` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_email` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_whatsapp` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaigns_created_by_foreign` (`created_by`),
  KEY `campaigns_status_index` (`status`),
  CONSTRAINT `campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_participants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_foreign` (`user_id`),
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('private','group') NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_created_by_foreign` (`created_by`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_catalog`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `title` varchar(200) NOT NULL,
  `short_title` varchar(120) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `duration` varchar(60) DEFAULT NULL,
  `price` int(10) unsigned NOT NULL DEFAULT 0,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `emi_text` varchar(120) DEFAULT NULL,
  `status` enum('live','coming_soon') NOT NULL DEFAULT 'live',
  `is_bestseller` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_slug` (`slug`),
  KEY `idx_course_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_inquiries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_inquiries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `course_slug` varchar(120) DEFAULT NULL,
  `course_title` varchar(200) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','closed') NOT NULL DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inquiry_ref` (`reference`),
  KEY `idx_inquiry_course` (`course_id`),
  KEY `idx_inquiry_status` (`status`),
  CONSTRAINT `fk_inquiry_course` FOREIGN KEY (`course_id`) REFERENCES `course_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_registrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `course_slug` varchar(120) NOT NULL,
  `course_title` varchar(200) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `razorpay_order_id` varchar(64) DEFAULT NULL,
  `razorpay_payment_id` varchar(64) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `payment_method` varchar(40) DEFAULT NULL,
  `payment_status` enum('pending','created','paid','failed') NOT NULL DEFAULT 'pending',
  `failure_reason` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(40) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reg_ref` (`reference`),
  UNIQUE KEY `uq_reg_invoice` (`invoice_number`),
  KEY `idx_reg_order` (`razorpay_order_id`),
  KEY `idx_reg_status` (`payment_status`),
  KEY `idx_reg_course` (`course_id`),
  CONSTRAINT `fk_reg_course` FOREIGN KEY (`course_id`) REFERENCES `course_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_attachments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email_id` bigint(20) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_attachments_email_id_foreign` (`email_id`),
  CONSTRAINT `email_attachments_email_id_foreign` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `emails`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `emails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`to`)),
  `cc` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cc`)),
  `bcc` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bcc`)),
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `error` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emails_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `emails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enrollments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) DEFAULT NULL,
  `student_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `course_slug` varchar(100) NOT NULL,
  `course_title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Registration fee amount in INR',
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_status` enum('pending','created','paid','failed','order_failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_reference_unique` (`reference`),
  KEY `enrollments_course_slug_index` (`course_slug`),
  KEY `enrollments_razorpay_order_id_index` (`razorpay_order_id`),
  KEY `enrollments_payment_status_index` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','card','upi','bank_transfer','cheque','other') NOT NULL DEFAULT 'cash',
  `vendor` varchar(255) DEFAULT NULL,
  `status` enum('paid','pending','cancelled') NOT NULL DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_created_by_foreign` (`created_by`),
  KEY `expenses_status_expense_date_index` (`status`,`expense_date`),
  CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `disk_path` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `drive_link` varchar(255) DEFAULT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_folder_id_foreign` (`folder_id`),
  KEY `files_user_id_folder_id_index` (`user_id`,`folder_id`),
  CONSTRAINT `files_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `drive_folder_id` varchar(255) DEFAULT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `folders_parent_id_foreign` (`parent_id`),
  KEY `folders_user_id_parent_id_index` (`user_id`,`parent_id`),
  CONSTRAINT `folders_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `folders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `google_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `google_email` varchar(255) DEFAULT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_accounts_user_id_unique` (`user_id`),
  CONSTRAINT `google_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `holidays`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holidays_created_by_foreign` (`created_by`),
  KEY `holidays_year_holiday_date_index` (`year`,`holiday_date`),
  CONSTRAINT `holidays_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `incentive_records`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incentive_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('incentive','bonus') NOT NULL DEFAULT 'incentive',
  `basis` varchar(255) DEFAULT NULL,
  `quantity` decimal(12,2) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `period_month` tinyint(3) unsigned NOT NULL,
  `period_year` smallint(5) unsigned NOT NULL,
  `status` enum('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `awarded_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `awarded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incentive_records_awarded_by_user_id_foreign` (`awarded_by_user_id`),
  KEY `incentive_records_user_id_type_index` (`user_id`,`type`),
  KEY `incentive_records_period_year_period_month_index` (`period_year`,`period_month`),
  CONSTRAINT `incentive_records_awarded_by_user_id_foreign` FOREIGN KEY (`awarded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incentive_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_ai_analyses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_ai_analyses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(40) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `analysis` longtext NOT NULL,
  `requested_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_ai_analyses_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `lead_ai_analyses_lead_id_created_at_index` (`lead_id`,`created_at`),
  CONSTRAINT `lead_ai_analyses_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_ai_analyses_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `assigned_to_user_id` bigint(20) unsigned NOT NULL,
  `assigned_by_user_id` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_assignments_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  KEY `lead_assignments_assigned_by_user_id_foreign` (`assigned_by_user_id`),
  KEY `lead_assignments_lead_id_index` (`lead_id`),
  CONSTRAINT `lead_assignments_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_assignments_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_assignments_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=602 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_calls`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_calls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `type` enum('ai','manual') NOT NULL DEFAULT 'manual',
  `provider` varchar(255) DEFAULT NULL,
  `external_campaign_id` varchar(255) DEFAULT NULL,
  `external_call_id` varchar(255) DEFAULT NULL,
  `agent_id` varchar(255) DEFAULT NULL,
  `status` enum('queued','ringing','in_progress','completed','failed','no_answer','busy','cancelled') NOT NULL DEFAULT 'queued',
  `disposition` varchar(255) DEFAULT NULL,
  `sentiment` varchar(255) DEFAULT NULL,
  `transcript` longtext DEFAULT NULL,
  `recording_url` varchar(1024) DEFAULT NULL,
  `audio_path` varchar(255) DEFAULT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `from_number` varchar(255) DEFAULT NULL,
  `to_number` varchar(255) DEFAULT NULL,
  `language` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `initiated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_calls_initiated_by_user_id_foreign` (`initiated_by_user_id`),
  KEY `lead_calls_lead_id_index` (`lead_id`),
  KEY `lead_calls_external_campaign_id_index` (`external_campaign_id`),
  KEY `lead_calls_external_call_id_index` (`external_call_id`),
  CONSTRAINT `lead_calls_initiated_by_user_id_foreign` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_calls_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_conversation_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_conversation_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `tag` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_conversation_tags_conversation_id_foreign` (`conversation_id`),
  KEY `lead_conversation_tags_tag_index` (`tag`),
  CONSTRAINT `lead_conversation_tags_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `lead_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `channel` enum('call','whatsapp','email','sms') NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `transcript` longtext NOT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `recording_url` varchar(255) DEFAULT NULL,
  `handled_by_user_id` bigint(20) unsigned NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_conversations_handled_by_user_id_foreign` (`handled_by_user_id`),
  KEY `lead_conversations_lead_id_occurred_at_index` (`lead_id`,`occurred_at`),
  KEY `lead_conversations_channel_index` (`channel`),
  CONSTRAINT `lead_conversations_handled_by_user_id_foreign` FOREIGN KEY (`handled_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_conversations_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `notify_user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_notifications_lead_id_foreign` (`lead_id`),
  KEY `lead_notifications_notify_user_id_is_read_index` (`notify_user_id`,`is_read`),
  CONSTRAINT `lead_notifications_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_notifications_notify_user_id_foreign` FOREIGN KEY (`notify_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_status_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `status_id` bigint(20) unsigned NOT NULL,
  `lost_reason_id` bigint(20) unsigned DEFAULT NULL,
  `changed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_status_history_status_id_foreign` (`status_id`),
  KEY `lead_status_history_lost_reason_id_foreign` (`lost_reason_id`),
  KEY `lead_status_history_changed_by_user_id_foreign` (`changed_by_user_id`),
  KEY `lead_status_history_lead_id_created_at_index` (`lead_id`,`created_at`),
  CONSTRAINT `lead_status_history_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_status_history_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_status_history_lost_reason_id_foreign` FOREIGN KEY (`lost_reason_id`) REFERENCES `lost_reasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_status_history_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `lead_statuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1496 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_statuses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_statuses_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `interested_course_slug` varchar(255) DEFAULT NULL,
  `masterclass_link_sent_at` timestamp NULL DEFAULT NULL,
  `masterclass_followup_at` timestamp NULL DEFAULT NULL,
  `allocated_batch_number` varchar(50) DEFAULT NULL,
  `lms_lead_id` bigint(20) unsigned DEFAULT NULL,
  `added_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `current_status_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to_user_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_lms_lead_id_unique` (`lms_lead_id`),
  KEY `leads_added_by_user_id_foreign` (`added_by_user_id`),
  KEY `leads_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  KEY `leads_assigned_by_user_id_foreign` (`assigned_by_user_id`),
  KEY `leads_mobile_index` (`mobile`),
  KEY `leads_current_status_id_assigned_to_user_id_index` (`current_status_id`,`assigned_to_user_id`),
  CONSTRAINT `leads_added_by_user_id_foreign` FOREIGN KEY (`added_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leads_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_current_status_id_foreign` FOREIGN KEY (`current_status_id`) REFERENCES `lead_statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=605 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leave_balances`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `allocated_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `used_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_balances_user_id_leave_type_id_year_unique` (`user_id`,`leave_type_id`,`year`),
  KEY `leave_balances_leave_type_id_foreign` (`leave_type_id`),
  CONSTRAINT `leave_balances_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_balances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leave_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `is_half_day` tinyint(1) NOT NULL DEFAULT 0,
  `total_days` decimal(5,1) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_leave_type_id_foreign` (`leave_type_id`),
  KEY `leave_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `leave_requests_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `leave_requests_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leave_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `default_days_per_year` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_types_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_reminders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `reminder_date` date NOT NULL,
  `email_count` int(10) unsigned NOT NULL DEFAULT 0,
  `whatsapp_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_reminded_at` timestamp NULL DEFAULT NULL,
  `logged_in` tinyint(1) NOT NULL DEFAULT 0,
  `escalated` tinyint(1) NOT NULL DEFAULT 0,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login_reminders_user_id_reminder_date_unique` (`user_id`,`reminder_date`),
  KEY `login_reminders_reminder_date_index` (`reminder_date`),
  CONSTRAINT `login_reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lost_reasons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lost_reasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lost_reasons_reason_unique` (`reason`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `meeting_reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meeting_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `drive_file_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meeting_date` date NOT NULL,
  `attendees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attendees`)),
  `absentees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`absentees`)),
  `analysis` longtext NOT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `transcript_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meeting_reports_drive_file_id_unique` (`drive_file_id`),
  KEY `meeting_reports_meeting_date_index` (`meeting_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `menu_group` varchar(255) DEFAULT NULL,
  `permission_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_items_parent_id_foreign` (`parent_id`),
  KEY `menu_items_permission_id_foreign` (`permission_id`),
  CONSTRAINT `menu_items_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_items_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `milestones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `milestones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `metric` enum('leads','custom') NOT NULL DEFAULT 'leads',
  `period_type` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `period_year` smallint(5) unsigned NOT NULL,
  `period_month` tinyint(3) unsigned DEFAULT NULL,
  `period_quarter` tinyint(3) unsigned DEFAULT NULL,
  `target_value` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `milestones_created_by_foreign` (`created_by`),
  KEY `milestones_metric_period_type_period_year_index` (`metric`,`period_type`,`period_year`),
  CONSTRAINT `milestones_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'default',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notes_user_id_is_pinned_index` (`user_id`,`is_pinned`),
  CONSTRAINT `notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_otps`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `purpose` varchar(20) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_otps_email_purpose_index` (`email`,`purpose`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint(20) unsigned DEFAULT NULL,
  `reference` varchar(32) DEFAULT NULL,
  `source` enum('checkout','webhook','reconcile','manual') NOT NULL DEFAULT 'checkout',
  `event` varchar(60) DEFAULT NULL,
  `razorpay_order_id` varchar(64) DEFAULT NULL,
  `razorpay_payment_id` varchar(64) DEFAULT NULL,
  `status` varchar(40) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `raw_payload` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_txn_reg` (`registration_id`),
  KEY `idx_txn_order` (`razorpay_order_id`),
  CONSTRAINT `fk_txn_reg` FOREIGN KEY (`registration_id`) REFERENCES `course_registrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `module` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subscribable_type` varchar(255) NOT NULL,
  `subscribable_id` bigint(20) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `public_key` varchar(255) DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_subscribable_endpoint_unique` (`subscribable_type`,`subscribable_id`,`endpoint`) USING HASH,
  KEY `push_subscriptions_subscribable_type_subscribable_id_index` (`subscribable_type`,`subscribable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1289 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_role_name_unique` (`role_name`),
  UNIQUE KEY `roles_role_code_unique` (`role_code`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `platform` enum('instagram','youtube','linkedin') NOT NULL DEFAULT 'instagram',
  `label` varchar(255) NOT NULL,
  `ig_user_id` varchar(255) DEFAULT NULL,
  `channel_id` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `account_type` varchar(255) DEFAULT NULL,
  `profile_picture_url` text DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `following_count` int(10) unsigned DEFAULT NULL,
  `followers_count` bigint(20) unsigned DEFAULT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_error` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `social_accounts_created_by_foreign` (`created_by`),
  CONSTRAINT `social_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_alert_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_alert_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `platform` enum('instagram','youtube','linkedin') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_alert_settings_user_id_platform_unique` (`user_id`,`platform`),
  CONSTRAINT `social_alert_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_media_posts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_media_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `platform` varchar(255) NOT NULL,
  `external_post_id` varchar(255) NOT NULL,
  `caption` text DEFAULT NULL,
  `media_type` varchar(255) DEFAULT NULL,
  `permalink` varchar(255) DEFAULT NULL,
  `thumbnail_url` varchar(1024) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `likes` bigint(20) unsigned DEFAULT NULL,
  `comments` bigint(20) unsigned DEFAULT NULL,
  `shares` bigint(20) unsigned DEFAULT NULL,
  `views` bigint(20) unsigned DEFAULT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `metrics_synced_at` timestamp NULL DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_media_posts_social_account_id_external_post_id_unique` (`social_account_id`,`external_post_id`),
  KEY `social_media_posts_platform_index` (`platform`),
  CONSTRAINT `social_media_posts_social_account_id_foreign` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_media_stats`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_media_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `stat_date` date NOT NULL,
  `followers_count` int(10) unsigned DEFAULT NULL,
  `posts_today` int(10) unsigned NOT NULL DEFAULT 0,
  `reach` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_media_stats_social_account_id_stat_date_unique` (`social_account_id`,`stat_date`),
  CONSTRAINT `social_media_stats_social_account_id_foreign` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_post_reminders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_post_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `platform` varchar(255) NOT NULL,
  `last_post_at` timestamp NULL DEFAULT NULL,
  `reminder_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_reminded_at` timestamp NULL DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_post_reminders_social_account_id_unique` (`social_account_id`),
  KEY `social_post_reminders_platform_index` (`platform`),
  CONSTRAINT `social_post_reminders_social_account_id_foreign` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `students`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_email_unique` (`email`),
  UNIQUE KEY `students_phone_number_unique` (`phone_number`),
  UNIQUE KEY `students_external_id_unique` (`external_id`),
  KEY `students_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_assignees`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `flagged_at` timestamp NULL DEFAULT NULL,
  `last_reminded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_assignees_task_id_user_id_unique` (`task_id`,`user_id`),
  KEY `task_assignees_user_id_foreign` (`user_id`),
  CONSTRAINT `task_assignees_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `created_by` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_status_due_date_index` (`status`,`due_date`),
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `todos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `todos_user_id_is_completed_index` (`user_id`,`is_completed`),
  CONSTRAINT `todos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_login_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `login_device_id` varchar(255) DEFAULT NULL,
  `login_device_name` varchar(255) DEFAULT NULL,
  `login_device_type` varchar(50) DEFAULT NULL,
  `login_browser` varchar(255) DEFAULT NULL,
  `login_os` varchar(255) DEFAULT NULL,
  `login_user_agent` text DEFAULT NULL,
  `login_latitude` decimal(10,8) DEFAULT NULL,
  `login_longitude` decimal(11,8) DEFAULT NULL,
  `login_location` varchar(255) DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `logout_ip` varchar(45) DEFAULT NULL,
  `logout_device_id` varchar(255) DEFAULT NULL,
  `logout_reason` varchar(255) DEFAULT NULL,
  `logout_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `is_manual_entry` tinyint(1) NOT NULL DEFAULT 0,
  `manual_reason` text DEFAULT NULL,
  `is_active_session` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_login_logs_user_id_index` (`user_id`),
  KEY `user_login_logs_login_time_index` (`login_time`),
  KEY `user_login_logs_is_active_session_index` (`is_active_session`),
  KEY `user_login_logs_session_token_index` (`session_token`),
  KEY `user_login_logs_logout_by_user_id_foreign` (`logout_by_user_id`),
  KEY `user_login_logs_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `user_login_logs_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_login_logs_logout_by_user_id_foreign` FOREIGN KEY (`logout_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_login_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `reporting_manager_id` bigint(20) unsigned DEFAULT NULL,
  `employee_type` enum('Permanent','Contract','Intern') DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `resignation_date` date DEFAULT NULL,
  `last_working_day` date DEFAULT NULL,
  `employment_status` enum('Active','Inactive','Resigned','Terminated') NOT NULL DEFAULT 'Active',
  `salary` decimal(10,2) DEFAULT NULL,
  `salary_type` enum('Monthly','Hourly') DEFAULT NULL,
  `incentive` decimal(10,2) DEFAULT NULL,
  `bonus` decimal(10,2) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `aadhaar_number` varchar(20) DEFAULT NULL,
  `pf_number` varchar(30) DEFAULT NULL,
  `esi_number` varchar(30) DEFAULT NULL,
  `uan_number` varchar(30) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(30) DEFAULT NULL,
  `ifsc_code` varchar(15) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `upi_id` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `aadhaar_file` varchar(255) DEFAULT NULL,
  `pan_file` varchar(255) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `offer_letter` varchar(255) DEFAULT NULL,
  `experience_letter` varchar(255) DEFAULT NULL,
  `salary_slip` varchar(255) DEFAULT NULL,
  `cancelled_cheque` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_employee_id_unique` (`employee_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_reporting_manager_id_foreign` (`reporting_manager_id`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_updated_by_foreign` (`updated_by`),
  KEY `users_department_index` (`department`),
  KEY `users_role_id_index` (`role_id`),
  KEY `users_employment_status_index` (`employment_status`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_reporting_manager_id_foreign` FOREIGN KEY (`reporting_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:42:41
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobsbackendlaravel
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000001_create_cache_table',1),(2,'0001_01_01_000002_create_jobs_table',1),(3,'2026_07_02_120350_create_roles_table',2),(4,'2026_07_02_122655_create_user_login_logs_table',3),(6,'0001_01_01_000000_create_users_table',4),(7,'2026_07_02_151533_create_lead_statuses_table',5),(8,'2026_07_02_151602_create_lost_reasons_table',5),(9,'2026_07_02_151629_create_leads_table',5),(10,'2026_07_02_151714_create_lead_status_history_table',5),(11,'2026_07_02_151803_create_lead_conversations_table',5),(12,'2026_07_02_151829_create_lead_notifications_table',5),(13,'2026_07_02_151857_create_lead_assignments_table',5),(14,'2026_07_02_152130_create_lead_conversation_tags_table',6),(15,'2026_07_03_054931_create_permissions_table',7),(16,'2026_07_03_054937_create_role_permissions_table',7),(17,'2026_07_03_054942_create_menu_items_table',7),(18,'2026_07_03_105318_update_user_log_table',8),(19,'2026_07_03_170922_create_leave_types_table',9),(20,'2026_07_03_170926_create_leave_balances_table',9),(21,'2026_07_03_170931_create_leave_requests_table',9),(22,'2026_07_04_110326_create_holidays_table',10),(23,'2026_07_04_114707_create_notifications_table',11),(24,'2026_07_04_123735_create_push_subscriptions_table',12),(25,'2026_07_04_191639_create_tasks_table',13),(26,'2026_07_04_191646_create_task_assignees_table',13),(27,'2026_07_06_100759_add_last_reminded_at_to_task_assignees_table',14),(28,'2026_07_06_112818_create_conversations_table',15),(29,'2026_07_06_112824_create_conversation_participants_table',15),(30,'2026_07_06_112829_create_messages_table',15),(31,'2026_07_06_115442_create_social_accounts_table',16),(32,'2026_07_06_115451_create_social_media_stats_table',16),(33,'2026_07_06_132827_add_profile_fields_to_social_accounts_table',17),(34,'2026_07_06_133315_widen_profile_picture_url_column',18),(35,'2026_07_06_135610_add_youtube_support_to_social_accounts_table',19),(36,'2026_07_06_140000_add_youtube_support_to_social_accounts_table',19),(37,'2026_07_06_192430_create_expenses_table',20),(38,'2026_07_07_101418_create_students_table',21),(39,'2026_07_13_000000_create_enrollments_table',22),(40,'2026_07_15_100000_create_notes_table',23),(41,'2026_07_15_100100_create_todos_table',23),(42,'2026_07_15_100200_create_emails_table',23),(43,'2026_07_15_100300_create_folders_table',23),(44,'2026_07_15_110000_create_login_reminders_table',24),(45,'2026_07_15_110100_create_milestones_table',24),(46,'2026_07_15_110200_create_campaigns_table',24),(47,'2026_07_15_110300_create_social_media_posts_table',24),(48,'2026_07_15_120000_add_linkedin_and_engagement_support',25),(49,'2026_07_15_130000_create_google_accounts_table',26),(50,'2026_07_15_140000_create_lead_calls_table',27),(51,'2026_07_15_150000_relax_lead_actor_columns',28),(52,'2026_07_15_160000_create_incentive_records_table',29),(53,'2026_07_18_113538_create_lead_ai_analyses_table',30),(54,'2026_07_18_170000_create_meeting_reports_table',31),(56,'2026_07_20_130000_create_password_otps_table',32),(57,'2026_07_27_100000_add_ai_call_running_status_and_lms_lead_id',33),(58,'2026_07_27_150000_add_interested_course_slug_to_leads',34),(59,'2026_07_28_090000_add_masterclass_tracking_to_leads',35),(60,'2026_07_28_120000_add_allocated_batch_number_to_leads',36),(61,'2026_07_28_170000_add_mentors_menu_item',37),(62,'2026_07_30_130000_add_fee_collections_menu_item',38),(63,'2026_07_30_150000_add_grading_menu_item',39),(64,'2026_07_30_160000_add_support_tickets_menu_item',40),(65,'2026_07_30_170000_add_whitelabel_menu_item',41),(66,'2026_07_30_180000_remove_placeholder_menu_items',42),(67,'2026_07_30_200000_add_website_content_menu_item',43),(68,'2026_07_30_210000_move_website_items_to_own_group',44),(69,'2026_07_30_220000_add_pages_and_seo_menu_items',45);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `lead_statuses`
--

LOCK TABLES `lead_statuses` WRITE;
/*!40000 ALTER TABLE `lead_statuses` DISABLE KEYS */;
INSERT INTO `lead_statuses` VALUES (1,'New','new','#0d6efd',1,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(2,'Interested','interested','#20c997',3,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(3,'Follow-up','follow_up','#fd7e14',4,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(4,'Not Interested','not_interested','#dc3545',5,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(5,'Invalid Number','invalid_number','#6c757d',6,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(6,'Joined','joined','#198754',7,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(7,'Lost','lost','#343a40',8,'2026-07-02 09:49:58','2026-07-18 05:51:32'),(9,'AI Call Running','ai_call_running','#6f42c1',2,'2026-07-27 04:34:34','2026-07-27 04:34:34');
/*!40000 ALTER TABLE `lead_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `lost_reasons`
--

LOCK TABLES `lost_reasons` WRITE;
/*!40000 ALTER TABLE `lost_reasons` DISABLE KEYS */;
INSERT INTO `lost_reasons` VALUES (1,'Price too high','2026-07-02 09:49:58','2026-07-02 09:49:58'),(2,'Joined a competitor','2026-07-02 09:49:58','2026-07-02 09:49:58'),(3,'No response after follow-ups','2026-07-02 09:49:58','2026-07-02 09:49:58'),(4,'Wrong / invalid number','2026-07-02 09:49:58','2026-07-02 09:49:58'),(5,'Not interested in offer','2026-07-02 09:49:58','2026-07-02 09:49:58'),(6,'Location / timing not suitable','2026-07-02 09:49:58','2026-07-02 09:49:58'),(7,'Other','2026-07-02 09:49:58','2026-07-02 09:49:58');
/*!40000 ALTER TABLE `lost_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Dashboard','view_dashboard','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(2,'Leads Dashboard','view_leads_dashboard','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(3,'Revenue Summary','view_revenue_summary_dashboard','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(4,'Growth Dashboard','view_growth_dashboard','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(5,'Chat','view_chat','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(6,'Instagram','view_video_call','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(7,'LinkedIn','view_audio_call','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(8,'Youtube','view_call_history','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(9,'Calendar','view_calendar','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(10,'Email','view_email','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(11,'To Do','view_todo','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(12,'Notes','view_notes','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(13,'File Manager','view_file_manager','Main Menu','2026-07-03 09:28:00','2026-07-03 09:28:00'),(14,'Leads','view_leads','CRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(15,'Payments','view_payments','CRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(16,'Analytics','view_analytics','CRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(17,'Tasks','view_tasks','PROJECTS','2026-07-03 09:28:00','2026-07-03 09:28:00'),(18,'Milestones','view_milestones','PROJECTS','2026-07-03 09:28:00','2026-07-03 09:28:00'),(20,'Campaigns','view_campaign','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(21,'Email Campaigns','view_email_campaign','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(22,'SMS Campaigns','view_sms_campaign','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(23,'Social Campaigns','view_social_campaign','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(24,'WhatsApp Campaigns','view_whatsapp_campaign','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(25,'Email Marketing','view_email_marketing','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(26,'Email Engagement','view_email_engagement','MARKETING','2026-07-03 09:28:00','2026-07-03 09:28:00'),(27,'Manage Users','manage_users','User Management','2026-07-03 09:28:00','2026-07-03 09:28:00'),(28,'Roles & Permissions','manage_roles','User Management','2026-07-03 09:28:00','2026-07-03 09:28:00'),(31,'Attendance','view_attendance','HRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(32,'Leave Requests','view_leave_requests','HRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(33,'Holidays','view_holidays','HRM','2026-07-03 09:28:00','2026-07-03 09:28:00'),(34,'Company Report','view_company_reports','Reports','2026-07-03 09:28:00','2026-07-03 09:28:00'),(35,'Revenue Report','view_reports','Reports','2026-07-03 09:28:00','2026-07-03 09:28:00'),(36,'Attendance Summary','view_attendance_summary_report','Reports','2026-07-03 09:28:00','2026-07-03 09:28:00'),(37,'Leave Balance Summary','view_leave_balance_summary_report','Reports','2026-07-03 09:28:00','2026-07-03 09:28:00'),(38,'Pages','view_pages','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(39,'All Blogs','view_blogs','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(40,'Blog Categories','view_blog_categories','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(41,'Blog Comments','view_blog_comments','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(42,'Blog Tags','view_blog_tags','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(43,'Countries','view_countries','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(44,'States','view_states','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(45,'Cities','view_cities','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(46,'Testimonials','view_testimonials','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(47,'FAQ','view_faq','Content','2026-07-03 09:28:00','2026-07-03 09:28:00'),(48,'Manage Holidays','manage_holidays','HRM','2026-07-04 05:39:30','2026-07-04 05:39:30'),(49,'View Students','view_students','Student Management','2026-07-07 05:24:36','2026-07-07 05:24:36'),(50,'Manage Masterclass','manage_masterclass','Student Management','2026-07-07 05:24:36','2026-07-07 05:24:36'),(51,'Manage Live Batches','manage_live_batches','Student Management','2026-07-07 05:24:36','2026-07-07 05:24:36'),(52,'Manage Class Attendance','manage_class_attendance','Student Management','2026-07-07 05:24:36','2026-07-07 05:24:36'),(53,'Manage Mock Test Results','manage_mock_test_results','Student Management','2026-07-07 05:24:36','2026-07-07 05:24:36'),(58,'View Login Reminders','view_login_reminders','Reports','2026-07-16 06:37:50','2026-07-16 06:37:50'),(59,'Manage Social Alerts','view_social_alerts','Social','2026-07-16 06:37:50','2026-07-16 06:37:50'),(60,'View Office Finance','view_office_finance','Finance','2026-07-17 05:14:16','2026-07-17 05:14:16'),(61,'Create Lead','create_lead','CRM','2026-07-17 06:34:35','2026-07-17 06:34:35'),(62,'View All Leads','view_all_leads','CRM','2026-07-17 06:34:35','2026-07-17 06:34:35'),(63,'Manage Mentors','manage_mentors','Student Management','2026-07-28 12:08:19','2026-07-28 12:08:19'),(64,'View Fee Collections','view_fee_collections','Student Management','2026-07-30 07:54:36','2026-07-30 07:54:36'),(65,'Manage Grading','manage_grading','Student Management','2026-07-30 10:48:32','2026-07-30 10:48:32'),(66,'Manage Support Tickets','manage_support_tickets','Student Management','2026-07-30 11:34:32','2026-07-30 11:34:32'),(67,'Manage Whitelabel','manage_whitelabel','Student Management','2026-07-30 11:59:34','2026-07-30 11:59:34'),(68,'Manage Website Content','manage_website_content','Student Management','2026-07-30 14:13:49','2026-07-30 14:13:49');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Admin','SUPER_ADMIN','Full system access',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(2,'Admin','ADMIN','System Administrator',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(3,'HR','HR','Human Resources',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(4,'Sales Manager','SALES_MANAGER','Sales Team Manager',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(5,'Sales Executive','SALES_EXECUTIVE','Sales Executive',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(6,'Marketing Manager','MARKETING_MANAGER','Marketing Manager',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(7,'Marketing Executive','MARKETING_EXECUTIVE','Marketing Executive',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(8,'Trainer','TRAINER','Trainer',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(9,'Student Counselor','STUDENT_COUNSELOR','Student Counselor',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(10,'Support Executive','SUPPORT_EXECUTIVE','Support Executive',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(11,'Accountant','ACCOUNTANT','Accounts Department',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(12,'Employee','EMPLOYEE','General Employee',1,'2026-07-02 06:34:30','2026-07-02 06:34:30'),(14,'Head Of Operations','HEAD_OF_OPERATIONS','Oversees overall company operations',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(15,'HR Manager','HR_MANAGER','Manages HR team and lead assignments',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(16,'HR - Team Lead','HR_TEAM_LEAD','Leads a team of HR executives',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(17,'HR Admin','HR_ADMIN','Handles HR administrative tasks',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(18,'Social Media Manager','SOCIAL_MEDIA_MANAGER','Manages social media presence',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(19,'Media Strategist','MEDIA_STRATEGIST','Runs ad campaigns and adds leads',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(20,'Data Engineer Trainer','DATA_ENGINEER_TRAINER','Trains students in data engineering',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(21,'Tech Manager','TECH_MANAGER','Manages the technical team',1,'2026-07-02 15:29:48','2026-07-02 15:29:48'),(22,'Tech Mentor','TECH_MENTOR','Mentors students on technical topics',1,'2026-07-02 15:29:48','2026-07-02 15:29:48');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (804,14,27,'2026-07-04 14:55:55','2026-07-04 14:55:55'),(805,14,28,'2026-07-04 14:55:55','2026-07-04 14:55:55'),(850,1,27,'2026-07-04 14:55:55','2026-07-04 14:55:55'),(851,1,28,'2026-07-04 14:55:55','2026-07-04 14:55:55'),(915,2,27,NULL,NULL),(916,2,28,NULL,NULL),(1111,14,39,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1112,14,40,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1113,14,41,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1114,14,42,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1115,14,45,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1116,14,43,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1117,14,47,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1118,14,38,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1119,14,44,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1120,14,46,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1121,14,16,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1122,14,61,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1123,14,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1124,14,15,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1125,14,62,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1126,14,60,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1127,14,31,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1128,14,33,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1129,14,32,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1130,14,48,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1131,14,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1132,14,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1133,14,1,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1134,14,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1135,14,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1136,14,4,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1137,14,6,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1138,14,2,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1139,14,7,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1140,14,12,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1141,14,3,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1142,14,11,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1143,14,8,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1144,14,20,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1145,14,21,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1146,14,26,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1147,14,25,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1148,14,22,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1149,14,23,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1150,14,24,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1151,14,18,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1152,14,17,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1153,14,36,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1154,14,34,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1155,14,37,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1156,14,35,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1157,14,58,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1158,14,59,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1159,1,39,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1160,1,40,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1161,1,41,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1162,1,42,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1163,1,45,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1164,1,43,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1165,1,47,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1166,1,38,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1167,1,44,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1168,1,46,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1169,1,16,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1170,1,61,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1171,1,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1172,1,15,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1173,1,62,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1174,1,60,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1175,1,31,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1176,1,33,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1177,1,32,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1178,1,48,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1179,1,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1180,1,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1181,1,1,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1182,1,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1183,1,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1184,1,4,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1185,1,6,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1186,1,2,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1187,1,7,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1188,1,12,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1189,1,3,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1190,1,11,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1191,1,8,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1192,1,20,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1193,1,21,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1194,1,26,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1195,1,25,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1196,1,22,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1197,1,23,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1198,1,24,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1199,1,18,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1200,1,17,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1201,1,36,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1202,1,34,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1203,1,37,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1204,1,35,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1205,1,58,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1206,1,59,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1207,1,52,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1208,1,65,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1209,1,51,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1210,1,50,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1211,1,63,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1212,1,53,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1213,1,66,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1214,1,68,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1215,1,67,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1216,1,64,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1217,1,49,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1218,11,16,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1219,11,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1220,11,60,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1221,11,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1222,11,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1223,11,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1224,11,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1225,11,12,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1226,11,11,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1227,15,16,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1228,15,61,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1229,15,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1230,15,15,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1231,15,62,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1232,15,31,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1233,15,33,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1234,15,32,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1235,15,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1236,15,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1237,15,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1238,15,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1239,15,2,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1240,15,52,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1241,15,65,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1242,15,51,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1243,15,50,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1244,15,63,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1245,15,53,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1246,15,66,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1247,15,68,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1248,15,67,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1249,15,64,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1250,15,49,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1251,19,16,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1252,19,61,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1253,19,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1254,19,33,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1255,19,32,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1256,19,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1257,19,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1258,19,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1259,19,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1260,19,12,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1261,19,11,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1262,19,17,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1263,2,61,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1264,2,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1265,2,62,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1266,2,60,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1267,2,48,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1268,3,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1269,16,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1270,16,2,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1271,17,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1272,17,2,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1273,5,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1274,4,14,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1275,18,33,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1276,18,32,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1277,18,9,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1278,18,5,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1279,18,10,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1280,18,13,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1281,18,4,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1282,18,6,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1283,18,7,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1284,18,12,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1285,18,11,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1286,18,8,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1287,18,17,'2026-07-31 06:34:23','2026-07-31 06:34:23'),(1288,18,59,'2026-07-31 06:34:23','2026-07-31 06:34:23');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,NULL,'Dashboard','ti ti-dashboard',NULL,'Main Menu',NULL,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(2,1,'Dashboard',NULL,'dashboard','Main Menu',1,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(3,1,'Leads Dashboard',NULL,'leads-dashboard','Main Menu',2,30,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(4,1,'Revenue Summary',NULL,'revenue-summary-dashboard','Main Menu',3,40,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(6,NULL,'Applications','ti ti-brand-airtable',NULL,'Main Menu',NULL,60,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(7,6,'Chat',NULL,'chat','Main Menu',5,70,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(8,6,'Social Feed',NULL,NULL,'Main Menu',NULL,80,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(9,8,'Instagram',NULL,'social-accounts/instagram','Main Menu',6,90,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(10,8,'LinkedIn',NULL,'social-accounts/linkedin','Main Menu',7,100,1,'2026-07-03 09:28:00','2026-07-16 06:29:53'),(11,8,'Youtube',NULL,'social-accounts/youtube','Main Menu',8,110,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(12,6,'Calendar',NULL,'calendar','Main Menu',9,120,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(13,NULL,'Email','ti ti-mail','email','Workspace',NULL,40,1,'2026-07-15 14:27:17','2026-07-16 15:36:14'),(14,6,'To Do',NULL,'todo','Main Menu',11,140,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(15,NULL,'Notes','ti ti-notes','notes','Workspace',NULL,42,1,'2026-07-15 14:27:17','2026-07-16 15:36:14'),(16,NULL,'File Manager','ti ti-folders','file-manager','Workspace',NULL,43,1,'2026-07-15 14:27:17','2026-07-16 15:36:14'),(17,NULL,'Leads','ti ti-chart-arcs','leads','CRM',14,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(18,NULL,'Payments','ti ti-report-money','expenses','CRM',15,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(19,NULL,'Analytics','ti ti-chart-bar','expenses-analytics','CRM',16,30,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(20,NULL,'Tasks','ti ti-list-check','tasks','PROJECTS',17,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(21,NULL,'Milestones','ti ti-stack-2','milestones','PROJECTS',18,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(23,NULL,'Campaigns','ti ti-brand-campaignmonitor',NULL,'MARKETING',NULL,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(24,23,'Campaigns',NULL,'campaign','MARKETING',20,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(25,23,'Email Campaigns',NULL,'email-campaign','MARKETING',21,30,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(26,23,'SMS Campaigns',NULL,'sms-campaign','MARKETING',22,40,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(27,23,'Social Campaigns',NULL,'social-campaign','MARKETING',23,50,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(28,23,'WhatsApp Campaigns',NULL,'whatsapp-campaign','MARKETING',24,60,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(31,NULL,'Manage Users','ti ti-users','manage-users','User Management',27,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(32,NULL,'Roles & Permissions','ti ti-user-shield','roles-permissions','User Management',28,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(35,NULL,'Attendance','ti ti-article','attendance','HRM',31,10,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(36,NULL,'Leave Requests','ti ti-message-star','leave-requests','HRM',32,20,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(37,NULL,'Holidays','ti ti-stack','holidays','HRM',33,30,1,'2026-07-03 09:28:00','2026-07-03 09:28:00'),(55,NULL,'Student Management','ti ti-school',NULL,'Student Management',49,130,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(56,55,'Students','ti ti-users','students','Student Management',49,10,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(57,55,'Masterclass','ti ti-presentation','masterclasses','Student Management',50,20,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(58,55,'Live Batches','ti ti-broadcast','live-batches','Student Management',51,30,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(59,55,'Class Attendance','ti ti-checklist','class-attendance','Student Management',52,40,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(60,55,'Mock Test Result','ti ti-clipboard-check','mock-test-results','Student Management',53,50,1,'2026-07-07 05:24:36','2026-07-07 05:24:36'),(61,NULL,'To Do','ti ti-checklist','todos','Workspace',NULL,41,1,'2026-07-15 14:27:17','2026-07-16 15:36:14'),(62,NULL,'Login Reminders','ti ti-bell-exclamation','login-reminders','Reports',58,31,1,'2026-07-16 06:37:50','2026-07-16 06:37:50'),(63,NULL,'Social Alerts','ti ti-bell-ringing','social-alerts','Main Menu',59,6,1,'2026-07-16 06:37:50','2026-07-16 06:37:50'),(64,8,'Social Analytics',NULL,'social-analytics','Main Menu',NULL,12,1,'2026-07-16 07:03:10','2026-07-16 07:03:10'),(65,NULL,'Office Finance','ti ti-report-money','office-finance','CRM',60,15,1,'2026-07-17 05:14:16','2026-07-17 05:14:16'),(66,NULL,'Incentives & Bonuses','ti ti-trophy','incentives','CRM',60,16,1,'2026-07-17 05:27:08','2026-07-17 05:27:08'),(67,55,'Batch Funnel','ti ti-arrows-split-2','batch-funnel','Student Management',51,15,1,'2026-07-27 07:59:31','2026-07-27 07:59:31'),(68,55,'Mentors','ti ti-heart-handshake','mentors','Student Management',63,37,1,'2026-07-28 12:08:19','2026-07-28 12:08:19'),(69,55,'Fee Collections','ti ti-cash','fee-collections','Student Management',64,38,1,'2026-07-30 07:54:36','2026-07-30 07:54:36'),(70,55,'Grading','ti ti-checklist','grading','Student Management',65,39,1,'2026-07-30 10:48:32','2026-07-30 10:48:32'),(71,55,'Support Tickets','ti ti-headset','support-tickets','Student Management',66,40,1,'2026-07-30 11:34:32','2026-07-30 11:34:32'),(72,74,'Whitelabel','ti ti-palette','whitelabel','Website',67,4,1,'2026-07-30 11:59:34','2026-07-30 15:00:43'),(73,74,'Homepage','ti ti-home-edit','website-content','Website',68,1,1,'2026-07-30 14:13:49','2026-07-30 15:00:43'),(74,NULL,'Website','ti ti-world-www',NULL,'Website',68,140,1,'2026-07-30 14:23:27','2026-07-30 14:23:27'),(75,74,'Pages','ti ti-files','website-pages','Website',68,2,1,'2026-07-30 15:00:43','2026-07-30 15:00:43'),(76,74,'SEO','ti ti-search','website-seo','Website',68,3,1,'2026-07-30 15:00:43','2026-07-30 15:00:43'),(77,55,'Student Pulse',NULL,'student-pulse','Student Management',49,51,1,'2026-07-31 09:26:14','2026-07-31 09:26:14'),(78,NULL,'How It Works','ti ti-help-circle','how-it-works','Main Menu',NULL,5,1,'2026-08-03 04:54:18','2026-08-03 04:54:18');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'Casual Leave','casual',12,1,'2026-07-03 11:41:47','2026-07-03 11:41:47'),(2,'Sick Leave','sick',10,1,'2026-07-03 11:41:47','2026-07-03 11:41:47'),(3,'Earned Leave','earned',15,1,'2026-07-03 11:41:47','2026-07-03 11:41:47'),(4,'Maternity Leave','maternity',90,1,'2026-07-03 11:41:47','2026-07-03 11:41:47'),(5,'Paternity Leave','paternity',7,1,'2026-07-03 11:41:47','2026-07-03 11:41:47'),(6,'Unpaid Leave','unpaid',0,1,'2026-07-03 11:41:47','2026-07-03 11:41:47');
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
INSERT INTO `holidays` VALUES (1,'Independence Day','2026-08-15',2026,0,'Independence Day',1,'2026-07-04 05:41:45','2026-07-04 05:41:45'),(2,'Varasiddhi Vinayaka Vrata (Ganesh Chaturthi)','2026-09-14',2026,0,'Varasiddhi Vinayaka Vrata (Ganesh Chaturthi)',1,'2026-07-04 05:42:24','2026-07-04 05:42:24'),(3,'Intigration','2026-07-09',2026,0,'jhgj',1,'2026-07-06 10:34:09','2026-07-06 10:34:09');
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `course_catalog`
--

LOCK TABLES `course_catalog` WRITE;
/*!40000 ALTER TABLE `course_catalog` DISABLE KEYS */;
INSERT INTO `course_catalog` VALUES (1,'data-engineering','Data Engineering Certification Program','Data Engineering','Build the pipelines companies are hiring for right now.','6 months',30000,'INR','3 × ₹10,000','live',1,1,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(2,'devops','DevOps & Cloud Certification Program','DevOps & Cloud','Own the pipeline from commit to production.','6 months',30000,'INR','3 × ₹10,000','live',0,2,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(3,'python-backend','Python Backend Development Program','Python Backend','Ship the APIs that run real products.','6 months',30000,'INR','3 × ₹10,000','live',0,3,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(4,'data-analytics','Data Analytics Certification Program','Data Analytics','From data to decisions. Turn information into impact.','5-6 months',30000,'INR','3 × ₹10,000','live',0,4,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(5,'agentic-ai','Agentic AI Certification Program','Agentic AI','Build the AI employees every company will hire next.','6 months',30000,'INR','3 × ₹10,000','coming_soon',0,5,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(6,'cyber-security','Cyber Security Certification Program','Cyber Security','Defend the systems the world runs on.','6 months',30000,'INR','3 × ₹10,000','coming_soon',0,6,'2026-07-15 09:17:25','2026-07-15 09:17:25'),(7,'servicenow','ServiceNow Certification Program','ServiceNow','Master the platform enterprises run their operations on.','5-6 months',30000,'INR','3 × ₹10,000','coming_soon',0,7,'2026-07-15 09:17:25','2026-07-15 09:17:25');
/*!40000 ALTER TABLE `course_catalog` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:42:42
