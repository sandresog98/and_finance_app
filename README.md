# AndFinance App 💰

Aplicación web de gestión de finanzas personales desarrollada en PHP 8.2 con Bootstrap 5. Diseñada para ser intuitiva, responsive y visualmente atractiva.

## 🚀 Características Principales

### ✅ Autenticación y Seguridad
- Login con email/contraseña
- Registro con verificación por email (código de 6 dígitos)
- Recuperación de contraseña por email
- Sesiones independientes para admin y usuario
- Contraseñas hasheadas con bcrypt
- Protección de archivos en `uploads/`
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
- Gráficos de evolución y distribución
- Últimas transacciones
- Próximos gastos recurrentes
- Accesos rápidos a funciones principales
- Vista optimizada para móviles

#### 💳 Gestión de Cuentas
- Crear múltiples cuentas (Billetera, Bancos, Tarjetas, etc.)
- Selección visual de bancos con logos
- Colores e íconos personalizables
- Ajuste de saldo manual (genera transacción de ajuste)
- Excluir cuentas del saldo total
- Cuenta predeterminada
- Eliminación con doble validación

#### 💸 Transacciones
- Registro de ingresos, egresos y transferencias
- Transacciones programadas (futuras, sin afectar saldo)
- Adjuntar comprobantes (imágenes y PDFs)
- **Visualización de comprobantes** con preview y descarga
- Filtros avanzados por fecha, tipo, cuenta, categoría
- Vista móvil con filtros en drawer lateral
- Ajustes de saldo automáticos

#### 🏷️ Categorías
- Categorías del sistema predefinidas (16 de egreso, 9 de ingreso)
- Crear categorías personalizadas
- Subcategorías opcionales
- Íconos y colores personalizables
- Sistema oculta categorías del sistema en formularios de usuario

#### 🔄 Gastos Recurrentes
- Programar gastos/ingresos automáticos
- Frecuencia mensual con selección de día
- Vista de próximos 30 días
- Registro manual de pagos
- Manejo inteligente de días (ej: día 31 en meses cortos)
- Creación automática de transacciones programadas

#### 📊 Reportes
- Evolución de ingresos vs gastos (gráfico de barras)
- Gráfico de distribución por categoría (donut)
- Filtros por período: mes actual, anterior, año, personalizado
- Saldos por cuenta
- Vista optimizada para móviles

#### 📈 Presupuestos
- Establecer límites de gasto por categoría
- Seguimiento visual del progreso
- Alertas configurables (50% - 100%)
- Copiar presupuestos del mes anterior
- Vista mensual con navegación

#### 👤 Perfil
- Editar datos personales
- Cambiar contraseña (formulario colapsable)
- Preferencias: moneda, **tema (claro/oscuro/auto)**, notificaciones
- Estadísticas del usuario

### 🌙 Tema Oscuro
- Soporte completo de tema oscuro
- Opción automática según preferencia del sistema
- Cambio en tiempo real desde el perfil

## 🎨 Paleta de Colores

| Color | Código | Uso |
|-------|--------|-----|
| Azul Primario | `#55A5C8` | Color principal |
| Verde Secundario | `#9AD082` | Acentos, ingresos, éxito |
| Gris Terciario | `#B1BCBF` | Fondos, bordes |
| Azul Oscuro | `#35719E` | Encabezados, énfasis |
| Rojo Gasto | `#FF6B6B` | Egresos, alertas, peligro |
| Amarillo | `#F7DC6F` | Advertencias |

## 🖼️ Logos de la Aplicación

Los logos deben ubicarse en `assets/img/`:

| Archivo | Dimensiones | Uso | Formato |
|---------|-------------|-----|---------|
| `logo-square.png` | 512x512 px | Favicon, PWA, Index | PNG transparente |
| `logo-horizontal.png` | 280x60 px | Login sidebar, Sidebar interno | PNG transparente |
| `logo-horizontal-white.png` | 280x60 px | Login mobile (fondo oscuro) | PNG blanco transparente |

**Favicon:** Ubicar en `assets/favicons/favicon.ico` (32x32 px)

## 📁 Estructura del Proyecto

