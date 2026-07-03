import hashlib

from app.connectors.base import WebsiteFinderConnector
from app.schemas.campaigns import WebsiteResult

_NO_WEBSITE_CHANCE = 0.2  # 20% ของบริษัท จะไม่มีเว็บไซต์


def _company_hash_ratio(company_name: str) -> float:
    """แปลงชื่อบริษัทเป็นตัวเลข 0.0-1.0 แบบ deterministic (ชื่อเดิม -> เลขเดิมเสมอ)"""
    digest = hashlib.md5(company_name.encode()).hexdigest()
    return int(digest, 16) % 1000 / 1000


def _slugify(company_name: str) -> str:
    """แปลงชื่อบริษัทเป็น URL slug ง่ายๆ สำหรับ mock"""
    digest = hashlib.md5(company_name.encode()).hexdigest()[:8]
    return f"company-{digest}"


class MockWebsiteFinder(WebsiteFinderConnector):
    """นักสำรวจปลอม: เดาว่าบริษัทมีเว็บไซต์ไหม แบบ deterministic"""

    def find(self, company_name: str) -> WebsiteResult:
        ratio = _company_hash_ratio(company_name)

        if ratio < _NO_WEBSITE_CHANCE:
            return WebsiteResult(
                company_name=company_name,
                web_url=None,
                has_website=False,
                website_status="not_found",
            )

        slug = _slugify(company_name)
        return WebsiteResult(
            company_name=company_name,
            web_url=f"https://{slug}.example.com",
            has_website=True,
            website_status="found",
        )
