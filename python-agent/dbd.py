"""
DBD Data Warehouse Scraper
ดึงข้อมูลนิติบุคคลจาก DBD หลายบริษัทพร้อมกัน โดยใช้เลขทะเบียน

วิธีติดตั้ง:
    pip install requests beautifulsoup4 playwright pandas
    playwright install chromium

วิธีใช้:
    python dbd_scraper.py
"""

import asyncio
import csv
import json
import time
import pandas as pd
from dataclasses import dataclass, asdict
from typing import Optional
import requests
from bs4 import BeautifulSoup


# ============================================================
# Config
# ============================================================
BASE_URL = "https://datawarehouse.dbd.go.th"
DELAY_BETWEEN_REQUESTS = 1.5  # วินาที (อย่า spam server)
MAX_CONCURRENT = 3             # ดึงพร้อมกันสูงสุดกี่บริษัท


# ============================================================
# Data Model
# ============================================================
@dataclass
class CompanyInfo:
    juristic_id: str          # เลขทะเบียนนิติบุคคล
    name_th: str = ""         # ชื่อภาษาไทย
    name_en: str = ""         # ชื่อภาษาอังกฤษ
    type: str = ""            # ประเภทนิติบุคคล
    status: str = ""          # สถานะ (ดำเนินกิจการ / เลิกกิจการ ฯลฯ)
    registered_capital: str = ""  # ทุนจดทะเบียน
    registered_date: str = ""     # วันที่จดทะเบียน
    address: str = ""         # ที่อยู่
    objective: str = ""       # วัตถุประสงค์
    error: str = ""           # error message (ถ้ามี)


