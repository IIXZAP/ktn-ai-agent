@extends('layouts.app')

@section('title', 'สร้างแคมเปญ')

@section('content')
    <div class="mx-auto max-w-xl">
        <p class="font-mono text-xs uppercase tracking-widest text-signal-teal">สร้างใหม่</p>
        <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">ตั้งค่าการสแกนหาลูกค้า</h1>
        <p class="mt-1 text-sm text-ink-soft">
            กรอกเป้าหมายให้ชัดเจน ระบบจะแปลงเป็นเงื่อนไขค้นหาให้อัตโนมัติ
        </p>

        <form method="POST" action="{{ route('campaigns.store') }}" class="mt-8 space-y-6">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium">ชื่อแคมเปญ</label>
                <input type="text" name="name" id="name" required value="{{ old('name') }}"
                    placeholder="เช่น ร้านอาหารกรุงเทพ Q3"
                    class="mt-1.5 w-full rounded-lg border border-hairline bg-panel px-3.5 py-2.5 text-sm outline-none focus:border-signal-teal focus:ring-2 focus:ring-signal-teal/20">
                @error('name')
                    <p class="mt-1 text-xs text-signal-red">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="industry" class="block text-sm font-medium">อุตสาหกรรม</label>
                    <input type="text" name="industry" id="industry" value="{{ old('industry') }}"
                        placeholder="เช่น food"
                        class="mt-1.5 w-full rounded-lg border border-hairline bg-panel px-3.5 py-2.5 text-sm outline-none focus:border-signal-teal focus:ring-2 focus:ring-signal-teal/20">
                </div>
                <div>
                    <label for="maximum_leads" class="block text-sm font-medium">จำนวนเป้าหมาย</label>
                    <input type="number" name="maximum_leads" id="maximum_leads" min="1" max="500"
                        value="{{ old('maximum_leads', 20) }}"
                        class="mt-1.5 w-full rounded-lg border border-hairline bg-panel px-3.5 py-2.5 text-sm font-mono outline-none focus:border-signal-teal focus:ring-2 focus:ring-signal-teal/20">
                </div>
            </div>

            <div>
                <label for="locations" class="block text-sm font-medium">พื้นที่ (คั่นด้วยจุลภาค)</label>
                <input type="text" name="locations" id="locations" value="{{ old('locations') }}"
                    placeholder="เช่น Bangkok, Chiang Mai"
                    class="mt-1.5 w-full rounded-lg border border-hairline bg-panel px-3.5 py-2.5 text-sm outline-none focus:border-signal-teal focus:ring-2 focus:ring-signal-teal/20">
            </div>

            <div class="rounded-lg border border-signal-teal/25 bg-signal-teal/5 p-4">
                <label for="signal_description" class="flex items-center gap-1.5 text-sm font-medium">
                    <span>สัญญาณที่ต้องการ</span>
                    <span
                        class="rounded border border-signal-teal/40 px-1.5 py-0.5 font-mono text-[10px] text-signal-teal">AI
                        แปลให้</span>
                </label>
                <textarea name="signal_description" id="signal_description" rows="3"
                    placeholder="เช่น เว็บไซต์โหลดช้าหรือไม่มี SSL"
                    class="mt-1.5 w-full rounded-lg border border-hairline bg-panel px-3.5 py-2.5 text-sm outline-none focus:border-signal-teal focus:ring-2 focus:ring-signal-teal/20">{{ old('signal_description') }}</textarea>
                <p class="mt-1.5 text-xs text-ink-soft">
                    พิมพ์เป็นภาษาคนได้เลย ระบบจะแปลงเป็นรหัสสัญญาณที่ใช้ค้นหาให้เอง
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="rounded-lg bg-ink px-5 py-2.5 text-sm font-medium text-panel transition hover:bg-ink/90">
                    บันทึกแบบร่าง
                </button>
                <a href="{{ route('campaigns.index') }}" class="text-sm text-ink-soft hover:text-ink">ยกเลิก</a>
            </div>
        </form>
    </div>
@endsection
