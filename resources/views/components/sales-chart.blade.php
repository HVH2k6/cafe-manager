{{-- Area chart of revenue. $sales: label ("9:00", "23/09"…) => VND. --}}
@props(['sales'])

@php
    $left = 52;
    $right = 628;
    $top = 16;
    $bottom = 176;

    $labels = array_keys($sales);
    $values = array_map(fn ($value) => (float) $value / 1000, array_values($sales)); // thousand VND
    $max = max($values ?: [0]);

    // A "nice" grid step (1, 2 or 5 × 10^n) giving about 4 lines.
    $raw = max($max, 1) / 4;
    $magnitude = 10 ** floor(log10($raw));
    $gridStep = collect([1, 2, 5, 10])->map(fn ($factor) => $factor * $magnitude)->first(fn ($step) => $step >= $raw);
    $gridMax = ceil(max($max, 1) / $gridStep) * $gridStep;
    $scaleMax = $gridMax * 1.1;

    $xStep = ($right - $left) / max(count($values) - 1, 1);
    $labelEvery = max(1, (int) ceil(count($values) / 12));

    $points = [];
    foreach ($values as $index => $value) {
        $points[] = [
            'x' => round(count($values) === 1 ? ($left + $right) / 2 : $left + $index * $xStep, 1),
            'y' => round($bottom - $value / $scaleMax * ($bottom - $top), 1),
        ];
    }

    $line = collect($points)->map(fn ($point, $index) => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])->implode(' ');
    $area = $points ? $line.' L'.end($points)['x'].' '.$bottom.' L'.$points[0]['x'].' '.$bottom.' Z' : '';

    $hasSales = $max > 0;
    $peakIndex = $hasSales ? array_search($max, $values, true) : null;
    $peak = $peakIndex !== null ? $points[$peakIndex] : null;
    $peakLabel = $peak ? $labels[$peakIndex].' · '.number_format($max * 1000, 0, ',', '.').' đ' : null;
    $tooltipWidth = $peakLabel ? max(110, mb_strlen($peakLabel) * 7 + 16) : 0;
    $tooltipX = $peak ? min(max($peak['x'] - $tooltipWidth / 2, $left), $right - $tooltipWidth) : 0;
    $format = fn ($value) => $value >= 1000 ? rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',').'tr' : number_format($value, 0, ',', '.');
@endphp

<svg viewBox="0 0 640 200" class="h-auto w-full" role="img" aria-label="{{ $hasSales ? 'Doanh thu, cao nhất '.$peakLabel : 'Chưa có doanh thu trong khoảng này' }}">
    <g class="font-mono text-[11px]" fill="#57534e">
        @for ($value = 0; $value <= $gridMax; $value += $gridStep)
            @php $y = round($bottom - $value / $scaleMax * ($bottom - $top), 1); @endphp
            <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e7e5e4" stroke-dasharray="3 4" />
            <text x="{{ $left - 10 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $format($value) }}</text>
        @endfor

        @foreach ($labels as $index => $label)
            @if ($index % $labelEvery === 0)
                <text x="{{ $points[$index]['x'] }}" y="{{ $bottom + 20 }}" text-anchor="middle">{{ $label }}</text>
            @endif
        @endforeach
    </g>

    @if ($points)
        <path d="{{ $area }}" fill="#f4e6d6" />
        <path d="{{ $line }}" fill="none" stroke="#8b6f47" stroke-width="2.25" stroke-linejoin="round" />
    @endif

    @if ($peak)
        <line x1="{{ $peak['x'] }}" x2="{{ $peak['x'] }}" y1="{{ $peak['y'] }}" y2="{{ $bottom }}" stroke="#8b6f47" stroke-dasharray="3 3" />
        <circle cx="{{ $peak['x'] }}" cy="{{ $peak['y'] }}" r="5" fill="#fff" stroke="#3d2e20" stroke-width="2" />
        <g transform="translate({{ $tooltipX }} {{ max($peak['y'] - 34, 0) }})">
            <rect width="{{ $tooltipWidth }}" height="24" rx="5" fill="#3d2e20" />
            <text x="{{ $tooltipWidth / 2 }}" y="16" text-anchor="middle" fill="#fff" class="text-[12px] font-semibold">{{ $peakLabel }}</text>
        </g>
    @else
        <text x="{{ ($left + $right) / 2 }}" y="{{ ($top + $bottom) / 2 }}" text-anchor="middle" fill="#78716c" class="text-[13px]">Chưa có doanh thu</text>
    @endif
</svg>
