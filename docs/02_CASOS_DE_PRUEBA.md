# 02 · Plan y casos de prueba (pruebas previas a la codificación)

> **Principio aplicado:** *Principios de preparación para la codificación → Pruebas previas.*
> Estos casos (entrada → resultado esperado) se definieron **antes** de programar las clases de
> `docs/01_ESPECIFICACION.md`. Obligan a pensar desde el diseño en los casos límite
> (valor exacto en la frontera, vacío, demasiado largo, malicioso).
> Estructura inspirada en **IEEE 829-2008** (reemplazado por ISO/IEC/IEEE 29119-3): plan, casos,
> registro de ejecución (`docs/evidencias/`), informe de incidentes (`docs/04_PLAN_DE_MANTENIMIENTO.md`)
> e informe resumen (`docs/INFORME_TECNICO.md`).

## 1. Plan de pruebas

| Elemento | Contenido |
|---|---|
| **Identificador** | PP-RUBICAM-001 |
| **Objetivo** | Verificar que las reglas de negocio, la validación de entradas y la seguridad del panel Rubicam se cumplen y **no se rompen** al refactorizar o mantener el código. |
| **Elementos a probar** | Clases de `src/` y `tools/` (unitarias); defectos corregidos (regresión); aplicación completa sobre MySQL (E2E); configuración de Apache (manual/automatizada). |
| **Enfoque** | **TDD** (Red → Green → Refactor) para código nuevo; **prueba de regresión** (una prueba por defecto corregido, etiquetada con su `MR-xxx`); **caracterización** (fijar el comportamiento existente antes de moverlo); E2E de humo. |
| **Herramientas** | PHPUnit 9.6 (xUnit), PCOV (cobertura), suite E2E propia (`tests/e2e`), PHPCS/PHPMD como compuertas estáticas. |
| **Criterios de aprobación** | 100 % de casos en verde · 0 errores PHPCS (PSR-12) · 0 incumplimientos PHPMD · cobertura de líneas de `src/` ≥ 90 % · 28 casos E2E originales en verde sin modificar su resultado esperado. |
| **Criterios de suspensión** | Cualquier caso E2E original en rojo tras un cambio: se detiene el refactor y se corrige antes de continuar. |
| **Entorno** | PHP 8.2/8.3, MySQL/MariaDB 10.4+, base desechable `rubicam_e2e`. |
| **Entregables** | Este documento · código de pruebas en `tests/` · registros de ejecución en `docs/evidencias/` · `docs/INFORME_TECNICO.md`. |

### Nomenclatura

`CP-<MÓDULO>-nn` = caso de prueba unitaria (una fila ↔ un método o una fila de *data provider* de PHPUnit).
`MR-nnn` = prueba de regresión asociada a una solicitud de modificación (`@group MR-nnn`).
`E2E-nn` = caso de extremo a extremo. `CP-SEG-nn` = verificación de despliegue en Apache.

## 2. Casos de prueba unitarios

### 2.1 `Nit` — dígito de verificación (CP-NIT)

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-NIT-01 | `digitoVerificacion('890903938')` | `8` (Bancolombia) |
| CP-NIT-02 | `digitoVerificacion('800197268')` | `4` (DIAN) |
| CP-NIT-03 | `digitoVerificacion('860002964')` | `4` |
| CP-NIT-04 | `digitoVerificacion('900123456')` | `8` |
| CP-NIT-05 | `digitoVerificacion('')` | `InvalidArgumentException` |
| CP-NIT-06 | `digitoVerificacion('abc12')` · **MR-004** | `InvalidArgumentException` (antes devolvía `9`) |
| CP-NIT-07 | `digitoVerificacion('1234567890123456')` (16 dígitos) | `InvalidArgumentException` |
| CP-NIT-08 | `digitoVerificacion('12 345')` | `InvalidArgumentException` |
| CP-NIT-09 | `descomponer('900.123.456-8')` | `['base'=>'900123456','dv'=>8]` |
| CP-NIT-10 | `descomponer('900123456')` | `['base'=>'900123456','dv'=>null]` |
| CP-NIT-11 | `descomponer(' 900 123 456 - 8 ')` | `['base'=>'900123456','dv'=>8]` |
| CP-NIT-12 | `descomponer('1234')` · `('abc')` · `('900123456-')` · `('')` | `null` |

