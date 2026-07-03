from __future__ import annotations

from app.core.config import settings
from fastapi import Header, HTTPException, status


async def verify_internal_secret(
    authorization: str | None = Header(default=None),
) -> None:

    if authorization is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization header",
        )

    schema, __, token = authorization.partition(" ")

    # FIX 1: schema.lower() ได้ "bearer" ตัวเล็ก ต้องเทียบกับ "bearer" ไม่ใช่ "Bearer"
    # FIX 2: field จริงชื่อ PYTHON_AGENT_SECRET (ตัวใหญ่) เดิมเขียน python_agent_secret -> AttributeError
    if schema.lower() != "bearer" or token != settings.PYTHON_AGENT_SECRET:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal service credentials",
        )
