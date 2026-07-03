from __future__ import annotations

from datetime import datetime
from enum import Enum
from typing import Literal

from pydantic import BaseModel, Field, HttpUrl

# ค่า signal ที่ระบบรู้จัก (ใช้จำกัดค่าที่ AI Parser แปลงออกมา
# กัน AI มโนค่าที่ไม่มีในระบบ)
SignalType = Literal[
    "old_website",
    "slow_website",
    "no_ssl",
    "no_clear_cta",
    "not_mobile_friendly",
    "no_website",
    "broken_website",
    "no_seo",
    "seo_problem",
]


# ---------------------------------------------------------------------------
# 1. Criteria / Search
# ---------------------------------------------------------------------------


class ProcessMode(str, Enum):
    """โหมดการทำงานที่ Laravel สั่งให้ Python ทำ"""

    PARSE_ONLY = "parse_only"
    FULL = "full"


class SearchCriteria(BaseModel):
    """
    เกณฑ์ค้นหาสุดท้าย = ข้อมูลจากฟอร์ม (ตรงๆ) + target_signals (จาก AI แปล signal_description)
    ใช้เก็บลงตาราง campaign_search_criteria
    """

    country: str = "Thailand"
    locations: list[str] = Field(default_factory=list)
    industries: list[str] = Field(default_factory=list)
    distance: int | None = Field(default=None, description="รัศมีค้นหา (กม.) ถ้ามี")
    register_value: int | None = Field(default=None, description="ทุนจดทะเบียนขั้นต่ำ ถ้ามี")
    must_have_website: bool | None = None
    required_contact_types: list[str] = Field(default_factory=list)
    maximum_leads: int = Field(default=20, ge=1, le=500)

    # ฟิลด์เดียวที่มาจาก AI Criteria Parser (แปลงจาก signal_description)
    target_signals: list[SignalType] = Field(default_factory=list)

    source: str = Field(
        default="mock", description="'ai' หรือ 'mock' ขึ้นกับว่าใช้ GPT parse หรือไม่"
    )


# ---------------------------------------------------------------------------
# 2. Request / Response หลัก (Laravel -> Python -> Laravel)
# ---------------------------------------------------------------------------


class CampaignProcessRequest(BaseModel):
    """
    Body ที่ Laravel ส่งมาให้ Python ที่ POST /internal/v1/campaigns/{id}/process

    ฟิลด์ต่อไปนี้ Manager กรอก/เลือกจากฟอร์มโดยตรง (ไม่ผ่าน AI):
        country, locations, industries, distance, register_value,
        must_have_website, required_contact_types, maximum_leads

    ฟิลด์เดียวที่เป็น free text ให้ AI Criteria Parser (Step 3.1) แปลงต่อ:
        signal_description
    """

    mode: ProcessMode
    campaign_id: int
    research_job_id: int | None = None
    callback_base_url: HttpUrl

    # ---- มาจากฟอร์มตรงๆ ----
    country: str = "Thailand"
    locations: list[str] = Field(default_factory=list)
    industries: list[str] = Field(default_factory=list)
    distance: int | None = None
    register_value: int | None = None
    must_have_website: bool | None = None
    required_contact_types: list[str] = Field(default_factory=list)
    maximum_leads: int = Field(default=20, ge=1, le=500)

    # ---- ช่องเดียวที่ต้องให้ AI ช่วยแปล ----
    signal_description: str | None = Field(
        default=None,
        min_length=1,
        description="ข้อความอิสระ เช่น 'เว็บไซต์โหลดช้าหรือไม่มี SSL'",
    )


class CampaignProcessResponse(BaseModel):
    """
    สิ่งที่ Python ตอบกลับ Laravel ทันที (synchronous response)
    - ถ้า mode = parse_only  -> จะมี criteria แนบมาด้วยเลย
    - ถ้า mode = full        -> ตอบแค่ 'accepted' แล้วไปทำงานต่อใน background
    """

    status: str = Field(..., description="'accepted' หรือ 'parsed'")
    campaign_id: int
    criteria: SearchCriteria | None = None
    message: str | None = None


class AiUsage(BaseModel):
    """เก็บ token / cost ของการเรียก AI แต่ละครั้ง เพื่อส่งกลับไปบันทึกใน ai_usage_logs"""

    operation: str = Field(..., description="เช่น 'parse_criteria', 'analyse_company'")
    model: str | None = None
    input_tokens: int = 0
    output_tokens: int = 0
    estimated_cost: float = 0.0
    duration_ms: int = 0
    status: str = Field(default="success", description="'success' หรือ 'failed'")


