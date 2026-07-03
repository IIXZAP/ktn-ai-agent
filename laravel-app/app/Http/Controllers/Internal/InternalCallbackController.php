<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanyAiAnalysis;
use App\Models\CompanyPage;
use App\Models\CompanySignal;
use App\Models\CompanySource;
use App\Models\Lead;
use App\Models\ResearchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STEP 8 — InternalCallbackController: พนักงานรับจดหมายหน้าบ้าน Laravel
 *
 * หน้าที่: รับ callback ทั้ง 8 แบบจาก Python Agent (ดู laravel_callback.py ฝั่ง Python)
 * แล้วแกะเก็บลง Database ให้ถูกตาราง
 *
 * หมายเหตุ: endpoint เหล่านี้ต้องอยู่หลัง middleware ตรวจ
 * Authorization: Bearer <PYTHON_AGENT_SECRET> เสมอ (ดู routes/api.php)
 * เพื่อกัน "คนแปลกหน้า" ปลอมตัวเป็น Python Agent มายิง endpoint นี้
 */
class InternalCallbackController extends Controller
{
    /**
     * POST /api/internal/job-progress
     * อัปเดต ResearchJob.progress_percent และ Campaign.status
     */
    public function jobProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'current_stage' => ['required', 'string', 'max:255'],
        ]);

        ResearchJob::whereKey($data['research_job_id'])->update([
            'progress_percent' => $data['progress_percent'],
            'current_stage' => $data['current_stage'],
        ]);

        // current_stage ที่ Python ส่งมาตรงกับ campaigns_status_enum อยู่แล้ว
        // (parsing, discovering, finding_websites, crawling, analyzing, scoring, completed)
        Campaign::whereKey($data['campaign_id'])->update([
            'progress_percent' => $data['progress_percent'],
            'current_stage' => $data['current_stage'],
            'status' => $data['current_stage'],
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /api/internal/research-results
     * Company::firstOrCreate() ตามรายชื่อบริษัทที่ Python เจอ
     * + CompanySource::create() บันทึกว่าเจอบริษัทนี้จากแหล่งไหน (source_name)
     *   ใช้ source_external_id กันข้อมูลซ้ำเวลาเจอบริษัทเดิมจากคนละ Campaign/แหล่ง
     */
    public function researchResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'companies' => ['required', 'array', 'min:1'],
            'companies.*.name' => ['required', 'string', 'max:255'],
            'companies.*.province' => ['nullable', 'string', 'max:255'],
            'companies.*.address' => ['nullable', 'string'],
            'companies.*.tel' => ['nullable', 'string', 'max:255'],
            'companies.*.contact_arr' => ['nullable', 'array'],
            'companies.*.source_name' => ['nullable', 'string', 'max:255'],
            'companies.*.source_external_id' => ['nullable', 'string', 'max:255'],
            'companies.*.raw_payload' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['companies'] as $item) {
                $company = Company::firstOrCreate(
                    [
                        'campaign_id' => $data['campaign_id'],
                        'name' => $item['name'],
                    ],
                    [
                        'province' => $item['province'] ?? null,
                        'address' => $item['address'] ?? null,
                        'tel' => $item['tel'] ?? null,
                        'contact_arr' => $item['contact_arr'] ?? null,
                    ]
                );

                CompanySource::firstOrCreate(
                    [
                        // กันบันทึกซ้ำ ถ้า source เดิม + external_id เดิม ยิงเข้ามาซ้ำ
                        'source_name' => $item['source_name'] ?? 'mock',
                        'source_external_id' => $item['source_external_id'] ?? null,
                        'company_id' => $company->id,
                    ],
                    [
                        'research_job_id' => $data['research_job_id'],
                        'raw_payload' => $item['raw_payload'] ?? null,
                        'discovered_at' => now(),
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok', 'saved' => count($data['companies'])]);
    }

    /**
     * POST /api/internal/website-results
     * update Company.web_url ตามผลที่ Python หาเจอ (มีเว็บ/ไม่มีเว็บ)
     */
    public function websiteResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.company_name' => ['required', 'string', 'max:255'],
            'results.*.web_url' => ['nullable', 'url', 'max:255'],
            'results.*.has_website' => ['required', 'boolean'],
            'results.*.website_status' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['results'] as $item) {
                Company::where('campaign_id', $data['campaign_id'])
                    ->where('name', $item['company_name'])
                    ->update(['web_url' => $item['web_url'] ?? null]);
            }
        });

        return response()->json(['status' => 'ok', 'updated' => count($data['results'])]);
    }

    /**
     * POST /api/internal/crawl-results
     * CompanyPage::updateOrCreate() + CompanySignal::create() ตามผลตรวจเว็บ
     */
    public function crawlResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.company_name' => ['required', 'string', 'max:255'],
            'results.*.url' => ['required', 'url', 'max:255'],
            'results.*.http_code' => ['nullable', 'integer'],
            'results.*.title' => ['nullable', 'string', 'max:255'],
            'results.*.meta_description' => ['nullable', 'string'],
            'results.*.load_time_ms' => ['nullable', 'integer'],
            'results.*.page_speed_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'results.*.has_ssl' => ['nullable', 'boolean'],
            'results.*.is_mobile_friendly' => ['nullable', 'boolean'],
            'results.*.crawl_status' => ['required', 'string', 'max:255'],
            'results.*.crawl_error' => ['nullable', 'string'],
            'results.*.signals' => ['nullable', 'array'],
            'results.*.signals.*.signal_type' => ['required_with:results.*.signals', 'string', 'max:255'],
            'results.*.signals.*.signal_value' => ['nullable', 'string', 'max:255'],
            'results.*.signals.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['results'] as $item) {
                $company = Company::where('campaign_id', $data['campaign_id'])
                    ->where('name', $item['company_name'])
                    ->first();

                if (! $company) {
                    Log::warning('crawl-results: ไม่พบบริษัทในระบบ', [
                        'company_name' => $item['company_name'],
                        'campaign_id' => $data['campaign_id'],
                    ]);
                    continue;
                }

                $page = CompanyPage::updateOrCreate(
                    ['company_id' => $company->id, 'url' => $item['url']],
                    [
                        'http_code' => $item['http_code'] ?? null,
                        'title' => $item['title'] ?? null,
                        'meta_description' => $item['meta_description'] ?? null,
                        'load_time_ms' => $item['load_time_ms'] ?? null,
                        'page_speed_score' => $item['page_speed_score'] ?? null,
                        'has_ssl' => $item['has_ssl'] ?? null,
                        'is_mobile_friendly' => $item['is_mobile_friendly'] ?? null,
                        'crawl_status' => $item['crawl_status'],
                        'crawl_error' => $item['crawl_error'] ?? null,
                        'crawled_at' => now(),
                    ]
                );

                foreach ($item['signals'] ?? [] as $signal) {
                    CompanySignal::create([
                        'company_id' => $company->id,
                        'signal_type' => $signal['signal_type'],
                        'signal_value' => $signal['signal_value'] ?? null,
                        'confidence' => $signal['confidence'] ?? 1.0,
                        'source_stage' => 'crawling',
                        'detected_at' => now(),
                    ]);
                }
            }
        });

        return response()->json(['status' => 'ok', 'processed' => count($data['results'])]);
    }

    /**
     * POST /api/internal/ai-analysis-results
     * CompanyAiAnalysis::updateOrCreate() ตามผลวิเคราะห์จาก AI
     *
     * หมายเหตุ: README เดิมมี AiUsageLog::create() ด้วย แต่ payload ที่ตกลงกัน
     * (AiAnalysisResult ฝั่ง Python) มี usage เป็น field ที่ optional
     * ถ้าอยากบันทึกลง ai_usage_logs ต้องเช็คว่ามี usage ส่งมาก่อน
     */
    public function aiAnalysisResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.company_name' => ['required', 'string', 'max:255'],
            'results.*.opportunity_score' => ['required', 'integer', 'min:0', 'max:100'],
            'results.*.pain_points' => ['nullable', 'array'],
            'results.*.key_findings' => ['nullable', 'array'],
            'results.*.recommended_approach' => ['nullable', 'string'],
            'results.*.recommended_service' => ['nullable', 'string'],
            'results.*.source' => ['required', 'string', 'in:ai,mock'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['results'] as $item) {
                $company = Company::where('campaign_id', $data['campaign_id'])
                    ->where('name', $item['company_name'])
                    ->first();

                if (! $company) {
                    Log::warning('ai-analysis-results: ไม่พบบริษัทในระบบ', [
                        'company_name' => $item['company_name'],
                    ]);
                    continue;
                }

                CompanyAiAnalysis::updateOrCreate(
                    ['company_id' => $company->id],
                    [
                        'opportunity_score' => $item['opportunity_score'],
                        'pain_points' => $item['pain_points'] ?? [],
                        'key_findings' => $item['key_findings'] ?? [],
                        'recommended_approach' => $item['recommended_approach'] ?? null,
                        'source' => $item['source'],
                        'analysed_at' => now(),
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok', 'processed' => count($data['results'])]);
    }

    /**
     * POST /api/internal/lead-scores
     * Lead::create() เฉพาะบริษัทที่ above_threshold = true
     */
    public function leadScores(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.company_name' => ['required', 'string', 'max:255'],
            'scores.*.lead_score' => ['required', 'integer', 'min:0', 'max:100'],
            'scores.*.opportunity_score' => ['required', 'integer', 'min:0', 'max:100'],
            'scores.*.signal_match_score' => ['required', 'integer', 'min:0', 'max:100'],
            'scores.*.matched_signals' => ['nullable', 'array'],
            'scores.*.above_threshold' => ['required', 'boolean'],
        ]);

        $createdCount = 0;

        DB::transaction(function () use ($data, &$createdCount) {
            foreach ($data['scores'] as $item) {
                if (! $item['above_threshold']) {
                    continue; // ไม่ผ่านเกณฑ์ ไม่ต้องสร้างเป็น Lead
                }

                $company = Company::where('campaign_id', $data['campaign_id'])
                    ->where('name', $item['company_name'])
                    ->first();

                if (! $company) {
                    Log::warning('lead-scores: ไม่พบบริษัทในระบบ', [
                        'company_name' => $item['company_name'],
                    ]);
                    continue;
                }

                Lead::create([
                    'campaign_id' => $data['campaign_id'],
                    'company_id' => $company->id,
                    'lead_score' => $item['lead_score'],
                    'opportunity_score' => $item['opportunity_score'],
                    'signal_match_score' => $item['signal_match_score'],
                    'matched_signals' => $item['matched_signals'] ?? [],
                ]);

                $createdCount++;
            }
        });

        return response()->json(['status' => 'ok', 'leads_created' => $createdCount]);
    }

    /**
     * POST /api/internal/job-completed
     * update Campaign.status = completed
     */
    public function jobCompleted(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'completed_at' => ['required', 'date'],
            'total_leads' => ['nullable', 'integer', 'min:0'],
        ]);

        ResearchJob::whereKey($data['research_job_id'])->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'finished_at' => $data['completed_at'],
        ]);

        Campaign::whereKey($data['campaign_id'])->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => $data['completed_at'],
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /api/internal/job-failed
     * update Campaign.status = failed
     */
    public function jobFailed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'research_job_id' => ['required', 'integer', 'exists:research_jobs,id'],
            'error_message' => ['required', 'string'],
            'failed_at' => ['required', 'date'],
        ]);

        ResearchJob::whereKey($data['research_job_id'])->update([
            'status' => 'failed',
            'error_message' => $data['error_message'],
            'finished_at' => $data['failed_at'],
        ]);

        Campaign::whereKey($data['campaign_id'])->update([
            'status' => 'failed',
            'last_error' => $data['error_message'],
        ]);

        Log::error('Campaign pipeline ล้มเหลว', [
            'campaign_id' => $data['campaign_id'],
            'error' => $data['error_message'],
        ]);

        return response()->json(['status' => 'ok']);
    }
}
