"""
Comprobación manual en un NAVEGADOR REAL (Chromium con Playwright) del panel bajo la CSP estricta (MR-009).

Qué demuestra que PHPUnit y las E2E (que solo ven cabeceras y HTML) no pueden demostrar:
el JavaScript extraído a assets/js/panel.js SÍ funciona con `script-src 'self'`: la tabla carga, abre el
modal, filtra y descarga CSV/JSON sin violaciones de CSP ni errores de JavaScript.

Uso:
    # 1) levantar la app contra la base de las E2E (php tests/e2e/ejecutar.php la deja creada)
    DB_USER=rubicam_test DB_PASS=rubicam_test DB_NAME=rubicam_e2e php -S 127.0.0.1:8097 -t .
    # 2) en otra terminal (pip install playwright && playwright install chromium)
    python3 tests/ui/verificar_panel.py carpeta_de_salida

Salida: capturas .png y un resumen en consola. Los 3 «Failed to load resource» son las fuentes/CDN externos
(sin internet en el entorno de pruebas), no violaciones de la política de seguridad.
"""
import asyncio, json, sys
from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8097"
OUT = sys.argv[1]
async def main():
    resultados = []
    async with async_playwright() as p:
        nav = await p.chromium.launch(args=["--no-sandbox"])
        ctx = await nav.new_context(accept_downloads=True, viewport={"width": 1366, "height": 850})
        page = await ctx.new_page()
        consola, errores = [], []
        page.on("console", lambda m: consola.append((m.type, m.text)))
        page.on("pageerror", lambda e: errores.append(str(e)))

        # 1) Pantalla de acceso (CSP script-src 'none')
        await page.goto(f"{BASE}/panel/")
        await page.wait_for_selector("#usuario")
        await page.screenshot(path=f"{OUT}/01_login.png")
        cabeceras = (await page.request.get(f"{BASE}/panel/")).headers
        resultados.append(("CSP del acceso", cabeceras.get("content-security-policy", "")[:140]))

        # 2) Login
        await page.fill("#usuario", "e2e_admin")
        await page.fill("#password", "E2e-Pass-2026!")
        await page.click("button[type=submit]")
        await page.wait_for_url("**/dashboard.php")
        await page.wait_for_selector("tbody tr", timeout=8000)
        filas = await page.locator("tbody tr").count()
        resultados.append(("Filas de DO en la tabla", filas))
        await page.screenshot(path=f"{OUT}/02_dashboard.png")

        # 3) Interacciones (prueban que panel.js corre bajo la CSP)
        await page.click("#btn-nuevo-do")
        await page.wait_for_selector("#modal-nuevo:not(.hidden)", timeout=3000)
        resultados.append(("Modal 'Nueva operación' abre", True))
        await page.screenshot(path=f"{OUT}/03_modal_nuevo.png")
        await page.keyboard.press("Escape")

        # 4) Filtro
        antes = await page.locator("tbody tr").count()
        await page.select_option("#f-sucursal", index=1)
        await page.wait_for_timeout(400)
        despues = await page.locator("tbody tr").count()
        resultados.append(("Filtro por sucursal", f"{antes} -> {despues} filas"))

        # 5) Exportación CSV y JSON
        await page.select_option("#f-sucursal", index=0)
        async with page.expect_download() as d:
            await page.click("#btn-exportar-csv")
        dl = await d.value
        ruta = f"{OUT}/descarga.csv"
        await dl.save_as(ruta)
        datos = open(ruta, "rb").read()
        resultados.append(("CSV descargado", f"{dl.suggested_filename} · {len(datos)} bytes · BOM={datos[:3]==b'\xef\xbb\xbf'}"))
        async with page.expect_download() as d2:
            await page.click("#btn-exportar-json")
        dl2 = await d2.value
        await dl2.save_as(f"{OUT}/descarga.json")
        j = json.load(open(f"{OUT}/descarga.json", encoding="utf-8"))
        resultados.append(("JSON descargado", f"{dl2.suggested_filename} · total={j['total']}"))

        # 6) Resumen de la consola
        csp = [t for (ty, t) in consola if "Content Security Policy" in t or "Refused to" in t]
        resultados.append(("Violaciones de CSP en la consola", len(csp)))
        resultados.append(("Errores de JavaScript (pageerror)", len(errores)))
        resultados.append(("Mensajes de consola tipo error", len([1 for (ty, t) in consola if ty == "error"])))
        for ty, t in consola:
            if ty == "error":
                resultados.append(("  consola error", t[:200]))
        for e in errores:
            resultados.append(("  pageerror", e[:200]))
        await nav.close()
    for k, v in resultados:
        print(f"{k}: {v}")
asyncio.run(main())
