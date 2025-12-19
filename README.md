# And Finance App

Aplicación web de gestión de gastos personales desarrollada con PHP 8.2+ y Bootstrap 5. Sistema completo para controlar ingresos, egresos, cuentas bancarias y gastos recurrentes con reportes visuales.

## 🎯 Características Principales

### ✅ Autenticación y Seguridad
- Login con Email/Contraseña
- Registro de nuevos usuarios
- Preparado para Google OAuth
- Sistema de roles (usuario, admin)
- Protección de archivos en `uploads/`

### ✅ Interfaz de Administración (Admin)
- **Módulo de Bancos**: CRUD completo para gestionar bancos colombianos
  - Carga de logos de bancos
  - Gestión de códigos y nombres
  - Estados activo/inactivo

### ✅ Interfaz de Usuario (UI)

#### 📊 Dashboard
- Vista general del sistema
- Accesos rápidos a todos los módulos
- Información de bienvenida

#### 💰 Módulo de Cuentas
- Crear múltiples cuentas (bancarias, efectivo, inversión)
- Asociar cuentas con bancos
- Visualización de saldos actuales
- Saldo inicial configurable
- Actualización automática de saldos con transacciones
- **Ajuste de saldo manual**: Permite corregir desfases estableciendo un nuevo saldo objetivo
  - Genera automáticamente una transacción de tipo "ajuste"
  - No requiere categoría
  - Recalcula saldos correctamente respetando el ajuste

#### 🏷️ Módulo de Categorías
- Categorías predeterminadas del sistema (no editables)
- Crear categorías personalizadas
- Gestión de iconos y colores
- Separación por tipo: Ingresos y Egresos
- Edición y eliminación de categorías propias

#### 💸 Módulo de Transacciones (Núcleo del Sistema)
- **Registro de transacciones**:
  - Ingresos
  - Egresos
  - Transferencias entre cuentas (sin categoría requerida)
  - Ajustes de saldo (para corregir desfases)
- **Características**:
  - Campo de comentario opcional
  - Subida de múltiples archivos (comprobantes: JPG, PNG, PDF)
  - Actualización automática de saldos
  - Filtros avanzados (fecha, tipo, categoría, cuenta)
  - Transferencias no requieren categoría
  - Sistema de ajustes para corregir saldos manualmente
- **Gestión**:
  - Edición de transacciones (revierte y recrea)
  - Eliminación (revierte saldos automáticamente)
  - Historial completo con paginación
  - Recálculo automático de saldos al eliminar ajustes

#### 🔄 Módulo de Gastos Recurrentes
- **Programación de gastos**:
  - Frecuencia: Mensual, Quincenal, Semanal
  - Día del mes de ejecución (1-31)
  - Asociación con cuenta y categoría
- **Proyección Visual**:
  - Vista del mes actual con total proyectado
  - Vista del mes siguiente con total proyectado
  - Estado de cada gasto (ejecutado/pendiente)
- **Ejecución**:
  - Botón para ejecutar gastos pendientes
  - Crea automáticamente la transacción
  - Control de ejecuciones (evita duplicados)

#### 📈 Módulo de Reportes
- **Gráficos Visuales**:
  - Gráfico de línea: Ingresos vs. Egresos por mes (últimos 3 meses, actual y siguiente)
  - Gráfico de dona: Distribución de gastos por categoría
- **Resumen Financiero**:
  - Total de ingresos
  - Total de egresos
  - Balance (ingresos - egresos)
- **Análisis por Categoría**:
  - Top 10 gastos por categoría
  - Top 10 ingresos por categoría
- **Filtros Avanzados**:
  - Mes actual
  - Mes anterior
  - Año actual
  - Año completo (seleccionable)
  - Rango personalizado de fechas

### 🎨 Onboarding Automático
Al crear un nuevo usuario, el sistema genera automáticamente:
- Una cuenta por defecto llamada "Billetera" (Efectivo)
- Un set completo de categorías predeterminadas:
  - **Ingresos**: Salario, Inversiones, Bonos, Otros Ingresos
  - **Egresos**: Hogar, Comida, Transporte, Salud, Educación, Entretenimiento, Ropa, Servicios, Otros Gastos

## 🛠️ Requisitos

- PHP 8.2 o superior
- MariaDB 11.8.3 o superior
- Apache con mod_rewrite
- Extensiones PHP:
  - PDO
  - PDO_MySQL
  - GD (para procesamiento de imágenes)
  - mbstring

## 📦 Instalación

### 1. Configurar Base de Datos

Crear el archivo `.env` basado en `env.example`:

```bash
cp env.example .env
```

Editar `.env` con tus credenciales:

```env
DB_HOST=localhost
DB_NAME=and_finance_db
DB_USER=root
DB_PASS=

GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost/and_finance_app/ui/login.php

APP_URL=http://localhost/and_finance_app
APP_NAME=And Finance App
SESSION_LIFETIME=7200
```

### 2. Crear Base de Datos

**Opción A: Script automático**
```bash
php sql/reset_db.php
```

**Opción B: Manual**
```bash
mysql -u root -p < sql/ddl.sql
```

O ejecutar manualmente el archivo `sql/ddl.sql` en MariaDB.

### 3. Configurar Permisos

```bash
chmod -R 755 uploads/
```

### 4. Acceder a la Aplicación

- **Usuarios**: `http://localhost/and_finance_app/ui/login.php`
- **Admin**: `http://localhost/and_finance_app/admin/index.php`

## 🔐 Credenciales por Defecto

