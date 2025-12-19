-- ============================================
-- AND FINANCE APP - Base de Datos
-- MariaDB 11.8.3-log
-- ============================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS and_finance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE and_finance_db;

-- ============================================
-- TABLA: control_usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS control_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL, -- NULL si se registra solo con Google
    nombre_completo VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) NULL UNIQUE, -- ID de Google OAuth
    avatar_url VARCHAR(500) NULL, -- URL del avatar de Google
    rol VARCHAR(50) NOT NULL DEFAULT 'usuario', -- usuario, admin
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: bancos_bancos
-- ============================================
CREATE TABLE IF NOT EXISTS bancos_bancos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    logo_url VARCHAR(500) NULL, -- Ruta al logo del banco
    codigo VARCHAR(50) NULL, -- Código del banco (ej: BANCOLOMBIA, DAVIVIENDA)
    pais VARCHAR(100) NOT NULL DEFAULT 'Colombia',
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_estado (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: cuentas_cuentas
-- ============================================
CREATE TABLE IF NOT EXISTS cuentas_cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL, -- Referencia a control_usuarios.id (sin FK)
    nombre VARCHAR(255) NOT NULL,
    banco_id INT NULL, -- Referencia a bancos_bancos.id (sin FK), NULL para efectivo
    saldo_inicial DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    saldo_actual DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    tipo VARCHAR(50) NOT NULL DEFAULT 'bancaria', -- bancaria, efectivo, inversion
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_banco (banco_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: categorias_categorias
-- ============================================
CREATE TABLE IF NOT EXISTS categorias_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL, -- NULL para categorías predeterminadas del sistema
    nombre VARCHAR(255) NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- ingreso, egreso
    icono VARCHAR(100) NULL, -- Nombre del icono (ej: fa-home, fa-car)
    color VARCHAR(7) NULL, -- Color hex para UI (ej: #39843A)
    es_predeterminada BOOLEAN NOT NULL DEFAULT FALSE,
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_tipo (tipo),
    INDEX idx_predeterminada (es_predeterminada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: transacciones_transacciones
-- ============================================
CREATE TABLE IF NOT EXISTS transacciones_transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL, -- Referencia a control_usuarios.id (sin FK)
    cuenta_id INT NOT NULL, -- Referencia a cuentas_cuentas.id (sin FK)
    categoria_id INT NULL, -- Referencia a categorias_categorias.id (sin FK). NULL para transferencias
    tipo VARCHAR(50) NOT NULL, -- ingreso, egreso, transferencia
    monto DECIMAL(15, 2) NOT NULL,
    fecha DATE NOT NULL,
    comentario TEXT NULL,
    cuenta_destino_id INT NULL, -- Para transferencias: cuenta destino (sin FK)
    es_programada BOOLEAN NOT NULL DEFAULT FALSE, -- TRUE si es transacción programada (fecha futura). Las programadas no afectan el saldo actual hasta su fecha de ejecución. Se usan para proyecciones de saldo.
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_cuenta (cuenta_id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_tipo (tipo),
    INDEX idx_fecha (fecha),
    INDEX idx_cuenta_destino (cuenta_destino_id),
    INDEX idx_es_programada (es_programada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: transacciones_archivos
-- ============================================
CREATE TABLE IF NOT EXISTS transacciones_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaccion_id INT NOT NULL, -- Referencia a transacciones_transacciones.id (sin FK)
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL, -- Nombre único en el servidor
    ruta VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamano INT NOT NULL, -- Tamaño en bytes
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaccion (transaccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: gastos_recurrentes_gastos
-- ============================================
CREATE TABLE IF NOT EXISTS gastos_recurrentes_gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL, -- Referencia a control_usuarios.id (sin FK)
    cuenta_id INT NOT NULL, -- Referencia a cuentas_cuentas.id (sin FK)
    categoria_id INT NOT NULL, -- Referencia a categorias_categorias.id (sin FK)
    nombre VARCHAR(255) NOT NULL,
    monto DECIMAL(15, 2) NOT NULL,
    dia_mes INT NOT NULL, -- Día del mes (1-31) en que se ejecuta
    tipo VARCHAR(50) NOT NULL DEFAULT 'mensual', -- mensual, quincenal, semanal, bimestral, trimestral, semestral, anual
    estado_activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_cuenta (cuenta_id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_dia_mes (dia_mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: gastos_recurrentes_ejecuciones
-- ============================================
CREATE TABLE IF NOT EXISTS gastos_recurrentes_ejecuciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gasto_recurrente_id INT NOT NULL, -- Referencia a gastos_recurrentes_gastos.id (sin FK)
    transaccion_id INT NULL, -- Referencia a transacciones_transacciones.id (sin FK) si se ejecutó
    mes INT NOT NULL, -- Mes (1-12)
    anio INT NOT NULL, -- Año (ej: 2024)
    ejecutado BOOLEAN NOT NULL DEFAULT FALSE,
    ignorado BOOLEAN NOT NULL DEFAULT FALSE, -- TRUE si el usuario decidió ignorar este gasto para este mes
    fecha_ejecucion TIMESTAMP NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gasto_recurrente (gasto_recurrente_id),
    INDEX idx_mes_anio (mes, anio),
    INDEX idx_transaccion (transaccion_id),
    INDEX idx_ignorado (ignorado),
    UNIQUE KEY unique_gasto_mes_anio (gasto_recurrente_id, mes, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTS INICIALES
-- ============================================

-- Insertar categorías predeterminadas del sistema
INSERT INTO categorias_categorias (nombre, tipo, icono, color, es_predeterminada, usuario_id) VALUES
-- Ingresos
('Salario', 'ingreso', 'fa-money-bill-wave', '#39843A', TRUE, NULL),
('Inversiones', 'ingreso', 'fa-chart-line', '#39843A', TRUE, NULL),
('Bonos', 'ingreso', 'fa-gift', '#39843A', TRUE, NULL),
('Otros Ingresos', 'ingreso', 'fa-wallet', '#39843A', TRUE, NULL),
-- Egresos
('Hogar', 'egreso', 'fa-home', '#F1B10B', TRUE, NULL),
('Comida', 'egreso', 'fa-utensils', '#F1B10B', TRUE, NULL),
('Transporte', 'egreso', 'fa-car', '#F1B10B', TRUE, NULL),
('Salud', 'egreso', 'fa-heartbeat', '#F1B10B', TRUE, NULL),
('Educación', 'egreso', 'fa-graduation-cap', '#F1B10B', TRUE, NULL),
('Entretenimiento', 'egreso', 'fa-film', '#F1B10B', TRUE, NULL),
('Ropa', 'egreso', 'fa-tshirt', '#F1B10B', TRUE, NULL),
('Servicios', 'egreso', 'fa-bolt', '#F1B10B', TRUE, NULL),
('Otros Gastos', 'egreso', 'fa-ellipsis-h', '#F1B10B', TRUE, NULL);

-- Insertar algunos bancos colombianos comunes
INSERT INTO bancos_bancos (nombre, codigo, pais) VALUES
('Bancolombia', 'BANCOLOMBIA', 'Colombia'),
('Banco de Bogotá', 'BOGOTA', 'Colombia'),
('Davivienda', 'DAVIVIENDA', 'Colombia'),
('Banco Popular', 'POPULAR', 'Colombia'),
('Banco AV Villas', 'AVVILLAS', 'Colombia'),
('Banco Caja Social', 'CAJASOCIAL', 'Colombia'),
('Banco Agrario', 'AGRARIO', 'Colombia'),
('Banco de Occidente', 'OCCIDENTE', 'Colombia'),
('Banco Falabella', 'FALABELLA', 'Colombia'),
('Nequi', 'NEQUI', 'Colombia'),
('Daviplata', 'DAVIPLATA', 'Colombia');

-- ============================================
-- USUARIO ADMINISTRADOR POR DEFECTO
-- ============================================
-- Email: admin@andfinance.com
-- Password: admin123
-- IMPORTANTE: Cambiar la contraseña después del primer login
INSERT IGNORE INTO control_usuarios (email, password, nombre_completo, rol, estado_activo)
VALUES ('admin@andfinance.com', '$2y$10$BPGSMwk9u8YeZI0U2gBJE.X7XqmESvbPBiYMCbGqjhNfsVLLGlPtK', 'Administrador', 'admin', TRUE);
