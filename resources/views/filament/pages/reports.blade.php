<x-filament-panels::page>
    <style>
        .rpt-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .rpt-card { border: 1px solid rgba(128, 128, 128, .35); border-radius: .75rem; padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .5rem; }
        .rpt-card h3 { font-size: 1.05rem; font-weight: 700; margin: 0; }
        .rpt-card p { margin: 0 0 .25rem; opacity: .75; font-size: .875rem; }
        .rpt-card label { font-size: .8rem; font-weight: 600; margin-top: .25rem; }
        .rpt-card select, .rpt-card input { border: 1px solid rgba(128, 128, 128, .5); border-radius: .5rem; padding: .45rem .6rem; background: transparent; color: inherit; width: 100%; }
        .rpt-actions { display: flex; gap: .5rem; margin-top: .75rem; flex-wrap: wrap; }
        .rpt-actions button { border-radius: .5rem; padding: .5rem .9rem; font-weight: 600; cursor: pointer; border: 1px solid rgba(128, 128, 128, .5); background: transparent; color: inherit; }
        .rpt-actions button.primary { background: #d97706; border-color: #d97706; color: #fff; }
        .rpt-note { font-size: .8rem; opacity: .7; }
    </style>

    <div class="rpt-grid">
        {{-- 1. كشف تسجيل دورة --}}
        <form class="rpt-card" method="get" action="{{ route('reports.enrollment-roster') }}" target="_blank">
            <h3>كشف تسجيل دورة</h3>
            <p>طلاب الدورة بالهوية والهاتف وحالة التسجيل وعدد المواد غير المسددة.</p>
            <label>الدورة</label>
            <select name="course_id" required>
                <option value="">اختر الدورة</option>
                @foreach ($this->courseOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="rpt-actions">
                <button type="submit" name="format" value="html" class="primary">عرض للطباعة / PDF</button>
                <button type="submit" name="format" value="excel">تصدير Excel</button>
            </div>
        </form>

        {{-- 2. كشف نتائج اختبار --}}
        <form class="rpt-card" method="get" action="{{ route('reports.exam-results') }}" target="_blank">
            <h3>كشف نتائج اختبار</h3>
            <p>المسجّلون في موعد اختبار بنتائجهم وحالة الاعتماد ورسوم الإعادة.</p>
            <label>موعد الاختبار</label>
            <select name="exam_id" required>
                <option value="">اختر الاختبار</option>
                @foreach ($this->examOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="rpt-actions">
                <button type="submit" name="format" value="html" class="primary">عرض للطباعة / PDF</button>
                <button type="submit" name="format" value="excel">تصدير Excel</button>
            </div>
        </form>

        {{-- 3. كشف الدفعات --}}
        <form class="rpt-card" method="get" action="{{ route('reports.payments') }}" target="_blank">
            <h3>كشف الدفعات</h3>
            <p>إيصالات المواد ورسوم الإعادة في فترة، مع المجموع.</p>
            <label>من تاريخ</label>
            <input type="date" name="from" value="{{ now()->startOfMonth()->toDateString() }}">
            <label>إلى تاريخ</label>
            <input type="date" name="until" value="{{ now()->toDateString() }}">
            @unless ($this->isCenterBound())
                <label>المركز</label>
                <select name="center_id">
                    <option value="">كل المراكز</option>
                    @foreach ($this->centerOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endunless
            <div class="rpt-actions">
                <button type="submit" name="format" value="html" class="primary">عرض للطباعة / PDF</button>
                <button type="submit" name="format" value="excel">تصدير Excel</button>
            </div>
        </form>

        {{-- 4. المواد غير المسددة --}}
        <form class="rpt-card" method="get" action="{{ route('reports.unpaid-subjects') }}" target="_blank">
            <h3>المواد غير المسددة</h3>
            <p>الطلاب والمواد المستحقة عليهم بعد الإعفاء، مع إجمالي المستحق.</p>
            @unless ($this->isCenterBound())
                <label>المركز</label>
                <select name="center_id">
                    <option value="">كل المراكز</option>
                    @foreach ($this->centerOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endunless
            <label>الدورة (اختياري)</label>
            <select name="course_id">
                <option value="">كل الدورات</option>
                @foreach ($this->courseOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="rpt-actions">
                <button type="submit" name="format" value="html" class="primary">عرض للطباعة / PDF</button>
                <button type="submit" name="format" value="excel">تصدير Excel</button>
            </div>
        </form>
    </div>

    <p class="rpt-note">لحفظ التقرير بصيغة PDF: افتح «عرض للطباعة»، ثم اضغط «طباعة / حفظ بصيغة PDF» واختر الطابعة «Save as PDF» (أو «Microsoft Print to PDF»).</p>
</x-filament-panels::page>
