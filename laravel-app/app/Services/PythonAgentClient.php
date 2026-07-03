<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ResearchJob;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * STEP 9.1 — PythonAgentClient: ผู้ส่งสาส์นฝั่ง Laravel
 *
 * หน้าที่: เป็น HTTP Client ที่ Laravel ใช้เรียก Python Agent (Step 7)
 * คู่แฝดของ laravel_callback.py ฝั่ง Python แต่ทำงานตรงข้ามกัน
 * (ฝั่งนั้น Python ส่งผลกลับ Laravel / ฝั่งนี้ Laravel เริ่มยิงไปขอให้ Python ทำงาน)
 */
class PythonAgentClient
{
    protected string $baseUrl;
    protected string $secret;
    protected string $callbackBaseUrl;
    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.python_agent.base_url'), '/');
        $this->secret = (string) config('services.python_agent.secret');
        $this->callbackBaseUrl = (string) config('services.python_agent.callback_base_url');
        $this->timeoutSeconds = 10;
    }

    /**
     * เรียก Python แบบ mode=parse_only (Manager กด "Parse" ในหน้า UI)
     * รอผลตอบกลับทันที (synchronous) เพราะ Manager รอดู criteria อยู่หน้าจอ
     *
     * @return array{status: string, criteria: array|null}
     */
    public function parseOnly(Campaign $campaign): array
    {
        $response = $this->post(
            "/internal/v1/campaigns/{$campaign->id}/process",
            $this->buildBasePayload($campaign, mode: 'parse_only'),
        );

        return $response->json();
    }

    /**
     * เรียก Python แบบ mode=full (ตอน Manager กด "Start")
     * Python จะตอบกลับทันทีแค่ "accepted" แล้วไปทำงานต่อใน background
     * ผลลัพธ์จริงจะมาทาง callback (Step 8) ทีละ Stage แทน
     *
     * @return array{status: string, message: string|null}
     */
    public function processFull(Campaign $campaign, ResearchJob $researchJob): array
    {
        $payload = $this->buildBasePayload($campaign, mode: 'full');
        $payload['research_job_id'] = $researchJob->id;

        $response = $this->post(
            "/internal/v1/campaigns/{$campaign->id}/process",
            $payload,
        );

        return $response->json();
    }

    /**
     * ประกอบ payload ตาม CampaignProcessRequest schema ฝั่ง Python (Step 1)
     *
     * หมายเหตุสำคัญ: distance, register_value, must_have_website,
     * required_contact_types ยังไม่มีคอลัมน์เก็บใน database จริง
     * (ตัดสินใจไว้ตอน Step 1 ว่ายังไม่แก้ schema ตอนนั้น) เลยส่ง null ไปก่อน
     * TODO: ถ้าจะใช้ 4 field นี้จริง ต้องเพิ่มคอลัมน์ใน campaigns หรือ
     *       campaign_search_criteria แล้วดึงมาใส่ตรงนี้แทนค่า null
     */
    protected function buildBasePayload(Campaign $campaign, string $mode): array
    {
        $criteria = $campaign->searchCriteria; // CampaignSearchCriteria | null (Step 1.3)

        return [
            'mode' => $mode,
            'campaign_id' => $campaign->id,
            'callback_base_url' => $this->callbackBaseUrl,

            'country' => 'Thailand', // ยังไม่มีคอลัมน์เก็บ ใช้ default ไปก่อน (ดู TODO ด้านบน)
            'locations' => $criteria->locations ?? $campaign->locations ?? [],
            'industries' => $criteria->industries
                ?? ($campaign->industry ? [$campaign->industry] : []),
            'maximum_leads' => $criteria->maximum_leads ?? $campaign->maximum_leads,

            'distance' => null,               // TODO: ยังไม่มีคอลัมน์
            'register_value' => null,          // TODO: ยังไม่มีคอลัมน์
            'must_have_website' => null,       // TODO: ยังไม่มีคอลัมน์
            'required_contact_types' => [],    // TODO: ยังไม่มีคอลัมน์

            // ใช้ natural_language_query column เดิมเก็บข้อความอิสระที่ Manager
            // กรอกไว้ (Step 1 เรียก signal_description - ชื่อ column ใน DB ยังคงเดิม)
            'signal_description' => $campaign->natural_language_query,
        ];
    }

    /**
     * ยิง POST ไปหา Python พร้อมแปะตราประทับลับ (เหมือน _headers() ฝั่ง Python)
     * ถ้ายิงไม่สำเร็จ -> throw exception ให้ตัวเรียก (ProcessCampaignJob) จัดการ retry เอง
     */
    protected function post(string $path, array $payload)
    {
        try {
            $response = Http::withToken($this->secret)
                ->timeout($this->timeoutSeconds)
                ->post("{$this->baseUrl}{$path}", $payload);

            $response->throw(); // โยน exception ถ้า status code เป็น 4xx/5xx

            return $response;
        } catch (RequestException $exception) {
            Log::error('เรียก Python Agent ไม่สำเร็จ', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
