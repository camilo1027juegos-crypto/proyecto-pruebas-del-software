# Rubicam — Propuesta de Rediseño (Landing Page)

Código propietario: HTML5 semántico + Tailwind CSS (CDN) + JavaScript nativo modular
+ PHP 8.2+/8.3 nativo. Sin maquetadores (WPBakery, Elementor, etc.).

## Cómo probarlo

```bash
php -S localhost:8000
```
Abre `http://localhost:8000/`.

## Estructura

```
rubicam-redesign/
├── config/
│   ├── config.php      # Datos corporativos, constantes (editar aquí NIT, sedes, teléfonos, etc.)
│   └── security.php     # CSRF, sanitización, rate limiting, cabeceras seguras
├── handlers/
│   └── quote-handler.php # Backend del cotizador (mismo estándar de seguridad ya validado)
├── assets/
│   ├── css/custom.css   # Complementa Tailwind (patrón de fondo, focos, etc.)
│   └── js/app.js        # Menú móvil, modales, validación, tracking demo, chat Rubi
├── logs/
├── .htaccess
└── index.php             # Landing page completa
```

## 0. Corrección importante: Tailwind ya NO depende de un CDN

**Qué pasó:** la primera versión cargaba Tailwind vía `<script src="https://cdn.tailwindcss.com">`
(el "Play CDN"), pensado solo para prototipos rápidos, no para producción. Si el navegador
no tenía salida a internet o el CDN estaba bloqueado, ninguna clase de Tailwind se aplicaba,
y los íconos SVG (que dependían de clases como `w-4 h-4` para su tamaño) se renderizaban a
su tamaño por defecto del navegador — hasta 300×150px — produciendo un bloque negro gigante
cubriendo la página.

**Solución aplicada:**
1. Tailwind ahora se **compila localmente** a un archivo CSS estático
   (`assets/css/tailwind.build.css`, ~25 KB minificado) — cero dependencias de red en
   tiempo de ejecución, tal como corresponde a "código listo para producción".
2. Se agregó una **red de seguridad CSS inline** (`<style>svg{width:1.25em;...}</style>`)
   en el `<head>`, con menor especificidad que las clases de Tailwind, así que nunca
   interfiere cuando el CSS carga bien — pero si algún día el archivo compilado no se
   sube o su ruta se rompe, los íconos quedan en un tamaño razonable en vez de gigante.

### Compilar Tailwind (tras editar clases en `index.php` o `assets/js/app.js`)

```bash
npm install                 # una sola vez (instala tailwindcss)
npm run build:css           # recompila assets/css/tailwind.build.css
```

Si agregas una clase nueva de Tailwind al HTML/JS y no la ves en el sitio, es casi
seguro que falta recompilar con el comando de arriba — el archivo `.build.css` es
estático, no se genera en tiempo real como con el CDN.

## 1. Componentes interactivos — especificación técnica

### Cotizador (`#quote-form` + `assets/js/app.js`)
- **Validación en cliente**: operación requerida, valor CIF numérico (regex `^\d+(\.\d{1,2})?$`),
  nombre no vacío, email con regex RFC-simplificado, teléfono `^[0-9+\-\s()]{7,20}$`.
  Errores se muestran inline por campo (`.quote-error`), sin alerts bloqueantes.
- **Envío**: `fetch()` con `FormData`, header `X-Requested-With`, respuesta JSON.
  Doble validación: cliente (UX) + servidor (`handlers/quote-handler.php`, autoritativa).
- **Backend**: CSRF (`hash_equals`), honeypot invisible (`name="website"`), rate limiting
  (5 envíos / 10 min por IP), sanitización estricta de cada campo, envío de correo con
  `From:` fijo del dominio propio y `Reply-To:` del usuario (previene spoofing).

### Tracking de DO (`#tracking-modal` + `#tracking-form`)
- **Estado actual: DEMO en el front-end.** Genera un estado determinístico a partir del
  radicado ingresado (hash simple) para simular la experiencia visual. **No consulta una
  base de datos real** — eso requiere el sistema Lobby completo (ver Roadmap abajo).

### Chat "Rubi" (`#rubi-panel` + `assets/js/app.js`)
- UI de chat completa (burbujas, scroll, input) funcionando en el cliente.
- Si `CHAT_WEBHOOK_URL` (variable de entorno, en `config/config.php`) está configurada,
  cada mensaje del usuario se envía por `POST` JSON `{message, source}` al webhook de n8n
  y se muestra `data.reply` como respuesta. Si no está configurada, responde con un
  mensaje de cortesía local para no romper la demo.
- **Pendiente de producción**: configurar `RUBI_WEBHOOK_URL` en el servidor y decidir el
  formato de respuesta esperado del flujo n8n (aquí se asume `{ "reply": "texto" }`).

## 2. Roadmap — Ecosistema interno (Lobby / DO / MySQL)

