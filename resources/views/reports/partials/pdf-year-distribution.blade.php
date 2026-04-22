@php
    $stack = $stack ?? [];
@endphp
<h2 class="chart-title">{{ $title }}</h2>
@if (!empty($subtitle))
    <p class="chart-sub">{{ $subtitle }}</p>
@endif
@if (empty($stack))
    <p class="chart-empty">No data available for this period.</p>
@else
    <table class="stack-track" cellpadding="0" cellspacing="0">
        <tr>
            @foreach ($stack as $seg)
                @if ($seg['pct'] > 0)
                    <td class="stack-seg" style="width: {{ $seg['pct'] }}%; background: {{ $seg['color'] }};" title="{{ $seg['label'] }}: {{ $seg['value'] }} ({{ $seg['pct'] }}%)">
                        @if ($seg['pct'] >= 7)
                            <span class="stack-seg-inner">{{ $seg['value'] }}</span>
                        @endif
                    </td>
                @endif
            @endforeach
        </tr>
    </table>
    <table class="stack-legend">
        @foreach ($stack as $seg)
            <tr>
                <td class="leg-swatch"><span class="swatch" style="background:{{ $seg['color'] }};"></span></td>
                <td>{{ $seg['label'] }}</td>
                <td class="leg-num">{{ $seg['value'] }}</td>
                <td class="leg-num">{{ $seg['pct'] }}%</td>
            </tr>
        @endforeach
    </table>
@endif
