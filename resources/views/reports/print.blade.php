@php
    $currency = config('training.currency_symbol', '₪');
    $format = function ($value, string $type) use ($currency) {
        if ($value === null || $value === '') {
            return '-';
        }

        return $type === 'money' ? number_format((float) $value, 2).' '.$currency : $value;
    };
    $logo = public_path('images/ministry-eagle.png');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { size: A4 {{ $report['orientation'] ?? 'portrait' }}; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Tahoma, "Segoe UI", Arial, sans-serif; color: #111; font-size: 12px; background: #fff; }
        .toolbar { padding: 10px 16px; background: #f3f4f6; border-bottom: 1px solid #d1d5db; display: flex; gap: 8px; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; border: 1px solid #9ca3af; border-radius: 6px; background: #fff; }
        .toolbar button.primary { background: #d97706; border-color: #d97706; color: #fff; font-weight: 700; }
        .page { padding: 16px 20px; }
        .letterhead { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; }
        .lh-ar { width: 38%; text-align: right; font-weight: 700; font-size: 15px; line-height: 1.8; }
        .lh-logo { width: 24%; text-align: center; }
        .lh-logo img { height: 85px; }
        .lh-en { width: 38%; text-align: left; direction: ltr; font-weight: 700; font-size: 14px; line-height: 1.8; }
        .dept { text-align: center; font-weight: 700; font-size: 14px; margin-top: 6px; }
        .rule { border: 0; border-top: 2px solid #111; margin: 8px 0 12px; }
        h1 { text-align: center; font-size: 19px; margin: 0 0 10px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 3px 6px; border: 1px solid #bbb; }
        .meta td.k { width: 18%; background: #f3f4f6; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #555; padding: 4px 6px; text-align: right; }
        table.data th { background: #e5e7eb; font-weight: 700; }
        table.data td.num { text-align: center; }
        table.data td.money { text-align: left; direction: ltr; white-space: nowrap; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        .totals { margin-top: 10px; font-weight: 700; font-size: 13px; }
        .empty { text-align: center; padding: 20px; border: 1px dashed #999; margin-top: 10px; }
        .footer { margin-top: 18px; display: flex; justify-content: space-between; font-size: 11px; color: #333; }
        .signatures { margin-top: 28px; display: flex; justify-content: space-between; gap: 24px; page-break-inside: avoid; }
        .signatures div { flex: 1; text-align: center; font-weight: 700; }
        .signatures div span { display: block; margin-top: 34px; border-top: 1px solid #111; }
        @media print { .toolbar { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="primary" onclick="window.print()">طباعة / حفظ بصيغة PDF</button>
    <button onclick="window.close()">إغلاق</button>
</div>

<div class="page">
    <table class="letterhead">
        <tr>
            <td class="lh-ar">
                <div>دولة فلسطين</div>
                <div>وزارة العمل</div>
            </td>
            <td class="lh-logo">
                @if (file_exists($logo))
                    <img src="{{ asset('images/ministry-eagle.png') }}" alt="">
                @endif
            </td>
            <td class="lh-en">
                <div>State of Palestine</div>
                <div>Ministry of Labor</div>
            </td>
        </tr>
    </table>

    <div class="dept">الإدارة العامة للتدريب المهني – قسم السياقة</div>
    <hr class="rule">

    <h1>{{ $report['title'] }}</h1>

    <table class="meta">
        @foreach (array_chunk($report['meta'], 2, true) as $pair)
            <tr>
                @foreach ($pair as $label => $value)
                    <td class="k">{{ $label }}</td>
                    <td>{{ $value }}</td>
                @endforeach
                @if (count($pair) < 2)
                    <td class="k"></td><td></td>
                @endif
            </tr>
        @endforeach
    </table>

    @if (count($report['rows']) === 0)
        <div class="empty">لا توجد بيانات لعرضها بهذه المعايير.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 32px">م</th>
                    @foreach ($report['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $index => $row)
                    <tr>
                        <td class="num">{{ $index + 1 }}</td>
                        @foreach ($row as $i => $value)
                            @php($type = $report['columns'][$i]['type'])
                            <td class="{{ $type === 'text' ? '' : ($type === 'money' ? 'money' : 'num') }}">{{ $format($value, $type) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach ($report['totals'] as $label => $value)
        <div class="totals">{{ $label }}: {{ number_format((float) $value, 2) }} {{ $currency }}</div>
    @endforeach

    <div class="footer">
        <span>أُصدر بتاريخ: {{ now()->format('Y-m-d H:i') }}</span>
        <span>أصدره: {{ $issuedBy }}</span>
    </div>

    <div class="signatures">
        <div>معدّ الكشف<span></span></div>
        <div>رئيس القسم<span></span></div>
        <div>المدير<span></span></div>
    </div>
</div>
</body>
</html>
