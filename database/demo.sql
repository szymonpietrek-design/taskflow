-- TaskFlow PL demo | MySQL 8 / MariaDB | UTF-8 | import ONCE into a migrated LOCAL database.
-- Accounts are illustrative; change their passwords before any network exposure.
-- Demo password: TaskFlowDemo2026! | NEVER use in production.
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `locale`, `created_at`, `updated_at`) VALUES ('Anna Kowalska', 'anna@taskflow-demo.test', '2026-07-18 09:00:00', '$2y$12$B9FyZgUhOifuJHMFNU8Ct.EO3XRlKLK2X7nPTHWtApVIe7rmNzpCK', 'pl', '2026-07-16 09:00:00', '2026-07-16 09:00:00');
SET @u_anna = LAST_INSERT_ID();
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `locale`, `created_at`, `updated_at`) VALUES ('Piotr Zieliński', 'piotr@taskflow-demo.test', '2026-07-18 09:00:00', '$2y$12$B9FyZgUhOifuJHMFNU8Ct.EO3XRlKLK2X7nPTHWtApVIe7rmNzpCK', 'pl', '2026-07-17 09:00:00', '2026-07-17 09:00:00');
SET @u_piotr = LAST_INSERT_ID();
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `locale`, `created_at`, `updated_at`) VALUES ('Aleksandra Nowak', 'ola@taskflow-demo.test', '2026-07-18 09:00:00', '$2y$12$B9FyZgUhOifuJHMFNU8Ct.EO3XRlKLK2X7nPTHWtApVIe7rmNzpCK', 'pl', '2026-07-18 09:00:00', '2026-07-18 09:00:00');
SET @u_ola = LAST_INSERT_ID();
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `locale`, `created_at`, `updated_at`) VALUES ('Marek Wiśniewski', 'marek@taskflow-demo.test', '2026-07-18 09:00:00', '$2y$12$B9FyZgUhOifuJHMFNU8Ct.EO3XRlKLK2X7nPTHWtApVIe7rmNzpCK', 'pl', '2026-07-19 09:00:00', '2026-07-19 09:00:00');
SET @u_marek = LAST_INSERT_ID();
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `locale`, `created_at`, `updated_at`) VALUES ('Katarzyna Wójcik', 'kasia@taskflow-demo.test', '2026-07-18 09:00:00', '$2y$12$B9FyZgUhOifuJHMFNU8Ct.EO3XRlKLK2X7nPTHWtApVIe7rmNzpCK', 'pl', '2026-07-20 09:00:00', '2026-07-20 09:00:00');
SET @u_kasia = LAST_INSERT_ID();
INSERT INTO `workspaces` (`created_by`, `name`, `slug`, `created_at`, `updated_at`) VALUES (@u_anna, 'Studio Projektowe — Demo PL', 'studio-projektowe-demo-pl', '2026-07-20 09:00:00', '2026-07-20 09:00:00');
SET @workspace = LAST_INSERT_ID();
INSERT INTO `workspace_members` (`workspace_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES (@workspace, @u_anna, 1, '2026-07-20 09:00:00', '2026-07-20 09:00:00', '2026-07-20 09:00:00');
INSERT INTO `workspace_members` (`workspace_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES (@workspace, @u_piotr, 2, '2026-07-21 09:00:00', '2026-07-21 09:00:00', '2026-07-21 09:00:00');
INSERT INTO `workspace_members` (`workspace_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES (@workspace, @u_ola, 3, '2026-07-22 09:00:00', '2026-07-22 09:00:00', '2026-07-22 09:00:00');
INSERT INTO `workspace_members` (`workspace_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES (@workspace, @u_marek, 3, '2026-07-23 09:00:00', '2026-07-23 09:00:00', '2026-07-23 09:00:00');
INSERT INTO `workspace_members` (`workspace_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES (@workspace, @u_kasia, 3, '2026-07-24 09:00:00', '2026-07-24 09:00:00', '2026-07-24 09:00:00');
INSERT INTO `projects` (`workspace_id`, `created_by`, `name`, `slug`, `key`, `description`, `type`, `visibility`, `task_sequence`, `created_at`, `updated_at`) VALUES (@workspace, @u_anna, 'Platforma TaskFlow', 'taskflow-platforma-pl', 'TFPL', 'Rozwój narzędzia do zarządzania zadaniami, raportami, wydaniami i czasem pracy.', 2, 1, 20, '2026-07-22 09:00:00', '2026-09-28 09:00:00');
SET @p_app = LAST_INSERT_ID();
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, 1, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_app, @u_piotr, 2, '2026-07-23 10:00:00', '2026-07-23 10:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_app, @u_ola, 2, '2026-07-23 11:00:00', '2026-07-23 11:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_app, @u_marek, 2, '2026-07-23 12:00:00', '2026-07-23 12:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_app, @u_kasia, 2, '2026-07-23 13:00:00', '2026-07-23 13:00:00');
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_app, 'Do zrobienia', 'todo', 1, 1000, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
SET @s_app_0 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_app, 'W trakcie', 'in-progress', 2, 2000, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
SET @s_app_1 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_app, 'Do weryfikacji', 'review', 2, 3000, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
SET @s_app_2 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_app, 'Zrobione', 'done', 3, 4000, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
SET @s_app_3 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_app, 'Anulowane', 'canceled', 4, 5000, '2026-07-23 09:00:00', '2026-07-23 09:00:00');
SET @s_app_4 = LAST_INSERT_ID();
INSERT INTO `releases` (`project_id`, `created_by`, `version`, `version_major`, `version_minor`, `version_patch`, `name`, `description`, `status`, `planned_for`, `released_at`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, '1.0.0', 1, 0, 0, 'Pierwsza wersja produkcyjna', 'Zakres wydania 1.0.0 projektu Platforma TaskFlow.', 3, '2026-08-29', '2026-08-30 09:00:00', '2026-08-01 09:00:00', '2026-08-30 09:00:00');
SET @r_app_0 = LAST_INSERT_ID();
INSERT INTO `release_changelogs` (`release_id`, `draft_body`, `draft_locale`, `published_body`, `published_locale`, `updated_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES (@r_app_0, '## Dodano
- Nowe funkcje i usprawnienia w projekcie Platforma TaskFlow.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', '## Dodano
- Nowe funkcje i usprawnienia w projekcie Platforma TaskFlow.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', @u_anna, @u_anna, '2026-08-30 11:00:00', '2026-08-01 11:00:00', '2026-08-30 11:00:00');
INSERT INTO `releases` (`project_id`, `created_by`, `version`, `version_major`, `version_minor`, `version_patch`, `name`, `description`, `status`, `planned_for`, `released_at`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, '1.1.0', 1, 1, 0, 'Usprawnienia zespołowe', 'Zakres wydania 1.1.0 projektu Platforma TaskFlow.', 3, '2026-09-29', '2026-09-23 09:00:00', '2026-09-01 09:00:00', '2026-09-23 09:00:00');
SET @r_app_1 = LAST_INSERT_ID();
INSERT INTO `release_changelogs` (`release_id`, `draft_body`, `draft_locale`, `published_body`, `published_locale`, `updated_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES (@r_app_1, '## Dodano
- Nowe funkcje i usprawnienia w projekcie Platforma TaskFlow.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', '## Dodano
- Nowe funkcje i usprawnienia w projekcie Platforma TaskFlow.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', @u_anna, @u_anna, '2026-09-23 11:00:00', '2026-09-01 11:00:00', '2026-09-23 11:00:00');
INSERT INTO `releases` (`project_id`, `created_by`, `version`, `version_major`, `version_minor`, `version_patch`, `name`, `description`, `status`, `planned_for`, `released_at`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, '1.2.0', 1, 2, 0, 'Raporty i automatyzacje', 'Zakres wydania 1.2.0 projektu Platforma TaskFlow.', 2, '2026-10-25', NULL, '2026-09-27 09:00:00', '2026-09-27 09:00:00');
SET @r_app_2 = LAST_INSERT_ID();
INSERT INTO `release_changelogs` (`release_id`, `draft_body`, `draft_locale`, `published_body`, `published_locale`, `updated_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES (@r_app_2, '## Dodano
- Nowe funkcje i usprawnienia w projekcie Platforma TaskFlow.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', NULL, NULL, @u_anna, NULL, NULL, '2026-09-27 11:00:00', '2026-09-27 11:00:00');
INSERT INTO `projects` (`workspace_id`, `created_by`, `name`, `slug`, `key`, `description`, `type`, `visibility`, `task_sequence`, `created_at`, `updated_at`) VALUES (@workspace, @u_anna, 'Sklep Zielona Półka', 'zielona-polka-pl', 'SKLP', 'Nowy sklep internetowy z katalogiem produktów, koszykiem i płatnościami.', 1, 1, 15, '2026-07-23 09:00:00', '2026-09-28 09:00:00');
SET @p_shop = LAST_INSERT_ID();
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_shop, @u_anna, 1, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_shop, @u_piotr, 2, '2026-07-24 10:00:00', '2026-07-24 10:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_shop, @u_ola, 2, '2026-07-24 11:00:00', '2026-07-24 11:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_shop, @u_marek, 2, '2026-07-24 12:00:00', '2026-07-24 12:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_shop, @u_kasia, 2, '2026-07-24 13:00:00', '2026-07-24 13:00:00');
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_shop, 'Do zrobienia', 'todo', 1, 1000, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
SET @s_shop_0 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_shop, 'W trakcie', 'in-progress', 2, 2000, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
SET @s_shop_1 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_shop, 'Do weryfikacji', 'review', 2, 3000, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
SET @s_shop_2 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_shop, 'Zrobione', 'done', 3, 4000, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
SET @s_shop_3 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_shop, 'Anulowane', 'canceled', 4, 5000, '2026-07-24 09:00:00', '2026-07-24 09:00:00');
SET @s_shop_4 = LAST_INSERT_ID();
INSERT INTO `projects` (`workspace_id`, `created_by`, `name`, `slug`, `key`, `description`, `type`, `visibility`, `task_sequence`, `created_at`, `updated_at`) VALUES (@workspace, @u_anna, 'Strona Studio Nova', 'studio-nova-pl', 'NOVA', 'Odświeżenie witryny agencji kreatywnej i uruchomienie bloga.', 1, 1, 15, '2026-07-24 09:00:00', '2026-09-28 09:00:00');
SET @p_site = LAST_INSERT_ID();
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_site, @u_anna, 1, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_site, @u_piotr, 2, '2026-07-25 10:00:00', '2026-07-25 10:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_site, @u_ola, 2, '2026-07-25 11:00:00', '2026-07-25 11:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_site, @u_marek, 2, '2026-07-25 12:00:00', '2026-07-25 12:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_site, @u_kasia, 2, '2026-07-25 13:00:00', '2026-07-25 13:00:00');
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_site, 'Do zrobienia', 'todo', 1, 1000, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
SET @s_site_0 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_site, 'W trakcie', 'in-progress', 2, 2000, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
SET @s_site_1 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_site, 'Do weryfikacji', 'review', 2, 3000, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
SET @s_site_2 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_site, 'Zrobione', 'done', 3, 4000, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
SET @s_site_3 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_site, 'Anulowane', 'canceled', 4, 5000, '2026-07-25 09:00:00', '2026-07-25 09:00:00');
SET @s_site_4 = LAST_INSERT_ID();
INSERT INTO `projects` (`workspace_id`, `created_by`, `name`, `slug`, `key`, `description`, `type`, `visibility`, `task_sequence`, `created_at`, `updated_at`) VALUES (@workspace, @u_anna, 'API Zamówień', 'api-zamowien-pl', 'APIZ', 'Integracje z magazynem i systemem dostaw, autoryzacja i monitorowanie API.', 2, 1, 15, '2026-07-25 09:00:00', '2026-09-28 09:00:00');
SET @p_api = LAST_INSERT_ID();
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, 1, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_api, @u_piotr, 2, '2026-07-26 10:00:00', '2026-07-26 10:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_api, @u_ola, 2, '2026-07-26 11:00:00', '2026-07-26 11:00:00');
INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `created_at`, `updated_at`) VALUES (@p_api, @u_marek, 2, '2026-07-26 12:00:00', '2026-07-26 12:00:00');
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_api, 'Do zrobienia', 'todo', 1, 1000, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
SET @s_api_0 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_api, 'W trakcie', 'in-progress', 2, 2000, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
SET @s_api_1 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_api, 'Do weryfikacji', 'review', 2, 3000, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
SET @s_api_2 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_api, 'Zrobione', 'done', 3, 4000, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
SET @s_api_3 = LAST_INSERT_ID();
INSERT INTO `task_statuses` (`project_id`, `name`, `slug`, `category`, `position`, `created_at`, `updated_at`) VALUES (@p_api, 'Anulowane', 'canceled', 4, 5000, '2026-07-26 09:00:00', '2026-07-26 09:00:00');
SET @s_api_4 = LAST_INSERT_ID();
INSERT INTO `releases` (`project_id`, `created_by`, `version`, `version_major`, `version_minor`, `version_patch`, `name`, `description`, `status`, `planned_for`, `released_at`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, '1.0.0', 1, 0, 0, 'Pierwsza wersja produkcyjna', 'Zakres wydania 1.0.0 projektu API Zamówień.', 3, '2026-08-29', '2026-08-30 09:00:00', '2026-08-01 09:00:00', '2026-08-30 09:00:00');
SET @r_api_0 = LAST_INSERT_ID();
INSERT INTO `release_changelogs` (`release_id`, `draft_body`, `draft_locale`, `published_body`, `published_locale`, `updated_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES (@r_api_0, '## Dodano
- Nowe funkcje i usprawnienia w projekcie API Zamówień.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', '## Dodano
- Nowe funkcje i usprawnienia w projekcie API Zamówień.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', @u_anna, @u_anna, '2026-08-30 11:00:00', '2026-08-01 11:00:00', '2026-08-30 11:00:00');
INSERT INTO `releases` (`project_id`, `created_by`, `version`, `version_major`, `version_minor`, `version_patch`, `name`, `description`, `status`, `planned_for`, `released_at`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, '1.1.0', 1, 1, 0, 'Usprawnienia zespołowe', 'Zakres wydania 1.1.0 projektu API Zamówień.', 3, '2026-09-29', '2026-09-23 09:00:00', '2026-09-01 09:00:00', '2026-09-23 09:00:00');
SET @r_api_1 = LAST_INSERT_ID();
INSERT INTO `release_changelogs` (`release_id`, `draft_body`, `draft_locale`, `published_body`, `published_locale`, `updated_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES (@r_api_1, '## Dodano
- Nowe funkcje i usprawnienia w projekcie API Zamówień.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', '## Dodano
- Nowe funkcje i usprawnienia w projekcie API Zamówień.
- Rozszerzono obsługę codziennych procesów zespołu.

## Poprawiono
- Czytelność komunikatów i walidację danych.
- Obsługę błędów w scenariuszach brzegowych.

## Naprawiono
- Zgłoszone problemy z filtrowaniem i terminami.', 'pl', @u_anna, @u_anna, '2026-09-23 11:00:00', '2026-09-01 11:00:00', '2026-09-23 11:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 1, 'Zaplanować architekturę modułu projektów', '### Cel
Zaplanować architekturę modułu projektów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_app_3, @u_piotr, @u_anna, 1000, NULL, '2026-08-07 16:00:00', @r_app_0, '2026-07-26 09:00:00', '2026-07-27 16:00:00');
SET @t_app_0 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_0, @u_anna, 'Proszę o weryfikację zakresu: zaplanować architekturę modułu projektów.', '2026-07-27 10:00:00', '2026-07-27 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_0, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-07-28 11:00:00', '2026-07-28 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_0, @u_piotr, '2026-07-28', 45, 'Analiza zadania: zaplanować architekturę modułu projektów.', 0, '2026-07-28 15:00:00', '2026-07-28 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 2, 'Przygotować makiety panelu głównego', '### Cel
Przygotować makiety panelu głównego.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_3, @u_ola, @u_anna, 2000, '2026-08-15', '2026-08-09 16:00:00', @r_app_0, '2026-07-28 09:00:00', '2026-07-30 16:00:00');
SET @t_app_1 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_1, @u_anna, 'Proszę o weryfikację zakresu: przygotować makiety panelu głównego.', '2026-07-29 10:00:00', '2026-07-29 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_1, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-07-30 11:00:00', '2026-07-30 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_1, @u_ola, '2026-07-30', 90, 'Analiza zadania: przygotować makiety panelu głównego.', 0, '2026-07-30 15:00:00', '2026-07-30 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_1, @u_ola, '2026-07-31', 120, 'Implementacja i testy zadania: przygotować makiety panelu głównego.', 0, '2026-07-31 15:01:00', '2026-07-31 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 3, 'Utworzyć model przestrzeni roboczych', '### Cel
Utworzyć model przestrzeni roboczych.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_3, @u_marek, @u_anna, 3000, '2026-08-17', '2026-08-11 16:00:00', @r_app_0, '2026-07-30 09:00:00', '2026-08-02 16:00:00');
SET @t_app_2 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_2, @u_anna, 'Proszę o weryfikację zakresu: utworzyć model przestrzeni roboczych.', '2026-07-31 10:00:00', '2026-07-31 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_2, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-01 11:00:00', '2026-08-01 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_2, @u_marek, '2026-08-01', 120, 'Analiza zadania: utworzyć model przestrzeni roboczych.', 0, '2026-08-01 15:00:00', '2026-08-01 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_2, @u_marek, '2026-08-02', 150, 'Implementacja i testy zadania: utworzyć model przestrzeni roboczych.', 0, '2026-08-02 15:01:00', '2026-08-02 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_2, @u_marek, '2026-08-03', 60, 'Implementacja i testy zadania: utworzyć model przestrzeni roboczych.', 0, '2026-08-03 15:02:00', '2026-08-03 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 4, 'Dodać zapraszanie członków zespołu', '### Cel
Dodać zapraszanie członków zespołu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_app_3, @u_kasia, @u_anna, 4000, '2026-08-19', '2026-08-13 16:00:00', @r_app_0, '2026-08-01 09:00:00', '2026-08-05 16:00:00');
SET @t_app_3 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_3, @u_anna, 'Proszę o weryfikację zakresu: dodać zapraszanie członków zespołu.', '2026-08-02 10:00:00', '2026-08-02 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_3, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-03 11:00:00', '2026-08-03 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_3, @u_kasia, '2026-08-03', 150, 'Analiza zadania: dodać zapraszanie członków zespołu.', 1, '2026-08-03 15:00:00', '2026-08-03 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 5, 'Zaimplementować tablicę zadań', '### Cel
Zaimplementować tablicę zadań.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_app_3, @u_anna, @u_anna, 5000, '2026-08-21', '2026-08-15 16:00:00', @r_app_0, '2026-08-03 09:00:00', '2026-08-08 16:00:00');
SET @t_app_4 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_4, @u_anna, 'Proszę o weryfikację zakresu: zaimplementować tablicę zadań.', '2026-08-04 10:00:00', '2026-08-04 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_4, @u_anna, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-05 11:00:00', '2026-08-05 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_4, @u_anna, '2026-08-05', 60, 'Analiza zadania: zaimplementować tablicę zadań.', 1, '2026-08-05 15:00:00', '2026-08-05 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_4, @u_anna, '2026-08-06', 45, 'Implementacja i testy zadania: zaimplementować tablicę zadań.', 1, '2026-08-06 15:01:00', '2026-08-06 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 6, 'Dodać filtrowanie po statusie i osobie', '### Cel
Dodać filtrowanie po statusie i osobie.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_app_2, @u_piotr, @u_anna, 6000, NULL, NULL, @r_app_0, '2026-08-05 09:00:00', '2026-08-11 16:00:00');
SET @t_app_5 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_5, @u_anna, 'Proszę o weryfikację zakresu: dodać filtrowanie po statusie i osobie.', '2026-08-06 10:00:00', '2026-08-06 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_5, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-07 11:00:00', '2026-08-07 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_5, @u_piotr, '2026-08-07', 45, 'Analiza zadania: dodać filtrowanie po statusie i osobie.', 1, '2026-08-07 15:00:00', '2026-08-07 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_5, @u_piotr, '2026-08-08', 90, 'Implementacja i testy zadania: dodać filtrowanie po statusie i osobie.', 1, '2026-08-08 15:01:00', '2026-08-08 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_5, @u_piotr, '2026-08-09', 120, 'Implementacja i testy zadania: dodać filtrowanie po statusie i osobie.', 1, '2026-08-09 15:02:00', '2026-08-09 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, @t_app_5, 7, 'Zaimplementować komentarze w zadaniach', '### Cel
Zaimplementować komentarze w zadaniach.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_app_2, @u_ola, @u_anna, 7000, '2026-08-25', NULL, @r_app_0, '2026-08-07 09:00:00', '2026-08-14 16:00:00');
SET @t_app_6 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_6, @u_anna, 'Proszę o weryfikację zakresu: zaimplementować komentarze w zadaniach.', '2026-08-08 10:00:00', '2026-08-08 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_6, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-09 11:00:00', '2026-08-09 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_6, @u_ola, '2026-08-09', 90, 'Analiza zadania: zaimplementować komentarze w zadaniach.', 1, '2026-08-09 15:00:00', '2026-08-09 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 8, 'Dodać śledzenie czasu pracy', '### Cel
Dodać śledzenie czasu pracy.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 1, @s_app_1, @u_marek, @u_anna, 8000, '2026-08-27', NULL, @r_app_0, '2026-08-09 09:00:00', '2026-08-17 16:00:00');
SET @t_app_7 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_7, @u_anna, 'Proszę o weryfikację zakresu: dodać śledzenie czasu pracy.', '2026-08-10 10:00:00', '2026-08-10 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_7, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-11 11:00:00', '2026-08-11 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_7, @u_marek, '2026-08-11', 120, 'Analiza zadania: dodać śledzenie czasu pracy.', 1, '2026-08-11 15:00:00', '2026-08-11 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_7, @u_marek, '2026-08-12', 150, 'Implementacja i testy zadania: dodać śledzenie czasu pracy.', 1, '2026-08-12 15:01:00', '2026-08-12 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 9, 'Przygotować widok raportów miesięcznych', '### Cel
Przygotować widok raportów miesięcznych.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 3, @s_app_1, @u_kasia, @u_anna, 9000, '2026-08-29', NULL, @r_app_1, '2026-08-11 09:00:00', '2026-08-20 16:00:00');
SET @t_app_8 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_8, @u_anna, 'Proszę o weryfikację zakresu: przygotować widok raportów miesięcznych.', '2026-08-12 10:00:00', '2026-08-12 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_8, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-13 11:00:00', '2026-08-13 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_8, @u_kasia, '2026-08-13', 150, 'Analiza zadania: przygotować widok raportów miesięcznych.', 0, '2026-08-13 15:00:00', '2026-08-13 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_8, @u_kasia, '2026-08-14', 60, 'Implementacja i testy zadania: przygotować widok raportów miesięcznych.', 0, '2026-08-14 15:01:00', '2026-08-14 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_8, @u_kasia, '2026-08-15', 45, 'Implementacja i testy zadania: przygotować widok raportów miesięcznych.', 0, '2026-08-15 15:02:00', '2026-08-15 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 10, 'Naprawić błąd stref czasowych terminów', '### Cel
Naprawić błąd stref czasowych terminów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 4, 2, @s_app_0, @u_anna, @u_anna, 10000, '2026-08-31', NULL, @r_app_1, '2026-08-13 09:00:00', '2026-08-23 16:00:00');
SET @t_app_9 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_9, @u_anna, 'Proszę o weryfikację zakresu: naprawić błąd stref czasowych terminów.', '2026-08-14 10:00:00', '2026-08-14 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 11, 'Dodać eksport raportu do PDF', '### Cel
Dodać eksport raportu do PDF.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_app_0, @u_piotr, @u_anna, 11000, NULL, NULL, @r_app_1, '2026-08-15 09:00:00', '2026-08-16 16:00:00');
SET @t_app_10 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_10, @u_anna, 'Proszę o weryfikację zakresu: dodać eksport raportu do pdf.', '2026-08-16 10:00:00', '2026-08-16 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, @t_app_10, 12, 'Przygotować generator changeloga AI', '### Cel
Przygotować generator changeloga AI.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_app_0, @u_ola, @u_anna, 12000, '2026-09-04', NULL, @r_app_1, '2026-08-17 09:00:00', '2026-08-19 16:00:00');
SET @t_app_11 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_11, @u_anna, 'Proszę o weryfikację zakresu: przygotować generator changeloga ai.', '2026-08-18 10:00:00', '2026-08-18 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 13, 'Dodać polską wersję interfejsu', '### Cel
Dodać polską wersję interfejsu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_app_2, @u_marek, @u_anna, 13000, '2026-09-06', NULL, @r_app_1, '2026-08-19 09:00:00', '2026-08-22 16:00:00');
SET @t_app_12 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_12, @u_anna, 'Proszę o weryfikację zakresu: dodać polską wersję interfejsu.', '2026-08-20 10:00:00', '2026-08-20 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_12, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-21 11:00:00', '2026-08-21 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_12, @u_marek, '2026-08-21', 120, 'Analiza zadania: dodać polską wersję interfejsu.', 1, '2026-08-21 15:00:00', '2026-08-21 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 14, 'Napisać testy uprawnień użytkowników', '### Cel
Napisać testy uprawnień użytkowników.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_1, @u_kasia, @u_anna, 14000, '2026-09-08', NULL, @r_app_1, '2026-08-21 09:00:00', '2026-08-25 16:00:00');
SET @t_app_13 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_13, @u_anna, 'Proszę o weryfikację zakresu: napisać testy uprawnień użytkowników.', '2026-08-22 10:00:00', '2026-08-22 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_13, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-23 11:00:00', '2026-08-23 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_13, @u_kasia, '2026-08-23', 150, 'Analiza zadania: napisać testy uprawnień użytkowników.', 0, '2026-08-23 15:00:00', '2026-08-23 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_13, @u_kasia, '2026-08-24', 60, 'Implementacja i testy zadania: napisać testy uprawnień użytkowników.', 0, '2026-08-24 15:01:00', '2026-08-24 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 15, 'Zoptymalizować zapytania listy zadań', '### Cel
Zoptymalizować zapytania listy zadań.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_app_0, @u_anna, @u_anna, 15000, '2026-09-10', NULL, @r_app_1, '2026-08-23 09:00:00', '2026-08-28 16:00:00');
SET @t_app_14 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_14, @u_anna, 'Proszę o weryfikację zakresu: zoptymalizować zapytania listy zadań.', '2026-08-24 10:00:00', '2026-08-24 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 16, 'Przygotować publikację wersji 1.2', '### Cel
Przygotować publikację wersji 1.2.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_3, @u_piotr, @u_anna, 16000, NULL, '2026-09-06 16:00:00', @r_app_1, '2026-08-25 09:00:00', '2026-08-31 16:00:00');
SET @t_app_15 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_15, @u_anna, 'Proszę o weryfikację zakresu: przygotować publikację wersji 1.2.', '2026-08-26 10:00:00', '2026-08-26 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_15, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-27 11:00:00', '2026-08-27 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_15, @u_piotr, '2026-08-27', 45, 'Analiza zadania: przygotować publikację wersji 1.2.', 0, '2026-08-27 15:00:00', '2026-08-27 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 17, 'Sprawdzić dostępność klawiaturową', '### Cel
Sprawdzić dostępność klawiaturową.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 3, @s_app_1, @u_ola, @u_anna, 17000, '2026-09-14', NULL, @r_app_1, '2026-08-27 09:00:00', '2026-09-03 16:00:00');
SET @t_app_16 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_16, @u_anna, 'Proszę o weryfikację zakresu: sprawdzić dostępność klawiaturową.', '2026-08-28 10:00:00', '2026-08-28 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_16, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-29 11:00:00', '2026-08-29 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_16, @u_ola, '2026-08-29', 90, 'Analiza zadania: sprawdzić dostępność klawiaturową.', 0, '2026-08-29 15:00:00', '2026-08-29 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_16, @u_ola, '2026-08-30', 120, 'Implementacja i testy zadania: sprawdzić dostępność klawiaturową.', 0, '2026-08-30 15:01:00', '2026-08-30 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 18, 'Uzupełnić instrukcję użytkownika', '### Cel
Uzupełnić instrukcję użytkownika.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_0, @u_marek, @u_anna, 18000, '2026-09-16', NULL, @r_app_1, '2026-08-29 09:00:00', '2026-09-06 16:00:00');
SET @t_app_17 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_17, @u_anna, 'Proszę o weryfikację zakresu: uzupełnić instrukcję użytkownika.', '2026-08-30 10:00:00', '2026-08-30 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 19, 'Zaprojektować panel powiadomień', '### Cel
Zaprojektować panel powiadomień.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_app_0, @u_kasia, @u_anna, 19000, '2026-09-18', NULL, @r_app_1, '2026-08-31 09:00:00', '2026-09-09 16:00:00');
SET @t_app_18 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_18, @u_anna, 'Proszę o weryfikację zakresu: zaprojektować panel powiadomień.', '2026-09-01 10:00:00', '2026-09-01 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_app, NULL, 20, 'Naprawić resetowanie filtrów po odświeżeniu', '### Cel
Naprawić resetowanie filtrów po odświeżeniu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 4, 2, @s_app_2, @u_anna, @u_anna, 20000, '2026-09-20', NULL, @r_app_1, '2026-09-02 09:00:00', '2026-09-12 16:00:00');
SET @t_app_19 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_19, @u_anna, 'Proszę o weryfikację zakresu: naprawić resetowanie filtrów po odświeżeniu.', '2026-09-03 10:00:00', '2026-09-03 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_app_19, @u_anna, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-09-04 11:00:00', '2026-09-04 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_19, @u_anna, '2026-09-04', 60, 'Analiza zadania: naprawić resetowanie filtrów po odświeżeniu.', 0, '2026-09-04 15:00:00', '2026-09-04 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_app_19, @u_anna, '2026-09-05', 45, 'Implementacja i testy zadania: naprawić resetowanie filtrów po odświeżeniu.', 0, '2026-09-05 15:01:00', '2026-09-05 15:01:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, 'Raport postępu — Platforma TaskFlow — 2026-07-30', '2026-07-30', '2026-08-18', '["tasks", "worklogs", "messages"]', '# Raport postępu — Platforma TaskFlow — 2026-07-30