# ---------------------------------------------------------------------------
# 3. Callback payloads (Python -> Laravel ทีละ Stage)
# ---------------------------------------------------------------------------


class JobProgressPayload(BaseModel):
    """POST /api/internal/job-progress"""

    campaign_id: int
    research_job_id: int
    progress_percent: int = Field(..., ge=0, le=100)
    current_stage: str


class DiscoveredCompany(BaseModel):
    """บริษัทที่เจอจากขั้นตอนค้นหา (Stage 2)"""

    name: str
    province: str | None = None
    address: str | None = None
    tel: str | None = None
    contact_arr: dict | None = None

    # เพิ่มตาม company_sources (Laravel) เพื่อบันทึกว่าเจอบริษัทนี้จากแหล่งไหน
    source_name: str = Field(
        default="mock", description="เช่น mock, google_places, serpapi"
    )
    source_external_id: str | None = Field(
        default=None, description="ID ของบริษัทนี้ในระบบต้นทาง ใช้กันข้อมูลซ้ำ"
    )
    raw_payload: dict | None = Field(
        default=None, description="ข้อมูลดิบจากแหล่งต้นทาง ไว้ debug"
    )


class ResearchResultsPayload(BaseModel):
    """POST /api/internal/research-results"""

    campaign_id: int
    research_job_id: int
    companies: list[DiscoveredCompany]


class WebsiteResult(BaseModel):
    """ผลการหาเว็บไซต์ของบริษัทหนึ่งราย (Stage 3)"""

    company_name: str
    web_url: HttpUrl | None = None
    has_website: bool
    website_status: str | None = None


class WebsiteResultsPayload(BaseModel):
    """POST /api/internal/website-results"""

    campaign_id: int
    research_job_id: int
    results: list[WebsiteResult]


class CrawlSignal(BaseModel):
    """สัญญาณที่ตรวจเจอระหว่าง crawl เช่น slow_website, no_ssl"""

    signal_type: str
    signal_value: str | None = None
    confidence: float = Field(default=1.0, ge=0.0, le=1.0)


class CrawlResult(BaseModel):
    """ผลการ crawl เว็บไซต์หนึ่งหน้า (Stage 4)"""

    company_name: str
    url: HttpUrl
    http_code: int | None = None
    title: str | None = None
    meta_description: str | None = None
    load_time_ms: int | None = None
    page_speed_score: int | None = Field(default=None, ge=0, le=100)
    has_ssl: bool | None = None
    is_mobile_friendly: bool | None = None
    crawl_status: str = Field(default="pending")
    crawl_error: str | None = None
    signals: list[CrawlSignal] = Field(default_factory=list)


class CrawlResultsPayload(BaseModel):
    """POST /api/internal/crawl-results"""

    campaign_id: int
    research_job_id: int
    results: list[CrawlResult]


class AiAnalysisResult(BaseModel):
    """ผลวิเคราะห์บริษัทจาก AI Company Analyser (Stage 5)"""

    company_name: str
    opportunity_score: int = Field(..., ge=0, le=100)
    pain_points: list[str] = Field(default_factory=list)
    key_findings: list[str] = Field(default_factory=list)
    recommended_approach: str | None = None
    recommended_service: str | None = None
    source: str = Field(default="mock", description="'ai' หรือ 'mock'")
    usage: AiUsage | None = None


class AiAnalysisResultsPayload(BaseModel):
    """POST /api/internal/ai-analysis-results"""

    campaign_id: int
    research_job_id: int
    results: list[AiAnalysisResult]


class LeadScore(BaseModel):
    """คะแนน lead ของบริษัทหนึ่งราย (Stage 6)"""

    company_name: str
    lead_score: int = Field(..., ge=0, le=100)
    opportunity_score: int = Field(..., ge=0, le=100)
    signal_match_score: int = Field(..., ge=0, le=100)
    matched_signals: list[str] = Field(default_factory=list)
    above_threshold: bool


class LeadScoresPayload(BaseModel):
    """POST /api/internal/lead-scores"""

    campaign_id: int
    research_job_id: int
    scores: list[LeadScore]


class JobCompletedPayload(BaseModel):
    """POST /api/internal/job-completed"""

    campaign_id: int
    research_job_id: int
    completed_at: datetime
    total_leads: int = 0


class JobFailedPayload(BaseModel):
    """POST /api/internal/job-failed"""

    campaign_id: int
    research_job_id: int
    error_message: str
    failed_at: datetime
