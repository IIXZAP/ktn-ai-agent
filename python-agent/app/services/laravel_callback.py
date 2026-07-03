import logging

import httpx
from app.core.config import get_settings
from app.schemas.campaigns import (
    AiAnalysisResultsPayload,
    CrawlResultsPayload,
    JobCompletedPayload,
    JobFailedPayload,
    JobProgressPayload,
    LeadScoresPayload,
    ResearchResultsPayload,
    WebsiteResultsPayload,
)

logger = logging.getLogger(__name__)


class LaravelCallbackClient:
    """
    บุรุษไปรษณีย์: ส่งจดหมาย (callback) แต่ละแบบไปหา Laravel

    วิธีใช้ (Pipeline ใน Step 6 จะเรียกแบบนี้):
        client = LaravelCallbackClient(callback_base_url=request.callback_base_url)
        client.job_progress(payload)
    """

    def __init__(self, callback_base_url: str, timeout: float = 10.0) -> None:
        # ตัด "/" ท้าย URL ออก กันเคส URL ซ้อนกันเป็น "//job-progress"
        self._base_url = str(callback_base_url).rstrip("/")
        self._timeout = timeout

    def _headers(self) -> dict[str, str]:
        """แปะตราประทับลับทุกครั้งที่ส่งจดหมาย"""
        settings = get_settings()
        return {"Authorization": f"Bearer {settings.PYTHON_AGENT_SECRET}"}

    def _post(self, endpoint: str, payload) -> httpx.Response | None:
        """
        ส่ง POST ไปยัง {base_url}/{endpoint}
        คืน None ถ้าส่งไม่สำเร็จ (ไม่ raise exception กัน Pipeline ทั้งสายพานล้มเพราะ
        เน็ตหลุดแค่แว้บเดียว - แค่ log ไว้เตือน)
        """
        url = f"{self._base_url}/{endpoint}"
        try:
            response = httpx.post(
                url,
                json=payload.model_dump(mode="json"),
                headers=self._headers(),
                timeout=self._timeout,
            )
            response.raise_for_status()
            return response
        except httpx.HTTPError as exc:
            logger.error("ส่ง callback ไป %s ไม่สำเร็จ: %s", url, exc)
            return None

    # ------------------------------------------------------------------
    # Method สำหรับแต่ละ Stage (ตรงกับตาราง Step 1.2 / Step 7 ใน README)
    # ------------------------------------------------------------------

    def job_progress(self, payload: JobProgressPayload) -> httpx.Response | None:
        return self._post("job-progress", payload)

    def research_results(
        self, payload: ResearchResultsPayload
    ) -> httpx.Response | None:
        return self._post("research-results", payload)

    def website_results(self, payload: WebsiteResultsPayload) -> httpx.Response | None:
        return self._post("website-results", payload)

    def crawl_results(self, payload: CrawlResultsPayload) -> httpx.Response | None:
        return self._post("crawl-results", payload)

    def ai_analysis_results(
        self, payload: AiAnalysisResultsPayload
    ) -> httpx.Response | None:
        return self._post("ai-analysis-results", payload)

    def lead_scores(self, payload: LeadScoresPayload) -> httpx.Response | None:
        return self._post("lead-scores", payload)

    def job_completed(self, payload: JobCompletedPayload) -> httpx.Response | None:
        return self._post("job-completed", payload)

    def job_failed(self, payload: JobFailedPayload) -> httpx.Response | None:
        return self._post("job-failed", payload)
