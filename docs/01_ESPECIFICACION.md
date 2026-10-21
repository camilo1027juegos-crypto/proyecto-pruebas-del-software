# 01 · Especificación técnica (documentación previa a la codificación)

> **Principio aplicado:** *Principios de preparación para la codificación → Documentación previa.*
> Este documento se redactó **antes** de escribir las clases nuevas: define qué debe hacer cada módulo,
> cómo se diseña y por qué se toman ciertas decisiones. Los casos de prueba que verifican cada
> especificación están en `docs/02_CASOS_DE_PRUEBA.md` (principio *Pruebas previas*).

## 1. Propósito y alcance

El proyecto es la landing y el **panel operativo de Órdenes de Operación (DO)** de *Agencia de Aduanas
Rubicam S.A.S.* (PHP 8.2+ nativo, MySQL/MariaDB, Tailwind compilado, webhook a n8n).
Este trabajo no agrega un sistema nuevo: **aplica los principios de codificación y pruebas** sobre el
sistema existente para volverlo legible, modular, mantenible, seguro y verificable.

**Dentro del alcance:** reglas de negocio del panel, validación de entradas, cargas de archivos, cotizador,
login, exportación, herramientas de calidad, pruebas y despliegue.
**Fuera del alcance:** rediseño visual, nuevos módulos de negocio (Maestras, Reportes DO), cambios al esquema de BD.

## 2. Arquitectura objetivo

```
                   ┌────────────────────────────────────────────────────┐
   Navegador ───►  │ Puntos de entrada delgados (≤ 60 líneas)            │
                   │ index.php · panel/*.php · handlers/*.php            │
                   └───────────────┬────────────────────────────────────┘
                                   │ Solicitud ─► Controlador ─► Respuesta
                   ┌───────────────▼────────────────────────────────────┐
                   │ Aplicación  src/Panel · src/Publico               │  orquesta casos de uso
                   └───────┬───────────────────────┬────────────────────┘
          ┌────────────────▼───────┐   ┌───────────▼────────────────────┐
          │ Dominio (PURO, sin I/O)│   │ Infraestructura                │
          │ src/Dominio            │   │ src/Infraestructura (PDO, cURL)│
          │ Catalogos · Nit ·      │   │ src/Archivos (sistema de       │
          │ SemaforoDo · Bitacora ·│   │ archivos) · src/Seguridad      │
          │ ValidadorNuevoDo       │   │ (sesión, CSRF, límite de tasa) │
          └────────────────────────┘   └────────────────────────────────┘
                   src/Http: Solicitud · Respuesta · ErrorHttp · SolicitudJson
```

**Reglas de dependencia** (verificables en revisión de código):

1. `Dominio` **no** usa `$_SERVER`, `$_SESSION`, PDO, cURL, `header()`, `exit` ni la hora del sistema sin recibirla por parámetro.
2. Los controladores **devuelven** un objeto `Respuesta`; solo el punto de entrada la emite. Ninguna clase llama `exit`.
3. Todo valor que cambia con el entorno (BD, URL de n8n, directorio de cargas) llega por variable de entorno.
4. Las vistas (`panel/vistas/`) no consultan la BD ni contienen reglas de negocio.

## 3. Especificación de módulos

> Formato tomado del material de clase: *Función · Objetivo · Entradas · Salidas · Reglas de negocio*.

### 3.1 `Rubicam\Dominio\Nit`

```
Función:   Nit::digitoVerificacion(string $base): int
Objetivo:  Calcular el dígito de verificación (DV) de un NIT colombiano con el algoritmo
           módulo 11 de la DIAN.
Entradas:  $base — solo dígitos, de 1 a 15 caracteres.
Salidas:   int entre 0 y 9.
Reglas de negocio:
 - pesos (de derecha a izquierda): 3,7,13,17,19,23,29,37,41,43,47,53,59,67,71
 - r = suma(dígito × peso) mod 11 ; DV = (r > 1) ? 11 − r : r
Errores:   InvalidArgumentException si $base está vacío, tiene letras o más de 15 dígitos
           (codificación defensiva: no devolver un DV «inventado»).

Función:   Nit::descomponer(string $texto): ?array{base:string, dv:?int}
Objetivo:  Normalizar lo que escribe el usuario (con puntos, espacios y guion) en base + DV opcional.
Reglas:    base de 5 a 15 dígitos; DV de un dígito opcional; cualquier otro formato → null.
```

### 3.2 `Rubicam\Dominio\SemaforoDo`

