@php
    $items = $items ?? [];
    $max = max(1, (int) ($max ?? 1));
    $fillClass = $fillClass ?? 'bar-fill-primary';
@endphp
<h2 class="chart-title">{{ $title }}</h2>
@if (!empty($subtitle))
    <p class="chart-sub">{{ $subtitle }}</p>
@endif
@if (empty($items))
    <p class="chart-empty">No data available for this period.</p>
@else
    <table class="chart-hbar {{ $tableExtraClass ?? '' }}">
        @foreach ($items as $row)
            @php $pct = round($row['value'] / $max * 100); @endphp
            <tr>
                <td class="hbar-label">{{ $row['label'] }}</td>
                <td class="hbar-bar">
                    <div class="bar-track">
                        <div class="{{ $fillClass }}" style="width: {{ $pct }}%;"></div>
                    </div>
                </td>
                <td class="hbar-count">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>
@endif
