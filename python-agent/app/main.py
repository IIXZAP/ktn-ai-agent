import logging

from app.api import campaign
from app.core.config import get_settings
from fastapi import FastAPI

logging.basicConfig(level=logging.INFO)

settings = get_settings()

app = FastAPI(
    title="AI Sales Lead - Python Agent",
    description="ฝั่งประมวลผล AI: parse criteria, ค้นหาบริษัท, ตรวจเว็บไซต์, วิเคราะห์ lead",
    version="1.0.0",
)

app.include_router(campaign.router)


@app.get("/health", tags=["health"])
def health_check() -> dict:
    """เช็คง่ายๆ ว่าเซิร์ฟเวอร์ยังมีชีวิตอยู่ไหม (ไม่ต้องผ่านยาม เพราะไม่ใช่ endpoint ลับ)"""
    return {
        "status": "ok",
        "has_ai": settings.has_ai,
        "env": settings.APP_ENV,
    }
