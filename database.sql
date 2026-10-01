-- =========================================================
-- Database: ujian_asts
-- Import file ini lewat phpMyAdmin (tab "Import") atau lewat
-- terminal: mysql -u root -p < database.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS ujian_asts;
USE ujian_asts;

DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    nisn VARCHAR(20) NOT NULL,
    ttl VARCHAR(100) NOT NULL,      -- Tempat Tanggal Lahir
    gender VARCHAR(10) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL
);

-- Data contoh (dimodifikasi dari jsonplaceholder.typicode.com/users
-- supaya sesuai kolom tugas: nisn, ttl, gender, address jadi satu string)
INSERT INTO users (name, nisn, ttl, gender, email, address) VALUES
('Leanne Graham',        '0031234561', 'Ponorogo, 12 Agustus 2010', 'Perempuan', 'Sincere@april.biz',        'Kulas Light, Apt. 556, Gwenborough, 92998-3874'),
('Ervin Howell',         '0031234562', 'Ponorogo, 15 Januari 2010', 'Laki-laki', 'Shanna@melissa.tv',        'Victor Plains, Suite 879, Wisokyburgh, 90566-7771'),
('Clementine Bauch',     '0031234563', 'Madiun, 03 Maret 2010',     'Perempuan', 'Nathan@yesenia.net',       'Douglas Extension, Suite 847, McKenziehaven, 59590-4157'),
('Patricia Lebsack',     '0031234564', 'Madiun, 21 Mei 2010',       'Perempuan', 'Julianne.OConner@kory.org','Hoeger Mall, Apt. 692, South Elvis, 53919-4257'),
('Chelsey Dietrich',     '0031234565', 'Pacitan, 09 Juni 2010',     'Perempuan', 'Lucio_Hettinger@annie.ca', 'Skiles Walks, Suite 351, Roscoeview, 33263'),
('Dennis Schulist',      '0031234566', 'Pacitan, 30 Juli 2010',     'Laki-laki', 'Karley_Dach@jasper.info',  'Norberto Crossing, Apt. 950, South Christy, 23505-1337'),
('Kurtis Weissnat',      '0031234567', 'Trenggalek, 17 Agustus 2010','Laki-laki','Telly.Hoeger@billy.biz',   'Rex Trail, Suite 280, Howemouth, 58804-1099'),
('Nicholas Runolfsdottir','0031234568','Trenggalek, 25 September 2010','Laki-laki','Sherwood_Runolfsdottir@rosamond.me','Ellsworth Summit, Suite 729, Aliyaview, 45169'),
('Glenna Reichert',      '0031234569', 'Ngawi, 02 Oktober 2010',    'Perempuan', 'Chaim_McDermott@dana.io',  'Dayna Park, Suite 449, Bartholomebury, 76495-3109'),
('Clementina DuBuque',   '0031234570', 'Ngawi, 11 November 2010',  'Perempuan', 'Rey.Padberg@karina.biz',   'Kattie Turnpike, Suite 198, Lebsackbury, 31428-2261');
