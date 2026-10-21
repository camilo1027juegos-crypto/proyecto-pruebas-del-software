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
| **Criterios de aprobación** | 100 % de casos en verde · 0 errores y 0 advertencias PHPCS (PSR-12) · 0 hallazgos PHPMD con `phpmd.xml` · 0 hallazgos del escáner de seguridad · cobertura de líneas ≥ 90 % en las capas de lógica pura (`Dominio`, `Publico`) y verificación del resto (acceso a BD y HTTP) mediante E2E · los 28 casos E2E originales en verde sin modificar su resultado esperado. |
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

### 2.7 `Respuesta`, `Ejecutor`, `SolicitudJson`, `Solicitud` (CP-HTTP)

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-HTTP-01 | `Respuesta::json(...)` con estado, mensaje y datos extra | JSON `{"status":…,"message":…,…extra}` y código HTTP indicado |
| CP-HTTP-02 | cualquier respuesta JSON | cabeceras `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff` |
| CP-HTTP-03 | tildes y barras en el mensaje | no se escapan (`JSON_UNESCAPED_UNICODE`, `JSON_UNESCAPED_SLASHES`) |
| CP-HTTP-04 | texto con UTF-8 inválido | la respuesta no se rompe (se sustituye el byte inválido) |
| CP-HTTP-05 | `Respuesta::descarga($csv, 'text/csv', 'a.csv')` · **MR-010** | `Content-Disposition: attachment; filename="a.csv"`, sin caché, `nosniff` |
| CP-HTTP-06 | `Ejecutor` y un controlador que lanza `ErrorHttp(…, 403)` | respuesta JSON con código 403 y el mensaje |
| CP-HTTP-07 | `Ejecutor` y una excepción inesperada (p. ej. `RuntimeException('clave de BD')`) | respuesta 500 **genérica**: no filtra detalles internos |
| CP-HTTP-08 | `Ejecutor` y un controlador que devuelve `Respuesta` | se entrega tal cual |
| CP-HTTP-09 | `SolicitudJson::leer('{"a":1}')` | `['a'=>1]` |
| CP-HTTP-10 | cuerpo vacío · `{no es json` · `"texto"` · `5` | `ErrorHttp` 400 |
| CP-HTTP-11 | cuerpo de 65 537 bytes | `ErrorHttp` 413 |
| CP-HTTP-12 | JSON con más de 16 niveles de anidamiento | `ErrorHttp` 400 |
| CP-HTTP-13 | `Solicitud::consulta('action')` con `?action[]=x` | `null` (solo se acepta texto) |
| CP-HTTP-14 | `Solicitud::cabecera('X-CSRF-Token')` | lee `HTTP_X_CSRF_TOKEN` del servidor |
| CP-HTTP-15 | `esPost()` con método `post` en minúsculas · sin método | `true` · `false` (por defecto GET) |
| CP-HTTP-16 | POST que superó `post_max_size` (cuerpo descartado por PHP) | se detecta con `cuerpoDescartadoPorElServidor()` |

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

### 2.10 `ExportadorDo` (CP-EXP) — mantenimiento evolutivo **MR-010**

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-EXP-01 | `aCsv([...])` | empieza con BOM UTF-8 (`EF BB BF`) para que Excel respete las tildes |
| CP-EXP-02 | 1 operación | fila de encabezado (`do_codigo;cliente;nit;…`) + una fila por operación; separador `;` |
| CP-EXP-03 | cliente con `;`, comillas o salto de línea | el campo va entre comillas y las comillas se duplican |
| CP-EXP-04 | cliente que empieza por `=`, `+`, `-`, `@`, tabulación o retorno | se antepone `'` (**inyección de fórmulas en Excel**) |
| CP-EXP-05 | texto normal con `-` o `=` en medio | no se altera |
| CP-EXP-06 | semáforo | el CSV incluye `nivel_alerta` y `dias_transcurridos` |
| CP-EXP-07 | lista vacía | solo la fila de encabezados |
| CP-EXP-08 | `aJson([...], $ahora)` | `{"generado":…,"total":N,"operaciones":[…]}` con tildes sin escapar |
| CP-EXP-09 | operación con bitácora completa | el JSON **no** expone campos internos (solo una lista fija de columnas) |
| CP-EXP-10 | `filtrar` sin criterios · con criterios vacíos | devuelve todo |
| CP-EXP-11 | `filtrar` por sucursal · régimen · nivel · combinados | solo las que cumplen **todos** los criterios |
| CP-EXP-12 | `filtrar` con un criterio desconocido · sin coincidencias | el criterio desconocido se ignora · lista vacía |
| CP-EXP-13 | `nombreArchivo('csv', 2026-10-21 16:30)` | `rubicam_dos_20261021-1630.csv` |

