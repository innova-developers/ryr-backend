# RYR Comisiones

Sistema integral de gestion de comisiones, logistica y facturacion electronica para empresas de transporte y mensajeria.

**Stack:** Laravel 12 (PHP 8.2) + React 19 + Flutter (app cadetes) | MySQL 8 | Docker | Nginx

---

## Tabla de Contenidos

- [Arquitectura General](#arquitectura-general)
- [Requisitos](#requisitos)
- [Instalacion](#instalacion)
- [Variables de Entorno](#variables-de-entorno)
- [Docker](#docker)
- [Backend (ryr-backend)](#backend-ryr-backend)
  - [Estructura DDD](#estructura-ddd)
  - [Contextos de Dominio](#contextos-de-dominio)
  - [Modelos Compartidos](#modelos-compartidos)
  - [Enums](#enums)
  - [Servicios](#servicios)
  - [Controllers](#controllers)
  - [Middleware](#middleware)
  - [Rutas API](#rutas-api)
  - [Base de Datos](#base-de-datos)
  - [Tests](#tests)
- [Frontend (ryr-front)](#frontend-ryr-front)
  - [Paginas](#paginas)
  - [Componentes](#componentes)
  - [Hooks](#hooks)
  - [API Client](#api-client)
- [App Movil (ryr_cadetes_app)](#app-movil-ryr_cadetes_app)
- [Roles y Autenticacion](#roles-y-autenticacion)
- [Funcionalidades Principales](#funcionalidades-principales)
  - [Comisiones](#comisiones)
  - [Facturacion Electronica (ARCA)](#facturacion-electronica-arca)
  - [WhatsApp (Green API)](#whatsapp-green-api)
  - [Notificaciones Push (FCM)](#notificaciones-push-fcm)
  - [Sistema de Franquicias](#sistema-de-franquicias)
  - [Geolocalizacion](#geolocalizacion)
  - [Feedback y Encuestas](#feedback-y-encuestas)
- [Integraciones Externas](#integraciones-externas)
- [Comandos Utiles](#comandos-utiles)
- [Migracion de Base de Datos Legacy](#migracion-de-base-de-datos-legacy)

---

## Arquitectura General

```
ryr/
├── ryr-backend/          # Laravel 12 API — DDD con 15 contextos acotados
├── ryr-front/            # React 19 SPA — Vite + TailwindCSS
├── ryr_cadetes_app/      # Flutter — App movil para cadetes
├── docker-compose.yml    # Orquestacion Docker
└── docs/                 # Documentacion adicional
```

El backend sigue **Domain-Driven Design (DDD)** con arquitectura hexagonal. Cada contexto acotado tiene sus propias capas:

```
app/Contexts/{Contexto}/
├── Application/          # Casos de uso, DTOs
├── Domain/               # Entidades, repositorios (interfaces)
└── Infrastructure/       # Controllers, implementaciones, Form Requests
```

Los modelos, enums, middleware y traits compartidos viven en `app/Shared/`.

---

## Requisitos

| Componente | Version |
|-----------|---------|
| PHP | >= 8.2 |
| Node.js | >= 18 |
| MySQL | 8.0 |
| Composer | >= 2.x |
| Docker + Docker Compose | Recomendado |
| Flutter | >= 3.x (solo para app movil) |

---

## Instalacion

### Con Docker (recomendado)

```bash
git clone <repo-url> ryr
cd ryr

# Levantar servicios
docker-compose up -d

# Backend
docker exec -it ryrapp composer install
docker exec -it ryrapp cp .env.example .env
docker exec -it ryrapp php artisan key:generate
docker exec -it ryrapp php artisan migrate --seed

# Frontend
cd ryr-front
npm install
npm run dev    # Dev server en http://localhost:3000
```

### Sin Docker

```bash
# Backend
cd ryr-backend
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB en .env
php artisan migrate --seed
php artisan serve    # http://localhost:8000

# Frontend
cd ryr-front
npm install
npm run dev          # http://localhost:3000
```

---

## Variables de Entorno

### Backend (`ryr-backend/.env`)

#### Aplicacion
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `APP_NAME` | Nombre de la app | `RYR` |
| `APP_ENV` | Entorno | `local` / `production` |
| `APP_DEBUG` | Debug mode | `true` / `false` |
| `APP_URL` | URL base del backend | `http://localhost:8000` |

#### Base de Datos
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `DB_CONNECTION` | Driver | `mysql` |
| `DB_HOST` | Host | `mysql` (Docker) / `127.0.0.1` |
| `DB_PORT` | Puerto | `3306` |
| `DB_DATABASE` | Nombre BD | `laravel` |
| `DB_USERNAME` | Usuario | `laravel` |
| `DB_PASSWORD` | Password | `laravel` |

#### Autenticacion (Sanctum)
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `SANCTUM_STATEFUL_DOMAINS` | Dominios permitidos | `localhost,127.0.0.1` |
| `SANCTUM_TOKEN_EXPIRATION` | Expiracion token (min) | `1440` |

#### ARCA / AFIP (Facturacion Electronica)
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `ARCA_MOCK` | Modo simulacion | `true` / `false` |
| `ARCA_CUIT` | CUIT del emisor | `20000000001` |
| `ARCA_PRODUCTION` | Entorno produccion ARCA | `false` |
| `ARCA_PASSPHRASE` | Passphrase certificado | `xxxxx` |
| `ARCA_PUNTO_VENTA` | Punto de venta habilitado | `5` |
| `ARCA_RAZON_SOCIAL` | Razon social emisor | `RYR COMISIONES SRL` |
| `ARCA_DOMICILIO` | Domicilio fiscal | `Av. Ejemplo 1234, CABA` |
| `ARCA_CONDICION_IVA` | Condicion IVA | `IVA Responsable Inscripto` |

#### WhatsApp (Green API)
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `WHATSAPP_API_URL` | URL base Green API | `https://api.green-api.com` |
| `WHATSAPP_API_INSTANCE` | Instance ID | (provisto por Green API) |
| `WHATSAPP_API_TOKEN` | Token de acceso | (provisto por Green API) |

#### Firebase (Push Notifications)
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `FIREBASE_CREDENTIALS_PATH` | Ruta credenciales JSON | `storage/app/firebase-credentials.json` |

#### Google Maps
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `GOOGLE_MAPS_API_KEY` | API Key Maps | (provisto por Google) |

#### Groq (AI)
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `GROQ_API_KEY` | API Key Groq | (provisto por Groq) |

#### Mail
| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `MAIL_MAILER` | Driver de mail | `smtp` |
| `MAIL_HOST` | Host SMTP | `mailhog` (dev) |
| `MAIL_PORT` | Puerto SMTP | `1025` (dev) |

---

## Docker

### Servicios

| Servicio | Imagen | Puerto | Descripcion |
|----------|--------|--------|-------------|
| `app` | `php:8.2-fpm` | — | Backend PHP-FPM |
| `nginx` | `nginx:stable-alpine` | `8000` | Reverse proxy |
| `mysql` | `mysql:8.0` | `3306` | Base de datos principal |
| `mysql_old` | `mysql:8.0` | `3307` | BD legacy (migracion) |
| `phpmyadmin` | `phpmyadmin` | `8081` | Admin BD |

Red: `laravel-network` (bridge)

Timezone del contenedor: `America/Argentina/Buenos_Aires`

### Comandos Docker

```bash
docker-compose up -d              # Iniciar servicios
docker-compose down               # Detener servicios
docker exec -it ryrapp bash       # Shell en el contenedor
docker exec -it ryrapp php artisan tinker  # Tinker
```

---

## Backend (ryr-backend)

### Estructura DDD

```
app/
├── Contexts/                     # 15 contextos acotados (DDD)
│   ├── Auth/
│   ├── Branchs/
│   ├── Commissions/
│   ├── CurrentAccount/
│   ├── Customers/
│   ├── Destinations/
│   ├── ExpenseCategories/
│   ├── Expenses/
│   ├── ExtraordinaryCommissions/
│   ├── Franchises/
│   ├── IncomeCategories/
│   ├── Incomes/
│   ├── Locations/
│   ├── Transports/
│   └── Users/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                # Controllers de administracion
│   │   ├── Cadete/               # Controllers de cadetes
│   │   ├── Client/               # Controllers de clientes
│   │   └── Public/               # Controllers publicos (tracking)
│   └── Middleware/
├── Mail/                         # Mailables
├── Services/                     # Servicios de negocio
└── Shared/
    ├── Enums/                    # Enumeraciones
    ├── Middleware/                # Middleware compartido
    ├── Models/                   # 24 modelos Eloquent
    └── Traits/                   # Traits compartidos
```

### Contextos de Dominio

| Contexto | Descripcion | Modelo Principal |
|----------|-------------|-----------------|
| **Auth** | Autenticacion, login, verificacion de codigo | User |
| **Users** | Gestion de usuarios, salarios, contratos | User |
| **Branchs** | Sucursales/oficinas | Branch |
| **Customers** | Clientes, CUIT, calculo IVA automatico | Customer |
| **Commissions** | Comisiones/envios (core del negocio) | Commission, CommissionItem, CommissionLog |
| **Destinations** | Tarifas origen-destino | Destination |
| **Locations** | Ubicaciones con coordenadas GPS | Location |
| **Transports** | Gestiones de cadetes/transportistas | Transport |
| **Expenses** | Gastos | Expense |
| **ExpenseCategories** | Categorias de gastos | ExpenseCategory |
| **Incomes** | Ingresos | Income |
| **IncomeCategories** | Categorias de ingresos | IncomeCategory |
| **ExtraordinaryCommissions** | Comisiones especiales fuera de tarifa | ExtraordinaryCommission |
| **CurrentAccount** | Cuenta corriente de clientes | CurrentAccount |
| **Franchises** | Sistema de franquicias multi-tenant | Franchise |

### Modelos Compartidos

24 modelos en `app/Shared/Models/`:

| Modelo | Descripcion |
|--------|-------------|
| `User` | Usuarios del sistema (todos los roles) |
| `Branch` | Sucursales |
| `Customer` | Clientes |
| `Commission` | Comisiones/envios |
| `CommissionItem` | Items de una comision (paquetes, sobres) |
| `CommissionLog` | Log de cambios de estado |
| `CurrentAccount` | Movimientos de cuenta corriente |
| `Destination` | Tarifas por destino |
| `Expense` | Gastos |
| `ExpenseCategory` | Categorias de gastos |
| `Income` | Ingresos |
| `IncomeCategory` | Categorias de ingresos |
| `ExtraordinaryCommission` | Comisiones extraordinarias |
| `Invoice` | Facturas electronicas |
| `Franchise` | Franquicias |
| `MatrixReceivable` | Cuentas por cobrar de franquicias |
| `Transport` | Gestiones de transporte |
| `Location` | Ubicaciones geograficas |
| `ShipmentLocation` | Ubicaciones de envio |
| `FcmToken` | Tokens Firebase para push notifications |
| `FeedbackSurvey` | Encuestas de feedback |
| `SystemSetting` | Configuracion del sistema (IVA, etc.) |
| `WhatsAppCampaign` | Campanas de WhatsApp |
| `WhatsAppCampaignMessage` | Mensajes individuales de campana |

### Enums

12 enums en `app/Shared/Enums/`:

| Enum | Valores |
|------|---------|
| `UserRole` | ADMINISTRADOR, CADETE, MOSTRADOR, CLIENTE, CADETE_EXTERNO, COBRADOR, ADMIN_FRANQUICIA |
| `CommissionStatus` | PENDIENTE, PRESUPUESTADO, ACEPTADO, CADETE_ASIGNADO, RETIRADO, RETIRADO_SUCURSAL, ENTREGADO, CANCELADO, PAGO_VALIDACION, PAGADO, PAGO_CONFIRMADO, ENCOMIENDA_RETIRADA |
| `CommissionType` | REGULAR, EXPRESS, ENCOMIENDA |
| `CommissionItemSize` | SMALL, MEDIUM, LARGE |
| `CommissionItemType` | PAQUETE, SOBRE, DOCUMENTO |
| `PaymentMethod` | EFECTIVO, TRANSFERENCIA, TARJETA, CHEQUE, CUENTA_CORRIENTE |
| `CurrentAccountStatus` | PENDING, OK, CANCELLED |
| `InvoiceStatus` | EMITIDA, ANULADA |
| `InvoiceType` | FACTURA_A (1), NOTA_DEBITO_A (2), NOTA_CREDITO_A (3), FACTURA_B (6), NOTA_DEBITO_B (7), NOTA_CREDITO_B (8), FACTURA_C (11), NOTA_DEBITO_C (12), NOTA_CREDITO_C (13) |
| `IvaStatus` | RESPONSABLE_INSCRIPTO, MONOTRIBUTISTA, EXENTO, CONSUMIDOR_FINAL |
| `FranchiseStatus` | ACTIVE, INACTIVE, SUSPENDED |
| `CampaignStatus` | DRAFT, SCHEDULED, SENDING, COMPLETED, FAILED |

### Servicios

13 servicios en `app/Services/`:

| Servicio | Descripcion |
|----------|-------------|
| `InvoiceService` | Emision, anulacion y generacion de PDF de facturas |
| `ArcaService` | Integracion con ARCA/AFIP para facturacion electronica |
| `WhatsAppService` | Envio de mensajes y archivos via Green API |
| `FcmNotificationService` | Push notifications via Firebase Cloud Messaging |
| `CommissionNotificationService` | Notificaciones de cambio de estado de comisiones |
| `CampaignService` | Gestion de campanas masivas de WhatsApp |
| `IvaCalculationService` | Calculo de IVA segun condicion del cliente |
| `MatrixCommissionService` | Logica de comisiones entre matriz y franquicias |
| `FeedbackService` | Gestion de encuestas y feedback |
| `GoogleMapsService` | Geocodificacion via Google Maps API |
| `NominatimService` | Geocodificacion fallback via OpenStreetMap |
| `VerificationCodeService` | Generacion y validacion de codigos de verificacion |
| `ScheduleNormalizer` | Normalizacion de horarios |

### Controllers

#### Admin (`app/Http/Controllers/Admin/`)

| Controller | Descripcion |
|-----------|-------------|
| `DashboardController` | Dashboard administrativo con metricas |
| `InvoiceController` | CRUD facturas, PDF, email, WhatsApp |
| `IvaReportController` | Reportes de IVA (libro IVA ventas) |
| `CadetePaymentController` | Gestion de pagos a cadetes |
| `CollectionPoolController` | Pool de cobranza |
| `FeedbackController` | Encuestas de satisfaccion |
| `MatrixReceivableController` | Cuentas por cobrar franquicias |
| `PaymentMethodController` | Metodos de pago |
| `SystemSettingsController` | Configuracion IVA y sistema |
| `WhatsAppCampaignController` | Campanas masivas WhatsApp |

#### Cadete (`app/Http/Controllers/Cadete/`)

| Controller | Descripcion |
|-----------|-------------|
| `CadeteController` | Entregas, ganancias, perfil |
| `CadetePaymentController` | Pagos del cadete |
| `DashboardController` | Dashboard del cadete |
| `FcmTokenController` | Registro de tokens FCM |

#### Cliente (`app/Http/Controllers/Client/`)

| Controller | Descripcion |
|-----------|-------------|
| `ClientDashboardController` | Panel del cliente: envios, saldo, transacciones |

#### Publico (`app/Http/Controllers/Public/`)

| Controller | Descripcion |
|-----------|-------------|
| `PublicCommissionController` | Tracking publico de envios |
| `PublicCustomerController` | Registro de clientes |

### Middleware

| Middleware | Descripcion |
|-----------|-------------|
| `UserAdminMiddleware` | Permite solo roles ADMINISTRADOR y MOSTRADOR |
| `CadeteMiddleware` | Permite solo roles CADETE y CADETE_EXTERNO |
| `AdminOrCadeteMiddleware` | Permite admin + cadete |
| `ClientAuthMiddleware` | Autenticacion de clientes |
| `FranchiseScopeMiddleware` | Aislamiento de datos por franquicia |
| `HandleCors` | CORS headers |

### Rutas API

Archivo: `routes/api.php`

#### Rutas Publicas (sin auth)

| Metodo | Ruta | Descripcion |
|--------|------|-------------|
| POST | `/login` | Login |
| POST | `/logout` | Logout |
| POST | `/validate-identifier` | Validar identificador de cliente |
| POST | `/verify-code` | Verificar codigo |
| GET | `/destinations` | Listar destinos |
| GET | `/tracking/{commission}` | Tracking publico |
| POST | `/customers/register` | Registro de cliente |

#### Rutas Admin (auth:sanctum + isAdmin + franchiseScope)

| Prefijo | Recursos |
|---------|----------|
| `/admin/users` | CRUD usuarios |
| `/admin/customers` | CRUD clientes |
| `/admin/commissions` | CRUD comisiones, asignacion cadete, estados |
| `/admin/transports` | CRUD gestiones |
| `/admin/branches` | CRUD sucursales |
| `/admin/locations` | CRUD ubicaciones |
| `/admin/expenses` | CRUD gastos |
| `/admin/expense-categories` | CRUD categorias gastos |
| `/admin/incomes` | CRUD ingresos |
| `/admin/income-categories` | CRUD categorias ingresos |
| `/admin/current-accounts` | Cuenta corriente |
| `/admin/invoices` | Facturacion ARCA |
| `/admin/iva-report` | Reporte IVA |
| `/admin/dashboard` | Dashboard stats |
| `/admin/cadete-payments` | Pagos a cadetes |
| `/admin/collection-pool` | Pool cobranza |
| `/admin/whatsapp-campaigns` | Campanas WhatsApp |
| `/admin/feedback` | Feedback/encuestas |
| `/admin/franchises` | Franquicias |
| `/admin/matrix-receivables` | Cuentas por cobrar |
| `/admin/system-settings` | Config sistema |
| `/admin/payment-methods` | Metodos de pago |

#### Rutas Cadete (auth:sanctum + cadete)

| Prefijo | Recursos |
|---------|----------|
| `/cadete/dashboard` | Dashboard |
| `/cadete/deliveries` | Entregas asignadas |
| `/cadete/earnings` | Ganancias |
| `/cadete/profile` | Perfil |
| `/cadete/payments` | Pagos |
| `/cadete/fcm-token` | Token FCM |

#### Rutas Cliente (auth:sanctum)

| Prefijo | Recursos |
|---------|----------|
| `/client/dashboard` | Envios, saldo, transacciones |

### Base de Datos

58 migraciones. Tablas principales:

| Tabla | Descripcion |
|-------|-------------|
| `users` | Usuarios con roles, salario, contrato |
| `branches` | Sucursales |
| `customers` | Clientes con CUIT, IVA, saldo |
| `commissions` | Comisiones/envios |
| `commission_items` | Items de comision |
| `commission_logs` | Historial de estados |
| `current_accounts` | Movimientos cuenta corriente |
| `destinations` | Tarifas por destino |
| `transports` | Gestiones de transporte |
| `locations` | Ubicaciones GPS |
| `shipment_locations` | Ubicaciones de envio |
| `expenses` / `expense_categories` | Gastos y categorias |
| `incomes` / `income_categories` | Ingresos y categorias |
| `extraordinary_commissions` | Comisiones especiales |
| `invoices` | Facturas electronicas |
| `franchises` | Franquicias |
| `matrix_receivables` | Cuentas por cobrar franquicias |
| `whatsapp_campaigns` / `whatsapp_campaign_messages` | Campanas WhatsApp |
| `fcm_tokens` | Tokens Firebase |
| `feedback_surveys` | Encuestas |
| `system_settings` | Config (IVA, etc.) |
| `cadete_payments` | Pagos a cadetes |
| `delivery_signatures` | Firmas digitales |
| `notifications` | Notificaciones |
| `personal_access_tokens` | Tokens Sanctum |

### Tests

71+ tests automatizados.

```bash
# Correr todos los tests
php artisan test

# Correr un test especifico
php artisan test --filter=CommissionTest

# Con coverage
php artisan test --coverage
```

| Categoria | Tests |
|-----------|-------|
| Auth | Login, Logout |
| Commissions | CRUD, filtros, estados, asignacion cadete, eliminacion, entregas fallidas |
| Customers | CRUD, filtros, calculo IVA, balance |
| Current Account | Balance, transacciones, integracion |
| Cadete | Entregas, ganancias, perfil, pagos, actualizacion estado |
| Destinations, Expenses, Incomes | CRUD |
| Locations, Transports, Users | CRUD |
| Franchises | CRUD, scoping |
| WhatsApp | Servicio, campanas |
| Invoice | Emision, IVA |
| Feedback | Encuestas |
| Integracion | Flujos end-to-end |

---

## Frontend (ryr-front)

**Stack:** React 19 + Vite 6.3 + TailwindCSS 3.4 + React Router 7.6

Dev server: `http://localhost:3000`

### Dependencias Principales

| Paquete | Uso |
|---------|-----|
| `@tanstack/react-query` | Cache y estado del servidor |
| `react-router-dom` | Ruteo SPA |
| `react-hook-form` | Formularios |
| `react-select` | Selects avanzados |
| `chart.js` + `react-chartjs-2` | Graficos del dashboard |
| `react-hot-toast` / `react-toastify` | Notificaciones |
| `jspdf` + `jspdf-autotable` | Exportar reportes a PDF |
| `xlsx` | Exportar a Excel |
| `html5-qrcode` | Escaneo de QR |
| `date-fns` | Manejo de fechas |

### Paginas

33 paginas en `src/pages/`:

| Pagina | Ruta | Descripcion |
|--------|------|-------------|
| `Landing` | `/` | Landing publica |
| `Login` | `/login` | Login |
| `Tracking` | `/tracking/:id` | Tracking publico |
| `Dashboard` | `/admin/dashboard` | Dashboard administrativo |
| `FranchiseDashboard` | `/admin/franchise-dashboard` | Dashboard franquicia |
| `ListadoComisiones` | `/admin/comisiones` | Listado de comisiones |
| `DetalleComision` | `/admin/comisiones/:id` | Detalle de comision |
| `NuevaComision` | `/admin/nueva-comision` | Crear comision |
| `NuevaComisionRapida` | `/admin/nueva-comision-rapida` | Comision rapida |
| `ComisionesExtraordinarias` | `/admin/extraordinarias` | Comisiones especiales |
| `Usuarios` | `/admin/usuarios` | Gestion usuarios |
| `Clientes` | `/admin/clientes` | Gestion clientes |
| `Sucursales` | `/admin/sucursales` | Sucursales |
| `Locaciones` | `/admin/locaciones` | Ubicaciones |
| `Destinos` | `/admin/destinos` | Destinos/tarifas |
| `Transportes` | `/admin/transportes` | Gestiones |
| `Gastos` | `/admin/gastos` | Gastos |
| `CategoriasGastos` | `/admin/categorias-gastos` | Categorias gastos |
| `Ingresos` | `/admin/ingresos` | Ingresos |
| `CategoriasIngresos` | `/admin/categorias-ingresos` | Categorias ingresos |
| `Balances` | `/admin/balances` | Balances |
| `Facturacion` | `/admin/facturacion` | Facturacion electronica |
| `ReporteIva` | `/admin/reporte-iva` | Libro IVA ventas |
| `MatrixReceivables` | `/admin/matrix-receivables` | Cuentas por cobrar |
| `PoolCobranza` | `/admin/pool-cobranza` | Pool de cobranza |
| `PanelCobradores` | `/admin/panel-cobradores` | Panel cobradores |
| `CampanasWhatsApp` | `/admin/campanas-whatsapp` | Campanas WhatsApp |
| `FeedbackDashboard` | `/admin/feedback` | Dashboard feedback |
| `NuevoPresupuesto` | `/admin/nuevo-presupuesto` | Presupuestos |
| `SinCotizar` | `/admin/sin-cotizar` | Items sin cotizar |
| `ClientDashboard` | `/client/dashboard` | Panel del cliente |
| `Privacy` | `/privacy` | Politica privacidad |
| `Terms` | `/terms` | Terminos y condiciones |

### Componentes

33 componentes en `src/components/`:

**Navegacion:**
- `Navbar` — Barra superior
- `Sidebar` — Menu lateral con 7 secciones colapsables (Panel, Operaciones, Finanzas, Cobranza, Marketing, Franquicias, Configuracion)
- `Footer` — Pie de pagina
- `ClientHeader` — Header del portal cliente

**Comisiones:**
- `CommissionModal` — Modal nueva comision
- `EditCommissionModal` — Editar comision
- `CadeteAssignmentModal` — Asignar cadete
- `CadeteCommissionsList` — Comisiones del cadete
- `CadeteInfoCard` — Info del cadete
- `TrackingModal` — Modal de tracking

**Facturacion:**
- `InvoicePdfPreview` — Vista previa factura con acciones: Descargar PDF, Email, WhatsApp

**Pagos:**
- `PaymentForm` — Formulario de pago
- `PaymentMethodModal` — Selector metodo pago
- `PaymentFilters` — Filtros de pago
- `BankAccountModal` — Datos bancarios
- `CadetePaymentsModal` — Pagos cadete

**Formularios:**
- `GastoForm` / `GastosTable` — Gastos
- `IngresoForm` / `IngresosTable` — Ingresos
- `SucursalForm` — Sucursales
- `ComisionesExtraordinarias` — Comisiones especiales
- `LandingQuoteForm` — Cotizador publico

**Firma Digital:**
- `SignatureBadge` / `SignatureDetailsModal` / `SignatureInfo` — Firma de entrega

**Otros:**
- `QRScanner` / `QRCodeModal` — QR
- `DateRangePicker` — Rango de fechas
- `FloatingSearch` — Busqueda flotante
- `ClientRegistrationModal` — Registro cliente
- `WebLoginModal` — Login web
- `LanguageSwitcher` — Selector idioma
- `GlobalLoading` — Indicador de carga global

### Hooks

10 hooks personalizados en `src/hooks/`:

| Hook | Funciones |
|------|-----------|
| `useInvoices` | fetchInvoices, emitirFactura, anularFactura, getInvoicePdf, sendInvoiceEmail, sendInvoiceWhatsApp, consultarCuit |
| `useIvaReport` | Generacion libro IVA ventas |
| `useCampaigns` | CRUD campanas WhatsApp, envio, preview |
| `useCadeteAssignment` | Asignar/desasignar cadete a comision |
| `useFeedback` | Encuestas, respuestas, metricas |
| `useFranchises` | CRUD franquicias |
| `useMatrixReceivables` | Cuentas por cobrar matriz-franquicia |
| `usePaymentMethods` | CRUD metodos de pago |
| `useSucursales` | CRUD sucursales |
| `useLanguage` | Cambio de idioma |

### API Client

`src/api/apiClient.js` — Wrapper sobre `fetch()`:

- Token management automatico (Bearer token via `localStorage`)
- Manejo de errores con logging detallado
- Soporte FormData para uploads
- Tokens separados para web (landing) y app (admin)
- Auto-redirect en 401 (token expirado)

`src/api/config.js` — `API_BASE_URL` configurable

---

## App Movil (ryr_cadetes_app)

App Flutter para cadetes y cadetes externos.

### Funcionalidades

- Dashboard con estadisticas y resumen de ganancias
- Lista de entregas con filtros y busqueda
- Calculo de distancia GPS en tiempo real
- Captura de firma digital de entrega
- Historial de pagos y pagos proximos
- Historial completo de entregas
- Estadisticas de rendimiento
- Push notifications (FCM)

### Plataformas

- Android
- iOS
- Web (PWA)

---

## Roles y Autenticacion

### Roles del Sistema

| Rol | Acceso | Descripcion |
|-----|--------|-------------|
| `ADMINISTRADOR` | Admin completo | Acceso total al sistema (matriz) |
| `ADMIN_FRANQUICIA` | Admin franquicia | Admin limitado a su franquicia |
| `MOSTRADOR` | Admin operativo | Mismo acceso que admin (personal de mostrador) |
| `CADETE` | App movil + panel cadete | Cadete interno, entregas y ganancias |
| `CADETE_EXTERNO` | App movil + panel cadete | Cadete externo/freelance |
| `COBRADOR` | Panel cobradores | Gestion de cobranzas |
| `CLIENTE` | Portal cliente | Ver envios, saldo y transacciones |

### Flujo de Autenticacion

1. `POST /login` con email/password o identificador
2. Backend valida credenciales y genera token Sanctum
3. Token se almacena en `localStorage`
4. Todas las requests llevan header `Authorization: Bearer {token}`
5. Token expira en 1440 minutos (24 horas)
6. Verificacion por codigo disponible para clientes

---

## Funcionalidades Principales

### Comisiones

Core del negocio. Flujo completo de envios:

```
PENDIENTE → PRESUPUESTADO → ACEPTADO → CADETE_ASIGNADO → RETIRADO → ENTREGADO
                                                        → RETIRADO_SUCURSAL → ENCOMIENDA_RETIRADA
                                    → CANCELADO
                         → PAGO_VALIDACION → PAGADO → PAGO_CONFIRMADO
```

- Creacion normal y rapida
- Asignacion de cadete
- Tracking publico por ID
- Items con tipo (paquete, sobre, documento) y tamano
- Multiples metodos de pago
- Calculo automatico de IVA segun condicion del cliente
- Comisiones extraordinarias (fuera de tarifa)
- Log de cambios de estado (auditoria)

### Facturacion Electronica (ARCA)

Integracion con ARCA (ex-AFIP) para emision de comprobantes electronicos.

**Tipos de comprobante:** Factura A/B/C, Nota de Debito A/B/C, Nota de Credito A/B/C

**Funcionalidades:**
- Emision de facturas con CAE
- Facturacion de comisiones (desde estado PAGO_VALIDACION)
- Facturacion de ingresos (pagos confirmados)
- Anulacion de facturas
- Generacion de PDF server-side (dompdf)
- Envio por email con PDF adjunto
- Envio por WhatsApp con PDF adjunto (Green API)
- Consulta de CUIT a ARCA
- Modo mock para desarrollo (`ARCA_MOCK=true`)
- Reporte IVA / Libro IVA Ventas

### WhatsApp (Green API)

Mensajeria via Green API Business.

**Funcionalidades:**
- Envio de mensajes de texto
- Envio de archivos por URL
- Upload directo de archivos (PDF, imagenes)
- Notificaciones automaticas de cambio de estado de comision
- Notificacion de creacion de comision
- Campanas masivas con segmentacion por ciudad/historial
- Variables dinamicas en mensajes (nombre, monto, etc.)
- Preview de mensajes antes de enviar
- Estados de campana: borrador → programada → enviando → completada/fallida

### Notificaciones Push (FCM)

Firebase Cloud Messaging para la app movil de cadetes.

- Registro de tokens FCM por dispositivo
- Notificaciones de asignacion de entrega
- Notificaciones de cierre de ubicacion
- Comando artisan `NotifyCadetesLocationClosing`

### Sistema de Franquicias

Multi-tenancy a nivel de franquicia.

- Cada franquicia tiene sus propios datos aislados via `FranchiseScopeMiddleware`
- Dashboard especifico por franquicia
- Cuentas por cobrar matriz-franquicia (MatrixReceivables)
- Admin de franquicia con acceso limitado a su scope
- Marcado masivo de cuentas como pagadas

### Geolocalizacion

- Google Maps API para geocodificacion
- Nominatim (OpenStreetMap) como fallback
- Coordenadas GPS en ubicaciones
- Calculo de distancia entre puntos
- Agrupacion por origen

### Feedback y Encuestas

- Creacion de encuestas de satisfaccion
- Envio por enlace con token unico
- Dashboard con metricas y analisis
- Respuestas anonimas

---

## Integraciones Externas

| Servicio | Uso | Config |
|----------|-----|--------|
| **ARCA/AFIP** | Facturacion electronica | `ARCA_*` env vars |
| **Green API** | WhatsApp Business | `WHATSAPP_API_*` env vars |
| **Firebase** | Push notifications (FCM) | `FIREBASE_CREDENTIALS_PATH` |
| **Google Maps** | Geocodificacion | `GOOGLE_MAPS_API_KEY` |
| **Nominatim/OSM** | Geocodificacion fallback | Sin API key |
| **Groq** | AI (futuro) | `GROQ_API_KEY` |

---

## Comandos Utiles

### Backend

```bash
# Servidor de desarrollo
php artisan serve

# Migraciones
php artisan migrate
php artisan migrate:fresh --seed

# Tests
php artisan test
php artisan test --filter=CommissionTest

# Analisis estatico
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix

# Cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Rutas
php artisan route:list
php artisan route:list --path=admin/invoices

# Tinker
php artisan tinker

# Telescope (debug)
php artisan telescope:install
```

### Frontend

```bash
# Dev server (port 3000)
npm run dev

# Build produccion
npm run build

# Preview build
npm run preview

# Tests
npm test

# Lint
npm run lint
```

### Docker

```bash
docker-compose up -d
docker-compose down
docker exec -it ryrapp bash
docker exec -it ryrapp php artisan migrate
docker exec -it ryrapp php artisan test
```

---

## Migracion de Base de Datos Legacy

Scripts disponibles para migrar datos del sistema anterior:

```bash
# En el contenedor Docker
./migrate_background.sh      # Migracion en background
./migrate_low_memory.sh      # Migracion optimizada para bajo consumo de memoria
./migrate_optimized.sh       # Migracion optimizada
./scripts/import-old-database.sh  # Importar BD legacy
```

La BD legacy corre en `mysql_old` (puerto 3307) y se migra a `mysql` (puerto 3306).

---

## Licencia

Proyecto privado. Todos los derechos reservados.

Desarrollado por **Innova Developers**.