Este entregable cubre la **landing page pública**. El panel privado ("Lobby") con control
real de Órdenes de Operación, semáforo de vencimientos a 3 meses y autenticación de
usuarios es un sistema aparte que requiere:
- Esquema MySQL (tabla `ordenes_operacion` con radicado, estado, fecha_vencimiento, cliente_id…)
- Autenticación (sesiones PHP + hashing `password_hash()`/`password_verify()`)
- CRUD de DOs + lógica de semáforo (verde/amarillo/rojo según días a vencimiento)
- El botón "Ingreso / Lobby" en el navbar y footer ya apunta a `/lobby/` (constante
  `LOBBY_URL` en `config/config.php`) — listo para conectar cuando ese sistema exista.

Puedo construir el Lobby como una segunda fase si quieres que continúe con eso.

## 3. Notas de diseño

- Paleta aplicada estrictamente: `#082d42`/`#0b3954` (azul), `#065f46`/`#047857` (verde),
  `#f97316` (naranja), fondos `#f8fafc`/`#f1f5f9`, texto `#1e293b`.
- Tipografía: Plus Jakarta Sans (headings/UI) vía Google Fonts.
- Tailwind se carga vía CDN (`cdn.tailwindcss.com`) para desarrollo rápido sin build step;
  para producción de alto tráfico se recomienda migrar a Tailwind CLI/PostCSS compilado
  (reduce el peso de página al eliminar clases no usadas).
- Accesibilidad: skip-link, `aria-label`/`aria-expanded` en menú y modales, foco visible,
  `role="dialog"` + cierre con `Esc`.

## 4. Panel operativo de DOs (`/panel`)

### Instalación (XAMPP)
1. Importar `SQL/rubicam_db.sql` (instalación nueva) **o**, si la base ya existe,
   ejecutar una sola vez `SQL/migracion_panel_v2.sql` (actualiza los tipos de documento
   del legajo: Declaración de Importación, Factura Comercial, Lista de Empaque, RUT,
   BL/Guía aérea, Otro).
2. La configuración vive en `config/config_db.php` (BD, `N8N_WEBHOOK_URL`, `UPLOAD_DIR`).
   Ya **no** existe `panel/config_db.php`: si quedó una copia antigua, bórrela.
3. Si agrega clases de Tailwind al panel: `npm run build:css`
   (`tailwind.config.js` ya incluye `./panel/**/*.php`).

### Archivos
| Archivo | Función |
|---|---|
| `panel/index.php` | Login (sesión segura, `session_regenerate_id`). |
| `panel/dashboard.php` | Tabla de DOs, semáforo, KPIs, filtros y 3 modales. Endpoints AJAX internos: `?action=listar`, `crear` (POST), `documentos`, `descargar`. |
| `panel/logout.php` | Cierre de sesión (vacía sesión, borra cookie, `session_destroy`, redirige a `index.php`). |
| `handlers/webhook_trigger.php` | POST JSON → actualiza `procesos_do` (estado + bitácora) y alerta a n8n por cURL. Responde siempre `{status, message}`. |
| `handlers/upload_documento.php` | POST multipart (`documento`) → valida extensión, MIME real, firma y tamaño (10 MB), guarda en `/uploads` y registra en `documentos_do`. |

### Semáforo DIAN (días transcurridos desde la apertura)
- Verde «Al día»: menos de 30 días.
- Naranja «Seguimiento»: de 30 a 75 días.
- Rojo «Crítico»: más de 75 días, **o** fecha de vencimiento ya superada («Vencido»).
- Neutro «Cerrado»: estados Finalizada, Nacionalizado o Cerrado sin facturar (no generan alerta).

### Roles
`admin` y `operador` pueden crear DOs, cambiar estados y subir documentos.
`consulta` solo ve la tabla, la bitácora y descarga documentos.

### n8n
- El payload incluye `evento`, `do_id`, `do_codigo`, `cliente`, `email`, `estado_anterior`,
  `nuevo_estado`, `observaciones`, `dias_transcurridos`, `nivel_alerta`, `usuario`, `fecha_evento`.
- Si n8n falla, el estado **sí** queda guardado y el panel muestra un aviso naranja con el motivo.
- URL `/webhook/...` exige el workflow **activo**; `/webhook-test/...` solo con "Listen for test event".
- XAMPP en Windows: si aparece «falló la verificación SSL», descargue `cacert.pem`
  (https://curl.se/ca/cacert.pem) y en `php.ini` configure `curl.cainfo` y `openssl.cafile`
  con su ruta (o defina `N8N_CA_BUNDLE`). Reinicie Apache.

### Seguridad
- Todas las acciones de escritura exigen sesión, rol y token CSRF (cabecera `X-CSRF-Token`).
- `/uploads` tiene `.htaccess` con `Require all denied`: los archivos solo se descargan por
  `dashboard.php?action=descargar` (con sesión).
- Errores se registran en `logs/php-error.log` (bloqueado por `.htaccess`).