### 2.2 `SemaforoDo` (CP-SEM) — fecha de referencia «hoy» = **2026-10-21**

| ID | Apertura | Vencimiento | Estado | Esperado |
|---|---|---|---|---|
| CP-SEM-01 | 2026-10-12 | 2027-01-10 | En trámite | `al_dia`, «Al día», 9 d transcurridos, 81 restantes, no vencido |
| CP-SEM-02 | 2026-09-22 (29 d) | — | En trámite | `al_dia` *(frontera inferior)* |
| CP-SEM-03 | 2026-09-21 (30 d) | — | En trámite | `seguimiento` *(frontera exacta)* |
| CP-SEM-04 | 2026-08-07 (75 d) | — | En trámite | `seguimiento` *(último día)* |
| CP-SEM-05 | 2026-08-06 (76 d) | — | En trámite | `critico`, «Crítico» |
| CP-SEM-06 | 2026-09-21 | 2026-10-20 | En trámite | `critico`, «Vencido», `vencido=true`, −1 restante |
| CP-SEM-07 | 2026-01-01 | 2026-02-01 | Finalizada | `cerrado`, «Cerrado», `vencido=false` |
| CP-SEM-08 | 2026-01-01 | — | Nacionalizado · Cerrado sin facturar | `cerrado` |
| CP-SEM-09 | 2026-09-06 | 2026-12-05 | En trámite | `progreso = 50` |
| CP-SEM-10 | 2026-01-01 | 2026-02-01 | Aceptada | `progreso = 100` (acotado) |
| CP-SEM-11 | 2026-09-06 | — | En trámite | `dias_restantes = null`, `progreso = 50` (plazo de 90 d) |
| CP-SEM-12 | 2026-10-26 (futura) | — | En trámite | `dias_transcurridos = −5`, `al_dia`, `progreso = 0` |
| CP-SEM-13 | `'basura'` | — | En trámite | `dias_transcurridos = 0`, `al_dia` *(tolerancia documentada)* |

### 2.3 `Bitacora` (CP-BIT) — «ahora» = 21/10/2026 15:48

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-BIT-01 | `agregar(null,'Pendiente Formulario 500.','Ana Pérez','En trámite')` | `[21/10/2026 15:48 · Ana Pérez · En trámite] Pendiente Formulario 500.` |
| CP-BIT-02 | `agregar($anterior, …)` | la entrada nueva va **primero**, una por línea |
| CP-BIT-03 | usuario `Ana [x] · Pérez` | corchetes y `·` se sustituyen: `Ana x Pérez` |
| CP-BIT-04 | texto con saltos de línea y espacios dobles | se colapsa a una sola línea |
| CP-BIT-05 | bitácora previa de 70 000 bytes | resultado ≤ 60 000 bytes; se conserva la entrada nueva; se descartan las **más antiguas** |
| CP-BIT-06 | `parsear` de 2 entradas | lista (más reciente primero) con `fecha, usuario, estado, texto` |
| CP-BIT-07 | `parsear($b, 1)` | 1 entrada |
| CP-BIT-08 | línea antigua sin formato | `['fecha'=>'','usuario'=>'','estado'=>'','texto'=>'…']` |
| CP-BIT-09 | `parsear(null)` · `parsear('')` | `[]` |
| CP-BIT-10 | `parsear(agregar(…))` | ida y vuelta: recupera exactamente los campos |

### 2.4 `ValidadorNuevoDo` (CP-VAL) — «hoy» = 2026-10-21

