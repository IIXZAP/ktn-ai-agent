import hashlib
import random

from app.connectors.base import CrawlerConnector
from app.schemas.campaigns import CrawlResult, CrawlSignal


def _seed_from_url(url: str) -> int:
    """แปลง URL เป็นตัวเลข seed คงที่ (URL เดิม -> ผลตรวจเดิมเสมอ)"""
    return int(hashlib.md5(url.encode()).hexdigest(), 16) % (2**32)


class MockCrawler(CrawlerConnector):
    """นักสำรวจปลอม: จำลองผลตรวจเว็บไซต์ แบบ deterministic ตาม URL"""

    def crawl(self, company_name: str, url: str) -> CrawlResult:
        rng = random.Random(_seed_from_url(url))

        load_time_ms = rng.randint(300, 6000)
        page_speed_score = rng.randint(20, 100)
        has_ssl = rng.random() > 0.3  # 70% มี SSL
        is_mobile_friendly = rng.random() > 0.35  # 65% รองรับมือถือ
        has_clear_cta = rng.random() > 0.4  # 60% มีปุ่มติดต่อชัดเจน
        is_broken = rng.random() < 0.1  # 10% เว็บพัง/ลิงก์เสีย
        has_seo = rng.random() > 0.45  # 55% มี SEO พื้นฐาน

        signals: list[CrawlSignal] = []

        if load_time_ms > 3000:
            signals.append(CrawlSignal(signal_type="slow_website", confidence=0.9))
        if page_speed_score < 50:
            signals.append(CrawlSignal(signal_type="old_website", confidence=0.7))
        if not has_ssl:
            signals.append(CrawlSignal(signal_type="no_ssl", confidence=1.0))
        if not is_mobile_friendly:
            signals.append(
                CrawlSignal(signal_type="not_mobile_friendly", confidence=0.85)
            )
        if not has_clear_cta:
            signals.append(CrawlSignal(signal_type="no_clear_cta", confidence=0.75))
        if is_broken:
            signals.append(CrawlSignal(signal_type="broken_website", confidence=0.95))
        if not has_seo:
            signals.append(CrawlSignal(signal_type="no_seo", confidence=0.6))

        crawl_status = "failed" if is_broken and rng.random() < 0.3 else "success"

        return CrawlResult(
            company_name=company_name,
            url=url,
            http_code=200 if crawl_status == "success" else 500,
            title=f"{company_name} - หน้าแรก",
            meta_description=f"เว็บไซต์อย่างเป็นทางการของ {company_name}",
            load_time_ms=load_time_ms,
            page_speed_score=page_speed_score,
            has_ssl=has_ssl,
            is_mobile_friendly=is_mobile_friendly,
            crawl_status=crawl_status,
            crawl_error=None if crawl_status == "success" else "จำลอง server error 500",
            signals=signals,
        )