```
and_finance_app/
├── admin/                        # Panel de administración
│   ├── config/
│   │   └── paths.php
│   ├── controllers/
│   │   └── AuthController.php
│   ├── modules/
│   │   └── bancos/
│   │       ├── models/
│   │       │   └── BancoModel.php
│   │       └── pages/
│   │           ├── index.php
│   │           ├── crear.php
│   │           └── editar.php
│   ├── pages/
│   │   └── dashboard.php
│   ├── utils/
│   │   └── session.php
│   ├── views/layouts/
│   │   ├── header.php
│   │   └── footer.php
│   ├── index.php
│   ├── login.php
│   └── logout.php
│
├── ui/                           # Interfaz de usuario
│   ├── config/
│   │   └── paths.php
│   ├── controllers/
│   │   └── AuthController.php
│   ├── models/
│   │   ├── UserModel.php
│   │   └── VerificacionModel.php
│   ├── modules/
│   │   ├── cuentas/
│   │   │   ├── api/
│   │   │   │   └── info_eliminar.php
│   │   │   ├── models/
│   │   │   │   └── CuentaModel.php
│   │   │   └── pages/
│   │   │       ├── index.php
│   │   │       ├── crear.php
│   │   │       ├── editar.php
│   │   │       └── ajustar.php
│   │   ├── transacciones/
│   │   │   ├── api/
│   │   │   │   ├── get_archivos.php
│   │   │   │   └── ver_archivo.php
│   │   │   ├── models/
│   │   │   │   └── TransaccionModel.php
│   │   │   └── pages/
│   │   │       ├── index.php
│   │   │       ├── crear.php
│   │   │       └── editar.php
│   │   ├── categorias/
│   │   │   ├── models/
│   │   │   │   └── CategoriaModel.php
│   │   │   └── pages/
│   │   │       ├── index.php
│   │   │       ├── crear.php
│   │   │       └── editar.php
│   │   ├── recurrentes/
│   │   │   ├── models/
│   │   │   │   └── GastoRecurrenteModel.php
│   │   │   └── pages/
│   │   │       ├── index.php
│   │   │       ├── crear.php
│   │   │       └── editar.php
│   │   ├── presupuestos/
│   │   │   ├── models/
│   │   │   │   └── PresupuestoModel.php
│   │   │   └── pages/
│   │   │       ├── index.php
│   │   │       ├── crear.php
│   │   │       └── editar.php
│   │   ├── reportes/
│   │   │   └── pages/
│   │   │       └── index.php
│   │   └── perfil/
│   │       └── pages/
│   │           └── index.php
│   ├── pages/
│   │   └── dashboard.php
│   ├── utils/
│   │   └── session.php
│   ├── views/layouts/
│   │   ├── header.php
│   │   └── footer.php
│   ├── index.php
│   ├── login.php
│   └── logout.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── app.css
│   ├── favicons/
│   │   └── favicon.ico
│   ├── img/                      # Logos de la app
│   │   └── .gitkeep
│   └── PHPMailer/
│       ├── EmailHelper.php
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
│
├── config/
│   └── database.php
│
├── sql/
│   ├── ddl.sql                   # Script de creación de BD
│   └── reset_db.php              # Script de reinicio de BD
│
├── uploads/                      # Archivos subidos (protegido)
│   ├── bancos/                   # Logos de bancos
│   └── transacciones/            # Comprobantes por usuario
│
├── .env                          # Variables de entorno (NO en git)
├── .env.example                  # Plantilla de variables
├── .gitignore                    # Archivos ignorados por git
├── .htaccess                     # Configuración Apache
├── index.html                    # Página de bienvenida
├── roles.json                    # Definición de roles
└── README.md
```

## 🛠️ Instalación

### Requisitos
- PHP 8.2+
- MariaDB 11.8.3+ / MySQL 8.0+
- XAMPP o servidor web con Apache
- Extensiones PHP: PDO, pdo_mysql, mbstring

### Pasos

1. **Clonar/Copiar el proyecto** a tu directorio web:
   ```bash
   git clone <repositorio> and_finance_app
   # o
   cp -r and_finance_app /Applications/XAMPP/xamppfiles/htdocs/process/
   ```

2. **Crear el archivo `.env`**:
   ```bash
   cp .env.example .env
   # Editar con tus credenciales
   ```

   Contenido del `.env`:
   ```env
   # Base de datos
   DB_HOST=localhost
   DB_NAME=and_finance_app
   DB_USER=root
   DB_PASS=
   
   # Google OAuth (opcional)
   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   
   # SMTP para emails
   SMTP_HOST=smtp.example.com
   SMTP_USER=no-reply@example.com
   SMTP_PASS=your_password
   SMTP_PORT=465
   ```

