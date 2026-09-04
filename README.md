# Tienda Samir

Aplicación web para gestión de ventas y facturación electrónica colombiana, desarrollada como prueba técnica de desarrollo. La aplicación permite crear ventas, seleccionar clientes y productos, generar facturas locales y validarlas electrónicamente mediante la integración con **Factus API**.

## Descripción

Sistema completo de punto de venta que cubre el flujo:

```
Cliente → Productos → Creación de venta → Factura local (draft) → Envío a Factus → Validación DIAN → Persistencia de respuesta → Consulta de factura validada
```

La aplicación utiliza **Factus Sandbox** como proveedor de facturación electrónica, integrándose con la API v1 para validar facturas ante la DIAN. Las credenciales de acceso se gestionan mediante variables de entorno y nunca se exponen en el código.

## Características

- **Gestión de clientes** con campos mapeados a catálogos Factus (identificación, tributos, municipios).
- **Catálogo de productos** con precios sin IVA, unidades de medida y tributos configurados.
- **Creación de ventas** con selección de cliente, productos y cantidades.
- **Cálculo automático** de subtotales, impuestos (IVA 19%) y total de la venta.
- **Factura local** en estado `draft` con referencia única, ítems, impuestos y pago.
- **Integración con Factus Sandbox** para validación de facturas electrónicas.
- **Persistencia de datos Factus**: número de factura, CUFE, código QR, imagen QR, estado y URL pública.
- **Consulta de factura validada** mediante el endpoint `GET /v1/bills/show/{factus_number}`.
- **Visualización de factura** con redirección al documento público de Factus.
- **Manejo de errores y advertencias** de Factus (ej: FAJ43b).
- **Registro de auditoría** completo de solicitudes realizadas a Factus (`factus_requests`).
- **Dashboard** con KPIs reales (total facturado, validadas, rechazadas, ticket promedio), distribución por estado, tasa de éxito de Factus, últimas 5 facturas y top 3 productos.
- **Autenticación** con Fortify (registro, login, verificación email, 2FA, passkeys).
- **Suite de 96 pruebas automatizadas** con Pest.

## Tecnologías

| Componente | Versión |
|---|---|
| PHP | 8.3+ |
| Laravel | 13.x |
| Vue.js | 3.5 |
| Inertia.js | 3.0 |
| Vite | 8.0 |
| Tailwind CSS | 4.1 |
| SQLite | (base de datos por defecto) |
| Factus API | Sandbox v1 |
| Pest | 5.1 |
| Laravel Pint | 1.27 |
| Larastan | 3.9 |
| Laravel Wayfinder | 0.1 |
| Node.js | 24.x |
| NPM | 11.x |

## Arquitectura

```
┌─────────────────────────────────────┐
│        Frontend (Vue / Inertia)     │
│  resources/js/pages                 │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│     Controllers / Form Requests     │
│  VentaController, DashboardController│
│  StoreVentaRequest                  │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│            Services                 │
│  VentaService (reglas de negocio)   │
│  FactusService (integración API)    │
│  CustomerFieldMapper (mapeo campos) │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│         Models / Database           │
│  Invoice, InvoiceItem, Customer,    │
│  Product, FactusRequest, etc.       │
└─────────────────────────────────────┘
```

**Flujo Factus:**

```
VentaController::sendToFactus()
  → FactusService::sendInvoice()
    → FactusService::buildPayload()
    → FactusService::mapCustomer()  ← CustomerFieldMapper
    → FactusService::mapItems()
    → HTTP POST → Factus API /v1/bills/validate
    → FactusService::saveResponse()
    → FactusRequest::create()  (auditoría)
```

La integración con Factus está completamente aislada en `FactusService`. El mapeo de campos de cliente a catálogos Factus se maneja en `CustomerFieldMapper`, que lanza excepciones ante códigos desconocidos para evitar envíos inconsistentes.

## Requisitos previos