Entrada base válida: código `cuc26imp0001`, cliente `Textiles del Norte S.A.S.`, NIT `900.123.456`, régimen `Importación`, sucursal `Cúcuta`, apertura `2026-10-01`, teléfono `300 123 4567`.

| ID | Variación | Resultado esperado |
|---|---|---|
| CP-VAL-01 | entrada base | sin errores; `do_codigo=CUC26IMP0001`; `nit=900123456-8`; `fecha_vencimiento=2026-12-30` |
| CP-VAL-02 | código vacío | error `do_codigo` «obligatorio» |
| CP-VAL-03 | código `xx` · `BOG26IMP1` · 31 caracteres | error `do_codigo` de formato |
| CP-VAL-04 | cliente `AB` · 151 caracteres | error `cliente` |
| CP-VAL-05 | cliente `<b>Ana</b> Ltda` | válido; se guarda `Ana Ltda` (sin etiquetas) |
| CP-VAL-06 | NIT `abc` · `1234` | error `nit` de formato |
| CP-VAL-07 | NIT `900123456-1` | error: «El dígito de verificación no corresponde: para 900123456 es 8.» |
| CP-VAL-08 | NIT `900123456-8` | válido |
| CP-VAL-09 | régimen `Otro` · sucursal `Marte` | errores `regimen` y `sucursal` |
| CP-VAL-10 | apertura `basura` · `2026-02-30` · `2026-10-22` (futura) | error `fecha_apertura` (el día de hoy **sí** es válido) |
| CP-VAL-11 | vencimiento anterior a la apertura · inválido | error `fecha_vencimiento` |
| CP-VAL-12 | vencimiento vacío | se calcula apertura + 90 días |
| CP-VAL-13 | email `no-es-correo` · 151 caracteres | error `email`; vacío es válido |
| CP-VAL-14 | teléfono `-------` · `()()()()` · **MR-002** | error `telefono` |
| CP-VAL-15 | teléfono `(607) 123-4567` · vacío | válido |
| CP-VAL-16 | teléfono de 31 caracteres | error `telefono` |
| CP-VAL-17 | observación de 1 000 / 1 001 caracteres | válida / error `observacion` |
| CP-VAL-18 | todo inválido a la vez | exactamente los 8 campos con error (ver E2E-09) |

### 2.5 `Sanitizador` (CP-SAN)

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-SAN-01 | `texto('  <b>Hola</b>\x00 mundo ')` | `Hola mundo` |
| CP-SAN-02 | `texto(null)` | `''` |
| CP-SAN-03 | `correo('ana@example.com')` | `ana@example.com` |
| CP-SAN-04 | `correo('no-es-correo')` · `('')` · `('a@b')` | `null` |
| CP-SAN-05 | `correo("a@b.co\nBcc: x@y.z")` | `null` (inyección de cabeceras) |
| CP-SAN-06 | `telefono('3001234567')` · `('+57 (607) 123-4567')` · `('1234567')` | valor sin cambios |
| CP-SAN-07 | `telefono('-------')` · `('()()()()')` · `('++++++++')` · **MR-002** | `null` |
| CP-SAN-08 | `telefono('123456')` (6 dígitos) · 21 caracteres · letras | `null` |
| CP-SAN-09 | `importe('1500.50')` · `('0')` · `(' 12 ')` | `1500.5` · `0.0` · `12.0` |
| CP-SAN-10 | `importe('1e3')` · `('1e999')` · `('0x1A')` · **MR-001** | `null` (antes `1000.0` / `INF`) |
| CP-SAN-11 | `importe('-5')` · `('1,5')` · `('12.345')` · `('.5')` · `('')` · `('NaN')` | `null` |
| CP-SAN-12 | `importe('999999999999.99')` / `('1000000000000')` | `999999999999.99` / `null` |
| CP-SAN-13 | `html('<a href="x">&\'</a>')` | `&lt;a href=&quot;x&quot;&gt;&amp;&#039;&lt;/a&gt;` |
| CP-SAN-14 | `longitudMaxima('a×200', 200)` · `('a×201', 200)` · **MR-003** | `true` · `false` |

