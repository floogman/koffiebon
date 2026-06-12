@extends('site.layout')

@section('title', 'Menukaart — '.($merchant?->name ?? 'Koffiebon'))

@section('content')
    <section class="wrap" style="padding: 48px 20px 16px;">
        <h1 style="font-size: 32px; margin: 0 0 6px;">Menukaart</h1>
        <p class="muted" style="margin: 0 0 28px;">Elke koffie, elk formaat — één kop per scan met je Koffiebon.</p>

        <div class="card" style="padding: 8px 24px;">
            @foreach ($drinksByType as $type => $sizes)
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;
                            padding: 16px 0; {{ ! $loop->last ? 'border-bottom: 1px solid rgba(0,0,0,.07);' : '' }}">
                    <div style="font-weight: 700; font-size: 18px;">{{ $type }}</div>
                    <div class="muted" style="text-align: right;">{{ implode(' · ', $sizes) }}</div>
                </div>
            @endforeach
        </div>

        @if (count($products))
            <h2 style="font-size: 22px; margin: 36px 0 12px;">Koffiebon — vooruit betaald, voordeliger</h2>
            <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                @foreach ($products as $p)
                    <div class="card" style="padding: 18px;">
                        <div style="font-weight: 700;">{{ $p['name'] }}</div>
                        <div class="muted" style="font-size: 14px;">
                            {{ $p['cups_total'] }} koppen{{ $p['gift'] > 0 ? ' · '.$p['gift'].' cadeau' : '' }}
                        </div>
                        <div style="font-size: 22px; font-weight: 800; margin-top: 8px;">
                            € {{ number_format($p['price'] / 100, 2, ',', '.') }}
                        </div>
                        <div class="muted" style="font-size: 13px;">
                            € {{ number_format($p['per_cup'] / 100, 2, ',', '.') }} per kop
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div style="margin: 32px 0 8px; display: flex; gap: 12px; flex-wrap: wrap;">
            <a class="btn" href="{{ $frontendUrl }}">Open Koffiebon →</a>
            <a class="btn ghost" href="{{ route('site.home') }}">← Terug</a>
        </div>
    </section>
@endsection
