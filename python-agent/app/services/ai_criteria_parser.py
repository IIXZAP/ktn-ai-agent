import logging

from app.core.config import get_settings
from app.schemas.campaigns import CampaignProcessRequest, SearchCriteria, SignalType

logger = logging.getLogger(__name__)

_SIGNAL_KEYWORDS: dict[SignalType, list[str]] = {
    "old_website": ["เว็บเก่า"],
    "slow_website": ["เว็บช้า"],
    "no_ssl": ["ไม่มี ssl"],
    "no_website": ["ไม่มีเว็บไซต์"],
    "broken_website": ["เว็บพัง", "เว็บ error"],
    "no_seo": ["ไม่มี SEO"],
    "seo_problem": ["SEO มีปัญหา"],
}


def _fallback_parse_signals(signal_description: str) -> list[SignalType]:
    text = signal_description.lower()
    found: list[SignalType] = []
    # FIX: .item() -> .items() (dict ไม่มีเมธอด .item())
    for signal, keywords in _SIGNAL_KEYWORDS.items():
        # NOTE: keyword บางตัวมีตัวพิมพ์ใหญ่ (เช่น "ไม่มี SEO") จึง lower() ทั้งคู่กันพลาด
        if any(keyword.lower() in text for keyword in keywords):
            found.append(signal)
    return found


def _ai_parse_signal(signal_description: str) -> list[SignalType]:
    raise NotImplementedError("Can't connect OpenAi API")


def parse(request: CampaignProcessRequest) -> SearchCriteria:
    # call ai
    settings = get_settings()

    target_signals: list[SignalType] = []
    source = "mock"

    if request.signal_description:
        if settings.has_ai:
            try:
                target_signals = _ai_parse_signal(request.signal_description)
                source = "ai"
            except NotImplementedError:
                logger.warning("AI parser Unavailable")
                target_signals = _fallback_parse_signals(request.signal_description)
                source = "mock"
        else:
            target_signals = _fallback_parse_signals(request.signal_description)
            # FIX: เดิมเขียน `mock = "mock"` ทำให้ source ไม่ถูกอัปเดต
            source = "mock"

    return SearchCriteria(
        country=request.country,
        locations=request.locations,
        industries=request.industries,
        distance=request.distance,
        register_value=request.register_value,
        must_have_website=request.must_have_website,
        required_contact_types=request.required_contact_types,
        maximum_leads=request.maximum_leads,
        # FIX: เดิมหยิบจาก request (ซึ่งไม่มี field นี้ และทิ้งผลที่ parse มา)
        target_signals=target_signals,
        source=source,
    )
