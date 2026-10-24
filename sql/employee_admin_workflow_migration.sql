-- Apply once to an existing MAU-HRMS database.
CREATE TABLE IF NOT EXISTS `profile_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `changes_json` longtext NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `fk_profile_request_employee` (`emp_id`),
  KEY `fk_profile_request_user` (`user_id`),
  KEY `fk_profile_request_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_profile_request_employee` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
