#!/usr/bin/env python3
"""
DESPACE LUXURY - PLAYWRIGHT FIGMA EXPORT STUDIO
Captures pixel-perfect full-page designs (Desktop 1440px & Mobile 390px) & clean HTML
ready for direct drag-and-drop or 1-click import into Figma (via html.to.design / Builder.io).
"""

import os
import sys
import asyncio
from pathlib import Path
from playwright.async_api import async_playwright

OUTPUT_DIR = Path(__file__).resolve().parent / "exports"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

TARGET_PAGES = [
    {
        "name": "01_homepage",
        "url": "http://localhost:8090/",
        "title": "Trang chủ DeSpace Luxury"
    },
    {
        "name": "02_catalog_shop",
        "url": "http://localhost:8090/shop/",
        "title": "Cửa hàng & Bộ lọc DeSpace (Shop Catalog)"
    },
    {
        "name": "03_single_product_3d",
        "url": "http://localhost:8090/product/tu-sach-canh-kinh-chong-bui-despace-shelvo-go-soi-modern-oak/",
        "title": "Chi tiết sản phẩm 3D (Tủ sách Shelvo)"
    },
    {
        "name": "04_cart",
        "url": "http://localhost:8090/cart/",
        "title": "Trang Giỏ hàng (Cart)"
    },
    {
        "name": "05_my_account",
        "url": "http://localhost:8090/my-account/",
        "title": "Tài khoản khách hàng (My Account)"
    }
]

async def capture_page(browser, page_info, device="desktop"):
    name = page_info["name"]
    url = page_info["url"]

    if device == "desktop":
        viewport = {"width": 1440, "height": 900}
        scale = 2
        suffix = "desktop_1440px"
    else:
        viewport = {"width": 390, "height": 844}
        scale = 2
        suffix = "mobile_390px"

    context = await browser.new_context(
        viewport=viewport,
        device_scale_factor=scale,
        is_mobile=(device == "mobile")
    )
    page = await context.new_page()

    print(f"[*] Navigating to {url} [{device}]...")
    try:
        await page.goto(url, wait_until="networkidle", timeout=25000)
    except Exception as e:
        print(f"  [!] Note: {e}. Falling back to domcontentloaded...")
        try:
            await page.goto(url, wait_until="domcontentloaded", timeout=15000)
        except Exception:
            pass

    # Wait for webfonts and components to settle
    await asyncio.sleep(2.0)

    # 1. Full Page Screenshot (@2x Retina PNG for Figma import)
    png_path = OUTPUT_DIR / f"{name}_{suffix}.png"
    await page.screenshot(path=str(png_path), full_page=True)
    print(f"  [+] Saved Figma PNG: {png_path.name} ({os.path.getsize(png_path):,} bytes)")

    # 2. HTML Snapshot (For Figma plugins like html.to.design)
    if device == "desktop":
        html_content = await page.content()
        html_path = OUTPUT_DIR / f"{name}.html"
        with open(html_path, "w", encoding="utf-8") as f:
            f.write(html_content)
        print(f"  [+] Saved Figma HTML: {html_path.name} ({os.path.getsize(html_path):,} bytes)")

    # 3. Special capture for 3D Modal if on product page
    if name == "03_single_product_3d" and device == "desktop":
        btn_3d = await page.query_selector("#axView3DModelBtn")
        if btn_3d:
            try:
                print("  [*] Opening 3D Modal for Figma active-state capture...")
                await btn_3d.click()
                await asyncio.sleep(2.5)
                modal_png = OUTPUT_DIR / "03_single_product_3d_modal_active.png"
                await page.screenshot(path=str(modal_png), full_page=False)
                print(f"  [+] Saved 3D Modal Figma PNG: {modal_png.name} ({os.path.getsize(modal_png):,} bytes)")
            except Exception as e:
                print(f"  [!] Modal capture note: {e}")

    await context.close()

async def main():
    print("=======================================================================")
    print("🚀 DESPACE LUXURY - PLAYWRIGHT FIGMA EXPORT STUDIO")
    print(f"📁 Destination: {OUTPUT_DIR}")
    print("=======================================================================\n")

    async with async_playwright() as p:
        chrome_path = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
        if os.path.exists(chrome_path):
            browser = await p.chromium.launch(executable_path=chrome_path, headless=True)
        else:
            browser = await p.chromium.launch(headless=True)

        for page_info in TARGET_PAGES:
            print(f"\n=== CAPTURING: {page_info['title']} ===")
            await capture_page(browser, page_info, device="desktop")
            await capture_page(browser, page_info, device="mobile")

        await browser.close()

    print("\n=======================================================================")
    print("🎉 ALL DESPACE SCREENS SUCCESSFULLY EXPORTED FOR FIGMA!")
    print(f"📁 Output Directory: {OUTPUT_DIR}")
    print("=======================================================================\n")

if __name__ == "__main__":
    asyncio.run(main())
