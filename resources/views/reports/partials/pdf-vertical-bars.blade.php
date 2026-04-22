@php
    $items = $items ?? [];
    $max = max(1, (int) ($max ?? 1));
    $chunks = array_chunk($items, 8);
@endphp
<h2 class="chart-title">{{ $title }}</h2>
@if (!empty($subtitle))
    <p class="chart-sub">{{ $subtitle }}</p>
@endif
@if (empty($items))
    <p class="chart-empty">No data available for this period.</p>
@else
    @foreach ($chunks as $chunk)
        <table class="chart-vbar-wrap">
            <tr>
                @foreach ($chunk as $row)
                    @php $h = max(10, (int) round($row['value'] / $max * 92)); @endphp
                    <td class="chart-vbar-cell">
                        <div class="vbar-val">{{ $row['value'] }}</div>
                        <div class="vbar-col" style="height: {{ $h }}px;"></div>
                        <div class="vbar-lbl" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                    </td>
                @endforeach
                @for ($i = count($chunk); $i < 8; $i++)
                    <td class="chart-vbar-cell">&nbsp;</td>
                @endfor
            </tr>
        </table>
    @endforeach
@endif
