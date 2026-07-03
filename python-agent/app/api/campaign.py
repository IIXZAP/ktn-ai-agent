import logging

from app.core.security import verify_internal_secret
from app.schemas.campaigns import (
    CampaignProcessRequest,
    CampaignProcessResponse,
    ProcessMode,
)
from app.services import ai_criteria_parser
from app.services.campaign_pipeline import CampaignPipeline
from fastapi import APIRouter, BackgroundTasks, Depends

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/internal/v1/campaigns", tags=["campaigns"])


@router.post(
    "/{campaign_id}/process",
    response_model=CampaignProcessResponse,
    dependencies=[Depends(verify_internal_secret)],  # ยามตรวจบัตรก่อนเข้าประตูเสมอ
)
def process_campaign(
    campaign_id: int,
    request: CampaignProcessRequest,
    background_tasks: BackgroundTasks,
) -> CampaignProcessResponse:
    """
    Endpoint เดียวที่ Laravel เรียกเข้ามา แยกการทำงานตาม request.mode
    """
    # กันเคส path param กับ body ไม่ตรงกัน (เผื่อ Laravel ส่งมาไม่ sync กัน)
    if request.campaign_id != campaign_id:
        logger.warning(
            "campaign_id ใน path (%s) ไม่ตรงกับใน body (%s)",
            campaign_id,
            request.campaign_id,
        )

    if request.mode == ProcessMode.PARSE_ONLY:
        # ---- ทำงานทันที รอผลได้เลย (synchronous) ----
        criteria = ai_criteria_parser.parse(request)
        return CampaignProcessResponse(
            status="parsed",
            campaign_id=campaign_id,
            criteria=criteria,
        )

    # ---- mode == full: ส่งงานไปทำ background แล้วตอบกลับทันที ----
    pipeline = CampaignPipeline()
    background_tasks.add_task(pipeline.run, request)

    return CampaignProcessResponse(
        status="accepted",
        campaign_id=campaign_id,
        message="งานถูกรับเข้าคิวแล้ว จะรายงานผลผ่าน callback ทีละ Stage",
    )