### 2.11 `EscanerSeguridad` (CP-ESC) — herramienta estilo *Bandit* para PHP

El escáner analiza **tokens** (no texto): una palabra dentro de un comentario o de una cadena no genera falsos positivos.

| ID | Código analizado | Hallazgo esperado |
|---|---|---|
| CP-ESC-01 | `eval($x);` · `create_function(...)` | `SEG-001`, severidad **alto** |
| CP-ESC-02 | `exec` · `shell_exec` · `system` · `proc_open` · comillas invertidas | `SEG-002`, **alto** |
| CP-ESC-03 | `unserialize($d);` | `SEG-003`, **medio** |
| CP-ESC-04 | `$pdo->query("… " . $id)` · `"… $id"` · `$pdo->exec($sql)` · `prepare('… ' . $col)` | `SEG-004`, **medio** (SQL con variables) |
| CP-ESC-05 | `$password = "x123";` · `['db_pass' => 'x123']` · `define('API_TOKEN','abc')` | `SEG-005`, **alto** (credencial en el código) |
| CP-ESC-06 | `CURLOPT_SSL_VERIFYPEER => false` · `CURLOPT_SSL_VERIFYHOST, 0` | `SEG-006`, **alto** |
| CP-ESC-07 | `include $pagina;` · `require_once __DIR__ . '/' . $m . '.php';` | `SEG-007`, **medio** |
| CP-ESC-08 | `md5` · `sha1` · `mt_rand` | `SEG-008`, **bajo** |
| CP-ESC-09 | `$_POST['x']` · `$_GET['x']` fuera de `Solicitud` | `SEG-009`, **medio** |
| CP-ESC-10 | `prepare('… :id')` · `query(self::SQL)` · `DB_PASS => ''` · `getenv('DB_PASS')` · `$this->exec(self::X)` · `$_SESSION[..]` · `$_SERVER[..]` · `password_hash` · `random_bytes` | **sin** hallazgos |
| CP-ESC-11 | `// eval($x)` en un comentario · `"no use eval"` en una cadena | **sin** hallazgos (tokens, no texto) |
| CP-ESC-12 | `$pdo->exec('A' . 'B')` · `query(self::X . ' ORDER BY id')` · `$clave = 'HTTP_' . $n` | **sin** hallazgos (falsos positivos hallados al analizar el proyecto; ver `docs/evidencias/tdd/escaner-seguridad-*.txt`) |
| CP-ESC-13 | `// seguridad:ignorar SEG-004 motivo` en la misma línea o en la anterior | se omite **solo** esa regla en esa línea |
| CP-ESC-14 | `escanearRuta(carpeta)` | recorre `.php` en subcarpetas, ordenado; ignora otros archivos; ruta inexistente → vacío |
| CP-ESC-15 | `superaUmbral` con severidad × umbral (5 combinaciones) | alto/medio superan «medio»; bajo no; sin hallazgos nunca falla |
| CP-ESC-16 | `formatearTexto` | `archivo:línea [ALTO] SEG-001 mensaje` y «Total: N hallazgo(s) — alto: a, medio: m, bajo: b» |

### 2.12 `CargadorEntorno` y `Configuracion` (CP-ENT) — mantenimiento adaptativo **MR-008**

