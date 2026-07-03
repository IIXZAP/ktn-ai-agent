@props(['status'])

@php
    // map สถานะ (ตาม campaigns_status_enum) ไปหา "ไฟสัญญาณ" ที่เหมาะสม
    // active = กำลังสแกนอยู่ (มี animation), static = นิ่งแล้ว
    //
    // สำคัญ: ต้องเขียน class เป็น string เต็มๆ ในแต่ละเคส (ไม่ประกอบ class
    // แบบ dynamic เช่น "border-{$color}/30") เพราะ Tailwind สแกนหา class
    // ด้วย regex บนตัวอักษรตรงๆ ในไฟล์ ไม่ได้รันโค้ด PHP จึงมองไม่เห็น
    // class ที่ประกอบขึ้นมาแบบ runtime
    $config = match ($status) {
        'draft' => [
            'label' => 'ร่าง',
            'active' => false,
            'classes' => 'border-ink-soft/30 bg-ink-soft/5 text-ink-soft',
            'dot' => 'bg-ink-soft',
        ],
        'queued' => [
            'label' => 'รอคิว',
            'active' => false,
            'classes' => 'border-signal-amber/30 bg-signal-amber/5 text-signal-amber',
            'dot' => 'bg-signal-amber',
        ],
        'parsing', 'discovering', 'finding_websites', 'crawling', 'analyzing', 'scoring' => [
            'label' => 'กำลังสแกน',
            'active' => true,
            'classes' => 'border-signal-teal/30 bg-signal-teal/5 text-signal-teal',
            'dot' => 'bg-signal-teal',
        ],
        'completed' => [
            'label' => 'เสร็จสิ้น',
            'active' => false,
            'classes' => 'border-signal-green/30 bg-signal-green/5 text-signal-green',
            'dot' => 'bg-signal-green',
        ],
        'failed' => [
            'label' => 'ล้มเหลว',
            'active' => false,
            'classes' => 'border-signal-red/30 bg-signal-red/5 text-signal-red',
            'dot' => 'bg-signal-red',
        ],
        'cancelled' => [
            'label' => 'ยกเลิกแล้ว',
            'active' => false,
            'classes' => 'border-ink-soft/30 bg-ink-soft/5 text-ink-soft',
            'dot' => 'bg-ink-soft',
        ],
        default => [
            'label' => $status,
            'active' => false,
            'classes' => 'border-ink-soft/30 bg-ink-soft/5 text-ink-soft',
            'dot' => 'bg-ink-soft',
        ],
    };
@endphp

<span {{ $attributes->merge(['class' => "status-pill {$config['classes']}"]) }}>
    <span class="status-dot {{ $config['dot'] }} {{ $config['active'] ? 'animate-pulse' : '' }}"></span>
    {{ $config['label'] }}
</span>