## Podsumowanie
Zespół realizował rozwój projektu **Platforma TaskFlow**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-08-19 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-08-19 09:00:00', '2026-08-19 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, 'Ewidencja pracy — Platforma TaskFlow — 2026-08-19', '2026-08-19', '2026-09-07', '["tasks", "worklogs", "messages"]', '# Ewidencja pracy — Platforma TaskFlow — 2026-08-19

## Podsumowanie
Zespół realizował rozwój projektu **Platforma TaskFlow**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-08 09:00:00', 'pl', 'work_report', '[]', NULL, NULL, NULL, '2026-09-08 09:00:00', '2026-09-08 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_app, @u_anna, 'Raport postępu — Platforma TaskFlow — 2026-09-08', '2026-09-08', '2026-09-27', '["tasks", "worklogs", "messages"]', '# Raport postępu — Platforma TaskFlow — 2026-09-08

## Podsumowanie
Zespół realizował rozwój projektu **Platforma TaskFlow**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-28 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 1, 'Zebrać wymagania katalogu produktów', '### Cel
Zebrać wymagania katalogu produktów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_shop_3, @u_piotr, @u_anna, 1000, NULL, '2026-08-09 16:00:00', NULL, '2026-07-28 09:00:00', '2026-07-29 16:00:00');
SET @t_shop_0 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_0, @u_anna, 'Proszę o weryfikację zakresu: zebrać wymagania katalogu produktów.', '2026-07-29 10:00:00', '2026-07-29 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_0, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-07-30 11:00:00', '2026-07-30 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_0, @u_piotr, '2026-07-30', 45, 'Analiza zadania: zebrać wymagania katalogu produktów.', 0, '2026-07-30 15:00:00', '2026-07-30 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 2, 'Zaprojektować stronę główną sklepu', '### Cel
Zaprojektować stronę główną sklepu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_shop_3, @u_ola, @u_anna, 2000, '2026-08-17', '2026-08-11 16:00:00', NULL, '2026-07-30 09:00:00', '2026-08-01 16:00:00');
SET @t_shop_1 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_1, @u_anna, 'Proszę o weryfikację zakresu: zaprojektować stronę główną sklepu.', '2026-07-31 10:00:00', '2026-07-31 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_1, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-01 11:00:00', '2026-08-01 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_1, @u_ola, '2026-08-01', 90, 'Analiza zadania: zaprojektować stronę główną sklepu.', 0, '2026-08-01 15:00:00', '2026-08-01 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_1, @u_ola, '2026-08-02', 120, 'Implementacja i testy zadania: zaprojektować stronę główną sklepu.', 0, '2026-08-02 15:01:00', '2026-08-02 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 3, 'Przygotować widok kategorii', '### Cel
Przygotować widok kategorii.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_shop_3, @u_marek, @u_anna, 3000, '2026-08-19', '2026-08-13 16:00:00', NULL, '2026-08-01 09:00:00', '2026-08-04 16:00:00');
SET @t_shop_2 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_2, @u_anna, 'Proszę o weryfikację zakresu: przygotować widok kategorii.', '2026-08-02 10:00:00', '2026-08-02 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_2, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-03 11:00:00', '2026-08-03 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_2, @u_marek, '2026-08-03', 120, 'Analiza zadania: przygotować widok kategorii.', 0, '2026-08-03 15:00:00', '2026-08-03 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_2, @u_marek, '2026-08-04', 150, 'Implementacja i testy zadania: przygotować widok kategorii.', 0, '2026-08-04 15:01:00', '2026-08-04 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_2, @u_marek, '2026-08-05', 60, 'Implementacja i testy zadania: przygotować widok kategorii.', 0, '2026-08-05 15:02:00', '2026-08-05 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 4, 'Wdrożyć wyszukiwanie produktów', '### Cel
Wdrożyć wyszukiwanie produktów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_shop_3, @u_kasia, @u_anna, 4000, '2026-08-21', '2026-08-15 16:00:00', NULL, '2026-08-03 09:00:00', '2026-08-07 16:00:00');
SET @t_shop_3 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_3, @u_anna, 'Proszę o weryfikację zakresu: wdrożyć wyszukiwanie produktów.', '2026-08-04 10:00:00', '2026-08-04 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_3, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-05 11:00:00', '2026-08-05 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_3, @u_kasia, '2026-08-05', 150, 'Analiza zadania: wdrożyć wyszukiwanie produktów.', 1, '2026-08-05 15:00:00', '2026-08-05 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 5, 'Dodać filtry ceny i dostępności', '### Cel
Dodać filtry ceny i dostępności.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_shop_3, @u_anna, @u_anna, 5000, '2026-08-23', '2026-08-17 16:00:00', NULL, '2026-08-05 09:00:00', '2026-08-10 16:00:00');
SET @t_shop_4 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_4, @u_anna, 'Proszę o weryfikację zakresu: dodać filtry ceny i dostępności.', '2026-08-06 10:00:00', '2026-08-06 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_4, @u_anna, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-07 11:00:00', '2026-08-07 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_4, @u_anna, '2026-08-07', 60, 'Analiza zadania: dodać filtry ceny i dostępności.', 1, '2026-08-07 15:00:00', '2026-08-07 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_4, @u_anna, '2026-08-08', 45, 'Implementacja i testy zadania: dodać filtry ceny i dostępności.', 1, '2026-08-08 15:01:00', '2026-08-08 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 6, 'Zaimplementować koszyk zakupowy', '### Cel
Zaimplementować koszyk zakupowy.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_shop_2, @u_piotr, @u_anna, 6000, NULL, NULL, NULL, '2026-08-07 09:00:00', '2026-08-13 16:00:00');
SET @t_shop_5 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_5, @u_anna, 'Proszę o weryfikację zakresu: zaimplementować koszyk zakupowy.', '2026-08-08 10:00:00', '2026-08-08 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_5, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-09 11:00:00', '2026-08-09 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_5, @u_piotr, '2026-08-09', 45, 'Analiza zadania: zaimplementować koszyk zakupowy.', 1, '2026-08-09 15:00:00', '2026-08-09 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_5, @u_piotr, '2026-08-10', 90, 'Implementacja i testy zadania: zaimplementować koszyk zakupowy.', 1, '2026-08-10 15:01:00', '2026-08-10 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_5, @u_piotr, '2026-08-11', 120, 'Implementacja i testy zadania: zaimplementować koszyk zakupowy.', 1, '2026-08-11 15:02:00', '2026-08-11 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, @t_shop_5, 7, 'Wdrożyć formularz adresu dostawy', '### Cel
Wdrożyć formularz adresu dostawy.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_shop_2, @u_ola, @u_anna, 7000, '2026-08-27', NULL, NULL, '2026-08-09 09:00:00', '2026-08-16 16:00:00');
SET @t_shop_6 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_6, @u_anna, 'Proszę o weryfikację zakresu: wdrożyć formularz adresu dostawy.', '2026-08-10 10:00:00', '2026-08-10 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_6, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-11 11:00:00', '2026-08-11 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_6, @u_ola, '2026-08-11', 90, 'Analiza zadania: wdrożyć formularz adresu dostawy.', 1, '2026-08-11 15:00:00', '2026-08-11 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 8, 'Zintegrować bramkę płatności', '### Cel
Zintegrować bramkę płatności.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_shop_1, @u_marek, @u_anna, 8000, '2026-08-29', NULL, NULL, '2026-08-11 09:00:00', '2026-08-19 16:00:00');
SET @t_shop_7 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_7, @u_anna, 'Proszę o weryfikację zakresu: zintegrować bramkę płatności.', '2026-08-12 10:00:00', '2026-08-12 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_7, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-13 11:00:00', '2026-08-13 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_7, @u_marek, '2026-08-13', 120, 'Analiza zadania: zintegrować bramkę płatności.', 0, '2026-08-13 15:00:00', '2026-08-13 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_7, @u_marek, '2026-08-14', 150, 'Implementacja i testy zadania: zintegrować bramkę płatności.', 0, '2026-08-14 15:01:00', '2026-08-14 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 9, 'Naprawić sumowanie rabatów', '### Cel
Naprawić sumowanie rabatów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 4, 3, @s_shop_1, @u_kasia, @u_anna, 9000, '2026-08-31', NULL, NULL, '2026-08-13 09:00:00', '2026-08-22 16:00:00');
SET @t_shop_8 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_8, @u_anna, 'Proszę o weryfikację zakresu: naprawić sumowanie rabatów.', '2026-08-14 10:00:00', '2026-08-14 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_8, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-15 11:00:00', '2026-08-15 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_8, @u_kasia, '2026-08-15', 150, 'Analiza zadania: naprawić sumowanie rabatów.', 0, '2026-08-15 15:00:00', '2026-08-15 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_8, @u_kasia, '2026-08-16', 60, 'Implementacja i testy zadania: naprawić sumowanie rabatów.', 0, '2026-08-16 15:01:00', '2026-08-16 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_8, @u_kasia, '2026-08-17', 45, 'Implementacja i testy zadania: naprawić sumowanie rabatów.', 0, '2026-08-17 15:02:00', '2026-08-17 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 10, 'Dodać wiadomości o statusie zamówienia', '### Cel
Dodać wiadomości o statusie zamówienia.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_shop_0, @u_anna, @u_anna, 10000, '2026-09-02', NULL, NULL, '2026-08-15 09:00:00', '2026-08-25 16:00:00');
SET @t_shop_9 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_9, @u_anna, 'Proszę o weryfikację zakresu: dodać wiadomości o statusie zamówienia.', '2026-08-16 10:00:00', '2026-08-16 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 11, 'Dostosować sklep do telefonu', '### Cel
Dostosować sklep do telefonu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_shop_0, @u_piotr, @u_anna, 11000, NULL, NULL, NULL, '2026-08-17 09:00:00', '2026-08-18 16:00:00');
SET @t_shop_10 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_10, @u_anna, 'Proszę o weryfikację zakresu: dostosować sklep do telefonu.', '2026-08-18 10:00:00', '2026-08-18 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, @t_shop_10, 12, 'Przygotować politykę zwrotów', '### Cel
Przygotować politykę zwrotów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_shop_0, @u_ola, @u_anna, 12000, '2026-09-06', NULL, NULL, '2026-08-19 09:00:00', '2026-08-21 16:00:00');
SET @t_shop_11 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_11, @u_anna, 'Proszę o weryfikację zakresu: przygotować politykę zwrotów.', '2026-08-20 10:00:00', '2026-08-20 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 13, 'Uzupełnić opisy kategorii', '### Cel
Uzupełnić opisy kategorii.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 3, @s_shop_2, @u_marek, @u_anna, 13000, '2026-09-08', NULL, NULL, '2026-08-21 09:00:00', '2026-08-24 16:00:00');
SET @t_shop_12 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_12, @u_anna, 'Proszę o weryfikację zakresu: uzupełnić opisy kategorii.', '2026-08-22 10:00:00', '2026-08-22 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_12, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-23 11:00:00', '2026-08-23 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_12, @u_marek, '2026-08-23', 120, 'Analiza zadania: uzupełnić opisy kategorii.', 0, '2026-08-23 15:00:00', '2026-08-23 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 14, 'Przetestować zakup jako gość', '### Cel
Przetestować zakup jako gość.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_shop_1, @u_kasia, @u_anna, 14000, '2026-09-10', NULL, NULL, '2026-08-23 09:00:00', '2026-08-27 16:00:00');
SET @t_shop_13 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_13, @u_anna, 'Proszę o weryfikację zakresu: przetestować zakup jako gość.', '2026-08-24 10:00:00', '2026-08-24 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_13, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-25 11:00:00', '2026-08-25 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_13, @u_kasia, '2026-08-25', 150, 'Analiza zadania: przetestować zakup jako gość.', 0, '2026-08-25 15:00:00', '2026-08-25 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_shop_13, @u_kasia, '2026-08-26', 60, 'Implementacja i testy zadania: przetestować zakup jako gość.', 0, '2026-08-26 15:01:00', '2026-08-26 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_shop, NULL, 15, 'Przygotować materiały do uruchomienia', '### Cel
Przygotować materiały do uruchomienia.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_shop_0, @u_anna, @u_anna, 15000, '2026-09-12', NULL, NULL, '2026-08-25 09:00:00', '2026-08-30 16:00:00');
SET @t_shop_14 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_shop_14, @u_anna, 'Proszę o weryfikację zakresu: przygotować materiały do uruchomienia.', '2026-08-26 10:00:00', '2026-08-26 10:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_shop, @u_anna, 'Raport postępu — Sklep Zielona Półka — 2026-07-30', '2026-07-30', '2026-08-18', '["tasks", "worklogs", "messages"]', '# Raport postępu — Sklep Zielona Półka — 2026-07-30