| ID | Entrada | Resultado esperado |
|---|---|---|
| CP-ENT-01 | `parsear("DB_HOST=localhost")` · con espacios alrededor de `=` | `['DB_HOST'=>'localhost']` |
| CP-ENT-02 | comentarios `#`, líneas vacías | se ignoran |
| CP-ENT-03 | `A="hola mundo"` · `A='hola mundo'` | comillas retiradas; el espacio se conserva |
| CP-ENT-04 | `A=valor # nota` · `A="no # comentario"` | `valor` · `no # comentario` |
| CP-ENT-05 | `export A=1` · `CLAVE=a=b=c` · `A=` · fin de línea Windows | `A=1` · `a=b=c` · vacío · sin `\r` |
| CP-ENT-06 | `1ABC=x` · `A-B=y` · línea sin `=` | se ignoran (nombre inválido) |
| CP-ENT-07 | `cargar($ruta)` | define las variables y devuelve cuántas definió |
| CP-ENT-08 | variable ya definida en el servidor | **no** se sobrescribe (el servidor manda) |
| CP-ENT-09 | archivo inexistente | devuelve 0, sin error |
| CP-ENT-10 | `UPLOAD_DIR=/var/rubicam/cargas` · `UPLOAD_DIR=datos/cargas` · sin definir | ruta absoluta · relativa a la raíz del proyecto · `uploads/` |

### 2.13 Controladores y guardias — pruebas de **caracterización** (CP-COT, CP-GUA, CP-CAR)

Esta lógica ya existía y solo la cubría el E2E. Se fija ahora con pruebas unitarias rápidas, sin servidor web ni correo real (el envío de correo se inyecta como `Closure`). **No son TDD** (se escribieron después del código y pasaron en la primera ejecución): protegen futuras refactorizaciones.

| ID | Escenario | Resultado esperado |
|---|---|---|
| CP-COT-01 | cotizador con GET · sin token CSRF | 405 · 419; no se envía correo |
| CP-COT-02 | honeypot `website` lleno | 200 «éxito» falso, sin correo |
| CP-COT-03 | límite 3 envíos por ventana | el 4.º responde 429 |
| CP-COT-04 | solicitud válida | 200; correo con `Reply-To` del cliente, `From` fijo, asunto codificado, «USD 1,500.50» |
| CP-COT-05 | operación fuera de la lista · correo inválido · correo con `\nBcc:` · teléfono `-------` · nombre vacío o de 201 · CIF `1e999` · origen/destino de 121 | 422 con mensaje claro; **nunca** se envía correo |
| CP-COT-06 | origen y destino de exactamente 120 | 200 (frontera) |
| CP-COT-07 | el correo falla | 500 con el correo de contacto como alternativa |
| CP-GUA-01 | sin sesión · método GET · token ausente/falso/en el cuerpo · rol consulta/desconocido/vacío | 401 · 405 · 403 · 403 · 403 |
| CP-GUA-02 | `escritura()` | aplica método → CSRF → rol, en ese orden |
| CP-GUA-03 | formulario clásico con `csrf_token` | aceptado por `csrfEnCabeceraOFormulario` |
| CP-CAR-01 | sin archivo · sin campo `error` · varios archivos · `UPLOAD_ERR_INI_SIZE` · ruta temporal inventada (`/etc/passwd`) | 422 · 422 · 422 · 422 · 400 |

## 3. Pruebas de regresión por solicitud de modificación

Cada defecto corregido tiene una prueba **que primero falla** (rojo, evidencia en `docs/evidencias/tdd/mr-0xx-rojo.txt`) y luego pasa (verde). Las clases de `tests/Regression/` y los demás casos llevan `@group MR-nnn`: `phpunit --group MR-001` ejecuta solo esa regresión.

