-- =====================================================
-- AND FINANCE APP - DDL (Data Definition Language)
-- Base de datos para gestión de finanzas personales
-- MariaDB 11.8.3
-- =====================================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS and_finance_app 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE and_finance_app;

-- =====================================================
-- TABLA: usuarios
-- Almacena información de usuarios del sistema
-- =====================================================
CREATE OR REPLACE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NULL COMMENT 'NULL si usa Google Auth',
    google_id VARCHAR(100) NULL UNIQUE COMMENT 'ID de Google para OAuth',
    avatar VARCHAR(255) NULL COMMENT 'URL o ruta del avatar',
    rol VARCHAR(50) NOT NULL DEFAULT 'usuario' COMMENT 'Valores: admin, usuario',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=inactivo, 1=activo',
    onboarding_completado INT NOT NULL DEFAULT 0 COMMENT '0=no, 1=si',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: bancos
-- Catálogo de bancos disponibles (gestionado por admin)
-- =====================================================
CREATE OR REPLACE TABLE bancos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NULL COMMENT 'Código bancario si aplica',
    logo VARCHAR(255) NULL COMMENT 'Ruta al logo del banco',
    color_primario VARCHAR(7) NULL COMMENT 'Color hex del banco',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=inactivo, 1=activo',
    orden INT DEFAULT 0 COMMENT 'Orden de aparición',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: cuentas
-- Cuentas financieras del usuario (billetera, bancos, etc)
-- =====================================================
CREATE OR REPLACE TABLE cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    banco_id INT NULL COMMENT 'Referencia a bancos.id, NULL si es efectivo',
    banco_personalizado VARCHAR(100) NULL COMMENT 'Nombre del banco si es personalizado',
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'efectivo' COMMENT 'Valores: efectivo, cuenta_ahorro, cuenta_corriente, tarjeta_credito, inversion',
    saldo_inicial DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_actual DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    moneda VARCHAR(3) NOT NULL DEFAULT 'COP' COMMENT 'Código ISO moneda',
    color VARCHAR(7) NULL COMMENT 'Color personalizado para la cuenta',
    icono VARCHAR(50) NULL COMMENT 'Clase de icono Bootstrap',
    es_predeterminada INT NOT NULL DEFAULT 0 COMMENT '0=no, 1=si',
    incluir_en_total INT NOT NULL DEFAULT 1 COMMENT '0=no incluir en balance total, 1=incluir',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=inactivo, 1=activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: categorias
-- Categorías para clasificar transacciones
-- =====================================================
CREATE OR REPLACE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'NULL=categoría del sistema, valor=categoría personalizada del usuario',
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL COMMENT 'Valores: ingreso, egreso',
    icono VARCHAR(50) NULL COMMENT 'Clase de icono Bootstrap',
    color VARCHAR(7) NULL COMMENT 'Color hex de la categoría',
    es_sistema INT NOT NULL DEFAULT 0 COMMENT '0=personalizada, 1=del sistema',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=inactivo, 1=activo',
    orden INT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: subcategorias
-- Subcategorías opcionales para mayor detalle
-- =====================================================
CREATE OR REPLACE TABLE subcategorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL COMMENT 'Referencia a categorias.id',
    usuario_id INT NULL COMMENT 'NULL=del sistema, valor=personalizada',
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) NULL,
    estado INT NOT NULL DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: transacciones
-- Registro de todos los movimientos financieros
-- =====================================================
CREATE OR REPLACE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    cuenta_id INT NOT NULL COMMENT 'Referencia a cuentas.id',
    categoria_id INT NULL COMMENT 'Referencia a categorias.id',
    subcategoria_id INT NULL COMMENT 'Referencia a subcategorias.id',
    tipo VARCHAR(20) NOT NULL COMMENT 'Valores: ingreso, egreso, transferencia, ajuste',
    monto DECIMAL(15,2) NOT NULL,
    descripcion VARCHAR(255) NULL COMMENT 'Descripción corta',
    comentario TEXT NULL COMMENT 'Notas adicionales detalladas',
    fecha_transaccion DATE NOT NULL,
    hora_transaccion TIME NULL,
    -- Campos para transferencias
    cuenta_destino_id INT NULL COMMENT 'Referencia a cuentas.id para transferencias',
    transferencia_id INT NULL COMMENT 'ID de la transacción relacionada en transferencias',
    -- Campos de control
    es_recurrente INT NOT NULL DEFAULT 0 COMMENT '0=no, 1=si',
    gasto_recurrente_id INT NULL COMMENT 'Referencia a gastos_recurrentes.id si aplica',
    realizada INT NOT NULL DEFAULT 1 COMMENT '0=programada/pendiente (no afecta saldo), 1=realizada (afecta saldo)',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=anulada, 1=activa',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: transaccion_archivos