- PHP 8.3 o superior (con extensiones: cURL, mbstring, openssl, pdo, tokenizer, xml)
- Composer 2.x
- Node.js 20+ y NPM
- Cuenta de sandbox en [Factus](https://developers.factus.com.co/) con `client_id`, `client_secret`, email y password

## Instalación

```bash
# Clonar el repositorio
git clone <URL_DEL_REPOSITORIO>
cd tienda_samir

# Instalar dependencias PHP
composer install

# Instalar dependencias JS
npm install

# Configurar archivo de entorno
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Crear base de datos SQLite (si no existe)
touch database/database.sqlite

# Ejecutar migraciones y seeders
php artisan migrate --force

# Compilar assets del frontend
npm run build
```

## Configuración de Factus Sandbox

Abrir el archivo `.env` y configurar las credenciales de Factus:

```env
# Factus API (sandbox)
FACTUS_API_URL=https://api-sandbox.factus.com.co
FACTUS_CLIENT_ID=tu_client_id_de_sandbox
FACTUS_CLIENT_SECRET=tu_client_secret_de_sandbox
FACTUS_USERNAME=tu_email_de_sandbox
FACTUS_PASSWORD=tu_password_de_sandbox
```

> **Nota:** Las credenciales se obtienen al registrar una aplicación en el portal de desarrollo de Factus: https://developers.factus.com.co/

## Ejecución

```bash
# Iniciar servidor de desarrollo (frontend + backend)
composer run dev
```

La aplicación estará disponible en `http://localhost:8000`.

## Base de datos

El proyecto utiliza **SQLite** por defecto. La base de datos se crea automáticamente en `database/database.sqlite` durante la instalación.

El seeder `DatabaseSeeder` crea:
- Unidades de medida (con `factus_id` para el catálogo Factus)
- Impuestos (IVA 19%)
- 2 clientes de ejemplo (CC y NIT, con campos mapeados a Factus)
- 2 productos de ejemplo (con precio, tributos y unidad de medida)
- Rango de numeración activo para Factus (`factus_id = 8`)
- 1 usuario de prueba (`test@example.com`)

> **Nota:** Las bases de datos SQLite están en `.gitignore` y no se incluyen en el repositorio.

## Flujo completo de una venta

### 1. Crear venta

**Ruta:** `GET /ventas`

El formulario muestra los clientes y productos disponibles. Se selecciona un cliente, se agregan productos con cantidades y se envía el formulario.

**Endpoint:** `POST /api/ventas`

```json
{
  "customer_id": 1,
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

`VentaService::create()` ejecuta todo dentro de una transacción:
1. Valida que el cliente exista y esté activo
2. Resuelve y valida los productos (existentes y activos)
3. Calcula subtotales e impuestos (`price * quantity` + IVA 19%)
4. Busca el rango de numeración activo (`factus_id = 8`)
5. Crea la factura en estado `draft`
6. Crea los ítems con sus impuestos (`invoice_items` + `invoice_item_taxes`)
7. Crea el registro de pago (`payment_form = 1`, `payment_method_code = 10`)

### 2. Enviar a Factus

**Endpoint:** `POST /api/ventas/{invoice}/factus`

Solo se permiten facturas en estado `draft`. `FactusService::sendInvoice()`:
1. Carga relaciones del invoice (customer, items, payments, etc.)
2. Construye el payload para la API de Factus
3. Mapea campos del cliente usando `CustomerFieldMapper`
4. Resuelve `unit_measure_id` del catálogo Factus
5. Resuelve `tribute_id` del ítem
6. Envía POST a `https://api-sandbox.factus.com.co/v1/bills/validate`
7. Registra la solicitud en `factus_requests` (auditoría)
8. Si es exitoso: guarda número Factus, CUFE, QR, estado y fecha de validación
9. Si falla: marca la factura como `rejected` con los errores de Factus

### 3. Consultar factura validada

**Ruta:** `GET /ventas/{invoice}/document`

`VentaController::document()` verifica que la factura esté validada, consulta el endpoint `GET /v1/bills/show/{factus_number}` de Factus y redirige al usuario al documento público (`public_url`).

> **Nota:** La funcionalidad de descarga de PDF no está implementada en esta versión.

## Dashboard

**Ruta:** `GET /dashboard`

El dashboard muestra indicadores calculados en tiempo real a partir de los datos de la base de datos:

- **KPIs:** Total facturado (solo validadas), facturas validadas, facturas rechazadas, ticket promedio
- **Distribución por estado:** Borrador, pendiente, validada, rechazada (gráfico de dona CSS)
- **Tasa de éxito Factus:** Solicitudes exitosas vs total (desde tabla de auditoría)
- **Últimas 5 facturas:** Número, cliente, total, estado, fecha
- **Top 3 productos:** Por cantidad vendida en facturas validadas

## Decisiones técnicas

1. **Backend como fuente de verdad fiscal:** Los valores de subtotal, IVA y total se calculan en el backend (`VentaService`). El frontend muestra una previsualización pero nunca define valores finales.

2. **Mapeo seguro de campos:** `CustomerFieldMapper` lanza excepciones ante códigos desconocidos en lugar de inventar valores. Esto previene envíos a Factus con datos incorrectos.

3. **Estado de Factus separado del estado interno:** `InvoiceStatus` (draft/pending/validated/rejected/cancelled) es el ciclo de vida interno. `factus_status` reporta el estado de la API.

4. **Factus `status=1` con `errors`:** Si Factus responde con `bill.status=1` pero incluye errores (ej: FAJ43b), se muestra como validada con advertencias, no como rechazada.

5. **Cache de token de acceso:** El token de Factus se cachea en Laravel `Cache` con un margen de seguridad de 60 segundos antes de expirar para nunca enviar un token vencido.

6. **Auditoría completa:** Cada solicitud a la API de Factus se registra en `factus_requests` con request body, response body, HTTP status y estado de éxito.

7. **SQLite para desarrollo y pruebas:** Base de datos ligera, sin configuración externa. El archivo `.sqlite` está en `.gitignore`.

8. **`dv` auto-calculado:** El dígito de verificación de clientes NIT no se envía al Factus; la API lo calcula automáticamente.

9. **Número de rango Factus `8`:** Corresponde al rango de numeración "Factura de Venta" configurado en el sandbox.

## Pruebas

```bash
# Ejecutar todas las pruebas
php artisan test --compact

# Ejecutar pruebas de un archivo específico
php artisan test --compact --filter="VentaTest"

# Ejecutar pruebas de Dashboard
php artisan test --compact --filter="Dashboard"
```

**Cobertura de pruebas (96 tests):**

| Módulo | Tests |
|---|---|
| Autenticación (Login, registro, 2FA, verificación) | 24 |
| Perfil y seguridad | 12 |
| Creación de ventas | 12 |
| Integración Factus (servicio) | 8 |
| Documento Factus (consulta, visualización) | 7 |
| Dashboard (KPIs, distribución, productos) | 12 |
| Modelos (relaciones, constraints únicos) | 13 |
| CustomerFieldMapper (mapeo de campos) | 5 |
| Ejemplo | 3 |
| **Total** | **96** |

## Código y estilo

```bash
# Verificar formato de código PHP
vendor/bin/pint --dirty --format agent

# Verificar tipos con Larastan
vendor/bin/phpstan analyse

# Verificar tipos TypeScript
npm run types:check
```

## Estructura del proyecto

```
tienda_samir/
├── app/
│   ├── Enums/
│   │   └── InvoiceStatus.php              # Estado interno de facturas
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php    # Dashboard con KPIs
│   │   │   └── VentaController.php        # Ventas + envío Factus
│   │   └── Requests/
│   │       └── StoreVentaRequest.php      # Validación de venta
│   ├── Models/
│   │   ├── Customer.php
│   │   ├── FactusRequest.php              # Auditoría de solicitudes
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── InvoiceItemTax.php
│   │   ├── InvoicePayment.php
│   │   ├── InvoiceAllowanceCharge.php
│   │   ├── NumberingRange.php
│   │   ├── Product.php
│   │   ├── Tax.php
│   │   └── UnitOfMeasure.php
│   └── Services/
│       ├── CustomerFieldMapper.php        # Mapeo de campos a IDs Factus
│       ├── FactusService.php              # Integración con API Factus
│       └── VentaService.php               # Lógica de negocio de ventas
├── config/
│   └── factus.php                         # Configuración de Factus
├── database/
│   ├── .gitignore                         # Ignora *.sqlite*
│   ├── factories/                         # Factories para testing
│   ├── migrations/                        # 24 migraciones
│   └── seeders/                           # Seeders con datos de ejemplo
├── resources/js/
│   └── pages/
│       ├── Dashboard.vue                  # Dashboard con KPIs reales
│       └── ventas/
│           └── Create.vue                 # Formulario de creación de venta
├── routes/
│   ├── web.php                            # Rutas web (dashboard, ventas)
│   └── settings.php                       # Rutas de perfil/seguridad
├── tests/
│   ├── Feature/
│   │   ├── Auth/                          # Pruebas de autenticación
│   │   ├── DashboardTest.php              # Pruebas de dashboard
│   │   ├── Factus/                        # Pruebas de FactusService
│   │   ├── FactusDocumentTest.php         # Pruebas de consulta de documento
│   │   ├── Models/                        # Pruebas de modelos
│   │   ├── Settings/                      # Pruebas de perfil
│   │   └── Venta/                         # Pruebas de ventas
│   └── Unit/
│       └── Services/                      # Pruebas unitarias
├── .env.example                           # Plantilla de variables de entorno
├── .gitignore                             # Archivos ignorados por git
└── composer.json                          # Dependencias PHP
```

## Licencia

MIT
