from __future__ import annotations

from fastapi import Header, HTTPException, status
from app.core.config import settings


async def verify_internal_secret(
        authorization: str | None = Header(default=None),
) -> None:
    
    if authorization is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization header",
        )
    
    schema, __, token = authorization.partition(" ")

    if schema.lower() != "bearer" or token != settings.python_agent_secret:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal service credentials",
        )