-- Archivos adjuntos a transacciones (comprobantes)
-- =====================================================
CREATE OR REPLACE TABLE transaccion_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaccion_id INT NOT NULL COMMENT 'Referencia a transacciones.id',
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL COMMENT 'Nombre en el servidor',
    ruta VARCHAR(500) NOT NULL,
    tipo_archivo VARCHAR(50) NOT NULL COMMENT 'Valores: imagen, pdf',
    mime_type VARCHAR(100) NOT NULL,
    tamano INT NOT NULL COMMENT 'Tamaño en bytes',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: gastos_recurrentes
-- Programación de gastos automáticos
-- =====================================================
CREATE OR REPLACE TABLE gastos_recurrentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    cuenta_id INT NOT NULL COMMENT 'Referencia a cuentas.id',
    categoria_id INT NOT NULL COMMENT 'Referencia a categorias.id',
    subcategoria_id INT NULL,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre identificador del gasto',
    monto DECIMAL(15,2) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'egreso' COMMENT 'Valores: ingreso, egreso',
    frecuencia VARCHAR(20) NOT NULL COMMENT 'Valores: diario, semanal, quincenal, mensual, anual',
    dia_ejecucion INT NULL COMMENT 'Día del mes (1-31) o día de la semana (1-7)',
    dias_ejecucion VARCHAR(100) NULL COMMENT 'Múltiples días separados por coma. Ej: 5,15,25',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL COMMENT 'NULL=indefinido',
    ultima_ejecucion DATE NULL,
    proxima_ejecucion DATE NULL,
    notificar INT NOT NULL DEFAULT 1 COMMENT '0=no notificar, 1=notificar',
    dias_anticipacion INT DEFAULT 1 COMMENT 'Días antes para notificar',
    auto_registrar INT NOT NULL DEFAULT 0 COMMENT '0=solo notificar, 1=registrar automáticamente',
    estado INT NOT NULL DEFAULT 1 COMMENT '0=pausado, 1=activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: presupuestos