### Usuario Administrador
- **Email**: `admin@andfinance.com`
- **Contraseña**: `admin123`
- **Rol**: `admin`

> ⚠️ **IMPORTANTE**: Cambiar la contraseña después del primer login.

### Crear Admin Manualmente

Si necesitas crear o actualizar el administrador:

```bash
php admin/create_admin.php
```

O desde el navegador:
```
http://localhost/and_finance_app/admin/create_admin.php?token=create_admin_2024
```

## 📁 Estructura del Proyecto

```
and_finance_app/
├── admin/                      # Interfaz de administración
│   ├── modules/
│   │   └── bancos/            # CRUD de bancos
│   │       ├── api/
│   │       ├── models/
│   │       └── pages/
│   ├── views/layouts/         # Header, footer, sidebar
│   ├── config/
│   │   └── paths.php
│   ├── index.php
│   └── create_admin.php
│
├── ui/                         # Interfaz de usuario
│   ├── modules/
│   │   ├── cuentas/           # Gestión de cuentas
│   │   ├── categorias/        # Gestión de categorías
│   │   ├── transacciones/     # Registro de transacciones
│   │   ├── gastos_recurrentes/# Gastos programados
│   │   └── reportes/          # Gráficos y estadísticas
│   ├── views/layouts/
│   ├── config/
│   ├── index.php
│   ├── login.php
│   └── logout.php
│
├── utils/                      # Utilidades globales
│   ├── Database.php           # Conexión a BD
│   ├── Env.php                # Variables de entorno
│   ├── Auth.php               # Autenticación
│   └── FileUploadManager.php  # Gestión de archivos
│
├── assets/
│   ├── css/
│   │   └── common.css         # Estilos con variables CSS
│   ├── img/
│   └── js/
│
├── sql/
│   ├── ddl.sql                # Script de creación de BD
│   └── reset_db.php           # Script de reinicio
│
├── uploads/                    # Archivos subidos (protegido)
│   ├── bancos/
│   └── transacciones/
│
├── roles.json                  # Configuración de roles
├── .env                        # Variables de entorno (no versionado)
└── README.md
```

## 🎨 Paleta de Colores

El sistema utiliza una paleta de colores consistente definida en `assets/css/common.css`:

- **Primary**: `#39843A` (Verde)
- **Secondary**: `#F1B10B` (Amarillo)
- **Third**: `#1F4738` (Verde oscuro)
- **Fourth**: `#31424B` (Gris azulado)

## 📊 Base de Datos

### Tablas Principales

- `control_usuarios` - Usuarios del sistema
- `bancos_bancos` - Bancos disponibles
- `cuentas_cuentas` - Cuentas de los usuarios
- `categorias_categorias` - Categorías de transacciones
- `transacciones_transacciones` - Registro de transacciones
- `transacciones_archivos` - Archivos adjuntos
- `gastos_recurrentes_gastos` - Gastos programados
- `gastos_recurrentes_ejecuciones` - Historial de ejecuciones

### Características de BD

- **Sin Foreign Keys**: Las relaciones se manejan a nivel de aplicación
- **Nomenclatura**: Tablas prefijadas por módulo (ej: `cuentas_cuentas`)
- **Integridad**: Validación en la capa de aplicación PHP

## 🔒 Seguridad

- Archivos en `uploads/` accesibles directamente (igual que en we_are_app)
- Contraseñas hasheadas con `password_hash()`
- Validación de permisos mediante `roles.json`
- Sanitización de inputs en todos los formularios

## 📝 Notas de Desarrollo

- **Tipografía**: Poppins (ExtraBold para títulos, Regular para cuerpo)
- **Framework CSS**: Bootstrap 5.3.2
- **Gráficos**: Chart.js 4.4.0
- **Iconos**: Font Awesome 6.4.0
- **Límites de archivos**: Imágenes 5MB, PDFs 10MB
- **Renombrado de archivos**: Formato único para evitar sobrescritura

## 🚀 Funcionalidades Futuras

- Integración completa de Google OAuth
- Exportación de reportes a PDF/Excel
- Notificaciones de gastos recurrentes
- Presupuestos y límites por categoría
- Múltiples monedas
- App móvil

## 🔧 Desarrollo

### Estructura de Archivos

- **Modular**: Cada módulo tiene su propia carpeta con `api/`, `models/`, `pages/`
- **Separación de interfaces**: `admin/` y `ui/` son interfaces independientes
- **Utilidades compartidas**: `utils/` contiene clases reutilizables

### Convenciones de Código

- PHP 8.2+ con tipado estricto
- PDO para acceso a base de datos
- Sin frameworks externos (PHP puro)
- Bootstrap 5 para UI
- Chart.js para gráficos

### Base de Datos

- **Sin Foreign Keys**: Las relaciones se validan en la capa de aplicación
- **Nomenclatura**: `modulo_tabla` (ej: `transacciones_transacciones`)
- **Tipos de transacciones**: `ingreso`, `egreso`, `transferencia`, `ajuste`
- **Ajustes de saldo**: Tipo especial que establece el saldo directamente sin categoría

### Migraciones

Si necesitas aplicar cambios en la base de datos, crea un script SQL en `sql/migrate_*.sql` y ejecútalo manualmente.

## 📄 Licencia

Proyecto privado - Todos los derechos reservados

## 👥 Soporte

Para reportar problemas o sugerencias, contactar al equipo de desarrollo.

---

**Versión**: 1.1.0  
**Última actualización**: Diciembre 2025