```
Función:   SemaforoDo::calcular(fechaApertura, ?fechaVencimiento, estado, ?hoy): array
Objetivo:  Clasificar un DO según los días transcurridos desde su apertura (plazo DIAN de 3 meses).
Salidas:   nivel, etiqueta, descripcion, dias_transcurridos, dias_restantes, vencido, progreso (0-100).
Reglas de negocio:
 - estado Finalizada | Nacionalizado | Cerrado sin facturar  → nivel «cerrado» (sin alerta)
 - fecha de vencimiento ya superada                            → «critico», etiqueta «Vencido»
 - días > 75                                                   → «critico», etiqueta «Crítico»
 - 30 ≤ días ≤ 75                                              → «seguimiento»
 - días < 30                                                   → «al_dia»
 - progreso = días / plazo × 100, acotado a [0,100]; plazo = vencimiento − apertura (o 90 si no hay vencimiento)
Nota de diseño: la hora actual se recibe por parámetro (`$hoy`) para que la prueba sea determinista.
```

### 3.3 `Rubicam\Dominio\Bitacora`

```
Función:   Bitacora::agregar(?actual, texto, usuario, estado, ?ahora): string
Objetivo:  Anteponer una entrada «[dd/mm/aaaa HH:MM · usuario · estado] texto» a la bitácora del DO.
Reglas:    - se retiran «[», «]» y «·» de usuario y estado (rompen el formato);
           - el texto colapsa espacios en blanco;
           - la bitácora vive en una columna TEXT (65 535 bytes): al superar 60 000 bytes se descartan
             las entradas MÁS ANTIGUAS.
Función:   Bitacora::parsear(?bitacora, limite = 10): list<array{fecha,usuario,estado,texto}>
Reglas:    - la más reciente primero; - líneas antiguas sin formato se devuelven como texto plano.
```

### 3.4 `Rubicam\Dominio\ValidadorNuevoDo`

```
Función:   ValidadorNuevoDo::validar(array $entrada): ResultadoValidacion
Objetivo:  Validar y normalizar los datos de un DO nuevo (validación autoritativa del servidor).
Entradas:  do_codigo, cliente, nit, regimen, sucursal, fecha_apertura, fecha_vencimiento?, email?, telefono?, observacion?
Salidas:   ResultadoValidacion { datos normalizados, errores[campo => mensaje] }
Reglas de negocio:
 - do_codigo: /^[A-Z]{3}\d{2}[A-Z]{3}\d{3,12}$/ en mayúsculas, ≤ 30 caracteres
 - cliente: 3 a 150 caracteres, sin etiquetas HTML
 - nit: se normaliza a «base-DV»; si trae DV y no coincide → error
 - regimen y sucursal: pertenecer a los catálogos
 - fecha_apertura: fecha real, no futura
 - fecha_vencimiento: si falta = apertura + 90 días; no puede ser anterior a la apertura
 - email (opcional): formato válido, ≤ 150
 - telefono (opcional): ≥ 7 dígitos, solo [0-9+-() y espacio], ≤ 30 caracteres
 - observacion (opcional): ≤ 1 000 caracteres
Diseño:    cada regla es un método privado de CC ≤ 5; el método público solo las compone.
```

### 3.5 `Rubicam\Seguridad\Sanitizador`

```
texto(?string): string          Recorta, quita etiquetas HTML y caracteres de control.
correo(?string): ?string        Correo válido sin saltos de línea (evita inyección de cabeceras) o null.
telefono(?string, int max=20)   ≥ 7 dígitos; solo [0-9+-() y espacio]; ≤ max caracteres; si no, null.
importe(?string, max)           Decimal estricto /^\d+(\.\d{1,2})?$/ y ≤ 999 999 999 999,99; si no, null.
                                (Mismo contrato que valida el navegador: el servidor es la autoridad.)
html(?string): string           Escapa < > " ' & para imprimir en HTML (ayudante h()).
```

### 3.6 `Rubicam\Seguridad\LimitadorTasa`

```
Objetivo:  Limitar intentos por clave (IP) y ventana de tiempo, con archivos bloqueados con flock().
intentar(cubeta, clave): bool   true si el intento se permite (y lo cuenta); false si se superó el máximo.
reiniciar(cubeta, clave): void  Borra el contador (p. ej. tras un login correcto).
Decisión:  si el almacenamiento no está disponible se PERMITE el intento y se registra el error
           (disponibilidad sobre rigidez: el login sigue protegido por password_verify).
Uso:       cotizador: 5 envíos / 10 min · login: 5 intentos / 15 min.
```

### 3.7 `Rubicam\Http\*`