### 2.6 `LimitadorTasa` (CP-TAS) — reloj simulado

| ID | Escenario | Resultado esperado |
|---|---|---|
| CP-TAS-01 | máx. 5; 5 intentos seguidos | los 5 devuelven `true` |
| CP-TAS-02 | el 6.º intento dentro de la ventana | `false` |
| CP-TAS-03 | pasa la ventana completa y se intenta de nuevo | `true` (contador reiniciado) |
| CP-TAS-04 | claves distintas (dos IP) | contadores independientes |
| CP-TAS-05 | cubetas distintas con la misma clave | contadores independientes |
| CP-TAS-06 | `reiniciar()` tras 5 intentos | el siguiente intento es `true` |
| CP-TAS-07 | directorio de almacenamiento imposible de crear | `true` (falla abierta) y se registra el error |
| CP-TAS-08 | archivo de contador corrupto | se trata como contador nuevo |

### 2.7 `Respuesta`, `ErrorHttp`, `SolicitudJson` (CP-HTTP)

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-HTTP-01 | `Respuesta::estado('success','Listo',201,['data'=>[1]])` | código 201; JSON `{"status":"success","message":"Listo","data":[1]}` |
| CP-HTTP-02 | cualquier `Respuesta::estado/json` | cabeceras `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff` |
| CP-HTTP-03 | `Respuesta::redirigir('index.php', 303)` | código 303; cabecera `Location: index.php`; sin cuerpo |
| CP-HTTP-04 | JSON con tildes y barras | no se escapan (`JSON_UNESCAPED_UNICODE`, `JSON_UNESCAPED_SLASHES`) |
| CP-HTTP-05 | `new ErrorHttp('Prohibido', 403)->aRespuesta()` | JSON `status=error`, código 403 |
| CP-HTTP-06 | `ErrorHttp` con datos extra (`errors`) | el JSON los incluye |
| CP-HTTP-07 | `SolicitudJson::leer('{"a":1}')` | `['a'=>1]` |
| CP-HTTP-08 | `leer('')` | `ErrorHttp` 400 |
| CP-HTTP-09 | cuerpo de 65 537 bytes | `ErrorHttp` 413 |
| CP-HTTP-10 | `leer('{no es json')` · `leer('"texto"')` · `leer('5')` | `ErrorHttp` 400 |
| CP-HTTP-11 | JSON con más de 16 niveles de anidamiento | `ErrorHttp` 400 |

### 2.8 `PoliticaArchivos` (CP-ARC)

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-ARC-01 | `limpiarNombreOriginal('..\\..\\evil.pdf')` | `evil.pdf` |
| CP-ARC-02 | nombre con caracteres de control o `"<>|:*?` | se eliminan |
| CP-ARC-03 | nombre vacío o solo caracteres inválidos | `documento` |
| CP-ARC-04 | nombre de 250 caracteres con extensión | ≤ 200 caracteres y conserva la extensión |
| CP-ARC-05 | `extensionPermitida('A.PDF')` · `('foto.jpeg')` | `pdf` · `jpeg` |
| CP-ARC-06 | `('virus.exe')` · `('sin_extension')` · `('a.pdf.exe')` | `null` |
| CP-ARC-07 | PDF real mínimo | firma válida |
| CP-ARC-08 | texto plano con extensión `.pdf` | firma **inválida** |
| CP-ARC-09 | PNG 1×1 real · JPEG real | firma válida; un PNG declarado como `.jpg` → inválida |
| CP-ARC-10 | DOCX con `word/document.xml` | firma válida |
| CP-ARC-11 | ZIP sin `word/document.xml` declarado `.docx` | inválida |
| CP-ARC-12 | `nombreSeguro('CUC26IMP0001','Factura Comercial', 2026-10-21 16:30:05, 'a1b2c3')` | `CUC26IMP0001_FACTURA_20261021-163005_a1b2c3.pdf` (con ext. pdf) |
| CP-ARC-13 | código de DO con caracteres raros (`../x`) | solo `A-Z0-9` en el prefijo |
| CP-ARC-14 | tamaño 0 · 10 MB + 1 byte | rechazado (422 / 413); 10 MB exactos aceptado |

