<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignSearchCriteria;
use App\Models\Lead;
use App\Services\PythonAgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 10 — CampaignController: รีโมทคอนโทรลของ Manager
 *
 * หน้าที่: ควบคุม Flow ที่ Manager ใช้งานในหน้าเว็บ (ตาม README ตาราง "Action สำคัญ")
 *   Create          -> store()
 *   Parse           -> parse()
 *   Update Criteria -> updateCriteria()
 *   Start           -> start()
 *   Cancel          -> cancel()
 *   Retry           -> retry()
 */
class CampaignController extends Controller
{
    /**
     * กันไม่ให้ user เปิด/สั่งงาน Campaign ของคนอื่นด้วยการเดา ID (IDOR)
     *
     * หมายเหตุ (ชั่วคราวตอนเทส): ตอนนี้ระบบยังไม่มี auth/login
     * ถ้าไม่มี user login (auth()->id() เป็น null) จะ "ข้าม" การเช็คไปก่อน
     * เพื่อให้เทส flow ได้ — เมื่อติดตั้งระบบ login จริงแล้ว การเช็คจะทำงานตามปกติ
     * TODO: เอา `if (auth()->id() === null) return;` ออก เมื่อมีระบบ login แล้ว
     */
    private function authorizeOwner(Campaign $campaign): void
    {
        if (auth()->id() === null) {
            return; // ยังไม่มีระบบ login — ข้ามการเช็คเจ้าของชั่วคราว
        }

        abort_unless($campaign->created_by === auth()->id(), 403);
    }

    /**
     * แสดงรายการ Campaign ทั้งหมด (หน้า Dashboard)
     */
    public function index(Request $request): View
    {
        $campaigns = Campaign::latest()->get();

        return view('campaigns.index', compact('campaigns'));
    }

    /**
     * แสดงฟอร์มสร้าง Campaign ใหม่
     */
    public function create(): View
    {
        return view('campaigns.create');
    }

    /**
     * แสดงรายละเอียด Campaign หนึ่งตัว (progress, criteria, leads)
     */
    public function show(Campaign $campaign): View
    {
        $this->authorizeOwner($campaign);

        $leads = Lead::where('campaign_id', $campaign->id)
            ->with('company')
            ->orderByDesc('lead_score')
            ->get();

        return view('campaigns.show', compact('campaign', 'leads'));
    }

    /**
     * ปุ่ม "Create": สร้าง Campaign ด้วย status = draft
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'locations' => ['nullable', 'string'],
            'maximum_leads' => ['nullable', 'integer', 'min:1', 'max:500'],
            'signal_description' => ['nullable', 'string'],
        ]);

        // locations ส่งมาจากฟอร์มเป็น string คั่นด้วยจุลภาค แปลงเป็น array ก่อนเก็บ
        $locations = $data['locations'] ?? '';
        $locations = array_values(array_filter(array_map('trim', explode(',', $locations))));

        $campaign = Campaign::create([
            'created_by' => $request->user()?->id ?? \App\Models\User::first()?->id,
            'title' => $data['name'],
            'natural_language_query' => $data['signal_description'] ?? '',
            'industry' => $data['industry'] ?? null,
            'locations' => $locations,
            'maximum_leads' => $data['maximum_leads'] ?? 50,
            'status' => 'draft',
            'progress_percent' => 0,
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'สร้างแคมเปญแล้ว ขั้นต่อไปกด "แปลเงื่อนไข" เพื่อให้ AI ช่วยตั้งค่าการค้นหา');
    }

    /**
     * ปุ่ม "Parse": เรียก Python แบบ parse_only แล้วเก็บ SearchCriteria
     */
    public function parse(Campaign $campaign, PythonAgentClient $client): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        $result = $client->parseOnly($campaign);

        if (($result['status'] ?? null) !== 'parsed' || empty($result['criteria'])) {
            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('status', 'Python Agent ไม่สามารถแปลเงื่อนไขนี้ได้ ลองใหม่อีกครั้ง');
        }

        $criteria = $result['criteria'];

        CampaignSearchCriteria::updateOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'industries' => $criteria['industries'] ?? [],
                'locations' => $criteria['locations'] ?? [],
                'target_signals' => $criteria['target_signals'] ?? [],
                'maximum_leads' => $criteria['maximum_leads'] ?? $campaign->maximum_leads,
                'source' => $criteria['source'] ?? 'mock',
            ]
        );

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'แปลเงื่อนไขเสร็จแล้ว ตรวจสอบสัญญาณที่พบด้านล่าง แล้วกด "เริ่มสแกน" ได้เลย');
    }

    /**
     * ปุ่ม "Update Criteria": Manager แก้ Criteria เอง แล้วบันทึกลง DB ตรงๆ
     */
    public function updateCriteria(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        $data = $request->validate([
            'industries' => ['sometimes', 'array'],
            'locations' => ['sometimes', 'array'],
            'target_signals' => ['sometimes', 'array'],
            'maximum_leads' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $searchCriteria = $campaign->searchCriteria;

        if (! $searchCriteria) {
            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('status', 'ยังไม่มีเงื่อนไขให้แก้ กรุณากด "แปลเงื่อนไข" ก่อน');
        }

        $searchCriteria->update($data);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'บันทึกเงื่อนไขที่แก้ไขแล้ว');
    }

    /**
     * ปุ่ม "Start": dispatch ProcessCampaignJob เข้า Queue
     * อนุญาตเฉพาะ Campaign ที่ยังไม่เคยเริ่ม (draft) เท่านั้น
     */
    public function start(Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        if ($campaign->status !== 'draft') {
            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('status', "แคมเปญสถานะ '{$campaign->status}' เริ่มงานใหม่ไม่ได้ (ต้องเป็นแบบร่าง)");
        }

        $campaign->update(['status' => 'queued', 'started_at' => now()]);

        ProcessCampaignJob::dispatch($campaign);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'ส่งงานเข้าคิวแล้ว ระบบจะเริ่มสแกนภายในไม่กี่วินาที');
    }

    /**
     * ปุ่ม "Cancel": update Campaign.status = cancelled
     */
    public function cancel(Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('status', "แคมเปญสถานะ '{$campaign->status}' ยกเลิกไม่ได้แล้ว");
        }

        $campaign->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'ยกเลิกแคมเปญแล้ว');
    }

    /**
     * ปุ่ม "Retry": dispatch ProcessCampaignJob ใหม่อีกครั้ง
     */
    public function retry(Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        if ($campaign->status !== 'failed') {
            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('status', "Retry ได้เฉพาะแคมเปญที่สถานะ 'failed' เท่านั้น (ตอนนี้คือ '{$campaign->status}')");
        }

        $campaign->update([
            'status' => 'queued',
            'last_error' => null,
        ]);

        ProcessCampaignJob::dispatch($campaign);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'สั่งรันงานใหม่แล้ว');
    }
}
