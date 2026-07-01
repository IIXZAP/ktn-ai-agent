from __future__ import annotations

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, status

from app.core.security import verify_internal_secret
from app.schemas.campaigns import 


router = APIRouter(
    prefix="/internal/v1",
    tags=["internal", "campaign"],
    dependencies=[Depends(verify_internal_secret)],
)