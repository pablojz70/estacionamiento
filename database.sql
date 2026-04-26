-- =====================================================
-- CREACIÓN DE BASE DE DATOS Y TABLAS
-- Sistema de Gestión de Estacionamiento
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS parking_db;
USE parking_db;

-- Tabla de configuración de tarifas
CREATE TABLE IF NOT EXISTS tarifas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo ENUM('hora', 'fraccion', 'dia') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    duracion_minutos INT DEFAULT 60, -- Para fracciones: 15, 30, 60
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de vehículos registrados
CREATE TABLE IF NOT EXISTS vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(20) NOT NULL UNIQUE,
    fecha_entrada DATETIME NOT NULL,
    fecha_salida DATETIME NULL,
    monto_pagado DECIMAL(10,2) DEFAULT 0,
    tarifa_aplicada VARCHAR(50) NULL,
    status ENUM('dentro', 'pagado', 'cancelado') DEFAULT 'dentro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de configuraciones generales
CREATE TABLE IF NOT EXISTS configuraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT NOT NULL
);

-- Insertar tarifas por defecto
INSERT INTO tarifas (nombre, tipo, monto, duracion_minutos) VALUES 
('Tarifa por Hora', 'hora', 5.00, 60),
('Tarifa por Fracción (30 min)', 'fraccion', 2.50, 30),
('Tarifa por Día', 'dia', 50.00, 1440);

-- Insertar configuración inicial
INSERT INTO configuraciones (clave, valor) VALUES 
('nombre_estacionamiento', 'Estacionamiento Central'),
('moneda', 'BS');