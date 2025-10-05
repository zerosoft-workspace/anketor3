-- Database schema for Anketor platform
CREATE DATABASE IF NOT EXISTS `anketor` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `anketor`;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surveys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','scheduled','active','closed') NOT NULL DEFAULT 'draft',
    start_date DATE NULL,
    end_date DATE NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_surveys_category FOREIGN KEY (category_id) REFERENCES survey_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_surveys_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','rating','text') NOT NULL,
    category_key VARCHAR(120) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    max_length INT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_questions_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    option_value VARCHAR(100) NULL,
    order_index INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_library (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','rating','text') NOT NULL,
    category_key VARCHAR(120) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    max_length INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_library_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    library_question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    option_value VARCHAR(100) NULL,
    order_index INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_library_options_question FOREIGN KEY (library_question_id) REFERENCES question_library(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    token VARCHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    invited_at DATETIME NULL,
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant_token (token_hash),
    CONSTRAINT fk_participants_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    participant_id INT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_responses_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    CONSTRAINT fk_responses_participant FOREIGN KEY (participant_id) REFERENCES survey_participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS response_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NULL,
    answer_text TEXT NULL,
    numeric_value DECIMAL(10,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_answers_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_option FOREIGN KEY (option_id) REFERENCES question_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_ai_suggestions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    prompt TEXT NOT NULL,
    suggestion TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_suggestions_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    report_type ENUM('trend','summary','smart') NOT NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password_hash)
VALUES ('Admin', 'admin@example.com', '$2y$10$Wz0sMijh1X1Fh2W0Fwuq7Ot5GDL8NcNehJIKBNB1LsY5cqK8QxSN2');
-- Password: ChangeMe!23
-- Demo seed data
INSERT INTO survey_categories (id, name, description) VALUES
    (1, 'Siber Guvenlik', 'Web uygulamalari, parola ve MFA odakli calismalar'),
    (2, 'E-Posta Guvenligi', 'Oltalama farkindaligi ve mesaj incelemeleri'),
    (3, 'Risk ve Uyum', 'Politika uyumu ve bilincli calisma degerlendirmeleri')

ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT INTO surveys (id, category_id, title, description, status, start_date, end_date, created_by, created_at, updated_at) VALUES
    (1, 1, '2025 Siber Guvenlik Kisiel Degerlendirme', 'Web ve e-posta guvenligi odakli bireysel erisim degerlendirmesi.', 'active', '2025-03-01', '2025-03-31', 1, '2025-02-20 09:00:00', '2025-02-20 09:00:00'),
    (2, 2, '2024 Q4 Oltalama Farkindalik Tarama', 'Calisanlarin e-posta saldirilarina verdigi yanitlari olcekleyen kampanya.', 'closed', '2024-10-15', '2024-11-30', 1, '2024-10-01 09:30:00', '2024-12-02 08:45:00')

ON DUPLICATE KEY UPDATE
    category_id = VALUES(category_id),
    title = VALUES(title),
    description = VALUES(description),
    status = VALUES(status),
    start_date = VALUES(start_date),
    end_date = VALUES(end_date),
    created_by = VALUES(created_by),
    updated_at = VALUES(updated_at);

INSERT INTO survey_questions (id, survey_id, question_text, question_type, category_key, is_required, max_length, order_index, created_at) VALUES
    (1, 1, 'Web uygulamalarinda guclu parola kullanirim.', 'rating', 'web_guvenligi', 1, NULL, 1, '2025-02-20 09:05:00'),
    (2, 1, 'Cok faktorlu kimlik dogrulamasini hangi siklikla etkinlestiriyorsunuz?', 'multiple_choice', 'web_guvenligi', 1, NULL, 2, '2025-02-20 09:06:00'),
    (3, 1, 'Web guvenligini artirmak icin hangi desteklere ihtiyaciniz var?', 'text', 'web_guvenligi', 0, 400, 3, '2025-02-20 09:07:00'),
    (4, 2, 'Supheli e-postalari tanima becerimi 1-5 arasinda puanlarim.', 'rating', 'eposta_guvenligi', 1, NULL, 1, '2024-10-01 09:40:00'),
    (5, 2, 'Olta saldirisi oldugunu dusundugunuz maili nasil raporlarsiniz?', 'multiple_choice', 'eposta_guvenligi', 1, NULL, 2, '2024-10-01 09:41:00'),
    (6, 2, 'E-posta saldirilarina karsi daha guvende hissetmek icin ne gerekir?', 'text', 'eposta_guvenligi', 0, 400, 3, '2024-10-01 09:42:00')

ON DUPLICATE KEY UPDATE
    survey_id = VALUES(survey_id),
    question_text = VALUES(question_text),
    question_type = VALUES(question_type),
    is_required = VALUES(is_required),
    max_length = VALUES(max_length),
    order_index = VALUES(order_index);

INSERT INTO question_options (id, question_id, option_text, option_value, order_index) VALUES
    (1, 2, 'Her zaman', NULL, 1),
    (2, 2, 'Cogu uygulamada', NULL, 2),
    (3, 2, 'Nadiren', NULL, 3),
    (4, 5, 'Aninda guvenlik ekibine bildiririm', NULL, 1),
    (5, 5, 'Ekibime iletir ve teyit beklerim', NULL, 2),
    (6, 5, 'Yanlislikla siler ya da yok sayarim', NULL, 3)

ON DUPLICATE KEY UPDATE
    question_id = VALUES(question_id),
    option_text = VALUES(option_text),
    option_value = VALUES(option_value),
    order_index = VALUES(order_index);

INSERT INTO question_library (id, question_text, question_type, category_key, is_required, max_length, created_at) VALUES
    (1, 'Web uygulamalarinda guclu parola kullanirim.', 'rating', 'web_guvenligi', 1, NULL, '2025-02-20 09:05:00'),
    (2, 'Cok faktorlu kimlik dogrulamasini hangi siklikla etkinlestiriyorsunuz?', 'multiple_choice', 'web_guvenligi', 1, NULL, '2025-02-20 09:06:00'),
    (3, 'Web guvenligini artirmak icin hangi desteklere ihtiyaciniz var?', 'text', 'web_guvenligi', 0, 400, '2025-02-20 09:07:00'),
    (4, 'Supheli e-postalari tanima becerimi 1-5 arasinda puanlarim.', 'rating', 'eposta_guvenligi', 1, NULL, '2024-10-01 09:40:00'),
    (5, 'Olta saldirisi oldugunu dusundugunuz maili nasil raporlarsiniz?', 'multiple_choice', 'eposta_guvenligi', 1, NULL, '2024-10-01 09:41:00'),
    (6, 'E-posta saldirilarina karsi daha guvende hissetmek icin ne gerekir?', 'text', 'eposta_guvenligi', 0, 400, '2024-10-01 09:42:00')

ON DUPLICATE KEY UPDATE
    question_text = VALUES(question_text),
    question_type = VALUES(question_type),
    category_key = VALUES(category_key),
    is_required = VALUES(is_required),
    max_length = VALUES(max_length);

INSERT INTO question_library_options (id, library_question_id, option_text, option_value, order_index) VALUES
    (1, 2, 'Her zaman', NULL, 1),
    (2, 2, 'Cogu uygulamada', NULL, 2),
    (3, 2, 'Nadiren', NULL, 3),
    (4, 5, 'Aninda guvenlik ekibine bildiririm', NULL, 1),
    (5, 5, 'Ekibime iletir ve teyit beklerim', NULL, 2),
    (6, 5, 'Yanlislikla siler ya da yok sayarim', NULL, 3)

ON DUPLICATE KEY UPDATE
    library_question_id = VALUES(library_question_id),
    option_text = VALUES(option_text),
    option_value = VALUES(option_value),
    order_index = VALUES(order_index);

INSERT INTO survey_participants (id, survey_id, email, token, token_hash, invited_at, responded_at, created_at) VALUES
    (1, 1, 'ayse.security@acme.com', 'sec-2025-a1', 'bd78547e4e89f04e5a0653ea241828b8dcf4e95f10f0b75b744aab399f35b559', '2025-03-05 09:00:00', '2025-03-07 10:15:00', '2025-03-05 09:00:00'),
    (2, 1, 'berk.security@acme.com', 'sec-2025-b2', 'a7081ef29abbd113a8d404a706de996516db5d1ac536c0d31b332f3d5c5580a6', '2025-03-05 09:05:00', '2025-03-08 14:20:00', '2025-03-05 09:05:00'),
    (3, 1, 'cem.security@acme.com', 'sec-2025-c3', 'a3723b5facba3f7b50bb982c738ec1b4e5789085f68767f17a4a9b7b5fe9c444', '2025-03-05 09:10:00', NULL, '2025-03-05 09:10:00'),
    (4, 2, 'dilan.phish@saleshub.com', 'phish-2024-a1', '03ff7d6a0a993fcd8d14d6fd369ed4bdb0ab2df01b55e5689a2842937d1ca527', '2024-11-10 10:00:00', '2024-11-18 16:45:00', '2024-11-10 10:00:00'),
    (5, 2, 'emre.phish@saleshub.com', 'phish-2024-b2', '45e2a77bf712d347a977aaadb6238afe2d01f5a16c7faed037a6591fddbdaf74', '2024-11-10 10:05:00', '2024-11-20 09:30:00', '2024-11-10 10:05:00')

ON DUPLICATE KEY UPDATE
    survey_id = VALUES(survey_id),
    email = VALUES(email),
    token = VALUES(token),
    token_hash = VALUES(token_hash),
    invited_at = VALUES(invited_at),
    responded_at = VALUES(responded_at);

INSERT INTO survey_responses (id, survey_id, participant_id, submitted_at) VALUES
    (1, 1, 1, '2025-03-07 10:15:00'),
    (2, 1, 2, '2025-03-08 14:20:00'),
    (3, 2, 4, '2024-11-18 16:45:00'),
    (4, 2, 5, '2024-11-20 09:30:00')

ON DUPLICATE KEY UPDATE
    survey_id = VALUES(survey_id),
    participant_id = VALUES(participant_id),
    submitted_at = VALUES(submitted_at);

INSERT INTO response_answers (id, response_id, question_id, option_id, answer_text, numeric_value, created_at) VALUES
    (1, 1, 1, NULL, NULL, 4.0, '2025-03-07 10:15:00'),
    (2, 1, 2, 1, NULL, NULL, '2025-03-07 10:15:00'),
    (3, 1, 3, NULL, 'Uygulama envanteri icin rehber ve kontrol listesi istiyorum.', NULL, '2025-03-07 10:15:00'),
    (4, 2, 1, NULL, NULL, 5.0, '2025-03-08 14:20:00'),
    (5, 2, 2, 2, NULL, NULL, '2025-03-08 14:20:00'),
    (6, 2, 3, NULL, 'Egitim kayitlarinin tekrarina hizli erisim ihtiyacim var.', NULL, '2025-03-08 14:20:00'),
    (7, 3, 4, NULL, NULL, 4.0, '2024-11-18 16:45:00'),
    (8, 3, 5, 4, NULL, NULL, '2024-11-18 16:45:00'),
    (9, 3, 6, NULL, 'Simulasyon mailleri ile egitimler surdurulmeli.', NULL, '2024-11-18 16:45:00'),
    (10, 4, 4, NULL, NULL, 3.0, '2024-11-20 09:30:00'),
    (11, 4, 5, 6, NULL, NULL, '2024-11-20 09:30:00'),
    (12, 4, 6, NULL, 'Ekip icinde hizli paylasim icin prosedur guncellenmeli.', NULL, '2024-11-20 09:30:00')

ON DUPLICATE KEY UPDATE
    response_id = VALUES(response_id),
    question_id = VALUES(question_id),
    option_id = VALUES(option_id),
    answer_text = VALUES(answer_text),
    numeric_value = VALUES(numeric_value);

INSERT INTO survey_reports (id, survey_id, report_type, payload, created_at) VALUES
    (1, 1, 'smart', '{"content": "Web parolalari buyuk olcude guclu, MFA kullanimi yaygin. E-posta guvenligi icin hedefli egitimler planlanabilir."}', '2025-03-09 09:00:00'),
    (2, 1, 'trend', '{"previous": 3.8, "current": 4.4}', '2025-03-09 09:05:00'),
    (3, 2, 'summary', '{"highlights": ["Olta raporlama disiplini"], "lowlights": ["Farkindalik egitimi tekrar edilmeli"]}', '2024-12-02 08:45:00')

ON DUPLICATE KEY UPDATE
    survey_id = VALUES(survey_id),
    report_type = VALUES(report_type),
    payload = VALUES(payload);












