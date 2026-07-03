import logging

from app.core.config import get_settings
from app.schemas.campaigns import AiAnalysisResult, CrawlResult

logger = logging.getLogger(__name__)

_SIGNAL_RULES: dict[str, dict] = {
    "old_website": {
        "pain_point": "เว็บไซต์ดูล้าสมัย อาจทำให้ลูกค้าลดความเชื่อมั่น",
        "service": "ออกแบบเว็บไซต์ใหม่",
        "score_boost": 15,
    },
    "slow_website": {
        "pain_point": "เว็บไซต์โหลดช้า เสี่ยงลูกค้าหนีก่อนเห็นสินค้า",
        "service": "ปรับปรุงประสิทธิภาพเว็บไซต์ (Performance Optimization)",
        "score_boost": 20,
    },
    "no_ssl": {
        "pain_point": "เว็บไซต์ไม่มี SSL ลูกค้าอาจไม่กล้ากรอกข้อมูล",
        "service": "ติดตั้ง SSL Certificate",
        "score_boost": 15,
    },
    "no_website": {
        "pain_point": "ยังไม่มีเว็บไซต์เลย พลาดโอกาสเข้าถึงลูกค้าออนไลน์",
        "service": "สร้างเว็บไซต์ใหม่ทั้งหมด",
        "score_boost": 25,
    },
    "broken_website": {
        "pain_point": "เว็บไซต์มีลิงก์เสียหรือบางส่วนเข้าใช้งานไม่ได้ ทำให้ลูกค้าหงุดหงิดและออกจากเว็บ",
        "service": "แก้ไขบั๊กเว็บไซต์และดูแลระบบ (Website Maintenance)",
        "score_boost": 20,
    },
    "no_seo": {
        "pain_point": "เว็บไซต์ไม่มีการทำ SEO เลย ลูกค้าหาเจอยากบน Google",
        "service": "วางแผน SEO เบื้องต้น (SEO Setup)",
        "score_boost": 15,
    },
    "seo_problem": {
        "pain_point": "เว็บไซต์มีปัญหาด้าน SEO ทำให้อันดับการค้นหาต่ำกว่าคู่แข่ง",
        "service": "ปรับปรุง SEO (SEO Optimization)",
        "score_boost": 15,
    },
}

_BASE_SCORE = 30


def _fallback_analyse(company_name: str, signal_types: list[str]) -> AiAnalysisResult:

    pain_points: list[str] = []
    services: list[str] = []
    score = _BASE_SCORE

    for signal in signal_types:
        rule = _SIGNAL_RULES.get(signal)
        if rule:
            pain_points.append(rule["pain_point"])
            services.append(rule["service"])
            score += rule["score_boost"]

    score = min(score, 100)  # กันคะแนนเกิน 100

    recommended_service = (
        services[0] if services else "ให้คำปรึกษาด้าน Digital Marketing ทั่วไป"
    )
    recommended_approach = (
        f"เข้าหาโดยเน้นปัญหา: {', '.join(pain_points)}"
        if pain_points
        else "เว็บไซต์อยู่ในสภาพดี อาจเสนอบริการเสริมด้าน Marketing แทน"
    )

    return AiAnalysisResult(
        company_name=company_name,
        opportunity_score=score,
        pain_points=pain_points,
        key_findings=[f"เจอสัญญาณ: {s}" for s in signal_types],
        recommended_approach=recommended_approach,
        recommended_service=recommended_service,
        source="mock",
    )


# ส่งข้อมูลบริษัท + signals ให้ GPT วิเคราะห์เชิงลึก
def _ai_analyse(company_name: str, signal_types: list[str]) -> AiAnalysisResult:

    raise NotImplementedError(
        "ยังไม่ได้ต่อ OpenAI API จริง - ตอนนี้ใช้ _fallback_analyse แทนไปก่อน"
    )


# ดึง signal_type ทั้งหมดที่เจอจากทุกหน้าที่ crawl มาของบริษัทนี้แล้วส่งไปวิเคราะห์ต่อ (AI หรือ fallback)
def analyse(company_name: str, crawl_results: list[CrawlResult]) -> AiAnalysisResult:

    settings = get_settings()

    signal_types = [
        signal.signal_type for result in crawl_results for signal in result.signals
    ]

    if settings.has_ai:
        try:
            return _ai_analyse(company_name, signal_types)
        except NotImplementedError:
            logger.warning("AI analyser ยังไม่พร้อมใช้งาน, fallback ไปใช้ rule-based แทน")

    return _fallback_analyse(company_name, signal_types)
