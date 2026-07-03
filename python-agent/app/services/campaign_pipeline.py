import logging
from datetime import datetime, timezone

from app.connectors.base import (
    BusinessSearchConnector,
    CrawlerConnector,
    WebsiteFinderConnector,
)
from app.connectors.mock import MockConnector
from app.connectors.mock_crawler import MockCrawler
from app.connectors.mock_website_finder import MockWebsiteFinder
from app.schemas.campaigns import (
    AiAnalysisResultsPayload,
    CampaignProcessRequest,
    CrawlResultsPayload,
    JobCompletedPayload,
    JobFailedPayload,
    JobProgressPayload,
    LeadScoresPayload,
    ResearchResultsPayload,
    WebsiteResultsPayload,
)
from app.services import ai_company_analyser, ai_criteria_parser, lead_scorer
from app.services.laravel_callback import LaravelCallbackClient

logger = logging.getLogger(__name__)


class CampaignPipeline:
    """
    หัวหน้าโรงงาน: รับ CampaignProcessRequest แล้วเดินสายพานทั้ง 7 Stage

    Connector ทั้ง 3 ตัวใส่มาจากข้างนอกได้ (dependency injection) เพื่อ:
      - วันหลังสลับจาก Mock เป็นของจริงได้ง่าย (ตามที่ตกลงไว้ Step 4)
      - เขียน test ปลอม (inject mock ปลอมซ้อนอีกที) ได้สะดวก
    ถ้าไม่ระบุมา จะใช้ตัว Mock เป็นค่าเริ่มต้น
    """

    def __init__(
        self,
        business_connector: BusinessSearchConnector | None = None,
        website_finder: WebsiteFinderConnector | None = None,
        crawler: CrawlerConnector | None = None,
    ) -> None:
        self.business_connector = business_connector or MockConnector()
        self.website_finder = website_finder or MockWebsiteFinder()
        self.crawler = crawler or MockCrawler()

    def run(self, request: CampaignProcessRequest) -> None:
        """
        ฟังก์ชันหลักที่ Step 7 (API) จะเรียกผ่าน background_tasks.add_task()
        ไม่ return อะไร เพราะผลลัพธ์ทั้งหมดส่งผ่าน callback ไป Laravel แทน
        """
        if request.research_job_id is None:
            logger.error("ไม่มี research_job_id ส่งมา - ไม่สามารถรัน pipeline แบบ full ได้")
            return

        campaign_id = request.campaign_id
        research_job_id = request.research_job_id
        client = LaravelCallbackClient(callback_base_url=str(request.callback_base_url))

        try:
            # ---------------- Stage 1: Parse Criteria ----------------
            criteria = ai_criteria_parser.parse(request)
            self._report_progress(client, campaign_id, research_job_id, 10, "parsing")

            # ---------------- Stage 2: Search Companies ----------------
            companies = self.business_connector.search(criteria)
            client.research_results(
                ResearchResultsPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    companies=companies,
                )
            )
            self._report_progress(
                client, campaign_id, research_job_id, 25, "discovering"
            )

            # ---------------- Stage 3: Find Websites ----------------
            websites = {c.name: self.website_finder.find(c.name) for c in companies}
            client.website_results(
                WebsiteResultsPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    results=list(websites.values()),
                )
            )
            self._report_progress(
                client, campaign_id, research_job_id, 40, "finding_websites"
            )

            # ---------------- Stage 4: Crawl Websites ----------------
            crawl_by_company: dict[str, list] = {}
            crawl_flat = []
            for company in companies:
                website = websites[company.name]
                if website.has_website and website.web_url:
                    result = self.crawler.crawl(company.name, str(website.web_url))
                    crawl_by_company[company.name] = [result]
                    crawl_flat.append(result)
                else:
                    crawl_by_company[company.name] = []

            client.crawl_results(
                CrawlResultsPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    results=crawl_flat,
                )
            )
            self._report_progress(client, campaign_id, research_job_id, 60, "crawling")

            # ---------------- Stage 5: AI Company Analysis ----------------
            analyses = [
                ai_company_analyser.analyse(
                    company.name, crawl_by_company[company.name]
                )
                for company in companies
            ]
            client.ai_analysis_results(
                AiAnalysisResultsPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    results=analyses,
                )
            )
            self._report_progress(client, campaign_id, research_job_id, 80, "analyzing")

            # ---------------- Stage 6: Lead Scoring ----------------
            matched_signals = {
                name: [signal.signal_type for r in results for signal in r.signals]
                for name, results in crawl_by_company.items()
            }
            scores = lead_scorer.score_all(analyses, matched_signals, criteria)
            client.lead_scores(
                LeadScoresPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    scores=scores,
                )
            )
            self._report_progress(client, campaign_id, research_job_id, 95, "scoring")

            # ---------------- Stage 7: Job Completed ----------------
            total_leads = sum(1 for s in scores if s.above_threshold)
            client.job_completed(
                JobCompletedPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    completed_at=datetime.now(timezone.utc),
                    total_leads=total_leads,
                )
            )
            self._report_progress(
                client, campaign_id, research_job_id, 100, "completed"
            )

        except Exception as exc:  # noqa: BLE001 - ต้องจับทุก error กัน background task ตายเงียบ
            logger.exception("Pipeline ล้มเหลวสำหรับ campaign_id=%s", campaign_id)
            client.job_failed(
                JobFailedPayload(
                    campaign_id=campaign_id,
                    research_job_id=research_job_id,
                    error_message=str(exc),
                    failed_at=datetime.now(timezone.utc),
                )
            )

    @staticmethod
    def _report_progress(
        client: LaravelCallbackClient,
        campaign_id: int,
        research_job_id: int,
        percent: int,
        stage: str,
    ) -> None:
        """เรียกซ้ำบ่อยๆ ตอนจบแต่ละ Stage เลยแยกออกมาเป็น helper กันเขียนโค้ดซ้ำ"""
        client.job_progress(
            JobProgressPayload(
                campaign_id=campaign_id,
                research_job_id=research_job_id,
                progress_percent=percent,
                current_stage=stage,
            )
        )