-- Presupuestos mensuales por categoría
-- =====================================================
CREATE OR REPLACE TABLE presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    categoria_id INT NOT NULL COMMENT 'Referencia a categorias.id',
    monto_limite DECIMAL(15,2) NOT NULL,
    mes INT NOT NULL COMMENT '1-12',
    anio INT NOT NULL,
    alertar_al INT DEFAULT 80 COMMENT 'Porcentaje para alertar',
    estado INT NOT NULL DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_presupuesto (usuario_id, categoria_id, mes, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: etiquetas
-- Etiquetas personalizadas para transacciones
-- =====================================================
CREATE OR REPLACE TABLE etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    nombre VARCHAR(50) NOT NULL,
    color VARCHAR(7) NULL,
    estado INT NOT NULL DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: transaccion_etiquetas
-- Relación muchos a muchos entre transacciones y etiquetas
-- =====================================================
CREATE OR REPLACE TABLE transaccion_etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaccion_id INT NOT NULL COMMENT 'Referencia a transacciones.id',
    etiqueta_id INT NOT NULL COMMENT 'Referencia a etiquetas.id',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_trans_etiq (transaccion_id, etiqueta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: notificaciones
-- Notificaciones del sistema para el usuario
-- =====================================================
CREATE OR REPLACE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    tipo VARCHAR(50) NOT NULL COMMENT 'Valores: gasto_recurrente, presupuesto_alerta, recordatorio',
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    url_accion VARCHAR(255) NULL,
    leida INT NOT NULL DEFAULT 0 COMMENT '0=no leída, 1=leída',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: configuracion_usuario
-- Preferencias del usuario
-- =====================================================
CREATE OR REPLACE TABLE configuracion_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE COMMENT 'Referencia a usuarios.id',
    moneda_principal VARCHAR(3) DEFAULT 'COP',
    tema VARCHAR(20) DEFAULT 'light' COMMENT 'Valores: light, dark, auto',
    idioma VARCHAR(5) DEFAULT 'es',
    formato_fecha VARCHAR(20) DEFAULT 'd/m/Y',
    primer_dia_semana INT DEFAULT 1 COMMENT '0=domingo, 1=lunes',
    notificaciones_email INT DEFAULT 1,
    notificaciones_push INT DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: sesiones
-- Control de sesiones activas
-- =====================================================
CREATE OR REPLACE TABLE sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Referencia a usuarios.id',
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    estado INT NOT NULL DEFAULT 1 COMMENT '0=expirada, 1=activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: verificacion_codigos
-- Códigos de verificación para email y recuperación
-- =====================================================
CREATE OR REPLACE TABLE verificacion_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    tipo VARCHAR(30) NOT NULL COMMENT 'Valores: registro, recuperacion_password',
    datos_temporales TEXT NULL COMMENT 'Datos JSON del usuario durante registro',
    intentos INT NOT NULL DEFAULT 0 COMMENT 'Intentos de verificación fallidos',
    usado INT NOT NULL DEFAULT 0 COMMENT '0=no usado, 1=usado',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    INDEX idx_email_tipo (email, tipo),
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- =====================================================
CREATE INDEX idx_transacciones_usuario ON transacciones(usuario_id);
CREATE INDEX idx_transacciones_fecha ON transacciones(fecha_transaccion);
CREATE INDEX idx_transacciones_cuenta ON transacciones(cuenta_id);
CREATE INDEX idx_transacciones_categoria ON transacciones(categoria_id);
CREATE INDEX idx_transacciones_tipo ON transacciones(tipo);
CREATE INDEX idx_cuentas_usuario ON cuentas(usuario_id);
CREATE INDEX idx_categorias_usuario ON categorias(usuario_id);
CREATE INDEX idx_categorias_tipo ON categorias(tipo);
CREATE INDEX idx_gastos_recurrentes_usuario ON gastos_recurrentes(usuario_id);
CREATE INDEX idx_gastos_recurrentes_proxima ON gastos_recurrentes(proxima_ejecucion);
CREATE INDEX idx_notificaciones_usuario ON notificaciones(usuario_id);
CREATE INDEX idx_sesiones_token ON sesiones(token);

-- =====================================================
-- DATOS INICIALES - BANCOS COLOMBIANOS
-- =====================================================
INSERT INTO bancos (nombre, codigo, color_primario, estado, orden) VALUES
('Bancolombia', 'BCOL', '#FDDA24', 1, 1),
('Banco de Bogotá', 'BBOG', '#003B7A', 1, 2),
('Davivienda', 'DAVI', '#ED1C24', 1, 3),
('BBVA Colombia', 'BBVA', '#004481', 1, 4),
('Banco de Occidente', 'BOCC', '#0066B3', 1, 5),
('Banco Popular', 'BPOP', '#00529B', 1, 6),
('Banco AV Villas', 'AVVI', '#00A651', 1, 7),
('Banco Caja Social', 'BCSC', '#E31837', 1, 8),
('Scotiabank Colpatria', 'SCOT', '#EC111A', 1, 9),
('Banco Agrario', 'BAGR', '#006633', 1, 10),
('Banco Itaú', 'ITAU', '#EC7000', 1, 11),
('Banco Pichincha', 'PICH', '#FFD100', 1, 12),
('Banco Falabella', 'FALA', '#B5D334', 1, 13),
('Banco Finandina', 'FINA', '#003366', 1, 14),
('Bancoomeva', 'COOM', '#003D7C', 1, 15),
('Nequi', 'NEQU', '#4D1A7F', 1, 16),
('Daviplata', 'DVPL', '#ED1C24', 1, 17),
('Lulo Bank', 'LULO', '#6B4EFF', 1, 18),
('Nu Colombia', 'NUCO', '#820AD1', 1, 19),
('RappiPay', 'RAPP', '#FF441F', 1, 20);

-- =====================================================
-- DATOS INICIALES - CATEGORÍAS DEL SISTEMA
-- =====================================================
-- Categorías de Egreso
INSERT INTO categorias (usuario_id, nombre, tipo, icono, color, es_sistema, orden) VALUES
(NULL, 'Alimentación', 'egreso', 'bi-basket', '#FF6B6B', 1, 1),
(NULL, 'Transporte', 'egreso', 'bi-car-front', '#4ECDC4', 1, 2),
(NULL, 'Vivienda', 'egreso', 'bi-house', '#45B7D1', 1, 3),
(NULL, 'Servicios Públicos', 'egreso', 'bi-lightning', '#96CEB4', 1, 4),
(NULL, 'Salud', 'egreso', 'bi-heart-pulse', '#FF8B94', 1, 5),
(NULL, 'Educación', 'egreso', 'bi-book', '#DDA0DD', 1, 6),
(NULL, 'Entretenimiento', 'egreso', 'bi-controller', '#F7DC6F', 1, 7),
(NULL, 'Ropa y Accesorios', 'egreso', 'bi-bag', '#BB8FCE', 1, 8),
(NULL, 'Cuidado Personal', 'egreso', 'bi-person-hearts', '#85C1E9', 1, 9),
(NULL, 'Mascotas', 'egreso', 'bi-emoji-heart-eyes', '#F8B500', 1, 10),
(NULL, 'Regalos', 'egreso', 'bi-gift', '#E74C3C', 1, 11),
(NULL, 'Tecnología', 'egreso', 'bi-laptop', '#5D6D7E', 1, 12),
(NULL, 'Seguros', 'egreso', 'bi-shield-check', '#1ABC9C', 1, 13),
(NULL, 'Deudas', 'egreso', 'bi-credit-card', '#E67E22', 1, 14),
(NULL, 'Impuestos', 'egreso', 'bi-receipt', '#95A5A6', 1, 15),
(NULL, 'Otros Gastos', 'egreso', 'bi-three-dots', '#7F8C8D', 1, 16);

-- Categorías de Ingreso
INSERT INTO categorias (usuario_id, nombre, tipo, icono, color, es_sistema, orden) VALUES
(NULL, 'Salario', 'ingreso', 'bi-wallet2', '#27AE60', 1, 1),
(NULL, 'Freelance', 'ingreso', 'bi-laptop', '#3498DB', 1, 2),
(NULL, 'Inversiones', 'ingreso', 'bi-graph-up-arrow', '#9B59B6', 1, 3),
(NULL, 'Arriendos', 'ingreso', 'bi-building', '#E74C3C', 1, 4),
(NULL, 'Ventas', 'ingreso', 'bi-shop', '#F39C12', 1, 5),
(NULL, 'Préstamos Recibidos', 'ingreso', 'bi-cash-coin', '#1ABC9C', 1, 6),
(NULL, 'Reembolsos', 'ingreso', 'bi-arrow-return-left', '#34495E', 1, 7),
(NULL, 'Bonificaciones', 'ingreso', 'bi-trophy', '#E91E63', 1, 8),
(NULL, 'Otros Ingresos', 'ingreso', 'bi-plus-circle', '#95A5A6', 1, 9);

-- =====================================================
-- SUBCATEGORÍAS PREDEFINIDAS
-- =====================================================
-- Subcategorías de Alimentación (ID 1)
INSERT INTO subcategorias (categoria_id, usuario_id, nombre, icono) VALUES
(1, NULL, 'Supermercado', 'bi-cart'),
(1, NULL, 'Restaurantes', 'bi-cup-straw'),
(1, NULL, 'Delivery', 'bi-bicycle'),
(1, NULL, 'Café y Snacks', 'bi-cup-hot');

-- Subcategorías de Transporte (ID 2)
INSERT INTO subcategorias (categoria_id, usuario_id, nombre, icono) VALUES
(2, NULL, 'Gasolina', 'bi-fuel-pump'),
(2, NULL, 'Transporte Público', 'bi-bus-front'),
(2, NULL, 'Taxi/Uber', 'bi-taxi-front'),
(2, NULL, 'Mantenimiento Vehículo', 'bi-tools'),
(2, NULL, 'Parqueadero', 'bi-p-circle');

-- Subcategorías de Vivienda (ID 3)
INSERT INTO subcategorias (categoria_id, usuario_id, nombre, icono) VALUES
(3, NULL, 'Arriendo/Hipoteca', 'bi-key'),
(3, NULL, 'Administración', 'bi-building'),
(3, NULL, 'Reparaciones', 'bi-wrench'),
(3, NULL, 'Muebles y Decoración', 'bi-lamp');

-- Subcategorías de Servicios Públicos (ID 4)
INSERT INTO subcategorias (categoria_id, usuario_id, nombre, icono) VALUES
(4, NULL, 'Energía', 'bi-lightbulb'),
(4, NULL, 'Agua', 'bi-droplet'),
(4, NULL, 'Gas', 'bi-fire'),
(4, NULL, 'Internet', 'bi-wifi'),
(4, NULL, 'Telefonía', 'bi-phone');

-- Subcategorías de Entretenimiento (ID 7)
INSERT INTO subcategorias (categoria_id, usuario_id, nombre, icono) VALUES
(7, NULL, 'Streaming', 'bi-play-circle'),
(7, NULL, 'Cine', 'bi-film'),
(7, NULL, 'Videojuegos', 'bi-controller'),
(7, NULL, 'Deportes', 'bi-trophy'),
(7, NULL, 'Viajes', 'bi-airplane');

-- =====================================================
-- USUARIO ADMINISTRADOR INICIAL
-- =====================================================
-- Password: Admin123! (hash bcrypt generado con password_hash)
INSERT INTO usuarios (nombre, email, password, rol, estado, onboarding_completado) VALUES
('Administrador', 'admin@andfinance.com', '$2y$10$w4Iepm6Nn8Bhl8y0fSYRB.3NzJY7hAqg3sMPOajipFVX1YMlbZEea', 'admin', 1, 1);

