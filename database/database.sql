-- Database Creation
-- Note: Manually create database 'trabajo_final_php' if it doesn't exist. Shared hosting often creates it for you.
-- The database is auto-created by MySQL Docker image from MYSQL_DATABASE env var
CREATE DATABASE IF NOT EXISTS trabajo_final_php;
USE trabajo_final_php;

-- Table: users_data
CREATE TABLE IF NOT EXISTS users_data (
    idUser INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20) NOT NULL,
    fecha_de_nacimiento DATE NOT NULL,
    direccion TEXT,
    calle VARCHAR(255),
    codigo_postal VARCHAR(10),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    sexo ENUM('Masculino', 'Femenino', 'Otro') NOT NULL
);

-- Table: users_login
CREATE TABLE IF NOT EXISTS users_login (
    idLogin INT AUTO_INCREMENT PRIMARY KEY,
    idUser INT NOT NULL UNIQUE,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    FOREIGN KEY (idUser) REFERENCES users_data(idUser) ON DELETE CASCADE
);

-- Table: citas
CREATE TABLE IF NOT EXISTS citas (
    idCita INT AUTO_INCREMENT PRIMARY KEY,
    idUser INT NULL,
    fecha_cita DATE NOT NULL,
    hora_cita TIME NOT NULL,
    motivo_cita TEXT,
    guest_name VARCHAR(100),
    guest_email VARCHAR(150),
    guest_phone VARCHAR(20),
    FOREIGN KEY (idUser) REFERENCES users_data(idUser) ON DELETE CASCADE
);

-- Table: noticias
CREATE TABLE IF NOT EXISTS noticias (
    idNoticia INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL UNIQUE,
    imagen VARCHAR(255) NOT NULL,
    texto TEXT NOT NULL,
    fecha DATE NOT NULL,
    enlace VARCHAR(255),
    idUser INT NOT NULL,
    FOREIGN KEY (idUser) REFERENCES users_data(idUser) ON DELETE CASCADE
);

-- Table: consejos
CREATE TABLE IF NOT EXISTS consejos (
    idConsejo INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    imagen VARCHAR(255),
    texto TEXT NOT NULL,
    fecha DATE NOT NULL,
    idUser INT NOT NULL,
    FOREIGN KEY (idUser) REFERENCES users_data(idUser) ON DELETE CASCADE
);

-- Performance Indexes
-- Indexes for frequently queried columns to improve query performance

-- Indexes for citas table
CREATE INDEX idx_citas_fecha ON citas(fecha_cita);
CREATE INDEX idx_citas_fecha_hora ON citas(fecha_cita, hora_cita);
CREATE INDEX idx_citas_iduser ON citas(idUser);

-- Indexes for noticias table
CREATE INDEX idx_noticias_fecha ON noticias(fecha);
CREATE INDEX idx_noticias_iduser ON noticias(idUser);

-- Indexes for consejos table
CREATE INDEX idx_consejos_fecha ON consejos(fecha);
CREATE INDEX idx_consejos_iduser ON consejos(idUser);

-- Indexes for users_data table (email already has UNIQUE index)
CREATE INDEX idx_users_data_nombre ON users_data(nombre);
CREATE INDEX idx_users_data_apellidos ON users_data(apellidos);

-- Indexes for users_login table (usuario already has UNIQUE index)
CREATE INDEX idx_users_login_rol ON users_login(rol);

-- Default admin user (for local/dev installs)
-- Usuario: admin
-- Contraseña: admin123
-- Nota: si ya existe, estos INSERT no hacen nada (INSERT IGNORE).
SET @admin_email = 'admin@local.test';
SET @admin_username = 'admin';
SET @admin_password_hash = '$2y$12$CrCgC52lwU1YznK5XIWIwOqpuVmH0/EVUWYoq6DLV8cYbVF6Uw/HC';

INSERT IGNORE INTO users_data (nombre, apellidos, email, telefono, fecha_de_nacimiento, direccion, calle, codigo_postal, ciudad, provincia, sexo)
VALUES ('Admin', 'Local', @admin_email, '000000000', '1990-01-01', NULL, NULL, NULL, NULL, NULL, 'Otro');

SET @admin_id_user = (SELECT idUser FROM users_data WHERE email = @admin_email LIMIT 1);

INSERT IGNORE INTO users_login (idUser, usuario, password, rol)
VALUES (@admin_id_user, @admin_username, @admin_password_hash, 'admin');