| Clase | Responsabilidad |
|---|---|
| `Respuesta` | Valor inmutable (código, cabeceras, cuerpo). Fábricas: `estado()` (JSON del panel `{status,message,…}`), `json()` (libre), `redirigir()`, `html()`, `archivo()`. `emitir()` envía; **no** llama `exit`. |
| `ErrorHttp` | Excepción con código HTTP y datos extra; `aRespuesta()` la convierte en JSON. Reemplaza a los `responder(...); exit;` anidados. |
| `SolicitudJson` | Lee el cuerpo JSON con límite de tamaño y profundidad: vacío → 400, > 64 KB → 413, inválido → 400. |
| `Solicitud` | Captura método, cabeceras, GET/POST/FILES y cuerpo para pasarlos al controlador (y simularlos en pruebas). |

### 3.8 `Rubicam\Archivos\PoliticaArchivos`

```
Objetivo:  Decidir si un archivo subido al legajo es aceptable y ponerle un nombre seguro.
Reglas:    - extensiones: pdf, jpg, jpeg, png, docx (única fuente de verdad: esta clase);
           - tamaño ≤ 10 MB y > 0;
           - tipo MIME real (finfo) compatible con la extensión;
           - firma interna: %PDF-, PNG/JPEG válidos por getimagesize, DOCX = ZIP con word/document.xml;
           - nombre mostrado: sin rutas ni caracteres de control, ≤ 200 caracteres;
           - nombre en disco: CÓDIGODO_TIPO_AAAAMMDD-HHMMSS_aleatorio.ext (nunca el nombre del usuario).
```

### 3.9 `Rubicam\Infraestructura\ClienteN8n`

```
Objetivo:  Notificar a n8n el cambio de estado de un DO (POST JSON con cURL).
Salidas:   {enviado: bool, http_code: ?int, error: ?string}
Reglas:    - el estado del DO YA está guardado: una falla de n8n no revierte nada (se informa al usuario);
           - la traducción de errores cURL/HTTP a mensajes es lógica pura y se prueba sin red.
```

### 3.10 `Rubicam\Exportacion\ExportadorDo` (mantenimiento evolutivo)

```
aJson(dos, generado): string    {generado, total, operaciones:[…]} con el semáforo de cada DO.
aCsv(dos): string               UTF-8 con BOM, separador «;» (Excel en configuración regional es-CO),
                                comillas dobles escapadas.
Seguridad: «inyección de fórmulas CSV»: toda celda que empiece por = + - @ (o tabulación) se
           antepone con «'» para que Excel no la ejecute.
Endpoint:  panel/dashboard.php?action=exportar&formato=json|csv  (requiere sesión; cualquier rol).
```

## 4. Estándares de codificación del proyecto

| Tema | Regla | Se verifica con |
|---|---|---|
| Estilo | **PSR-12** (4 espacios, LF, 120 columnas en PHP puro) | `composer lint` |
| Tipos | `declare(strict_types=1);` en todo archivo PHP; tipos de parámetros y retorno obligatorios | `php -l`, PHPMD |
| Nombres | Clases `StudlyCaps`, métodos `camelCase`, constantes `MAYUSCULAS_CON_GUION_BAJO`; **nombres descriptivos en español** del dominio (`SemaforoDo`, `digitoVerificacion`) | revisión + PHPMD (`ShortVariable`, `LongVariable`) |
| Estructuras | Cláusulas de guarda (retorno temprano); máximo 3 niveles de anidamiento; CC ≤ 10 por método | PHPMD (`CyclomaticComplexity`, `NPathComplexity`) |
| Constantes | Sin «números mágicos»: todo límite o plazo es una constante con nombre y un comentario del *por qué* | revisión |
| Comentarios | Explican el **por qué** de una decisión no evidente, no el «qué»; docblocks con tipos genéricos (`array<string,mixed>`) | revisión |
| Errores | Excepciones para fallas; sin operador `@`; sin `exit` fuera de los puntos de entrada | PHPMD |
| Seguridad | Consultas preparadas, validación en servidor, escape en salida, CSRF en escrituras | escáner de seguridad + revisión |

## 5. Requisitos de calidad (ISO/IEC 25010) y cómo se miden

