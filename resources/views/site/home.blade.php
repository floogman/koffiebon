@extends('site.layout')

@section('title', ($merchant?->name ?? 'Koffiebon').' — koffie & koffiebon')

@section('content')
    <section class="wrap" style="padding: 56px 20px 24px; text-align: center;">
        <div style="font-size: 56px;">☕</div>
        <h1 style="font-size: 38px; margin: 8px 0 6px;">Vers gezet, vooruit betaald.</h1>
        <p class="muted" style="max-width: 540px; margin: 0 auto 28px; font-size: 18px;">
            Bekijk onze menukaart of haal een <strong>Koffiebon</strong>: een prepaid koffiekaart die je
            aan de balie verzilvert met een roterende QR-code.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a class="btn" href="{{ $frontendUrl }}">Open Koffiebon →</a>
            <a class="btn ghost" href="{{ route('site.menu') }}">Bekijk de menukaart</a>
        </div>
    </section>

    <section class="wrap" style="padding: 16px 20px 56px;">
        <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <a class="card" href="{{ route('site.menu') }}" style="text-decoration: none; padding: 24px;">
                <div style="font-size: 28px;">📋</div>
                <h2 style="margin: 10px 0 6px; font-size: 20px;">Menukaart</h2>
                <p class="muted" style="margin: 0;">Onze koffies en maten — van espresso tot flat white.</p>
            </a>
            <a class="card" href="{{ $frontendUrl }}" style="text-decoration: none; padding: 24px;">
                <div style="font-size: 28px;">🎟️</div>
                <h2 style="margin: 10px 0 6px; font-size: 20px;">Koffiebon</h2>
                <p class="muted" style="margin: 0;">Koop een koffiekaart, toon je QR aan de balie en bespaar.</p>
            </a>
        </div>

        @if (count($products))
            <div class="card" style="margin-top: 16px; padding: 24px;">
                <h2 style="margin: 0 0 14px; font-size: 20px;">Onze koffiebonnen</h2>
                <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    @foreach ($products as $p)
                        <div style="border: 1px solid rgba(0,0,0,.08); border-radius: 14px; padding: 16px;">
                            <div style="font-weight: 700;">{{ $p['name'] }}</div>
                            <div class="muted" style="font-size: 14px;">
                                {{ $p['cups_total'] }} koppen{{ $p['gift'] > 0 ? ' · '.$p['gift'].' cadeau' : '' }}
                            </div>
                            <div style="font-size: 22px; font-weight: 800; margin-top: 8px;">
                                € {{ number_format($p['price'] / 100, 2, ',', '.') }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <a class="btn" href="{{ $frontendUrl }}" style="margin-top: 18px;">Haal je Koffiebon →</a>
            </div>
        @endif
    </section>
@endsection
