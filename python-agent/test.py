from playwright.async_api import async_playwright
import asyncio, json

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=False)  # เปิด browser จริง
        page = await browser.new_page()
        
        # ดัก JSON.parse
        await page.add_init_script("""
            const orig = JSON.parse.bind(JSON)
            JSON.parse = function(text) {
                const result = orig(text)
                if (result && typeof result === 'object') {
                    window.__intercepted = window.__intercepted || []
                    window.__intercepted.push(result)
                }
                return result
            }
        """)
        
        await page.goto("https://datawarehouse.dbd.go.th")
        
        # รอให้ login เอง
        input("Login ใน browser แล้วกด Enter...")
        
        await page.goto("https://datawarehouse.dbd.go.th/company/profile/50705569001321")
        await page.wait_for_load_state("networkidle")
        
        # ดึงข้อมูลที่ดักไว้
        data = await page.evaluate("window.__intercepted")
        print(json.dumps(data, ensure_ascii=False, indent=2))

asyncio.run(main())