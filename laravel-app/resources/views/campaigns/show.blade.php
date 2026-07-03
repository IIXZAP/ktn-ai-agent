@extends('layouts.app')

@section('title', $campaign->name)

@php
    $stages = [
        'parsing' => 'แปลเงื่อนไข',
        'discovering' => 'ค้นหาบริษัท',
        'finding_websites' => 'หาเว็บไซต์',
        'crawling' => 'ตรวจเว็บไซต์',
        'analyzing' => 'วิเคราะห์ AI',
        'scoring' => 'ให้คะแนน',
        'completed' => 'เสร็จสิ้น',
    ];
    $stageKeys = array_keys($stages);
    $currentIndex = array_search($campaign->current_stage, $stageKeys, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;
@endphp

@section('content')
    <a href="{{ route('campaigns.index') }}" class="text-sm text-ink-soft hover:text-ink">&larr; แคมเปญทั้งหมด</a>

    <div class="mt-3 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-display text-2xl font-semibold tracking-tight">{{ $campaign->name }}</h1>
                <x-status-pill :status="$campaign->status" />
            </div>
            <p class="mt-1 text-sm text-ink-soft">
                {{ $campaign->industry ?? 'ทุกอุตสาหกรรม' }}
                @if (!empty($campaign->locations))
                    · {{ implode(', ', $campaign->locations) }}
                @endif
            </p>
        </div>

        <div class="flex shrink-0 gap-2">
            @if ($campaign->status === 'draft')
                <form method="POST" action="{{ route('campaigns.parse', $campaign) }}">
                    @csrf
                    <button
                        class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium hover:border-ink">แปลเงื่อนไข</button>
                </form>
                <form method="POST" action="{{ route('campaigns.start', $campaign) }}">
                    @csrf
                    <button
                        class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-panel hover:bg-ink/90">เริ่มสแกน</button>
                </form>
            @elseif ($campaign->status === 'failed')
                <form method="POST" action="{{ route('campaigns.retry', $campaign) }}">
                    @csrf
                    <button
                        class="rounded-lg bg-signal-amber px-4 py-2 text-sm font-medium text-panel hover:bg-signal-amber/90">ลองใหม่</button>
                </form>
            @endif

            @if (!in_array($campaign->status, ['completed', 'cancelled'], true))
                <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}">
                    @csrf
                    <button
                        class="rounded-lg border border-hairline px-4 py-2 text-sm text-ink-soft hover:border-signal-red hover:text-signal-red">ยกเลิก</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Signature element: Stage timeline ตรงกับ 7 stage ใน pipeline จริง --}}
    <div class="mt-8 rounded-xl border border-hairline bg-panel p-6">
        <p class="mb-5 font-mono text-xs uppercase tracking-widest text-ink-soft">สถานะการสแกน</p>
        <div class="flex items-start">
            @foreach ($stages as $key => $label)
                @php
                    $index = $loop->index;
                    $isDone = $currentIndex > $index || $campaign->status === 'completed';
                    $isCurrent = $currentIndex === $index && $campaign->status !== 'completed';
                    $dotClass = $isDone
                        ? 'bg-signal-teal border-signal-teal'
                        : ($isCurrent
                            ? 'bg-signal-amber border-signal-amber animate-pulse'
                            : 'bg-panel border-hairline');
                    $lineClass = $isDone ? 'bg-signal-teal' : 'bg-hairline';
                    $textClass = $isDone || $isCurrent ? 'text-ink' : 'text-ink-soft/60';
                @endphp
                <div class="flex flex-1 flex-col items-center text-center last:flex-none">
                    <div class="flex w-full items-center">
                        <div class="h-px flex-1 {{ $loop->first ? 'invisible' : $lineClass }}"></div>
                        <span class="h-3 w-3 shrink-0 rounded-full border-2 {{ $dotClass }}"></span>
                        <div class="h-px flex-1 {{ $loop->last ? 'invisible' : $lineClass }}"></div>
                    </div>
                    <span class="mt-2 max-w-[72px] font-mono text-[10px] leading-tight {{ $textClass }}">
                        {{ $label }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-hairline bg-panel p-5">
            <p class="font-mono text-xs uppercase tracking-widest text-ink-soft">ความคืบหน้า</p>
            <p class="mt-2 font-mono text-3xl font-medium tabular-nums">{{ $campaign->progress_percent }}%</p>
        </div>
        <div class="rounded-xl border border-hairline bg-panel p-5">
            <p class="font-mono text-xs uppercase tracking-widest text-ink-soft">Lead ที่พบ</p>
            <p class="mt-2 font-mono text-3xl font-medium tabular-nums">{{ $leads->count() }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-panel p-5">
            <p class="font-mono text-xs uppercase tracking-widest text-ink-soft">เป้าหมาย</p>
            <p class="mt-2 font-mono text-3xl font-medium tabular-nums">{{ $campaign->maximum_leads }}</p>
        </div>
    </div>

    @if ($campaign->searchCriteria)
        <div class="mt-6 rounded-xl border border-hairline bg-panel p-5">
            <p class="mb-3 font-mono text-xs uppercase tracking-widest text-ink-soft">เงื่อนไขที่ใช้ค้นหา</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($campaign->searchCriteria->target_signals ?? [] as $signal)
                    <span
                        class="rounded border border-signal-amber/30 bg-signal-amber/5 px-2 py-1 font-mono text-[11px] text-signal-amber">
                        {{ $signal }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($leads->isNotEmpty())
        <div class="mt-6 overflow-hidden rounded-xl border border-hairline bg-panel">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-hairline text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-5 py-3 font-medium">บริษัท</th>
                        <th class="px-5 py-3 font-medium">คะแนน Lead</th>
                        <th class="px-5 py-3 font-medium">สัญญาณที่ตรง</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leads as $lead)
                        <tr class="border-b border-hairline last:border-0">
                            <td class="px-5 py-3">{{ $lead->company->name ?? '-' }}</td>
                            <td class="px-5 py-3 font-mono tabular-nums text-signal-green">{{ $lead->lead_score }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink-soft">
                                {{ implode(', ', $lead->matched_signals ?? []) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
