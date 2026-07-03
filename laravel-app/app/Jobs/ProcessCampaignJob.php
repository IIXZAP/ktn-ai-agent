<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\ResearchJob;
use App\Services\PythonAgentClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * STEP 9.2 — ProcessCampaignJob: ใบสั่งงานเข้าคิว
 *
 * หน้าที่: รันงาน Campaign แบบ Background ผ่าน Laravel Queue
 * สิ่งที่ทำตามลำดับ (ตาม README):
 *   1. สร้าง ResearchJob
 *   2. เรียก PythonAgentClient
 *   3. ส่ง mode = full ไปให้ Python
 *   4. ถ้าเกิด Error ให้ retry ได้ 3 ครั้ง
 */
class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** ลองใหม่ได้สูงสุด 3 ครั้งตามที่ README กำหนด */
    public int $tries = 3;

    /** เว้นช่วงก่อน retry แต่ละครั้ง (วินาที) - รอนานขึ้นเรื่อยๆ กันยิงรัวๆ ตอน Python ล่ม */
    public array $backoff = [10, 30, 60];

    /** กันงาน pending ค้างคิวนานเกินไปถ้า worker มีปัญหา */
    public int $timeout = 120;

    public function __construct(public Campaign $campaign) {}

    public function handle(PythonAgentClient $client): void
    {
        // ---- 1. สร้าง ResearchJob ----
        // กันสร้างซ้ำตอน retry (attempt 2, 3): reuse งานที่ยังไม่จบ (pending/queued/running)
        // แทนที่จะ key เฉพาะ status='pending' ซึ่งจะพลาดถ้า attempt แรกดันไปเป็น running แล้ว
        $researchJob = ResearchJob::where('campaign_id', $this->campaign->id)
            ->whereIn('status', ['pending', 'queued', 'running'])
            ->latest()
            ->first();

        $researchJob ??= ResearchJob::create([
            'campaign_id' => $this->campaign->id,
            'status' => 'pending',
            'progress_percent' => 0,
        ]);

        try {
            $this->campaign->update([
                'status' => 'queued',
                'started_at' => $this->campaign->started_at ?? now(),
            ]);

            // ---- 2-3. เรียก PythonAgentClient ส่ง mode=full ----
            $result = $client->processFull($this->campaign, $researchJob);

            if (($result['status'] ?? null) !== 'accepted') {
                throw new \RuntimeException(
                    'Python Agent ไม่ตอบรับงานตามที่คาด: ' . json_encode($result)
                );
            }

            $researchJob->update(['status' => 'running', 'started_at' => now()]);
        } catch (Throwable $exception) {
            Log::warning('ProcessCampaignJob ล้มเหลว (จะลองใหม่ถ้ายังไม่ครบ 3 ครั้ง)', [
                'campaign_id' => $this->campaign->id,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            // โยน exception ต่อ ให้ Laravel Queue จัดการ retry ตาม $tries/$backoff เอง
            throw $exception;
        }
    }

    /**
     * ---- 4. เรียกอัตโนมัติเมื่อ retry ครบ 3 ครั้งแล้วยังไม่สำเร็จ ----
     * ปิดเคสให้ชัดเจน ไม่ปล่อยให้ Campaign ค้างสถานะ "queued" อยู่เฉยๆ
     */
    public function failed(Throwable $exception): void
    {
        $this->campaign->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);

        ResearchJob::where('campaign_id', $this->campaign->id)
            ->whereIn('status', ['pending', 'queued', 'running'])
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

        Log::error('ProcessCampaignJob ล้มเหลวถาวร (retry ครบแล้ว)', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
