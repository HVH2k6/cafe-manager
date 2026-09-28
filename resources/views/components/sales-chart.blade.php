{{-- Area chart of revenue per hour. $sales: hour => thousand VND. --}}
@props(['sales'])

@php
    $left = 44;
    $right = 628;
    $top = 16;
    $bottom = 176;
    $gridStep = 100;
    $gridMax = max($gridStep, (int) floor(max($sales) / $gridStep) * $gridStep);
    $scaleMax = max($gridMax, max($sales)) * 1.15;

    $hours = array_keys($sales);
    $xStep = ($right - $left) / max(count($sales) - 1, 1);

    $points = [];
    foreach (array_values($sales) as $index => $value) {
        $points[] = [
            'x' => round($left + $index * $xStep, 1),
            'y' => round($bottom - $value / $scaleMax * ($bottom - $top), 1),
        ];
    }

    $line = collect($points)->map(fn ($point, $index) => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])->implode(' ');
    $area = $line.' L'.end($points)['x'].' '.$bottom.' L'.$points[0]['x'].' '.$bottom.' Z';

    $peakIndex = array_search(max($sales), array_values($sales), true);
    $peak = $points[$peakIndex];
    $peakLabel = $hours[$peakIndex].':00 · '.number_format(max($sales) * 1000, 0, ',', '.').' đ';
    $tooltipWidth = 124;
    $tooltipX = min(max($peak['x'] - 6, $left), $right - $tooltipWidth);
@endphp

<svg viewBox="0 0 640 200" class="h-auto w-full" role="img" aria-label="Doanh số theo giờ, cao nhất lúc {{ $peakLabel }}">
    <g class="font-mono text-[11px]" fill="#57534e">
        @for ($value = 0; $value <= $gridMax; $value += $gridStep)
            @php $y = round($bottom - $value / $scaleMax * ($bottom - $top), 1); @endphp
            <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e7e5e4" stroke-dasharray="3 4" />
            <text x="{{ $left - 10 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $value }}</text>
        @endfor

        @foreach ($hours as $index => $hour)
            @if ($index % 2 === 0)
                <text x="{{ $points[$index]['x'] }}" y="{{ $bottom + 20 }}" text-anchor="middle">{{ $hour }}h</text>
            @endif
        @endforeach
    </g>

    <path d="{{ $area }}" fill="#f4e6d6" />
    <path d="{{ $line }}" fill="none" stroke="#8b6f47" stroke-width="2.25" stroke-linejoin="round" />

    <line x1="{{ $peak['x'] }}" x2="{{ $peak['x'] }}" y1="{{ $peak['y'] }}" y2="{{ $bottom }}" stroke="#8b6f47" stroke-dasharray="3 3" />
    <circle cx="{{ $peak['x'] }}" cy="{{ $peak['y'] }}" r="5" fill="#fff" stroke="#3d2e20" stroke-width="2" />
    <g transform="translate({{ $tooltipX + 18 }} {{ max($peak['y'] - 20, 0) }})">
        <rect width="{{ $tooltipWidth }}" height="24" rx="5" fill="#3d2e20" />
        <text x="{{ $tooltipWidth / 2 }}" y="16" text-anchor="middle" fill="#fff" class="text-[12px] font-semibold">{{ $peakLabel }}</text>
    </g>
</svg>
