# Rubicam — Landing y panel operativo de Órdenes de Operación (DO)

Sitio público y **panel privado** de *Agencia de Aduanas Rubicam S.A.S.* PHP 8.2+ nativo (sin frameworks), MySQL/MariaDB,
Tailwind CSS compilado y webhook a n8n. Versión **2.1.0**.

Este repositorio es además el trabajo final de **Codificación y Pruebas del Software** (FESC, Ingeniería de Software): sobre un
sistema existente se aplicaron documentación y pruebas previas, TDD, refactor modular, codificación defensiva, linters,
análisis estático, mantenimiento según ISO/IEC/IEEE 14764 y buenas prácticas de despliegue. El recorrido completo está en
[`docs/INFORME_TECNICO.md`](docs/INFORME_TECNICO.md).

## Requisitos

* PHP **8.2 o superior** con `curl`, `fileinfo`, `mbstring`, `pdo_mysql` (y `zip` para validar `.docx`).
* MySQL 5.7+ / MariaDB 10.4+.
* Apache con `mod_rewrite` y `mod_headers` (XAMPP sirve) — las reglas de seguridad están en `.htaccess`.
* Solo para desarrollo: PHPUnit 9.6, PHP_CodeSniffer 3.7, PHPMD 2.15 (`composer install`) y, para cobertura, la extensión `pcov`.

## Puesta en marcha (XAMPP)

```bash
# 1) Base de datos: importar SQL/rubicam_db.sql en phpMyAdmin
#    (si la base ya existe de una versión anterior a la v2: ejecutar una vez SQL/migracion_panel_v2.sql)

# 2) Configuración local
cp .env.example .env        # y completar DB_USER / DB_PASS, etc.

# 3) Ver el sitio con el servidor embebido (ignora .htaccess; para desarrollo)
php -S localhost:8000
#   http://localhost:8000/         → landing
#   http://localhost:8000/panel/   → panel (login)
```

Despliegue en un servidor real, variables de entorno y reversión: [`docs/05_DESPLIEGUE.md`](docs/05_DESPLIEGUE.md).

## Estructura

```
rubicam-redesign/
├── index.php                  # Landing (punto de entrada delgado + HTML)
├── panel/                     # Entradas del panel: index.php (login), dashboard.php, logout.php, vistas/
├── handlers/                  # Entradas AJAX: quote-handler, upload_documento, webhook_trigger
├── config/
│   ├── arranque.php           # Autocargador, .env, zona horaria y política de errores (lo incluye toda entrada)
│   └── config.php             # Constantes del sitio público (identidad, sedes, enlaces)
├── src/                       # Lógica de la aplicación (namespace Rubicam\, PSR-4)
│   ├── Dominio/               # Reglas puras, sin I/O: Nit, SemaforoDo, Bitacora, ValidadorNuevoDo, Catalogos
│   ├── Seguridad/             # Sanitizador, LimitadorTasa, TokenCsrf, SesionPanel, CabecerasSeguras
│   ├── Http/                  # Solicitud, Respuesta, ErrorHttp, Ejecutor, SolicitudJson
│   ├── Archivos/              # PoliticaArchivos (extensión, MIME real, firma, nombre seguro)
│   ├── Infraestructura/       # Conexion (PDO), ClienteN8n, Configuracion, CargadorEntorno
│   ├── Panel/                 # Controladores, repositorios, Guardia, Despachador, ExportadorDo
│   └── Publico/               # ControladorCotizacion
├── assets/                    # css/ (Tailwind compilado) y js/ (app.js del sitio, panel.js del panel)
├── SQL/                       # Esquema y migración
├── tests/                     # Unit/, Regression/, e2e/, apache/
├── tools/                     # Escáner de seguridad estático
├── docs/                      # Especificación, casos de prueba, auditoría, mantenimiento, despliegue, informe, evidencias
├── .github/workflows/ci.yml   # Integración continua
└── .htaccess                  # Niega por defecto todo lo interno (MR-005)
```

**Regla de dependencia:** `Dominio` no conoce sesión, base de datos, HTTP ni reloj (recibe la fecha por parámetro). Los controladores
devuelven un objeto `Respuesta`; solo el punto de entrada la envía. Ninguna clase llama `exit`.

## Calidad y pruebas

```bash
composer install            # herramientas de desarrollo (en producción no hace falta)
composer lint               # PSR-12 con PHP_CodeSniffer         → 0 errores, 0 advertencias
composer analizar           # PHP Mess Detector (complejidad, código muerto, diseño)
composer seguridad          # escáner de seguridad estático propio (equivalente a bandit)
composer test               # PHPUnit: 424 pruebas unitarias y de regresión
composer test:cobertura     # ídem con cobertura (requiere pcov)
composer e2e                # 36 pruebas de extremo a extremo (necesita MySQL/MariaDB)
composer check              # lint + analizar + seguridad + test
bash tests/apache/verificar_htaccess.sh   # reglas de .htaccess contra un Apache real

phpunit --group MR-006      # solo la regresión de una solicitud de modificación
```

La suite E2E crea y borra su propia base `rubicam_e2e`: variables `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` (por defecto `root` sin clave).
Las cargas de la prueba van a una carpeta temporal (`UPLOAD_DIR`), no a `uploads/`.

## Configuración por entorno

Las variables se leen del servidor y, si existe, del archivo `.env` (no versionado; lo definido en el servidor tiene prioridad).
Lista completa en [`.env.example`](.env.example): `APP_ENV`, `APP_BASE_URL`, `DB_*`, `N8N_WEBHOOK_URL`, `N8N_WEBHOOK_SECRET`,
`N8N_CA_BUNDLE`, `RUBI_WEBHOOK_URL`, `UPLOAD_DIR`.

