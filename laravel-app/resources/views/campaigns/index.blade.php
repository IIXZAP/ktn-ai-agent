@extends('layouts.app')

@section('title', 'แคมเปญของฉัน')

@section('content')
    <div class="mb-8 flex items-end justify-between">
        <div>
            <p class="font-mono text-xs uppercase tracking-widest text-signal-teal">01 · ภาพรวม</p>
            <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">แคมเปญของฉัน</h1>
            <p class="mt-1 text-sm text-ink-soft">
                กำหนดเป้าหมาย แล้วปล่อยให้ระบบสแกนหาลูกค้าที่มีสัญญาณน่าสนใจให้อัตโนมัติ
            </p>
        </div>
        <a href="{{ route('campaigns.create') }}"
            class="rounded-lg bg-ink px-4 py-2.5 text-sm font-medium text-panel transition hover:bg-ink/90">
            + สร้างแคมเปญ
        </a>
    </div>

    @forelse ($campaigns as $campaign)
        <a href="{{ route('campaigns.show', $campaign) }}"
            class="mb-3 block rounded-xl border border-hairline bg-panel p-5 transition hover:border-signal-teal/40 hover:shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3">
                        <h2 class="truncate font-display text-base font-semibold">{{ $campaign->name }}</h2>
                        <x-status-pill :status="$campaign->status" />
                    </div>
                    <p class="mt-1 truncate text-sm text-ink-soft">
                        {{ $campaign->industry ?? 'ทุกอุตสาหกรรม' }}
                        @if (!empty($campaign->locations))
                            · {{ implode(', ', $campaign->locations) }}
                        @endif
                        · เป้าหมาย {{ $campaign->maximum_leads }} ราย
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="font-mono text-lg font-medium tabular-nums">
                        {{ $campaign->progress_percent }}<span class="text-xs text-ink-soft">%</span>
                    </p>
                    @if ($campaign->current_stage)
                        <p class="font-mono text-[11px] text-ink-soft">{{ $campaign->current_stage }}</p>
                    @endif
                </div>
            </div>

            {{-- Signature element: radar sweep progress bar --}}
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-hairline/60">
                @php
                    $isActive = in_array(
                        $campaign->status,
                        ['parsing', 'discovering', 'finding_websites', 'crawling', 'analyzing', 'scoring'],
                        true,
                    );
                    $barColor = match ($campaign->status) {
                        'completed' => 'bg-signal-green',
                        'failed' => 'bg-signal-red',
                        'cancelled' => 'bg-ink-soft',
                        default => 'bg-signal-teal',
                    };
                @endphp
                <div class="h-full rounded-full {{ $barColor }} {{ $isActive ? 'bg-sweep-stripes bg-[length:56px_100%] animate-radar-sweep' : '' }}"
                    style="width: {{ max($campaign->progress_percent, 3) }}%"></div>
            </div>
        </a>
    @empty
        <div class="rounded-xl border border-dashed border-hairline bg-panel/50 px-6 py-16 text-center">
            <p class="font-display text-base font-medium">ยังไม่มีแคมเปญ</p>
            <p class="mt-1 text-sm text-ink-soft">เริ่มสแกนหาลูกค้ากลุ่มแรกของคุณได้เลย</p>
            <a href="{{ route('campaigns.create') }}"
                class="mt-4 inline-block rounded-lg bg-ink px-4 py-2 text-sm font-medium text-panel hover:bg-ink/90">
                + สร้างแคมเปญแรก
            </a>
        </div>
    @endforelse
@endsection
