SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `alert-notifier_alerts` (
  `uuid` binary(16) NOT NULL COMMENT 'Waze ID, unhexed and hyphens removed',
  `location` point NOT NULL,
  `start_execution` bigint(20) UNSIGNED NOT NULL,
  `end_execution` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(100) COLLATE utf16_bin NOT NULL,
  `max_confidence` tinyint(3) UNSIGNED NOT NULL,
  `max_reliability` tinyint(3) UNSIGNED NOT NULL,
  `max_rating` smallint(5) UNSIGNED NOT NULL,
  `notification_timestamp` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

CREATE TABLE `alert-notifier_executions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `partner_id` int(10) UNSIGNED NOT NULL,
  `timestamp` bigint(20) UNSIGNED NOT NULL COMMENT 'Time in seconds since epoch in UTC',
  `log_file` varchar(250) COLLATE utf16_bin DEFAULT NULL COMMENT 'Name of the trace log file used for all logs pertaining to this execution',
  `result` enum('NO_DATA','DATA_FOUND','ERROR','NOTIFICATION_SENT') COLLATE utf16_bin DEFAULT NULL COMMENT 'End result of the execution',
  `result_count` int(10) UNSIGNED DEFAULT NULL COMMENT 'Amount of data processed'
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

CREATE TABLE `alert-notifier_partners` (
  `id` int(10) UNSIGNED NOT NULL,
  `active` tinyint(1) NOT NULL,
  `name` varchar(250) COLLATE utf16_bin NOT NULL COMMENT 'Name of the partner',
  `access_token` char(20) COLLATE utf16_bin NOT NULL COMMENT 'Access token used to access the application as this user',
  `contact_address` varchar(250) COLLATE utf16_bin NOT NULL COMMENT 'E-mail address to be used as a contact point in case of issues',
  `area` polygon NOT NULL COMMENT ' The zone in which alerts should be considered for this rule',
  `map_image_path` varchar(200) COLLATE utf16_bin NOT NULL,
  `map_image_left` decimal(9,6) NOT NULL,
  `map_image_right` decimal(9,6) NOT NULL,
  `map_image_top` decimal(9,6) NOT NULL,
  `map_image_bottom` decimal(9,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

INSERT INTO `alert-notifier_partners` (`id`, `active`, `name`, `access_token`, `contact_address`, `area`, `map_image_path`, `map_image_left`, `map_image_right`, `map_image_top`, `map_image_bottom`) VALUES
(1, 1, 'Example partner', 'abdefghijklmnopqrstu', 'example@example.com', 0x, 'images/map_1.png', '4.311913', '4.572424', '51.178898', '51.083064'),
(2, 0, 'Update service', 'abdefghijklmnopqrstu', 'example@example.com', 0x, '', '0.000000', '0.000000', '0.000000', '0.000000');

CREATE TABLE `alert-notifier_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `partner_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(250) COLLATE utf16_bin NOT NULL,
  `description` varchar(800) COLLATE utf16_bin NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `area` polygon NOT NULL COMMENT 'The zone in which alerts should be considered for this rule',
  `alert_type` varchar(100) COLLATE utf16_bin NOT NULL COMMENT 'Alert type to consider',
  `mail_address` varchar(400) COLLATE utf16_bin NOT NULL COMMENT 'E-mail address to where notifications should be sent',
  `last_email_timestamp` bigint(20) UNSIGNED DEFAULT NULL,
  `min_confidence` tinyint(3) UNSIGNED NOT NULL COMMENT 'The minimum confidence required to send',
  `min_reliability` tinyint(3) UNSIGNED NOT NULL COMMENT 'The minimum reliability required to send',
  `min_rating` tinyint(4) UNSIGNED NOT NULL COMMENT 'Indication of the highest rank of users confirming a report'
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

CREATE TABLE `alert-notifier_rule_restrictions` (
  `rule_id` int(10) UNSIGNED NOT NULL,
  `start` smallint(5) UNSIGNED NOT NULL,
  `end` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `alert-notifier_alerts`
  ADD PRIMARY KEY (`uuid`),
  ADD KEY `end_execution` (`end_execution`),
  ADD KEY `notification_timestamp` (`notification_timestamp`),
  ADD KEY `start_execution` (`start_execution`) USING BTREE;

ALTER TABLE `alert-notifier_executions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_id` (`partner_id`);

ALTER TABLE `alert-notifier_partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_token` (`access_token`);

ALTER TABLE `alert-notifier_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_id` (`partner_id`);

ALTER TABLE `alert-notifier_rule_restrictions`
  ADD KEY `rule_id` (`rule_id`);


ALTER TABLE `alert-notifier_executions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `alert-notifier_partners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `alert-notifier_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `alert-notifier_alerts`
  ADD CONSTRAINT `alert-notifier_alerts_ibfk_1` FOREIGN KEY (`end_execution`) REFERENCES `alert-notifier_executions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alert-notifier_alerts_ibfk_2` FOREIGN KEY (`start_execution`) REFERENCES `alert-notifier_executions` (`id`) ON DELETE CASCADE;

ALTER TABLE `alert-notifier_rules`
  ADD CONSTRAINT `alert-notifier_rules_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `alert-notifier_partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `alert-notifier_rule_restrictions`
  ADD CONSTRAINT `alert-notifier_rule_restrictions_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `alert-notifier_rules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