## Panel operativo (`/panel`)

| Archivo | Función |
|---|---|
| `panel/index.php` | Login: sesión segura, `session_regenerate_id`, 5 intentos fallidos por ventana, respuesta uniforme (no revela si el usuario existe). |
| `panel/dashboard.php` | Tabla de DO, semáforo, KPI, filtros y modales. Acciones AJAX: `?action=listar`, `crear` (POST), `documentos`, `descargar`, **`exportar`** (`&formato=csv\|json` y filtros `sucursal`, `regimen`, `nivel`). |
| `panel/logout.php` | Cierra la sesión (vacía datos, borra la cookie, destruye la sesión). |
| `handlers/webhook_trigger.php` | POST JSON → cambia el estado del DO (con bloqueo de fila), anota la bitácora y avisa a n8n. |
| `handlers/upload_documento.php` | POST multipart → valida tamaño (10 MB), extensión, MIME real y firma; guarda con nombre seguro. |

**Semáforo DIAN** (días desde la apertura): verde «Al día» < 30 · naranja «Seguimiento» 30–75 · rojo «Crítico» > 75 **o** vencimiento superado
(«Vencido») · neutro «Cerrado» (Finalizada, Nacionalizado, Cerrado sin facturar). Plazo por defecto: 90 días.

**Roles:** `admin` y `operador` crean DO, cambian estados y suben documentos; `consulta` solo lee, descarga y exporta.

**Exportar (MR-010):** los botones «Exportar CSV» y «JSON» descargan las operaciones con los filtros activos. El CSV va en UTF-8 con BOM y
separador `;` (Excel en español) y neutraliza la inyección de fórmulas (`= + - @`).

**n8n:** el payload incluye `evento`, `do_id`, `do_codigo`, `cliente`, `email`, `estado_anterior`, `nuevo_estado`, `observaciones`,
`dias_transcurridos`, `nivel_alerta`, `usuario`, `fecha_evento`. Si n8n falla, el estado **sí** queda guardado y el panel avisa el motivo.
La URL `/webhook/…` exige el workflow activo; `/webhook-test/…` solo con «Listen for test event». En XAMPP/Windows, si aparece «falló la
verificación SSL», descargue `cacert.pem` (https://curl.se/ca/cacert.pem) y configure `curl.cainfo`/`openssl.cafile` en `php.ini`
(o `N8N_CA_BUNDLE`).

## Seguridad (resumen)

* Escrituras del panel: sesión + rol + token CSRF (`X-CSRF-Token`), en ese orden (`Guardia`).
* Entradas validadas en el servidor (`Sanitizador`, `ValidadorNuevoDo`); consultas preparadas; salida escapada.
* Cabeceras: CSP estricta en el panel (`script-src 'self'`), `nosniff`, `X-Frame-Options`, `Referrer-Policy`.
* `.htaccess` niega `SQL/`, `src/`, `tests/`, `tools/`, `docs/`, `config/`, `logs/`, `.git/`, archivos `.env/.json/.md/.sql/…` y `uploads/`
  (los documentos solo se descargan por el panel con sesión).
* Errores 500 genéricos hacia el usuario; el detalle va a `logs/php-error.log`.

## Sitio público

* **Cotizador** (`#quote-form` + `assets/js/app.js`): validación en cliente (UX) y en servidor (autoritativa, `ControladorCotizacion`): CSRF,
  honeypot, límite de 5 envíos / 10 min por IP, sanitización estricta, correo con `From:` fijo y `Reply-To:` del usuario.
* **Tracking de DO:** demostración en el front-end (no consulta la base de datos).
* **Chat «Rubi»:** si `RUBI_WEBHOOK_URL` está definida envía cada mensaje por POST JSON `{message, source}` y muestra `data.reply`.

### Tailwind (compilado, sin CDN)

El CSS se compila localmente a `assets/css/tailwind.build.css`. Si agrega clases nuevas en `index.php`, `panel/` o los `.js`:

```bash
npm install          # una sola vez
npm run build:css    # recompila el CSS
```

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/01_ESPECIFICACION.md`](docs/01_ESPECIFICACION.md) | Documentación previa: arquitectura, especificación de módulos, estándares, requisitos de calidad, desviaciones |
| [`docs/02_CASOS_DE_PRUEBA.md`](docs/02_CASOS_DE_PRUEBA.md) | Pruebas previas: plan y casos (entrada → resultado esperado), regresión, E2E, Apache |
| [`docs/03_AUDITORIA_CODIGO.md`](docs/03_AUDITORIA_CODIGO.md) | Auditoría del código heredado: 19 hallazgos con tipo de error y característica ISO 25010 |
| [`docs/04_PLAN_DE_MANTENIMIENTO.md`](docs/04_PLAN_DE_MANTENIMIENTO.md) | ISO/IEC/IEEE 14764: registro MR/PR, análisis de impacto, trazabilidad |
| [`docs/05_DESPLIEGUE.md`](docs/05_DESPLIEGUE.md) | Git, ramas, Conventional Commits, CI, entornos, despliegue y reversión |
| [`docs/INFORME_TECNICO.md`](docs/INFORME_TECNICO.md) | **Informe técnico** (también en PDF): antes/después, evidencias, TDD, depuración, declaración de uso de IA |
| [`CHANGELOG.md`](CHANGELOG.md) | Cambios por versión |
| `docs/evidencias/` | Salidas reales de las herramientas (antes, después y ciclo rojo → verde de cada MR) |

Historial: `git log --oneline --graph --decorate` (cada MR es una rama con su commit `[RED]` y su commit `[GREEN]`).
