from abc import ABC, abstractmethod

from app.schemas.campaigns import (
    CrawlResult,
    DiscoveredCompany,
    SearchCriteria,
    WebsiteResult,
)


# receive SearchCriteria and return value of list[company]
class BusinessSearchConnector(ABC):
    @abstractmethod
    def search(self, criteria: SearchCriteria) -> list[DiscoveredCompany]: ...


# filter that have website
class WebsiteFinderConnector(ABC):
    @abstractmethod
    def find(self, company_name: str) -> WebsiteResult: ...


# if (hasURL) -> analyes and return values (ความเร็ว, SSL, มือถือ ฯลฯ) พร้อม signals
class CrawlerConnector(ABC):
    @abstractmethod
    def crawl(self, company_name: str, url: str) -> CrawlResult: ...
