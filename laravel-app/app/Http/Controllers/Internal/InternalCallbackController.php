<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanyAiAnalysis;
use App\Models\CompanyPage;
use App\Models\CompanySignal;
use App\Models\Lead;
use App\Models\ResearchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InternalCallbackController extends Controller
{
    // ============================================================
    // POST /api/internal/job-progress
    // Python ส่ง progress มาตอนแต่ละ stage เปลี่ยน
    // ============================================================
    public function jobProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'  => 'required|integer|exists:research_jobs,id',
            'campaign_id'      => 'required|integer|exists:campaigns,id',
            'progress_percent' => 'required|integer|min:0|max:100',
            'current_stage'    => 'required|string',
        ]);

        DB::transaction(function () use ($validated) {
            ResearchJob::where('id', $validated['research_job_id'])
                ->update([
                    'progress_percent' => $validated['progress_percent'],
                    'current_stage'    => $validated['current_stage'],
                    'status'           => 'running',
                ]);

            Campaign::where('id', $validated['campaign_id'])
                ->update([
                    'progress_percent' => $validated['progress_percent'],
                    'current_stage'    => $validated['current_stage'],
                    'status'           => $validated['current_stage'],
                ]);
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/research-results
    // Python ส่งรายชื่อบริษัทที่ค้นพบ
    // ============================================================
    public function researchResults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'        => 'required|integer|exists:research_jobs,id',
            'campaign_id'            => 'required|integer|exists:campaigns,id',
            'companies'              => 'required|array|min:1',
            'companies.*.company_name' => 'required|string',
            'companies.*.province'   => 'nullable|string',
            'companies.*.web_url'    => 'nullable|url',
            'companies.*.industry'   => 'nullable|string',
            'companies.*.tel'        => 'nullable|string',
            'companies.*.email'      => 'nullable|email',
            'companies.*.address'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['companies'] as $data) {
                Company::create([
                    'campaign_id'  => $validated['campaign_id'],
                    'company_name' => $data['company_name'],
                    'province'     => $data['province'] ?? null,
                    'web_url'      => $data['web_url'] ?? null,
                    'industry'     => $data['industry'] ?? null,
                    'tel'          => $data['tel'] ?? null,
                    'email'        => $data['email'] ?? null,
                    'address'      => $data['address'] ?? null,
                ]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/website-results
    // Python ส่งผลการหาเว็บไซต์ของแต่ละบริษัท
    // ============================================================
    public function websiteResults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'              => 'required|integer|exists:research_jobs,id',
            'results'                      => 'required|array|min:1',
            'results.*.company_id'         => 'required|integer|exists:companies,id',
            'results.*.web_url'            => 'nullable|url',
            'results.*.has_website'        => 'required|boolean',
            'results.*.website_status'     => 'nullable|in:active,down,unknown',
            'results.*.website_confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['results'] as $data) {
                Company::where('id', $data['company_id'])
                    ->update([
                        'web_url'            => $data['web_url'] ?? null,
                        'has_website'        => $data['has_website'],
                        'website_status'     => $data['website_status'] ?? 'unknown',
                        'website_confidence' => $data['website_confidence'] ?? null,
                    ]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/crawl-results
    // Python ส่งผลการ crawl เว็บไซต์
    // ============================================================
    public function crawlResults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'                => 'required|integer|exists:research_jobs,id',
            'results'                        => 'required|array|min:1',
            'results.*.company_id'           => 'required|integer|exists:companies,id',
            'results.*.url'                  => 'required|url',
            'results.*.http_code'            => 'nullable|integer',
            'results.*.title'                => 'nullable|string',
            'results.*.meta_description'     => 'nullable|string',
            'results.*.meta_keywords'        => 'nullable|string',
            'results.*.load_time_ms'         => 'nullable|integer',
            'results.*.page_speed_score'     => 'nullable|integer|min:0|max:100',
            'results.*.has_ssl'              => 'nullable|boolean',
            'results.*.is_mobile_friendly'   => 'nullable|boolean',
            'results.*.crawl_status'         => 'required|in:success,failed,timeout,blocked',
            'results.*.crawl_error'          => 'nullable|string',
            'results.*.signals'              => 'nullable|array',
            'results.*.signals.*.type'       => 'required|string',
            'results.*.signals.*.value'      => 'nullable|string',
            'results.*.signals.*.confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['results'] as $data) {
                // บันทึกผล crawl
                CompanyPage::create([
                    'company_id'         => $data['company_id'],
                    'url'                => $data['url'],
                    'http_code'          => $data['http_code'] ?? null,
                    'title'              => $data['title'] ?? null,
                    'meta_description'   => $data['meta_description'] ?? null,
                    'meta_keywords'      => $data['meta_keywords'] ?? null,
                    'load_time_ms'       => $data['load_time_ms'] ?? null,
                    'page_speed_score'   => $data['page_speed_score'] ?? null,
                    'has_ssl'            => $data['has_ssl'] ?? null,
                    'is_mobile_friendly' => $data['is_mobile_friendly'] ?? null,
                    'crawl_status'       => $data['crawl_status'],
                    'crawl_error'        => $data['crawl_error'] ?? null,
                    'crawled_at'         => now(),
                ]);

                // บันทึก signals ที่ตรวจเจอ
                if (!empty($data['signals'])) {
                    foreach ($data['signals'] as $signal) {
                        CompanySignal::create([
                            'company_id'   => $data['company_id'],
                            'signal_type'  => $signal['type'],
                            'signal_value' => $signal['value'] ?? null,
                            'confidence'   => $signal['confidence'] ?? 1.0,
                            'source_stage' => 'crawling',
                        ]);
                    }
                }
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/ai-analysis-results
    // Python ส่งผลวิเคราะห์บริษัทจาก AI
    // ============================================================
    public function aiAnalysisResults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'                    => 'required|integer|exists:research_jobs,id',
            'campaign_id'                        => 'required|integer|exists:campaigns,id',
            'results'                            => 'required|array|min:1',
            'results.*.company_id'               => 'required|integer|exists:companies,id',
            'results.*.business_summary'         => 'nullable|string',
            'results.*.opportunity_score'        => 'nullable|integer|min:0|max:100',
            'results.*.pain_points'              => 'nullable|array',
            'results.*.key_findings'             => 'nullable|array',
            'results.*.recommended_approach'     => 'nullable|string',
            'results.*.source'                   => 'required|in:ai,mock',
            'results.*.estimated_cost'           => 'nullable|numeric|min:0',
            'results.*.model'                    => 'nullable|string',
            'results.*.input_tokens'             => 'nullable|integer|min:0',
            'results.*.output_tokens'            => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['results'] as $data) {
                // บันทึกผลวิเคราะห์ AI
                CompanyAiAnalysis::updateOrCreate(
                    ['company_id' => $data['company_id']],
                    [
                        'business_summary'     => $data['business_summary'] ?? null,
                        'opportunity_score'    => $data['opportunity_score'] ?? null,
                        'pain_points'          => $data['pain_points'] ?? null,
                        'key_findings'         => $data['key_findings'] ?? null,
                        'recommended_approach' => $data['recommended_approach'] ?? null,
                        'estimated_cost'       => $data['estimated_cost'] ?? 0,
                        'source'               => $data['source'],
                        'analysed_at'          => now(),
                    ]
                );

                // บันทึก AI usage cost
                if (!empty($data['model'])) {
                    AiUsageLog::create([
                        'campaign_id'    => $validated['campaign_id'],
                        'company_id'     => $data['company_id'],
                        'operation'      => 'analyse_company',
                        'model'          => $data['model'],
                        'input_tokens'   => $data['input_tokens'] ?? 0,
                        'output_tokens'  => $data['output_tokens'] ?? 0,
                        'estimated_cost' => $data['estimated_cost'] ?? 0,
                        'status'         => 'success',
                    ]);
                }
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/lead-scores
    // Python ส่งคะแนน lead — บันทึกเฉพาะที่ผ่าน threshold
    // ============================================================
    public function leadScores(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id'                  => 'required|integer|exists:research_jobs,id',
            'campaign_id'                      => 'required|integer|exists:campaigns,id',
            'leads'                            => 'required|array|min:1',
            'leads.*.company_id'               => 'required|integer|exists:companies,id',
            'leads.*.lead_score'               => 'required|integer|min:0|max:100',
            'leads.*.opportunity_score'        => 'required|integer|min:0|max:100',
            'leads.*.signal_match_score'       => 'required|integer|min:0|max:100',
            'leads.*.matched_signals'          => 'nullable|array',
            'leads.*.above_threshold'          => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['leads'] as $data) {
                // บันทึกเฉพาะที่ผ่าน threshold
                if (!$data['above_threshold']) {
                    continue;
                }

                Lead::create([
                    'campaign_id'        => $validated['campaign_id'],
                    'company_id'         => $data['company_id'],
                    'lead_score'         => $data['lead_score'],
                    'opportunity_score'  => $data['opportunity_score'],
                    'signal_match_score' => $data['signal_match_score'],
                    'matched_signals'    => $data['matched_signals'] ?? null,
                ]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/job-completed
    // Python แจ้งว่างานเสร็จสมบูรณ์
    // ============================================================
    public function jobCompleted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id' => 'required|integer|exists:research_jobs,id',
            'campaign_id'     => 'required|integer|exists:campaigns,id',
        ]);

        DB::transaction(function () use ($validated) {
            ResearchJob::where('id', $validated['research_job_id'])
                ->update([
                    'status'           => 'completed',
                    'progress_percent' => 100,
                    'finished_at'      => now(),
                ]);

            Campaign::where('id', $validated['campaign_id'])
                ->update([
                    'status'           => 'completed',
                    'progress_percent' => 100,
                    'current_stage'    => null,
                    'completed_at'     => now(),
                ]);
        });

        return response()->json(['message' => 'ok']);
    }

    // ============================================================
    // POST /api/internal/job-failed
    // Python แจ้งว่างานล้มเหลว
    // ============================================================
    public function jobFailed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_job_id' => 'required|integer|exists:research_jobs,id',
            'campaign_id'     => 'required|integer|exists:campaigns,id',
            'error'           => 'required|string',
        ]);

        DB::transaction(function () use ($validated) {
            ResearchJob::where('id', $validated['research_job_id'])
                ->update([
                    'status'        => 'failed',
                    'error_message' => $validated['error'],
                    'finished_at'   => now(),
                ]);

            Campaign::where('id', $validated['campaign_id'])
                ->update([
                    'status'     => 'failed',
                    'last_error' => $validated['error'],
                ]);
        });

        Log::error('Campaign failed', [
            'campaign_id'     => $validated['campaign_id'],
            'research_job_id' => $validated['research_job_id'],
            'error'           => $validated['error'],
        ]);

        return response()->json(['message' => 'ok']);
    }
}
