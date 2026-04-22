@php
    $items = collect($peak ?? [])->map(fn ($r) => [
        'label' => $r['label'] ?? sprintf('%02d:00', (int) ($r['hour'] ?? 0)),
        'value' => (int) ($r['value'] ?? 0),
    ])->all();
    $sum = collect($items)->sum('value');
    $peakMax = max(1, (int) ($peakMax ?? 1));
@endphp
@if ($sum === 0)
    <h2 class="chart-title">{{ $title }}</h2>
    @if (!empty($subtitle))
        <p class="chart-sub">{{ $subtitle }}</p>
    @endif
    <p class="chart-empty">No data available for this period.</p>
@else
    @include('reports.partials.pdf-horizontal-bars', [
        'title' => $title,
        'subtitle' => $subtitle,
        'items' => $items,
        'max' => $peakMax,
        'fillClass' => 'bar-fill-primary',
        'tableExtraClass' => 'chart-hbar--peak',
    ])
    <p class="chart-sub" style="margin-top: 10px;">Each row is one clock hour (00:00–23:00). Counts are approved reservations by reservation start hour.</p>
@endif
