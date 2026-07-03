# Test mock


import hashlib
import random

from app.connectors.base import BusinessSearchConnector
from app.schemas.campaigns import DiscoveredCompany, SearchCriteria

# ชื่อบริษัทตัวอย่าง แยกตาม industry เพื่อให้ผลลัพธ์ดูสมจริงขึ้นเล็กน้อย
_NAME_POOL: dict[str, list[str]] = {
    "food": ["ร้านอาหารสยาม", "ครัวคุณหญิง", "บ้านข้าวมัน", "ก๋วยเตี๋ยวเรือทอง", "ครัวริมทาง"],
    "fashion": ["สไตล์บูติก", "เสื้อผ้าแฟชั่นดี", "ร้านผ้าไหมทอง", "ชุดสวยดอทคอม"],
    "finance": ["สินเชื่อไทยพัฒนา", "การเงินมั่นคง", "ลงทุนยั่งยืน"],
    "real_estate": ["บ้านสวยพร็อพเพอร์ตี้", "ที่ดินทองคำ", "คอนโดวิว"],
    "default": ["บริษัท ตัวอย่าง จำกัด", "ห้างหุ้นส่วนตัวอย่าง", "SME ตัวอย่าง"],
}

_PROVINCES = ["Bangkok", "Chiang Mai", "Chonburi", "Nonthaburi", "Khon Kaen"]


def _make_seed(criteria: SearchCriteria) -> int:
    """
    แปลง criteria เป็นตัวเลข seed คงที่ (deterministic)
    ใช้ md5 hash ของข้อความรวม industries + locations
    เพื่อให้ criteria เดิม -> ได้ seed เดิมเสมอ -> ได้ผลลัพธ์เดิมเสมอ
    """
    raw = (
        "|".join(sorted(criteria.industries))
        + "|"
        + "|".join(sorted(criteria.locations))
    )
    return int(hashlib.md5(raw.encode()).hexdigest(), 16) % (2**32)


class MockConnector(BusinessSearchConnector):
    """นักสำรวจปลอม: สร้างรายชื่อบริษัทตาม criteria แบบ deterministic"""

    def search(self, criteria: SearchCriteria) -> list[DiscoveredCompany]:
        rng = random.Random(_make_seed(criteria))

        industry = criteria.industries[0] if criteria.industries else "default"
        name_pool = _NAME_POOL.get(industry, _NAME_POOL["default"])
        location_pool = criteria.locations or _PROVINCES

        companies: list[DiscoveredCompany] = []
        for i in range(criteria.maximum_leads):
            base_name = rng.choice(name_pool)
            name = f"{base_name} {i + 1}"
            # source_external_id จำลอง ID จากระบบต้นทาง (เช่น Google Place ID)
            # ใช้ hash ของชื่อ + criteria เพื่อให้ deterministic (ชื่อเดิม -> id เดิมเสมอ)
            external_id = hashlib.md5(f"{name}|{industry}".encode()).hexdigest()[:12]

            companies.append(
                DiscoveredCompany(
                    name=name,
                    province=rng.choice(location_pool),
                    address=f"เลขที่ {rng.randint(1, 999)} ถนนตัวอย่าง",
                    tel=f"0{rng.randint(600000000, 999999999)}",
                    contact_arr={"line": f"@mock{i + 1}"},
                    source_name="mock",
                    source_external_id=f"mock-{external_id}",
                    raw_payload={"mock_seed": external_id, "industry": industry},
                )
            )

        return companies