## Podsumowanie
Zespół realizował rozwój projektu **Sklep Zielona Półka**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-08-19 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-08-19 09:00:00', '2026-08-19 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_shop, @u_anna, 'Ewidencja pracy — Sklep Zielona Półka — 2026-08-19', '2026-08-19', '2026-09-07', '["tasks", "worklogs", "messages"]', '# Ewidencja pracy — Sklep Zielona Półka — 2026-08-19

## Podsumowanie
Zespół realizował rozwój projektu **Sklep Zielona Półka**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-08 09:00:00', 'pl', 'work_report', '[]', NULL, NULL, NULL, '2026-09-08 09:00:00', '2026-09-08 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_shop, @u_anna, 'Raport postępu — Sklep Zielona Półka — 2026-09-08', '2026-09-08', '2026-09-27', '["tasks", "worklogs", "messages"]', '# Raport postępu — Sklep Zielona Półka — 2026-09-08

## Podsumowanie
Zespół realizował rozwój projektu **Sklep Zielona Półka**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-28 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 1, 'Przeprowadzić audyt istniejącej strony', '### Cel
Przeprowadzić audyt istniejącej strony.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_site_3, @u_piotr, @u_anna, 1000, NULL, '2026-08-11 16:00:00', NULL, '2026-07-30 09:00:00', '2026-07-31 16:00:00');
SET @t_site_0 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_0, @u_anna, 'Proszę o weryfikację zakresu: przeprowadzić audyt istniejącej strony.', '2026-07-31 10:00:00', '2026-07-31 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_0, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-01 11:00:00', '2026-08-01 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_0, @u_piotr, '2026-08-01', 45, 'Analiza zadania: przeprowadzić audyt istniejącej strony.', 0, '2026-08-01 15:00:00', '2026-08-01 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 2, 'Ustalić strukturę nawigacji', '### Cel
Ustalić strukturę nawigacji.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_3, @u_ola, @u_anna, 2000, '2026-08-19', '2026-08-13 16:00:00', NULL, '2026-08-01 09:00:00', '2026-08-03 16:00:00');
SET @t_site_1 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_1, @u_anna, 'Proszę o weryfikację zakresu: ustalić strukturę nawigacji.', '2026-08-02 10:00:00', '2026-08-02 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_1, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-03 11:00:00', '2026-08-03 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_1, @u_ola, '2026-08-03', 90, 'Analiza zadania: ustalić strukturę nawigacji.', 0, '2026-08-03 15:00:00', '2026-08-03 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_1, @u_ola, '2026-08-04', 120, 'Implementacja i testy zadania: ustalić strukturę nawigacji.', 0, '2026-08-04 15:01:00', '2026-08-04 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 3, 'Zaprojektować nową stronę główną', '### Cel
Zaprojektować nową stronę główną.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_3, @u_marek, @u_anna, 3000, '2026-08-21', '2026-08-15 16:00:00', NULL, '2026-08-03 09:00:00', '2026-08-06 16:00:00');
SET @t_site_2 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_2, @u_anna, 'Proszę o weryfikację zakresu: zaprojektować nową stronę główną.', '2026-08-04 10:00:00', '2026-08-04 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_2, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-05 11:00:00', '2026-08-05 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_2, @u_marek, '2026-08-05', 120, 'Analiza zadania: zaprojektować nową stronę główną.', 0, '2026-08-05 15:00:00', '2026-08-05 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_2, @u_marek, '2026-08-06', 150, 'Implementacja i testy zadania: zaprojektować nową stronę główną.', 0, '2026-08-06 15:01:00', '2026-08-06 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_2, @u_marek, '2026-08-07', 60, 'Implementacja i testy zadania: zaprojektować nową stronę główną.', 0, '2026-08-07 15:02:00', '2026-08-07 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 4, 'Przygotować szablon oferty', '### Cel
Przygotować szablon oferty.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_3, @u_kasia, @u_anna, 4000, '2026-08-23', '2026-08-17 16:00:00', NULL, '2026-08-05 09:00:00', '2026-08-09 16:00:00');
SET @t_site_3 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_3, @u_anna, 'Proszę o weryfikację zakresu: przygotować szablon oferty.', '2026-08-06 10:00:00', '2026-08-06 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_3, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-07 11:00:00', '2026-08-07 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_3, @u_kasia, '2026-08-07', 150, 'Analiza zadania: przygotować szablon oferty.', 0, '2026-08-07 15:00:00', '2026-08-07 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 5, 'Dodać sekcję realizacji', '### Cel
Dodać sekcję realizacji.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_site_3, @u_anna, @u_anna, 5000, '2026-08-25', '2026-08-19 16:00:00', NULL, '2026-08-07 09:00:00', '2026-08-12 16:00:00');
SET @t_site_4 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_4, @u_anna, 'Proszę o weryfikację zakresu: dodać sekcję realizacji.', '2026-08-08 10:00:00', '2026-08-08 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_4, @u_anna, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-09 11:00:00', '2026-08-09 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_4, @u_anna, '2026-08-09', 60, 'Analiza zadania: dodać sekcję realizacji.', 1, '2026-08-09 15:00:00', '2026-08-09 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_4, @u_anna, '2026-08-10', 45, 'Implementacja i testy zadania: dodać sekcję realizacji.', 1, '2026-08-10 15:01:00', '2026-08-10 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 6, 'Wdrożyć formularz kontaktowy', '### Cel
Wdrożyć formularz kontaktowy.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_site_2, @u_piotr, @u_anna, 6000, NULL, NULL, NULL, '2026-08-09 09:00:00', '2026-08-15 16:00:00');
SET @t_site_5 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_5, @u_anna, 'Proszę o weryfikację zakresu: wdrożyć formularz kontaktowy.', '2026-08-10 10:00:00', '2026-08-10 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_5, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-11 11:00:00', '2026-08-11 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_5, @u_piotr, '2026-08-11', 45, 'Analiza zadania: wdrożyć formularz kontaktowy.', 1, '2026-08-11 15:00:00', '2026-08-11 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_5, @u_piotr, '2026-08-12', 90, 'Implementacja i testy zadania: wdrożyć formularz kontaktowy.', 1, '2026-08-12 15:01:00', '2026-08-12 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_5, @u_piotr, '2026-08-13', 120, 'Implementacja i testy zadania: wdrożyć formularz kontaktowy.', 1, '2026-08-13 15:02:00', '2026-08-13 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, @t_site_5, 7, 'Uzupełnić fotografie zespołu', '### Cel
Uzupełnić fotografie zespołu.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_2, @u_ola, @u_anna, 7000, '2026-08-29', NULL, NULL, '2026-08-11 09:00:00', '2026-08-18 16:00:00');
SET @t_site_6 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_6, @u_anna, 'Proszę o weryfikację zakresu: uzupełnić fotografie zespołu.', '2026-08-12 10:00:00', '2026-08-12 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_6, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-13 11:00:00', '2026-08-13 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_6, @u_ola, '2026-08-13', 90, 'Analiza zadania: uzupełnić fotografie zespołu.', 0, '2026-08-13 15:00:00', '2026-08-13 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 8, 'Przygotować treści SEO', '### Cel
Przygotować treści SEO.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_site_1, @u_marek, @u_anna, 8000, '2026-08-31', NULL, NULL, '2026-08-13 09:00:00', '2026-08-21 16:00:00');
SET @t_site_7 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_7, @u_anna, 'Proszę o weryfikację zakresu: przygotować treści seo.', '2026-08-14 10:00:00', '2026-08-14 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_7, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-15 11:00:00', '2026-08-15 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_7, @u_marek, '2026-08-15', 120, 'Analiza zadania: przygotować treści seo.', 0, '2026-08-15 15:00:00', '2026-08-15 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_7, @u_marek, '2026-08-16', 150, 'Implementacja i testy zadania: przygotować treści seo.', 0, '2026-08-16 15:01:00', '2026-08-16 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 9, 'Dodać sekcję aktualności', '### Cel
Dodać sekcję aktualności.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_site_1, @u_kasia, @u_anna, 9000, '2026-09-02', NULL, NULL, '2026-08-15 09:00:00', '2026-08-24 16:00:00');
SET @t_site_8 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_8, @u_anna, 'Proszę o weryfikację zakresu: dodać sekcję aktualności.', '2026-08-16 10:00:00', '2026-08-16 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_8, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-17 11:00:00', '2026-08-17 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_8, @u_kasia, '2026-08-17', 150, 'Analiza zadania: dodać sekcję aktualności.', 1, '2026-08-17 15:00:00', '2026-08-17 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_8, @u_kasia, '2026-08-18', 60, 'Implementacja i testy zadania: dodać sekcję aktualności.', 1, '2026-08-18 15:01:00', '2026-08-18 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_8, @u_kasia, '2026-08-19', 45, 'Implementacja i testy zadania: dodać sekcję aktualności.', 1, '2026-08-19 15:02:00', '2026-08-19 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 10, 'Zoptymalizować obrazy WebP', '### Cel
Zoptymalizować obrazy WebP.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_0, @u_anna, @u_anna, 10000, '2026-09-04', NULL, NULL, '2026-08-17 09:00:00', '2026-08-27 16:00:00');
SET @t_site_9 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_9, @u_anna, 'Proszę o weryfikację zakresu: zoptymalizować obrazy webp.', '2026-08-18 10:00:00', '2026-08-18 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 11, 'Dostosować kontrast przycisków', '### Cel
Dostosować kontrast przycisków.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_0, @u_piotr, @u_anna, 11000, NULL, NULL, NULL, '2026-08-19 09:00:00', '2026-08-20 16:00:00');
SET @t_site_10 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_10, @u_anna, 'Proszę o weryfikację zakresu: dostosować kontrast przycisków.', '2026-08-20 10:00:00', '2026-08-20 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, @t_site_10, 12, 'Sprawdzić wynik Lighthouse', '### Cel
Sprawdzić wynik Lighthouse.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_site_0, @u_ola, @u_anna, 12000, '2026-09-08', NULL, NULL, '2026-08-21 09:00:00', '2026-08-23 16:00:00');
SET @t_site_11 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_11, @u_anna, 'Proszę o weryfikację zakresu: sprawdzić wynik lighthouse.', '2026-08-22 10:00:00', '2026-08-22 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 13, 'Przygotować stronę 404', '### Cel
Przygotować stronę 404.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 3, @s_site_2, @u_marek, @u_anna, 13000, '2026-09-10', NULL, NULL, '2026-08-23 09:00:00', '2026-08-26 16:00:00');
SET @t_site_12 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_12, @u_anna, 'Proszę o weryfikację zakresu: przygotować stronę 404.', '2026-08-24 10:00:00', '2026-08-24 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_12, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-25 11:00:00', '2026-08-25 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_12, @u_marek, '2026-08-25', 120, 'Analiza zadania: przygotować stronę 404.', 0, '2026-08-25 15:00:00', '2026-08-25 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 14, 'Przetestować formularz na urządzeniach', '### Cel
Przetestować formularz na urządzeniach.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_site_1, @u_kasia, @u_anna, 14000, '2026-09-12', NULL, NULL, '2026-08-25 09:00:00', '2026-08-29 16:00:00');
SET @t_site_13 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_13, @u_anna, 'Proszę o weryfikację zakresu: przetestować formularz na urządzeniach.', '2026-08-26 10:00:00', '2026-08-26 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_13, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-27 11:00:00', '2026-08-27 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_13, @u_kasia, '2026-08-27', 150, 'Analiza zadania: przetestować formularz na urządzeniach.', 0, '2026-08-27 15:00:00', '2026-08-27 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_site_13, @u_kasia, '2026-08-28', 60, 'Implementacja i testy zadania: przetestować formularz na urządzeniach.', 0, '2026-08-28 15:01:00', '2026-08-28 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_site, NULL, 15, 'Opublikować witrynę produkcyjną', '### Cel
Opublikować witrynę produkcyjną.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_site_0, @u_anna, @u_anna, 15000, '2026-09-14', NULL, NULL, '2026-08-27 09:00:00', '2026-09-01 16:00:00');
SET @t_site_14 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_site_14, @u_anna, 'Proszę o weryfikację zakresu: opublikować witrynę produkcyjną.', '2026-08-28 10:00:00', '2026-08-28 10:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_site, @u_anna, 'Raport postępu — Strona Studio Nova — 2026-07-30', '2026-07-30', '2026-08-18', '["tasks", "worklogs", "messages"]', '# Raport postępu — Strona Studio Nova — 2026-07-30