| Característica | Requisito | Métrica / verificación |
|---|---|---|
| Mantenibilidad — modularidad | Puntos de entrada delgados (`panel/dashboard.php` 49 líneas; `handlers/*.php` ≤ 40); lógica en clases con una responsabilidad | `phploc`, conteo de clases |
| Mantenibilidad — analizabilidad | CC ≤ 10 y NPath ≤ 200 en todo método | PHPMD: 0 incumplimientos |
| Mantenibilidad — capacidad de ser probado | Lógica pura (`Dominio`, `Publico`) ≥ 90 % de líneas con pruebas unitarias; el código que toca BD, sesión real y cabeceras HTTP se verifica con E2E | `phpunit --coverage-text` + E2E |
| Fiabilidad — tolerancia a fallos | Una falla de n8n o del correo no pierde datos ni rompe el panel | E2E-14, E2E-27 |
| Seguridad — integridad / confidencialidad | Validación estricta, CSRF, cargas verificadas, archivos internos no expuestos | pruebas unitarias, E2E y CP-SEG-xx |
| Seguridad — autenticidad | Límite de intentos y respuesta uniforme en el login | `AutenticadorTest`, E2E-29/30 |
| Adecuación funcional | El comportamiento observable no cambia tras el refactor | 28 casos E2E en verde antes y después |
| Portabilidad — adaptabilidad | Mismo código en desarrollo, pruebas y producción vía variables de entorno | `.env.example`, `docs/05_DESPLIEGUE.md` |

## 6. Entornos

| Entorno | `APP_ENV` | Propósito | Base de datos |
|---|---|---|---|
| Desarrollo | `development` | Errores visibles, XAMPP local | `rubicam_db` |
| Pruebas / staging | `testing` | Suite E2E y verificación previa al despliegue | `rubicam_e2e` (desechable) |
| Producción | `production` | Errores solo en `logs/php-error.log` | `rubicam_db` del servidor |

## 7. Decisiones de diseño y su justificación

| Decisión | Por qué |
|---|---|
| Autocargador PSR-4 propio (`src/bootstrap.php`) y Composer solo para desarrollo | El cliente despliega en XAMPP/hosting compartido; no se le exige instalar Composer en producción. |
| Clases con métodos estáticos para funciones puras (`Nit`, `SemaforoDo`, `Sanitizador`) | No tienen estado; la prueba unitaria es una llamada directa. Las que tienen dependencias (BD, tiempo, disco) usan inyección por constructor. |
| `Respuesta` en lugar de `exit` | Permite probar controladores sin terminar el proceso de PHPUnit y centraliza las cabeceras de seguridad. |
| Separar `assets/js/panel.js` de la vista | Un JS estático se puede versionar, cachear y analizar; además permite una CSP sin `'unsafe-inline'` en scripts. |
| No cambiar el esquema de BD ni los formatos JSON existentes | El refactor no debe romper el cliente JavaScript ni los datos en producción. |

## 8. Desviaciones entre el diseño previo y la implementación

Este documento se escribió **antes** de codificar. Al implementar con TDD hubo ajustes; se registran aquí para que el diseño y el código no se contradigan en silencio.

| Diseño previo | Implementación final | Motivo |
|---|---|---|
| Carpeta `src/Cotizador` | `src/Publico` (`ControladorCotizacion`) | El espacio público del sitio (cotizador y futuros formularios) es más amplio que una sola función. |
| `Rubicam\Exportacion\ExportadorDo` | `Rubicam\Panel\ExportadorDo` | Solo lo usa el panel; una carpeta con una clase era sobre-ingeniería. Se agregaron `filtrar()` y `nombreArchivo()`. |
| `Respuesta::estado()` / `redirigir()` / `emitir()` y `ErrorHttp::aRespuesta()` | `Respuesta::json()` / `datos()` / `redireccion()` / `html()` / `archivo()` / `descarga()` / `enviar()`, y `Ejecutor` que convierte `ErrorHttp` y excepciones en respuestas | Centralizar el manejo de errores en un solo lugar (`Ejecutor`) y no filtrar detalles internos en errores 500. |
| Entorno `testing` con `APP_ENV` | Solo `development` es especial (muestra errores); cualquier otro valor es «producción» | Regla más simple y segura: ante la duda, ocultar errores. |
| Cobertura ≥ 90 % en Dominio **y Seguridad** | Dominio 100 %, Publico 100 %, escáner de seguridad 99 %; Seguridad 75 % | Lo no cubierto de `Seguridad` son las llamadas reales a `session_start()`/`header()` (`SesionPanel::iniciar/abrir/cerrar`), que solo existen con un servidor web: las cubre el E2E. |
| CI ejecuta las pruebas | Escrito (`ci.yml`) pero **no ejecutado** | El entorno de desarrollo no tenía acceso a Packagist/GitHub; las mismas compuertas se ejecutaron a mano. |
