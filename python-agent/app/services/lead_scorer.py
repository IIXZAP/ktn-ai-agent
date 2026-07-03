# Calculate Lead_score

from __future__ import annotations

# from dataclasses import Field
from app.schemas.campaigns import AiAnalysisResult, LeadScore, SearchCriteria

OPPORTUNITY_WEIGHT = 0.6
SIGNAL_MATCH_WEIGHT = 0.4

DEFAULT_THRESHOLD = 70


def calculate_signal_match_score(
    match_signals: list[str], target_signals: list[str]
) -> int:
    if not target_signals:
        return 100

    matched_count = len(set(match_signals) & set(target_signals))
    return round((matched_count / len(target_signals)) * 100)


def score(opportunity_score: int, signal_match_score: int) -> int:
    raw = (
        opportunity_score * OPPORTUNITY_WEIGHT
        + signal_match_score * SIGNAL_MATCH_WEIGHT
    )
    return round(raw)


def score_all(
    analyses: list[AiAnalysisResult],
    matched_signals_by_company: dict[str, list[str]],
    criteria: SearchCriteria,
    threshold: int = DEFAULT_THRESHOLD,
) -> list[LeadScore]:
    results: list[LeadScore] = []

    for analysis in analyses:
        matched = matched_signals_by_company.get(analysis.company_name, [])
        signal_match = calculate_signal_match_score(matched, criteria.target_signals)
        lead_score = score(analysis.opportunity_score, signal_match)

        results.append(
            LeadScore(
                company_name=analysis.company_name,
                lead_score=lead_score,
                opportunity_score=analysis.opportunity_score,
                signal_match_score=signal_match,
                matched_signals=matched,
                above_threshold=lead_score >= threshold,
            )
        )

    return results
