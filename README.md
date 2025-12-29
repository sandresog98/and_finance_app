# AndFinance App 💰

Aplicación web de gestión de finanzas personales desarrollada en PHP con arquitectura MVC. Sistema completo con autenticación, verificación por email, tema oscuro y diseño responsive.

---

## 📋 Índice

- [Stack Tecnológico](#-stack-tecnológico)
- [Arquitectura](#-arquitectura)
- [Características](#-características-principales)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Base de Datos](#-base-de-datos)
- [Sistema de Temas](#-sistema-de-temas-darklight)
- [API Endpoints](#-api-endpoints)
- [Sistema de Archivos](#-sistema-de-archivos)
- [Seguridad](#-seguridad)
- [Estructura del Proyecto](#-estructura-del-proyecto)

---

## 🛠 Stack Tecnológico

### Backend
| Tecnología | Versión | Uso |
|------------|---------|-----|
| PHP | 8.2.28 | Lenguaje principal |
| MariaDB | 11.8.3 | Base de datos |
| PDO | - | Conexión a BD (prepared statements) |
| PHPMailer | 6.9 | Envío de emails SMTP |

### Frontend
| Tecnología | Versión | CDN |
|------------|---------|-----|
| Bootstrap | 5.3.2 | `cdn.jsdelivr.net/npm/bootstrap@5.3.2` |
| Bootstrap Icons | 1.11.1 | `cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1` |
| Chart.js | 4.x | `cdn.jsdelivr.net/npm/chart.js` |
| Google Fonts | - | Poppins (300-800) |

### Extensiones PHP Requeridas
```
php-pdo
php-pdo_mysql
php-mbstring
php-json
php-openssl (para SMTP SSL)
php-fileinfo (para validación de archivos)
```

### Servidor
| Componente | Requisito |
|------------|-----------|
| Web Server | Apache 2.4+ con mod_rewrite |
| PHP Handler | libphp / php-fpm |
| SSL | Recomendado para producción |

---

## 🏗 Arquitectura

### Patrón de Diseño: MVC (Model-View-Controller)

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENTE                               │
│  (Browser: HTML5 + CSS3 + JavaScript + Bootstrap 5)         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     APACHE + PHP 8.2                         │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │   ADMIN     │  │     UI      │  │   ASSETS    │         │
│  │  /admin/    │  │    /ui/     │  │  /assets/   │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
│         │                │                                   │
│         ▼                ▼                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              CONTROLLERS (AuthController)            │   │
│  │         - Login / Logout / Register                  │   │
│  │         - Verificación Email                         │   │
│  │         - Recuperación Contraseña                    │   │
│  └─────────────────────────────────────────────────────┘   │
│         │                                                    │
│         ▼                                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    MODELS                            │   │
│  │  UserModel | CuentaModel | TransaccionModel | ...   │   │
│  └─────────────────────────────────────────────────────┘   │
│         │                                                    │
│         ▼                                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              DATABASE (config/database.php)          │   │
│  │                  PDO + Singleton                     │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    MariaDB 11.8.3                            │
│                  and_finance_app DB                          │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de Autenticación

```
┌──────────┐    ┌──────────────┐    ┌─────────────────┐    ┌──────────┐
│  LOGIN   │───▶│AuthController│───▶│   UserModel     │───▶│    BD    │
│  /login  │    │   login()    │    │ getByEmail()    │    │ usuarios │
└──────────┘    └──────────────┘    └─────────────────┘    └──────────┘
                       │
                       ▼
              ┌──────────────────┐
              │ password_verify()│
              │   bcrypt hash    │
              └──────────────────┘
                       │
                       ▼
              ┌──────────────────┐
              │  $_SESSION init  │
              │  user_id, email  │
              │  nombre, avatar  │
              └──────────────────┘
```

### Flujo de Registro con Verificación

```
┌──────────┐    ┌──────────────┐    ┌─────────────────┐
│ REGISTER │───▶│AuthController│───▶│VerificacionModel│
│          │    │iniciarRegistro│   │  createCode()   │
└──────────┘    └──────────────┘    └─────────────────┘
                       │                     │
                       ▼                     ▼
              ┌──────────────────┐  ┌─────────────────┐
              │  EmailHelper     │  │ verificacion_   │
              │  sendEmail()     │  │ codigos (temp)  │
              └──────────────────┘  └─────────────────┘
                       │
                       ▼
              ┌──────────────────┐
              │   SMTP Server    │
              │  (PHPMailer)     │
              └──────────────────┘
                       │
                       ▼
              ┌──────────────────┐    ┌─────────────────┐
              │  Usuario recibe  │───▶│ Ingresa código  │
              │  código 6 dígitos│    │  verificación   │
              └──────────────────┘    └─────────────────┘
                                              │
                                              ▼
                                     ┌─────────────────┐
                                     │verificarYRegistrar│
                                     │ Crea usuario    │
                                     │ Crea cuenta     │
                                     │ Copia categorías│
                                     └─────────────────┘
```

---

## 🚀 Características Principales

### ✅ Autenticación y Seguridad
- Login con email/contraseña
- Registro con verificación por email (código de 6 dígitos, expira en 15 min)
- Recuperación de contraseña por email
- Sesiones independientes para admin y usuario
- Contraseñas hasheadas con bcrypt (PASSWORD_DEFAULT)
- Protección de archivos en `uploads/` vía validación de sesión
- 🔄 Login con Google OAuth (requiere configuración)

### ✅ Interfaz de Administración (`/admin`)
- Dashboard con estadísticas generales
- CRUD completo de Bancos (20 bancos colombianos preconfigurados)
- Gestión de usuarios
- Categorías del sistema

### ✅ Interfaz de Usuario (`/ui`)

#### 📊 Dashboard
- Resumen financiero: saldo total, ingresos/gastos del mes
- **Proyección de saldo** para fin de mes actual y siguiente
- Gráficos de evolución (Chart.js)
- Últimas transacciones
- Próximos gastos recurrentes
- Accesos rápidos
- Vista optimizada para móviles

#### 💳 Gestión de Cuentas
- Múltiples tipos: Billetera, Banco, Tarjeta de Crédito, Inversión
- Selección visual de bancos con logos
- Colores e íconos personalizables (Bootstrap Icons)
- Ajuste de saldo manual (genera transacción tipo `ajuste`)
- Excluir cuentas del saldo total
- Cuenta predeterminada
- Eliminación con doble validación

#### 💸 Transacciones
- Tipos: `ingreso`, `egreso`, `transferencia`, `ajuste`
- Transacciones programadas (`realizada = 0`)
- Adjuntar comprobantes (imágenes: jpg, png, gif, webp | documentos: pdf)
- Visualización de comprobantes con preview y descarga
- Filtros avanzados por fecha, tipo, cuenta, categoría

#### 🏷️ Categorías
- 16 categorías de egreso predefinidas
- 9 categorías de ingreso predefinidas
- Categorías personalizadas por usuario
- Subcategorías opcionales
- Íconos (Bootstrap Icons) y colores personalizables

#### 🔄 Gastos Recurrentes
- Frecuencia mensual con selección de día (1-31)
- Manejo inteligente de días (ej: día 31 en febrero → día 28/29)
- Vista de próximos 30 días
- Registro manual de pagos
- Cálculo automático de siguiente ejecución

#### 📊 Reportes
- Gráfico de barras: evolución ingresos vs gastos
- Gráfico donut: distribución por categoría
- Filtros: mes actual, anterior, año, personalizado
- Saldos por cuenta

#### 📈 Presupuestos
- Límites de gasto por categoría
- Seguimiento visual (progress bar)
- Alertas: 50%, 80%, 100%
- Copiar presupuestos del mes anterior

#### 👤 Perfil
- Editar datos personales
- Cambiar contraseña (formulario colapsable)
- Preferencias: moneda, tema, notificaciones
- Estadísticas del usuario

---

## 💾 Instalación

### Requisitos del Sistema
```bash
# PHP 8.2+ con extensiones
php -v  # >= 8.2.0
php -m | grep -E "pdo|mysql|mbstring|json|openssl|fileinfo"

# MariaDB / MySQL
mysql --version  # >= 11.8.3 (MariaDB) o >= 8.0 (MySQL)

# Apache con mod_rewrite
apache2ctl -M | grep rewrite
```

### Pasos de Instalación

```bash
# 1. Clonar repositorio
git clone <repositorio> and_finance_app
cd and_finance_app

# 2. Crear archivo de configuración
cp .env.example .env

# 3. Editar .env con tus credenciales
nano .env

# 4. Crear base de datos
mysql -u root -p < sql/ddl.sql
# O usar el script PHP:
php sql/reset_db.php

# 5. Configurar permisos
chmod -R 755 uploads/
chmod 600 .env

# 6. Verificar .htaccess (Apache)
# Asegurar que AllowOverride All está habilitado
```

### Acceso a la Aplicación
| Interfaz | URL |
|----------|-----|
| Landing | `http://localhost/and_finance_app/` |
| Admin | `http://localhost/and_finance_app/admin/` |
| Usuario | `http://localhost/and_finance_app/ui/` |

### Credenciales por Defecto (Admin)
```
Email: admin@andfinance.com
Password: Admin123!
```

---

## ⚙️ Configuración

### Variables de Entorno (`.env`)

```env
# ═══════════════════════════════════════════════════════════
# BASE DE DATOS
# ═══════════════════════════════════════════════════════════
DB_HOST=localhost
DB_NAME=and_finance_app
DB_USER=root
DB_PASS=

# ═══════════════════════════════════════════════════════════
# SMTP - Envío de Emails (PHPMailer)
# ═══════════════════════════════════════════════════════════
SMTP_HOST=smtp.hostinger.com
SMTP_USER=no-reply@tudominio.com
SMTP_PASS=tu_password_seguro
SMTP_PORT=465

# ═══════════════════════════════════════════════════════════
# GOOGLE OAUTH (Opcional)
# ═══════════════════════════════════════════════════════════
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

### Carga de Variables de Entorno

```php
// config/env_loader.php
function loadEnv($path = null) {
    $envPath = $path ?? dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) return;
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

function env($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
```

---

## 🗄 Base de Datos

### Diagrama de Entidades

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    usuarios     │       │     cuentas     │       │  transacciones  │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │──┐    │ id (PK)         │──┐    │ id (PK)         │
│ nombre          │  │    │ usuario_id (FK) │◀─┤    │ usuario_id (FK) │
│ email (UNIQUE)  │  │    │ banco_id (FK)   │  │    │ cuenta_id (FK)  │
│ password        │  │    │ nombre          │  │    │ categoria_id    │
│ avatar          │  │    │ tipo_cuenta     │  │    │ cuenta_destino  │
│ google_id       │  │    │ saldo           │  │    │ tipo            │
│ rol             │  │    │ color           │  │    │ monto           │
│ estado          │  └───▶│ icono           │  │    │ descripcion     │
│ created_at      │       │ es_predeterminada│  │    │ fecha           │
└─────────────────┘       │ excluir_total   │  │    │ realizada       │
                          │ estado          │  └───▶│ estado          │
                          └─────────────────┘       └─────────────────┘
                                   │                        │
                                   ▼                        ▼
                          ┌─────────────────┐       ┌─────────────────┐
                          │     bancos      │       │transaccion_archivos│
                          ├─────────────────┤       ├─────────────────┤
                          │ id (PK)         │       │ id (PK)         │
                          │ nombre          │       │ transaccion_id  │
                          │ codigo          │       │ nombre_original │
                          │ logo            │       │ nombre_archivo  │
                          │ color           │       │ tipo_mime       │
                          │ estado          │       │ tamano          │
                          └─────────────────┘       └─────────────────┘
```

### Esquema Completo de Tablas

#### `usuarios`
```sql
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    google_id VARCHAR(100) NULL,
    rol INT NOT NULL DEFAULT 2,          -- 1=admin, 2=usuario
    estado INT NOT NULL DEFAULT 1,        -- 0=inactivo, 1=activo
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `cuentas`
```sql
CREATE TABLE cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    banco_id INT NULL,                    -- NULL si no es cuenta bancaria
    nombre VARCHAR(100) NOT NULL,
    tipo_cuenta INT NOT NULL DEFAULT 1,   -- 1=billetera, 2=banco, 3=tarjeta, 4=inversion
    saldo DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    color VARCHAR(7) DEFAULT '#55A5C8',
    icono VARCHAR(50) DEFAULT 'bi-wallet2',
    es_predeterminada INT DEFAULT 0,      -- 0=no, 1=si
    excluir_total INT DEFAULT 0,          -- 0=incluir, 1=excluir
    estado INT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### `transacciones`
```sql
CREATE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cuenta_id INT NOT NULL,
    categoria_id INT NULL,
    subcategoria_id INT NULL,
    cuenta_destino_id INT NULL,           -- Solo para transferencias
    tipo VARCHAR(20) NOT NULL,            -- ingreso, egreso, transferencia, ajuste
    monto DECIMAL(15,2) NOT NULL,
    descripcion TEXT NULL,
    fecha DATE NOT NULL,
    realizada INT NOT NULL DEFAULT 1,     -- 0=programada, 1=realizada
    gasto_recurrente_id INT NULL,         -- Referencia al gasto recurrente
    estado INT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### `categorias`
```sql
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,                  -- NULL = categoría del sistema
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL,            -- ingreso, egreso
    icono VARCHAR(50) DEFAULT 'bi-tag',
    color VARCHAR(7) DEFAULT '#55A5C8',
    es_sistema INT DEFAULT 0,             -- 1=sistema (no editable)
    estado INT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### `gastos_recurrentes`
```sql
CREATE TABLE gastos_recurrentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cuenta_id INT NOT NULL,
    categoria_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    frecuencia VARCHAR(20) DEFAULT 'mensual',
    dia_ejecucion INT DEFAULT 1,          -- 1-31
    proxima_ejecucion DATE NOT NULL,
    descripcion TEXT NULL,
    estado INT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### `presupuestos`
```sql
CREATE TABLE presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    monto_limite DECIMAL(15,2) NOT NULL,
    mes INT NOT NULL,                     -- 1-12
    anio INT NOT NULL,
    alerta_50 INT DEFAULT 1,              -- 0=no, 1=si
    alerta_80 INT DEFAULT 1,
    alerta_100 INT DEFAULT 1,
    estado INT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_presupuesto (usuario_id, categoria_id, mes, anio)
);
```

#### `verificacion_codigos`
```sql
CREATE TABLE verificacion_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,                  -- NULL si es registro nuevo
    email VARCHAR(150) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    tipo VARCHAR(50) NOT NULL,            -- registro, recuperacion
    data_temporal TEXT NULL,              -- JSON con datos temporales
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    intentos INT DEFAULT 0,
    estado INT NOT NULL DEFAULT 1         -- 0=usado, 1=activo
);
```

#### `configuracion_usuario`
```sql
CREATE TABLE configuracion_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    moneda VARCHAR(10) DEFAULT 'COP',
    tema VARCHAR(20) DEFAULT 'light',     -- light, dark, auto
    notificaciones INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Convenciones de Base de Datos

| Convención | Descripción |
|------------|-------------|
| **Sin Foreign Keys** | Relaciones manejadas por aplicación |
| **Sin ENUMs** | Valores como INT/VARCHAR con documentación |
| **estado** | 0=inactivo/eliminado, 1=activo |
| **realizada** | 0=programada, 1=realizada |
| **Timestamps** | created_at, updated_at automáticos |
| **Soft Delete** | Cambio de estado, no DELETE físico |

---

## 🌙 Sistema de Temas (Dark/Light)

### Implementación Técnica

El sistema de temas utiliza **CSS Custom Properties (Variables)** y el atributo `data-theme` en el elemento `<html>`.

#### Atributos HTML
```html
<html lang="es" 
      data-theme="dark"           <!-- Tema actual aplicado -->
      data-theme-preference="auto" <!-- Preferencia del usuario -->
>
```

#### Variables CSS - Tema Claro
```css
:root, [data-theme="light"] {
    /* Colores corporativos */
    --primary-blue: #55A5C8;
    --secondary-green: #9AD082;
    --tertiary-gray: #B1BCBF;
    --dark-blue: #35719E;
    --success-color: #9AD082;
    --danger-color: #FF6B6B;
    
    /* Fondos */
    --bg-body: #f4f7fa;
    --bg-card: #ffffff;
    --bg-sidebar: #ffffff;
    --bg-header: #ffffff;
    --bg-input: #ffffff;
    --bg-hover: rgba(85, 165, 200, 0.1);
    --bg-table-head: #f8fafc;
    
    /* Textos */
    --text-primary: #1a1d21;
    --text-secondary: #5a6f7c;
    --text-muted: #6c757d;
    
    /* Bordes */
    --border-color: #e9ecef;
    --border-light: #f0f0f0;
    
    /* Sombras */
    --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.04);
    --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.08);
}
```

#### Variables CSS - Tema Oscuro
```css
[data-theme="dark"] {
    /* Colores corporativos (ajustados para contraste) */
    --primary-blue: #5fb5d8;
    --secondary-green: #a5db8f;
    --dark-blue: #6ba8cc;
    --danger-color: #ff7b7b;
    
    /* Fondos */
    --bg-body: #0f1214;
    --bg-card: #1a1d21;
    --bg-sidebar: #1a1d21;
    --bg-header: #1a1d21;
    --bg-input: #23272b;
    --bg-hover: rgba(95, 181, 216, 0.15);
    --bg-table-head: #23272b;
    
    /* Textos */
    --text-primary: #f0f2f5;
    --text-secondary: #a8b3bd;
    --text-muted: #8a9499;
    
    /* Bordes */
    --border-color: #2d3238;
    --border-light: #23272b;
    
    /* Sombras */
    --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.25);
    --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.35);
}
```

#### Detección Automática de Tema
```javascript
(function() {
    const html = document.documentElement;
    const themePref = html.getAttribute('data-theme-preference');
    
    if (themePref === 'auto') {
        // Detectar preferencia del sistema
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        
        // Escuchar cambios en tiempo real
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (html.getAttribute('data-theme-preference') === 'auto') {
                html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
            }
        });
    }
})();
```

#### Guardar Preferencia en BD
```php
// configuracion_usuario.tema = 'light' | 'dark' | 'auto'
$stmt = $db->prepare("
    UPDATE configuracion_usuario 
    SET tema = :tema, updated_at = NOW() 
    WHERE usuario_id = :id
");
$stmt->execute(['tema' => $tema, 'id' => $userId]);
```

---

## 🔌 API Endpoints

### Transacciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/ui/modules/transacciones/api/get_archivos.php?id={id}` | Obtener archivos de una transacción |
| GET | `/ui/modules/transacciones/api/ver_archivo.php?id={id}` | Descargar/visualizar archivo |

#### Ejemplo: Obtener Archivos
```javascript
// Request
fetch(`${UI_URL}modules/transacciones/api/get_archivos.php?id=123`)

// Response
{
    "success": true,
    "archivos": [
        {
            "id": 1,
            "nombre_original": "comprobante.pdf",
            "tipo_mime": "application/pdf",
            "tamano": 102400,
            "url": "/ui/modules/transacciones/api/ver_archivo.php?id=1"
        }
    ]
}
```

### Cuentas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/ui/modules/cuentas/api/info_eliminar.php?id={id}` | Info previa a eliminar cuenta |

#### Ejemplo: Info Eliminar
```javascript
// Response
{
    "success": true,
    "cuenta": { "nombre": "Banco X", "saldo": 1500000 },
    "transacciones": 45,
    "transferencias": 12,
    "gastos_recurrentes": 3
}
```

---

## 📁 Sistema de Archivos

### Estructura de Uploads
```
uploads/
├── bancos/                    # Logos de bancos (admin)
│   └── {codigo}_{hash}.png
├── transacciones/             # Comprobantes por usuario
│   └── {usuario_id}/
│       └── {transaccion_id}_{hash}.{ext}
└── .htaccess                  # Protección de acceso directo
```

### Validación de Archivos
```php
// Tipos permitidos
$allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedDocs = ['application/pdf'];

// Tamaños máximos
$maxImageSize = 5 * 1024 * 1024;   // 5 MB
$maxDocSize = 10 * 1024 * 1024;    // 10 MB

// Renombrado seguro
$newName = sprintf(
    '%s_%d_%s.%s',
    'comprobante',
    $transaccionId,
    bin2hex(random_bytes(8)),
    $extension
);
```

### Protección de Archivos

#### `.htaccess` en `/uploads/`
```apache
# Denegar acceso directo
Order deny,allow
Deny from all
```

#### Endpoint Seguro para Servir Archivos
```php
// ver_archivo.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Validar que el archivo pertenece al usuario
$stmt = $db->prepare("
    SELECT ta.*, t.usuario_id 
    FROM transaccion_archivos ta
    JOIN transacciones t ON ta.transaccion_id = t.id
    WHERE ta.id = ? AND t.usuario_id = ?
");
$stmt->execute([$archivoId, $_SESSION['user_id']]);
$archivo = $stmt->fetch();

if (!$archivo) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

// Servir archivo
$filePath = UPLOADS_PATH . 'transacciones/' . $archivo['nombre_archivo'];
header('Content-Type: ' . $archivo['tipo_mime']);
header('Content-Disposition: inline; filename="' . $archivo['nombre_original'] . '"');
readfile($filePath);
```

---

## 🔒 Seguridad

### Autenticación

| Medida | Implementación |
|--------|----------------|
| Hash de contraseñas | `password_hash($pass, PASSWORD_DEFAULT)` (bcrypt) |
| Verificación | `password_verify($pass, $hash)` |
| Sesiones | Nombres únicos: `and_finance_user`, `and_finance_admin` |
| Cookies | `session.cookie_httponly = 1`, `session.cookie_secure = 1` (HTTPS) |

### Códigos de Verificación
```php
// Generación
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Expiración: 15 minutos
$expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Máximo 3 intentos fallidos
if ($intentos >= 3) {
    // Invalidar código
}
```

### Protección SQL Injection
```php
// ✅ Correcto: Prepared Statements
$stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
$stmt->execute(['email' => $email]);

// ❌ Incorrecto: Concatenación directa
$query = "SELECT * FROM usuarios WHERE email = '$email'";
```

### Protección XSS
```php
// Output encoding
<?= htmlspecialchars($variable, ENT_QUOTES, 'UTF-8') ?>
```

### Headers de Seguridad Recomendados
```apache
# .htaccess
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## 📂 Estructura del Proyecto

```
and_finance_app/
├── admin/                        # Panel de administración
│   ├── config/paths.php          # URLs y rutas admin
│   ├── controllers/AuthController.php
│   ├── modules/
│   │   ├── bancos/
│   │   │   ├── models/BancoModel.php
│   │   │   └── pages/{index,crear,editar}.php
│   │   ├── usuarios/
│   │   └── categorias/
│   ├── utils/session.php
│   ├── views/layouts/{header,footer}.php
│   ├── index.php
│   ├── login.php
│   └── logout.php
│
├── ui/                           # Interfaz de usuario
│   ├── config/paths.php          # URLs y rutas UI
│   ├── controllers/AuthController.php
│   ├── models/
│   │   ├── UserModel.php
│   │   └── VerificacionModel.php
│   ├── modules/
│   │   ├── cuentas/
│   │   │   ├── api/info_eliminar.php
│   │   │   ├── models/CuentaModel.php
│   │   │   └── pages/{index,crear,editar,ajustar}.php
│   │   ├── transacciones/
│   │   │   ├── api/{get_archivos,ver_archivo}.php
│   │   │   ├── models/TransaccionModel.php
│   │   │   └── pages/{index,crear,editar}.php
│   │   ├── categorias/
│   │   ├── recurrentes/
│   │   ├── presupuestos/
│   │   ├── reportes/
│   │   └── perfil/
│   ├── pages/dashboard.php
│   ├── utils/session.php
│   ├── views/layouts/{header,footer}.php
│   ├── index.php
│   ├── login.php
│   └── logout.php
│
├── assets/
│   ├── css/{admin,app}.css
│   ├── favicons/favicon.ico
│   ├── img/                      # Logos
│   │   ├── logo-square.png
│   │   ├── logo-horizontal.png
│   │   └── logo-horizontal-white.png
│   └── PHPMailer/
│       ├── EmailHelper.php       # Helper personalizado
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
│
├── config/
│   ├── database.php              # Conexión PDO Singleton
│   └── env_loader.php            # Carga de .env
│
├── sql/
│   ├── ddl.sql                   # CREATE TABLE statements
│   └── reset_db.php              # Script de reinicio
│
├── uploads/                      # Archivos subidos (protegido)
│   ├── bancos/
│   ├── transacciones/
│   └── .htaccess
│
├── .env                          # Variables de entorno (NO en git)
├── .env.example                  # Plantilla
├── .gitignore
├── .htaccess                     # Config Apache raíz
├── index.html                    # Landing page
├── roles.json                    # Definición de permisos
└── README.md
```

---

## 🎨 Paleta de Colores

| Color | Código | CSS Variable | Uso |
|-------|--------|--------------|-----|
| Azul Primario | `#55A5C8` | `--primary-blue` | Botones, enlaces |
| Verde Secundario | `#9AD082` | `--secondary-green` | Ingresos, éxito |
| Gris Terciario | `#B1BCBF` | `--tertiary-gray` | Textos secundarios |
| Azul Oscuro | `#35719E` | `--dark-blue` | Headers, énfasis |
| Rojo | `#FF6B6B` | `--danger-color` | Egresos, alertas |

---

## 🖼️ Especificaciones de Logos

| Archivo | Dimensiones | Uso | Fondo |
|---------|-------------|-----|-------|
| `logo-square.png` | 512×512 px | Index, PWA | Transparente |
| `logo-horizontal.png` | 280×60 px | Admin login | Transparente |
| `logo-horizontal-white.png` | 280×60 px | Sidebars, Login mobile | Azul |

**Favicon:** `assets/favicons/favicon.ico` - 32×32 px (multi-resolución recomendado)

---

## 🚧 Roadmap

- [ ] Integración completa Google OAuth
- [ ] Notificaciones push (Web Push API)
- [ ] Exportación Excel/PDF
- [ ] Multi-moneda con conversión
- [ ] PWA completa con Service Worker
- [ ] Sincronización Open Banking

---

## 📄 Licencia

Proyecto privado - Todos los derechos reservados.

---

<div align="center">

**Desarrollado con ❤️**

PHP 8.2 • Bootstrap 5.3 • Chart.js • MariaDB 11.8.3

</div>