### 2.9 `ClienteN8n` — traducción de errores (CP-N8N)

| ID | Entrada | Mensaje esperado |
|---|---|---|
| CP-N8N-01 | cURL `CURLE_OPERATION_TIMEDOUT` | «n8n no respondió a tiempo.» |
| CP-N8N-02 | `CURLE_COULDNT_RESOLVE_HOST` · `CURLE_COULDNT_CONNECT` | «no hay conexión con el servidor de n8n.» |
| CP-N8N-03 | errno 60 · 77 | menciona la verificación SSL y `N8N_CA_BUNDLE` |
| CP-N8N-04 | cualquier otro errno | «error de conexión (…)» |
| CP-N8N-05 | HTTP 404 | sugiere revisar que el workflow esté activo y la URL `/webhook/` |
| CP-N8N-06 | HTTP 401 · 403 | «rechazó la autenticación (HTTP …)» |
| CP-N8N-07 | HTTP 500 | «respondió con código HTTP 500» |
| CP-N8N-08 | URL inválida / sin cURL | `enviado=false` con el motivo |

### 2.10 `ExportadorDo` (CP-EXP) — mantenimiento evolutivo

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-EXP-01 | 2 DOs → `aJson` | JSON válido con `generado`, `total=2` y `operaciones` con semáforo |
| CP-EXP-02 | lista vacía → `aJson` | `total=0`, `operaciones=[]` |
| CP-EXP-03 | `aCsv` | empieza con BOM UTF-8; encabezados en español; separador `;`; fin de línea `\r\n` |
| CP-EXP-04 | cliente `Textiles "El Norte"; S.A.S.` | comillas dobles duplicadas y campo entre comillas |
| CP-EXP-05 | cliente `=HYPERLINK("http://x")` · `+1` · `-2` · `@SUM(A1)` | prefijo `'` (inyección de fórmulas) |
| CP-EXP-06 | tildes y eñes | se conservan en UTF-8 |
| CP-EXP-07 | lista vacía → `aCsv` | solo la fila de encabezados |

### 2.11 `EscanerSeguridad` (CP-ESC) — herramienta tipo *Bandit* para PHP

| ID | Código analizado | Hallazgo esperado |
|---|---|---|
| CP-ESC-01 | `eval($x);` | `S101 eval_usado`, severidad ALTA |
| CP-ESC-02 | `exec($c);` · `shell_exec` · `system` · `passthru` · `proc_open` · `popen` | `S102 ejecucion_de_comandos`, ALTA |
| CP-ESC-03 | `unserialize($d);` | `S103 deserializacion_insegura`, ALTA |
| CP-ESC-04 | `$pdo->query("SELECT … '" . $n . "'");` | `S201 sql_concatenado`, ALTA |
| CP-ESC-05 | `$pdo->prepare('SELECT … = :n');` | sin hallazgos |
| CP-ESC-06 | `md5($clave)` · `sha1($clave)` | `S301 hash_debil`, MEDIA |
| CP-ESC-07 | `CURLOPT_SSL_VERIFYPEER => false` | `S302 tls_sin_verificar`, ALTA |
| CP-ESC-08 | `ini_set('display_errors', '1');` | `S401 errores_visibles`, MEDIA |
| CP-ESC-09 | `include $_GET['p'];` | `S501 inclusion_dinamica`, ALTA |
| CP-ESC-10 | `echo $_GET['x'];` | `S601 salida_sin_escapar`, MEDIA |
| CP-ESC-11 | `// eval(` en comentario o `'eval('` en una cadena | **no** reporta (analiza *tokens*, no texto) |
| CP-ESC-12 | línea con `// nosec: S101` | se omite el hallazgo (excepción documentada) |
| CP-ESC-13 | código limpio | `0` hallazgos |

