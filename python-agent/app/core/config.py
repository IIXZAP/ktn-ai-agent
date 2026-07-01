# Auto read Environment Variable  
from __future__ import annotations

from pydantic_settings import BaseSettings, SettingsConfigDict

class Settings(BaseSettings):

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    APP_NAME: str
    APP_ENV: str
    APP_timezone: str

    PYTHON_AGENT_SECRET: str = "0JIlSM0omSC9clze0vhNoIVH"

    # Chatgpt    
    openai_api_key: str | None = None

    database_url: str | None = None

settings = Settings()