| MR | Tipo (ISO/IEC/IEEE 14764) | Pruebas | Casos |
|---|---|---|---|
| MR-001 | Correctivo | `Mr001ImporteEstrictoTest` · `ControladorCotizacionTest` · E2E-34 | CP-SAN-09…12, CP-COT-05 |
| MR-002 | Correctivo | `Mr002TelefonoConDigitosTest` · E2E-35 | CP-SAN-06…08, CP-VAL-14…16 |
| MR-003 | Correctivo | `ControladorCotizacionTest` · E2E-34 | CP-COT-05/06, CP-SAN-14 |
| MR-004 | Correctivo | `Mr004NitDefensivoTest` | CP-NIT-05…08 |
| MR-005 | Correctivo (seguridad) | `Mr005ArchivosInternosTest` (lee y evalúa las reglas de `.htaccess`) + CP-SEG-01…10 en Apache | todo archivo interno denegado |
| MR-006 | Correctivo (seguridad) | `LimitadorTasaTest` · `AutenticadorTest` · E2E-29/30 | CP-TAS-01…08 |
| MR-007 | Preventivo / perfectivo (refactor) | las 28 E2E originales (sin cambios) + todas las unitarias de `src/` | CP-NIT, CP-SEM, CP-BIT, CP-VAL… |
| MR-008 | Adaptativo | `CargadorEntornoTest` · E2E-36 | CP-ENT-01…10 |
| MR-009 | Preventivo (seguridad) | `CabecerasSegurasTest` · E2E-33 | CSP estricta en panel y acceso |
| MR-010 | Perfectivo / aditivo (nueva función) | `ExportadorDoTest` · `RespuestaTest` · E2E-31/32 | CP-EXP-01…13, CP-HTTP-05 |

## 4. Casos E2E (`php tests/e2e/ejecutar.php`) — 36 casos

* **E2E-01…28** (línea base, escritos **antes** de refactorizar): sesión y roles, CRUD de DO, semáforo, bitácora, webhook a n8n, cargas seguras, descarga, logout y cotizador. **Siguen en verde, sin modificar su resultado esperado, tras cada cambio.**
* **E2E-29** **MR-006** Tras 5 fallos seguidos, ni la clave correcta entra (responde 200 con aviso, sin redirigir).
* **E2E-30** **MR-006** Un inicio de sesión correcto reinicia el contador (4 fallos + éxito + 1 fallo no bloquea).
* **E2E-31** **MR-010** `?action=exportar&formato=csv` → 200, adjunto UTF-8 con BOM, encabezado y los DOs de la semilla.
* **E2E-32** **MR-010** El rol *consulta* puede exportar JSON (total correcto); formato inválido → 422; sin sesión → 401.
* **E2E-33** **MR-009** El panel envía CSP con `script-src 'self'` y no contiene scripts en línea; la pantalla de acceso no permite scripts.
* **E2E-34** **MR-001/003** Cotizador: origen > 120, destino > 120 y valor CIF `1e999` → 422.
* **E2E-35** **MR-002** Alta de DO con teléfono sin dígitos → 422 con error en `telefono`.
* **E2E-36** **MR-008** Los archivos subidos se guardan en `UPLOAD_DIR` y no ensucian `uploads/` del repositorio.

## 5. Verificación de despliegue en Apache (CP-SEG)

El servidor embebido de PHP ignora `.htaccess`, así que estos casos **no pueden hacerse con PHPUnit**. Se ejecutan contra un Apache 2.4 real con el script `tests/apache/verificar_htaccess.sh` (levanta un Apache temporal en el puerto 8081 con la copia del proyecto, hace los `GET` de la tabla y lo apaga). Además, `Mr005ArchivosInternosTest` evalúa las reglas del `.htaccess` en PHP (39 casos) para que el CI también las vigile.

**Resultado registrado:** en la línea base `v2.0.0` el script **falla** (`/SQL/rubicam_db.sql` y `/README.md` responden **200**: el volcado SQL con datos de clientes y el hash de contraseña se podía descargar; ver `docs/evidencias/antes-06-apache.txt`). En `main` todas las comprobaciones dan el código esperado (`docs/evidencias/despues-08-apache.txt`). En XAMPP/Windows se repiten a mano los mismos `GET`.

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