## Podsumowanie
Zespół realizował rozwój projektu **Strona Studio Nova**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-08-19 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-08-19 09:00:00', '2026-08-19 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_site, @u_anna, 'Ewidencja pracy — Strona Studio Nova — 2026-08-19', '2026-08-19', '2026-09-07', '["tasks", "worklogs", "messages"]', '# Ewidencja pracy — Strona Studio Nova — 2026-08-19

## Podsumowanie
Zespół realizował rozwój projektu **Strona Studio Nova**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-08 09:00:00', 'pl', 'work_report', '[]', NULL, NULL, NULL, '2026-09-08 09:00:00', '2026-09-08 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_site, @u_anna, 'Raport postępu — Strona Studio Nova — 2026-09-08', '2026-09-08', '2026-09-27', '["tasks", "worklogs", "messages"]', '# Raport postępu — Strona Studio Nova — 2026-09-08

## Podsumowanie
Zespół realizował rozwój projektu **Strona Studio Nova**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-28 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 1, 'Ustalić kontrakty endpointów', '### Cel
Ustalić kontrakty endpointów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_api_3, @u_piotr, @u_anna, 1000, NULL, '2026-08-13 16:00:00', @r_api_0, '2026-08-01 09:00:00', '2026-08-02 16:00:00');
SET @t_api_0 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_0, @u_anna, 'Proszę o weryfikację zakresu: ustalić kontrakty endpointów.', '2026-08-02 10:00:00', '2026-08-02 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_0, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-03 11:00:00', '2026-08-03 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_0, @u_piotr, '2026-08-03', 45, 'Analiza zadania: ustalić kontrakty endpointów.', 0, '2026-08-03 15:00:00', '2026-08-03 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 2, 'Dodać uwierzytelnianie tokenem', '### Cel
Dodać uwierzytelnianie tokenem.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_api_3, @u_ola, @u_anna, 2000, '2026-08-21', '2026-08-15 16:00:00', @r_api_0, '2026-08-03 09:00:00', '2026-08-05 16:00:00');
SET @t_api_1 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_1, @u_anna, 'Proszę o weryfikację zakresu: dodać uwierzytelnianie tokenem.', '2026-08-04 10:00:00', '2026-08-04 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_1, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-05 11:00:00', '2026-08-05 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_1, @u_ola, '2026-08-05', 90, 'Analiza zadania: dodać uwierzytelnianie tokenem.', 1, '2026-08-05 15:00:00', '2026-08-05 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_1, @u_ola, '2026-08-06', 120, 'Implementacja i testy zadania: dodać uwierzytelnianie tokenem.', 1, '2026-08-06 15:01:00', '2026-08-06 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 3, 'Przygotować migracje zamówień', '### Cel
Przygotować migracje zamówień.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_api_3, @u_marek, @u_anna, 3000, '2026-08-23', '2026-08-17 16:00:00', @r_api_0, '2026-08-05 09:00:00', '2026-08-08 16:00:00');
SET @t_api_2 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_2, @u_anna, 'Proszę o weryfikację zakresu: przygotować migracje zamówień.', '2026-08-06 10:00:00', '2026-08-06 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_2, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-07 11:00:00', '2026-08-07 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_2, @u_marek, '2026-08-07', 120, 'Analiza zadania: przygotować migracje zamówień.', 0, '2026-08-07 15:00:00', '2026-08-07 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_2, @u_marek, '2026-08-08', 150, 'Implementacja i testy zadania: przygotować migracje zamówień.', 0, '2026-08-08 15:01:00', '2026-08-08 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_2, @u_marek, '2026-08-09', 60, 'Implementacja i testy zadania: przygotować migracje zamówień.', 0, '2026-08-09 15:02:00', '2026-08-09 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 4, 'Zaimplementować pobieranie zamówień', '### Cel
Zaimplementować pobieranie zamówień.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_api_3, @u_kasia, @u_anna, 4000, '2026-08-25', '2026-08-19 16:00:00', @r_api_0, '2026-08-07 09:00:00', '2026-08-11 16:00:00');
SET @t_api_3 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_3, @u_anna, 'Proszę o weryfikację zakresu: zaimplementować pobieranie zamówień.', '2026-08-08 10:00:00', '2026-08-08 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_3, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-09 11:00:00', '2026-08-09 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_3, @u_kasia, '2026-08-09', 150, 'Analiza zadania: zaimplementować pobieranie zamówień.', 1, '2026-08-09 15:00:00', '2026-08-09 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 5, 'Dodać paginację wyników', '### Cel
Dodać paginację wyników.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_api_3, @u_anna, @u_anna, 5000, '2026-08-27', '2026-08-21 16:00:00', @r_api_0, '2026-08-09 09:00:00', '2026-08-14 16:00:00');
SET @t_api_4 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_4, @u_anna, 'Proszę o weryfikację zakresu: dodać paginację wyników.', '2026-08-10 10:00:00', '2026-08-10 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_4, @u_anna, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-11 11:00:00', '2026-08-11 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_4, @u_anna, '2026-08-11', 60, 'Analiza zadania: dodać paginację wyników.', 1, '2026-08-11 15:00:00', '2026-08-11 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_4, @u_anna, '2026-08-12', 45, 'Implementacja i testy zadania: dodać paginację wyników.', 1, '2026-08-12 15:01:00', '2026-08-12 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 6, 'Obsłużyć ponawianie żądań', '### Cel
Obsłużyć ponawianie żądań.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_api_2, @u_piotr, @u_anna, 6000, NULL, NULL, @r_api_0, '2026-08-11 09:00:00', '2026-08-17 16:00:00');
SET @t_api_5 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_5, @u_anna, 'Proszę o weryfikację zakresu: obsłużyć ponawianie żądań.', '2026-08-12 10:00:00', '2026-08-12 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_5, @u_piotr, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-13 11:00:00', '2026-08-13 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_5, @u_piotr, '2026-08-13', 45, 'Analiza zadania: obsłużyć ponawianie żądań.', 0, '2026-08-13 15:00:00', '2026-08-13 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_5, @u_piotr, '2026-08-14', 90, 'Implementacja i testy zadania: obsłużyć ponawianie żądań.', 0, '2026-08-14 15:01:00', '2026-08-14 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_5, @u_piotr, '2026-08-15', 120, 'Implementacja i testy zadania: obsłużyć ponawianie żądań.', 0, '2026-08-15 15:02:00', '2026-08-15 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, @t_api_5, 7, 'Naprawić duplikowanie webhooków', '### Cel
Naprawić duplikowanie webhooków.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 4, 2, @s_api_2, @u_ola, @u_anna, 7000, '2026-08-31', NULL, @r_api_0, '2026-08-13 09:00:00', '2026-08-20 16:00:00');
SET @t_api_6 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_6, @u_anna, 'Proszę o weryfikację zakresu: naprawić duplikowanie webhooków.', '2026-08-14 10:00:00', '2026-08-14 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_6, @u_ola, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-15 11:00:00', '2026-08-15 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_6, @u_ola, '2026-08-15', 90, 'Analiza zadania: naprawić duplikowanie webhooków.', 0, '2026-08-15 15:00:00', '2026-08-15 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 8, 'Przygotować integrację kurierską', '### Cel
Przygotować integrację kurierską.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_api_1, @u_marek, @u_anna, 8000, '2026-09-02', NULL, @r_api_0, '2026-08-15 09:00:00', '2026-08-23 16:00:00');
SET @t_api_7 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_7, @u_anna, 'Proszę o weryfikację zakresu: przygotować integrację kurierską.', '2026-08-16 10:00:00', '2026-08-16 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_7, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-17 11:00:00', '2026-08-17 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_7, @u_marek, '2026-08-17', 120, 'Analiza zadania: przygotować integrację kurierską.', 0, '2026-08-17 15:00:00', '2026-08-17 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_7, @u_marek, '2026-08-18', 150, 'Implementacja i testy zadania: przygotować integrację kurierską.', 0, '2026-08-18 15:01:00', '2026-08-18 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 9, 'Zaimplementować dziennik błędów', '### Cel
Zaimplementować dziennik błędów.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_api_1, @u_kasia, @u_anna, 9000, '2026-09-04', NULL, @r_api_1, '2026-08-17 09:00:00', '2026-08-26 16:00:00');
SET @t_api_8 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_8, @u_anna, 'Proszę o weryfikację zakresu: zaimplementować dziennik błędów.', '2026-08-18 10:00:00', '2026-08-18 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_8, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-19 11:00:00', '2026-08-19 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_8, @u_kasia, '2026-08-19', 150, 'Analiza zadania: zaimplementować dziennik błędów.', 1, '2026-08-19 15:00:00', '2026-08-19 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_8, @u_kasia, '2026-08-20', 60, 'Implementacja i testy zadania: zaimplementować dziennik błędów.', 1, '2026-08-20 15:01:00', '2026-08-20 15:01:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_8, @u_kasia, '2026-08-21', 45, 'Implementacja i testy zadania: zaimplementować dziennik błędów.', 1, '2026-08-21 15:02:00', '2026-08-21 15:02:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 10, 'Dodać limitowanie żądań', '### Cel
Dodać limitowanie żądań.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 2, @s_api_0, @u_anna, @u_anna, 10000, '2026-09-06', NULL, @r_api_1, '2026-08-19 09:00:00', '2026-08-29 16:00:00');
SET @t_api_9 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_9, @u_anna, 'Proszę o weryfikację zakresu: dodać limitowanie żądań.', '2026-08-20 10:00:00', '2026-08-20 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 11, 'Napisać testy integracyjne', '### Cel
Napisać testy integracyjne.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_api_0, @u_piotr, @u_anna, 11000, NULL, NULL, @r_api_1, '2026-08-21 09:00:00', '2026-08-22 16:00:00');
SET @t_api_10 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_10, @u_anna, 'Proszę o weryfikację zakresu: napisać testy integracyjne.', '2026-08-22 10:00:00', '2026-08-22 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, @t_api_10, 12, 'Przygotować dokumentację OpenAPI', '### Cel
Przygotować dokumentację OpenAPI.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 4, @s_api_0, @u_ola, @u_anna, 12000, '2026-09-10', NULL, @r_api_1, '2026-08-23 09:00:00', '2026-08-25 16:00:00');
SET @t_api_11 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_11, @u_anna, 'Proszę o weryfikację zakresu: przygotować dokumentację openapi.', '2026-08-24 10:00:00', '2026-08-24 10:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 13, 'Wdrożyć środowisko testowe', '### Cel
Wdrożyć środowisko testowe.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 2, 3, @s_api_2, @u_marek, @u_anna, 13000, '2026-09-12', NULL, @r_api_1, '2026-08-25 09:00:00', '2026-08-28 16:00:00');
SET @t_api_12 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_12, @u_anna, 'Proszę o weryfikację zakresu: wdrożyć środowisko testowe.', '2026-08-26 10:00:00', '2026-08-26 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_12, @u_marek, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-27 11:00:00', '2026-08-27 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_12, @u_marek, '2026-08-27', 120, 'Analiza zadania: wdrożyć środowisko testowe.', 1, '2026-08-27 15:00:00', '2026-08-27 15:00:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 14, 'Przetestować scenariusze awaryjne', '### Cel
Przetestować scenariusze awaryjne.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 2, @s_api_1, @u_kasia, @u_anna, 14000, '2026-09-14', NULL, @r_api_1, '2026-08-27 09:00:00', '2026-08-31 16:00:00');
SET @t_api_13 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_13, @u_anna, 'Proszę o weryfikację zakresu: przetestować scenariusze awaryjne.', '2026-08-28 10:00:00', '2026-08-28 10:00:00');
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_13, @u_kasia, 'Wprowadziłem zmiany i dodałem testy. Proszę o informację zwrotną.', '2026-08-29 11:00:00', '2026-08-29 11:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_13, @u_kasia, '2026-08-29', 150, 'Analiza zadania: przetestować scenariusze awaryjne.', 0, '2026-08-29 15:00:00', '2026-08-29 15:00:00');
INSERT INTO `task_worklogs` (`task_id`, `user_id`, `work_date`, `duration_minutes`, `description`, `is_creative`, `created_at`, `updated_at`) VALUES (@t_api_13, @u_kasia, '2026-08-30', 60, 'Implementacja i testy zadania: przetestować scenariusze awaryjne.', 0, '2026-08-30 15:01:00', '2026-08-30 15:01:00');
INSERT INTO `tasks` (`project_id`, `parent_id`, `number`, `title`, `description`, `type`, `priority`, `status_id`, `assignee_id`, `created_by`, `position`, `due_date`, `completed_at`, `release_id`, `created_at`, `updated_at`) VALUES (@p_api, NULL, 15, 'Uruchomić monitoring opóźnień', '### Cel
Uruchomić monitoring opóźnień.

### Kryteria akceptacji
- Zmiana działa zgodnie z uzgodnionym zakresem.
- Scenariusz podstawowy i błędny są przetestowane.
- Wynik został opisany w komentarzu zadania.', 1, 1, @s_api_0, @u_anna, @u_anna, 15000, '2026-09-16', NULL, @r_api_1, '2026-08-29 09:00:00', '2026-09-03 16:00:00');
SET @t_api_14 = LAST_INSERT_ID();
INSERT INTO `task_messages` (`task_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES (@t_api_14, @u_anna, 'Proszę o weryfikację zakresu: uruchomić monitoring opóźnień.', '2026-08-30 10:00:00', '2026-08-30 10:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, 'Raport postępu — API Zamówień — 2026-07-30', '2026-07-30', '2026-08-18', '["tasks", "worklogs", "messages"]', '# Raport postępu — API Zamówień — 2026-07-30

## Podsumowanie
Zespół realizował rozwój projektu **API Zamówień**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-08-19 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-08-19 09:00:00', '2026-08-19 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, 'Ewidencja pracy — API Zamówień — 2026-08-19', '2026-08-19', '2026-09-07', '["tasks", "worklogs", "messages"]', '# Ewidencja pracy — API Zamówień — 2026-08-19

## Podsumowanie
Zespół realizował rozwój projektu **API Zamówień**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-08 09:00:00', 'pl', 'work_report', '[]', NULL, NULL, NULL, '2026-09-08 09:00:00', '2026-09-08 09:00:00');
INSERT INTO `reports` (`project_id`, `created_by`, `title`, `period_start`, `period_end`, `sources`, `body`, `generated_at`, `generated_locale`, `format`, `contributor_ids`, `edited_at`, `published_at`, `published_by`, `created_at`, `updated_at`) VALUES (@p_api, @u_anna, 'Raport postępu — API Zamówień — 2026-09-08', '2026-09-08', '2026-09-27', '["tasks", "worklogs", "messages"]', '# Raport postępu — API Zamówień — 2026-09-08

## Podsumowanie
Zespół realizował rozwój projektu **API Zamówień**. Priorytetem były stabilność, funkcjonalność i jakość wdrożenia.

## Wykonane prace
- Analiza wymagań i uzgodnienie zakresu.
- Implementacja oraz przegląd kodu.
- Testy funkcjonalne i poprawki zgłoszeń.

## Ryzyka i dalsze kroki
- Zweryfikować termin kolejnego wdrożenia.
- Dokończyć zadania pozostające w toku.
- Przygotować testy regresji.', '2026-09-28 09:00:00', 'pl', 'project_summary', '[]', NULL, NULL, NULL, '2026-09-28 09:00:00', '2026-09-28 09:00:00');
COMMIT;
-- Koniec danych demonstracyjnych.
