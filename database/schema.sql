-- Create database
CREATE DATABASE IF NOT EXISTS appliances_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE appliances_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default admin (password: admin123)
INSERT INTO users (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

-- Global SEO settings
CREATE TABLE seo_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_name_ru VARCHAR(200) NOT NULL,
    site_name_uz VARCHAR(200) NOT NULL,
    meta_keywords_ru TEXT,
    meta_keywords_uz TEXT,
    meta_description_ru TEXT,
    meta_description_uz TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    address_ru TEXT,
    address_uz TEXT,
    working_hours_ru VARCHAR(200),
    working_hours_uz VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default SEO settings
INSERT INTO seo_settings (
    site_name_ru, site_name_uz,
    meta_keywords_ru, meta_keywords_uz,
    meta_description_ru, meta_description_uz,
    phone, email,
    address_ru, address_uz,
    working_hours_ru, working_hours_uz
) VALUES (
    'Скупка Бытовой Техники',
    'Texnikani sotib olish',
    'скупка техники, бытовая техника, выкуп техники',
    'texnika sotib olish, maishiy texnika',
    'Скупка бытовой техники в Ташкенте. Выгодные цены.',
    'Toshkentda maishiy texnikani sotib olish. Qulay narxlar.',
    '+998901234567',
    'info@appliances.uz',
    'г. Ташкент, ул. Примерная, 1',
    'Toshkent sh., Misol ko\'chasi, 1',
    'Пн-Вс: 9:00-20:00',
    'Du-Ya: 9:00-20:00'
);

-- Pages table
CREATE TABLE pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    title_ru VARCHAR(200) NOT NULL,
    title_uz VARCHAR(200) NOT NULL,
    content_ru LONGTEXT,
    content_uz LONGTEXT,
    meta_title_ru VARCHAR(200),
    meta_title_uz VARCHAR(200),
    meta_keywords_ru TEXT,
    meta_keywords_uz TEXT,
    meta_description_ru TEXT,
    meta_description_uz TEXT,
    jsonld_ru TEXT,
    jsonld_uz TEXT,
    is_published BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_published (is_published)
) ENGINE=InnoDB;

-- Insert sample pages
INSERT INTO pages (slug, title_ru, title_uz, content_ru, content_uz, meta_title_ru, meta_title_uz, meta_description_ru, meta_description_uz) VALUES
('home', 'Главная', 'Bosh sahifa', '<h1>Скупка бытовой техники</h1><p>Мы покупаем бытовую технику по выгодным ценам.</p>', '<h1>Maishiy texnikani sotib olish</h1><p>Biz maishiy texnikani qulay narxlarda sotib olamiz.</p>', 'Скупка Техники - Выгодные Цены', 'Texnika Sotib Olish - Qulay Narxlar', 'Скупка бытовой техники в Ташкенте', 'Toshkentda maishiy texnikani sotib olish'),
('about', 'О нас', 'Biz haqimizda', '<h1>О компании</h1><p>Мы работаем с 2020 года.</p>', '<h1>Kompaniya haqida</h1><p>Biz 2020 yildan beri ishlaymiz.</p>', NULL, NULL, NULL, NULL),
('contact', 'Контакты', 'Kontaktlar', '<h1>Свяжитесь с нами</h1><p>Телефон: {{global.phone}}</p>', '<h1>Biz bilan bog\'laning</h1><p>Telefon: {{global.phone}}</p>', NULL, NULL, NULL, NULL);

-- Media table
CREATE TABLE media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filename (filename)
) ENGINE=InnoDB;

-- Analytics placeholder
CREATE TABLE analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_slug VARCHAR(100),
    language VARCHAR(5),
    visits INT DEFAULT 0,
    date DATE NOT NULL,
    UNIQUE KEY unique_daily (page_slug, language, date)
) ENGINE=InnoDB;