# ============================================================
# Method 1: ลองเรียก API ตรงๆ (ถ้า DBD มี hidden API)
# ============================================================
def try_api(juristic_id: str) -> Optional[dict]:
    """
    บาง endpoint ของ DBD รับ JSON โดยตรง
    ลองหา hidden API จาก Network tab ของ browser ก่อน
    """
    endpoints = [
        f"{BASE_URL}/api/juristic/{juristic_id}",
        f"{BASE_URL}/juristic/getJuristicDetail?juristicID={juristic_id}",
        f"{BASE_URL}/company/profile/{juristic_id}",
        f"https://opendata.dbd.go.th/api/v1/company-profiles/committees/5/{juristic_id}",
    ]

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Accept": "application/json, text/html, */*",
        "Referer": BASE_URL,
    }

    for url in endpoints:
        try:
            r = requests.get(url, headers=headers, timeout=10)
            if r.status_code == 200 and "application/json" in r.headers.get("Content-Type", ""):
                return r.json()
        except Exception:
            continue
    return None


# ============================================================
# Method 2: Scrape HTML (Requests + BeautifulSoup)
# ============================================================
def scrape_with_requests(juristic_id: str) -> CompanyInfo:
    """
    ดึงข้อมูลด้วย requests ธรรมดา
    ใช้ได้ถ้าเว็บไม่ render ด้วย JavaScript
    """
    result = CompanyInfo(juristic_id=juristic_id)

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Accept-Language": "th,en;q=0.9",
    }

    # ปรับ URL ตาม endpoint จริงของ DBD (ดูจาก Network tab)
    url = f"{BASE_URL}/company/profile/5"
    # params = {"juristicID": juristic_id}

    try:
        r = requests.get(url,  headers=headers, timeout=15)
        r.raise_for_status()
        soup = BeautifulSoup(r.text, "html.parser")

        # ---- Parse ข้อมูลจากตาราง ----
        # โครงสร้างนี้อาจต้องปรับตามหน้าเว็บจริง
        rows = soup.select("table.info-table tr, .detail-table tr, table tr")
        data = {}
        for row in rows:
            cells = row.find_all(["th", "td"])
            if len(cells) >= 2:
                key = cells[0].get_text(strip=True)
                val = cells[1].get_text(strip=True)
                data[key] = val

        # map field
        field_map = {
            "ชื่อนิติบุคคล": "name_th",
            "ชื่อภาษาอังกฤษ": "name_en",
            "ประเภทนิติบุคคล": "type",
            "สถานะนิติบุคคล": "status",
            "ทุนจดทะเบียน": "registered_capital",
            "วันที่จดทะเบียน": "registered_date",
            "ที่ตั้งสำนักงานใหญ่": "address",
        }
        for th_key, field in field_map.items():
            if th_key in data:
                setattr(result, field, data[th_key])

    except Exception as e:
        result.error = str(e)

    return result


# ============================================================
# Method 3: Playwright (รองรับ JavaScript)
# ============================================================
async def scrape_with_playwright(juristic_ids: list[str]) -> list[CompanyInfo]:
    """
    ใช้ Playwright เมื่อเว็บ render ด้วย JavaScript
    ดีที่สุดสำหรับเว็บสมัยใหม่
    """
    from playwright.async_api import async_playwright

    results = []

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            locale="th-TH",
        )

        # ดักจับ API responses (ถ้ามี)
        api_cache = {}

        async def handle_response(response):
            if "juristic" in response.url.lower() and response.status == 200:
                try:
                    body = await response.json()
                    api_cache[response.url] = body
                except Exception:
                    pass

        page = await context.new_page()
        page.on("response", handle_response)

        for juristic_id in juristic_ids:
            result = CompanyInfo(juristic_id=juristic_id)
            try:
                # ไปหน้าค้นหา
                await page.goto(f"{BASE_URL}/searchJuristicPerson", timeout=30000)
                await page.wait_for_load_state("networkidle")

                # กรอก search form
                # *** ปรับ selector ตามหน้าเว็บจริง ***
                search_input = await page.query_selector('input[name="juristicID"], #juristicID, input[placeholder*="เลขทะเบียน"]')
                if search_input:
                    await search_input.fill(juristic_id)
                    await page.keyboard.press("Enter")
                    await page.wait_for_load_state("networkidle")

                # ดึง HTML และ parse
                content = await page.content()
                soup = BeautifulSoup(content, "html.parser")

                # ดึงข้อมูลจากตาราง
                rows = soup.select("table tr")
                for row in rows:
                    cells = row.find_all(["th", "td"])
                    if len(cells) >= 2:
                        key = cells[0].get_text(strip=True)
                        val = cells[1].get_text(strip=True)

                        if "ชื่อ" in key and "ไทย" in key:
                            result.name_th = val
                        elif "อังกฤษ" in key:
                            result.name_en = val
                        elif "ประเภท" in key:
                            result.type = val
                        elif "สถานะ" in key:
                            result.status = val
                        elif "ทุน" in key:
                            result.registered_capital = val
                        elif "วันที่จดทะเบียน" in key:
                            result.registered_date = val
                        elif "ที่ตั้ง" in key or "ที่อยู่" in key:
                            result.address = val

                print(f"✅ {juristic_id}: {result.name_th or 'found'}")

            except Exception as e:
                result.error = str(e)
                print(f"❌ {juristic_id}: {e}")

            results.append(result)
            await asyncio.sleep(DELAY_BETWEEN_REQUESTS)

        await browser.close()

    return results


# ============================================================
# Batch Runner
# ============================================================
def scrape_batch(juristic_ids: list[str], method: str = "requests") -> list[CompanyInfo]:
    """
    ดึงข้อมูลหลายบริษัทพร้อมกัน

    Args:
        juristic_ids: list ของเลขทะเบียนนิติบุคคล
        method: "requests" หรือ "playwright"
    """
    results = []

    if method == "playwright":
        results = asyncio.run(scrape_with_playwright(juristic_ids))
    else:
        for i, jid in enumerate(juristic_ids):
            print(f"[{i+1}/{len(juristic_ids)}] กำลังดึง {jid}...")

            # ลอง API ก่อน
            api_data = try_api(jid)
            if api_data:
                # ถ้า API ตอบ — map ข้อมูล
                r = CompanyInfo(juristic_id=jid)
                r.name_th = api_data.get("juristicNameTh", "")
                r.name_en = api_data.get("juristicNameEn", "")
                r.status = api_data.get("juristicStatus", "")
                r.registered_capital = str(api_data.get("registeredCapital", ""))
                r.registered_date = api_data.get("registeredDate", "")
                results.append(r)
                print(f"  ✅ API: {r.name_th}")
            else:
                # Fallback: scrape HTML
                r = scrape_with_requests(jid)
                results.append(r)
                print(f"  ✅ Scraped: {r.name_th or r.error}")

            if i < len(juristic_ids) - 1:
                time.sleep(DELAY_BETWEEN_REQUESTS)

    return results


# ============================================================
# Export ผลลัพธ์
# ============================================================
def export_results(results: list[CompanyInfo], output_file: str = "dbd_results"):
    """Export เป็น CSV และ JSON"""

    data = [asdict(r) for r in results]

    # CSV
    csv_file = f"{output_file}.csv"
    with open(csv_file, "w", newline="", encoding="utf-8-sig") as f:
        if data:
            writer = csv.DictWriter(f, fieldnames=data[0].keys())
            writer.writeheader()
            writer.writerows(data)
    print(f"💾 บันทึก CSV: {csv_file}")

    # JSON
    json_file = f"{output_file}.json"
    with open(json_file, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"💾 บันทึก JSON: {json_file}")

    # แสดงผลสรุป
    df = pd.DataFrame(data)
    print("\n📊 สรุปผล:")
    print(df[["juristic_id", "name_th", "status", "registered_capital"]].to_string(index=False))

    return df


# ============================================================
# Main
# ============================================================
if __name__ == "__main__":
    # ใส่เลขทะเบียนนิติบุคคลที่ต้องการ
    JURISTIC_IDS = [
        "0705569001321",   # ตัวอย่าง
        "0935566000471",   # ตัวอย่าง
        
        # เพิ่มได้เรื่อยๆ...
    ]

    print(f"🚀 เริ่มดึงข้อมูล {len(JURISTIC_IDS)} บริษัท\n")

    # เลือก method:
    # "requests"   — เร็ว แต่อาจไม่ได้ถ้าเว็บใช้ JS
    # "playwright" — ช้ากว่า แต่รองรับ JS rendering
    results = scrape_batch(JURISTIC_IDS, method="requests")

    # Export
    export_results(results, output_file="dbd_results")

    print("\n✅ เสร็จสิ้น!")