3. **Crear la base de datos**:
   ```bash
   # Opción 1: Ejecutar SQL directamente
   mysql -u root < sql/ddl.sql
   
   # Opción 2: Usar el script PHP
   php sql/reset_db.php
   ```

4. **Configurar permisos**:
   ```bash
   chmod -R 755 uploads/
   ```

5. **Subir logos** (opcional):
   - Colocar logos en `assets/img/`
   - Ver especificaciones en la sección "Logos de la Aplicación"

6. **Acceder a la aplicación**:
   - Bienvenida: `http://localhost/process/and_finance_app/`
   - Admin: `http://localhost/process/and_finance_app/admin/`
   - Usuario: `http://localhost/process/and_finance_app/ui/`

### Credenciales por defecto (Admin)
- **Email:** admin@andfinance.com
- **Contraseña:** Admin123!

## 📊 Base de Datos

### Tablas Principales

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema |
| `bancos` | Catálogo de bancos |
| `cuentas` | Cuentas financieras del usuario |
| `categorias` | Categorías de transacciones |
| `subcategorias` | Subcategorías opcionales |
| `transacciones` | Movimientos financieros |
| `transaccion_archivos` | Comprobantes adjuntos |
| `gastos_recurrentes` | Programación de gastos |
| `presupuestos` | Presupuestos mensuales |
| `verificacion_codigos` | Códigos de verificación email |
| `configuracion_usuario` | Preferencias del usuario |
| `sesiones` | Control de sesiones activas |

### Convenciones
- **Sin foreign keys** a nivel de motor (relaciones por aplicación)
- **Sin ENUMs** (valores documentados en comentarios)
- Prefijos de tabla por módulo cuando aplique
- `estado`: 0=inactivo/eliminado, 1=activo
- `realizada`: 0=programada, 1=realizada (en transacciones)

## 📝 Onboarding de Usuarios

Al registrarse un nuevo usuario, el sistema automáticamente:
1. Envía código de verificación por email
2. Al verificar, crea una cuenta "Billetera" (efectivo) predeterminada
3. Copia las categorías del sistema al usuario
4. Crea la configuración inicial (tema, moneda, etc.)

## 🔒 Seguridad

- Contraseñas hasheadas con `bcrypt` (PASSWORD_DEFAULT)
- Verificación de email con códigos temporales (15 min)
- Sesiones PHP con nombre personalizado (`and_finance_user`, `and_finance_admin`)
- Protección de archivos en `uploads/` vía `.htaccess`
- Validación de roles vía `roles.json`
- Credenciales en archivo `.env` (excluido de git)
- Validación de propiedad de recursos en cada operación

## 📱 Responsive Design

La aplicación está optimizada para dispositivos móviles:
- Dashboard compacto con estadísticas en una fila
- Filtros en drawer lateral (offcanvas)
- Listas en formato de tarjetas
- Botones flotantes de acción rápida
- Modales de selección visual (bancos, categorías, etc.)
- Gráficos adaptados al tamaño de pantalla

## 🔧 Funcionalidades Técnicas

### Transacciones Programadas
- Campo `realizada` en transacciones: 0=programada (no afecta saldo), 1=realizada
- Los gastos recurrentes crean transacciones programadas automáticamente
- Las transacciones programadas se pueden marcar como realizadas

### Ajuste de Saldo
- Tipo de transacción especial: `ajuste`
- No usa categorías del sistema
- Calcula automáticamente la diferencia
- Íconos distintos para ajustes positivos/negativos

### Eliminación de Cuentas
- Eliminación física (hard delete)
- Elimina transacciones normales asociadas
- Las transferencias se desasocian (no se eliminan)
- Requiere doble confirmación con información detallada

### Sistema de Emails
- PHPMailer integrado para envío de correos
- Verificación de registro por código
- Recuperación de contraseña por código
- Plantillas HTML para emails

## 🚧 Roadmap / Pendientes

- [ ] Integración completa con Google OAuth
- [ ] Notificaciones push en tiempo real
- [ ] Exportación a Excel/PDF
- [ ] Soporte multi-moneda completo
- [ ] App móvil (PWA)
- [ ] Sincronización con bancos (Open Banking)

## 📄 Licencia

Proyecto privado - Todos los derechos reservados.

---

**Desarrollado con ❤️ usando PHP 8.2, Bootstrap 5 y Chart.js**
