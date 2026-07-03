# Auto read Environment Variable
from __future__ import annotations

from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    APP_NAME: str
    APP_ENV: str
    APP_timezone: str

    PYTHON_AGENT_SECRET: str = "0JIlSM0omSC9clze0vhNoIVH"

    # AI (Chatgpt)
    OPENAI_API_KEY: str | None = None
    OPENAI_MODEL: str = "gpt-4o-mini"

    database_url: str | None = None

    # Check ว่ามี api key
    @property
    def has_ai(self) -> Settings:
        return bool(self.OPENAI_API_KEY)


# โหลด Settings แค่ครั้งเดียวแล้วเก็บ cache ไว้
@lru_cache
def get_settings() -> Settings:
    return Settings()


settings = get_settings()
