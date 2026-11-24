
CREATE DATABASE IF NOT EXISTS `product_management` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `product_management`;


CREATE TABLE `product` (
  `id` int PRIMARY KEY auto_increment NOT NULL,
  `name` varchar(128) NOT NULL,
  `price` int DEFAULT 0,
  `description` varchar(255) DEFAULT NULL
);

INSERT INTO `product` (`name`, `price`, `description`) VALUES
('Macbook Pro', 1500, '16 inch, 32GB RAM'),
('iPhone X', 1100, 'No Adapter');

CREATE DATABASE IF NOT EXISTS `note_management`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `note_management`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `is_activated` TINYINT(1) NOT NULL DEFAULT 0,
  `activation_token` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);