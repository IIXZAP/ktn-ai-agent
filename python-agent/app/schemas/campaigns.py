from __future__ import annotations

from pydantic import BaseModel, ConfigDict, Field

class searchCriteria(BaseModel):
    country: str = "Thailand"
    locations: list[str] = Field(default_factory=list)
    distance: int
    company_type: list[str]
    register_value: int
    target_signal: str
    must_have_website: bool | None = None
    required_contact_types: list[str] = Field(default_factory=list) 
    maximum_lead: int = 20

# Request
class CampaignProcessRequest(BaseModel):
    mode: str = "full"
    natural_language_query: str
    maximum_lead: int = 20
    
# Response
class CampaignProcessResponse(BaseModel):