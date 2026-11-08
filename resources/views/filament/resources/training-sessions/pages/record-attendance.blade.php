<x-filament-panels::page>
    <style>
        .att-meta { display: grid; gap: .5rem 1.5rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); border: 1px solid rgba(128, 128, 128, .35); border-radius: .75rem; padding: .9rem 1.1rem; }
        .att-meta span { opacity: .7; font-size: .8rem; display: block; }
        .att-meta strong { font-size: .95rem; }
        .att-warn { border: 1px solid #d97706; background: rgba(217, 119, 6, .12); border-radius: .75rem; padding: .7rem 1rem; font-weight: 600; }
        .att-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .att-actions button { border-radius: .5rem; padding: .5rem .9rem; font-weight: 600; cursor: pointer; border: 1px solid rgba(128, 128, 128, .5); background: transparent; color: inherit; }
        .att-actions button.primary { background: #d97706; border-color: #d97706; color: #fff; }
        .att-table { width: 100%; border-collapse: collapse; }
        .att-table th, .att-table td { padding: .5rem .6rem; border-bottom: 1px solid rgba(128, 128, 128, .3); text-align: right; }
        .att-table th { font-size: .8rem; opacity: .75; }
        .att-table select, .att-table input { border: 1px solid rgba(128, 128, 128, .5); border-radius: .4rem; padding: .35rem .5rem; background: transparent; color: inherit; width: 100%; }
    </style>

    @php($session = $this->getRecord())
    @php($hint = $this->recordHint())

    <div class="att-meta">
        <div><span>الدورة</span><strong>{{ $session->course->course_number }}</strong></div>
        <div><span>المادة</span><strong>{{ $session->subject->name }}</strong></div>
        <div><span>المدرب</span><strong>{{ $session->instructor?->name ?? '-' }}</strong></div>
        <div><span>التاريخ والوقت</span><strong>{{ $session->session_date->format('Y-m-d') }} | {{ $session->timeLabel() }}</strong></div>
        <div><span>النوع</span><strong>{{ $session->type->label() }}</strong></div>
        <div><span>الحالة</span><strong>{{ $session->status->label() }}</strong></div>
    </div>

    @if ($hint)
        <div class="att-warn">{{ $hint }}</div>
    @endif

    <div class="att-actions">
        <button type="button" wire:click="markAll('present')">تحديد غير المحدَّدين: حاضر</button>
        <button type="button" wire:click="markAll('absent')">تحديد غير المحدَّدين: غائب</button>
        <button type="button" class="primary" wire:click="save">حفظ الحضور</button>
    </div>

    <div style="overflow-x: auto">
        <table class="att-table">
            <thead>
                <tr>
                    <th style="width: 40px">م</th>
                    <th>رقم الهوية</th>
                    <th>اسم الطالب</th>
                    <th style="width: 190px">الحالة</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $id => $row)
                    <tr wire:key="att-row-{{ $id }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row['national_id'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>
                            <select wire:model="rows.{{ $id }}.status">
                                <option value="">—</option>
                                @foreach ($this->statusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" maxlength="255" wire:model="rows.{{ $id }}.notes"></td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا يوجد طلاب بتسجيل جارٍ في هذه الدورة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