## 3. Pruebas de regresión por solicitud de modificación

Cada defecto corregido tiene una prueba en `tests/Regression/` con `@group MR-nnn`. Primero se escribe la prueba y **debe fallar** (rojo, evidencia en `docs/evidencias/tdd/`); después se corrige.

| MR | Prueba | Casos que cubre |
|---|---|---|
| MR-001 | `Mr001ImporteEstrictoTest` | CP-SAN-09…12 |
| MR-002 | `Mr002TelefonoConDigitosTest` | CP-SAN-06…08, CP-VAL-14…16 |
| MR-003 | `Mr003LongitudMaximaTest` | CP-SAN-14 + E2E de cotizador con origen de 201 caracteres |
| MR-004 | `Mr004NitDefensivoTest` | CP-NIT-05…08 |
| MR-005 | `Mr005ArchivosInternosTest` (lee y evalúa las reglas de `.htaccess`) + CP-SEG-01…10 en Apache | todo archivo interno denegado |
| MR-006 | `LimitadorTasaTest` + E2E-29/30 | CP-TAS-01…08 |

## 4. Casos E2E (`php tests/e2e/ejecutar.php`)

* **E2E-01…28** (línea base): sesión y roles, CRUD de DO, semáforo, bitácora, webhook a n8n, cargas seguras, descarga, logout y cotizador. **Deben seguir en verde, sin modificar su resultado esperado, antes y después del refactor.**
* **E2E-29** Login: 5 intentos fallidos permitidos y el 6.º responde **429** con mensaje de espera; un login correcto reinicia el contador. **(MR-006)**
* **E2E-30** Login con usuario inexistente y con usuario real responden con el **mismo** mensaje y código. **(MR-006)**
* **E2E-31** `?action=exportar&formato=json` con sesión → 200, `Content-Disposition: attachment`, JSON con `total`. **(MR-010)**
* **E2E-32** `?action=exportar&formato=csv` → 200, BOM + `;`; sin sesión → 401; formato desconocido → 422. **(MR-010)**
* **E2E-33** El dashboard envía `Content-Security-Policy` con `script-src 'self'` (sin `'unsafe-inline'`). **(MR-009)**
* **E2E-34** Cotizador con origen de 201 caracteres → 422; con valor CIF `1e999` → 422. **(MR-001, MR-003)**

## 5. Verificación de despliegue en Apache (CP-SEG)

El servidor embebido de PHP ignora `.htaccess`; estos casos se ejecutan contra Apache 2.4 con `mod_rewrite` (instrucciones en `docs/05_DESPLIEGUE.md`).

| ID | Petición | Esperado |
|---|---|---|
| CP-SEG-01 | `GET /SQL/rubicam_db.sql` | **403** |
| CP-SEG-02 | `GET /README.md` · `/docs/INFORME_TECNICO.md` | **403** |
| CP-SEG-03 | `GET /composer.json` · `/composer.lock` · `/package-lock.json` | **403** |
| CP-SEG-04 | `GET /src/Dominio/Nit.php` · `/tests/Unit/NitTest.php` · `/tools/escanear_seguridad.php` | **403** |
| CP-SEG-05 | `GET /vendor/autoload.php` · `/.git/config` | **403** |
| CP-SEG-06 | `GET /config/config.php` · `/logs/php-error.log` | **403** |
| CP-SEG-07 | `GET /uploads/<archivo>.pdf` | **403** (solo se descarga por el panel con sesión) |
| CP-SEG-08 | `GET /phpunit.xml.dist` · `/phpcs.xml` · `/.github/workflows/ci.yml` | **403** |
| CP-SEG-09 | `GET /` · `/assets/css/tailwind.build.css` · `/assets/js/panel.js` | **200** (no se bloquea lo público) |
| CP-SEG-10 | `GET /panel/` sin sesión | formulario de login (200) |
