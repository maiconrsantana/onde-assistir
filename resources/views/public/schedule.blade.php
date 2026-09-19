<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Agenda publica de jogos e transmissoes do Campeonato Brasileiro no Brasil.">

        <title>{{ config('app.name', 'Onde Assistir') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-zinc-950 antialiased">
        <main>
            <section class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-6xl flex-col gap-8 px-5 py-10 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-normal text-emerald-700">Campeonato Brasileiro</p>
                        <h1 class="mt-3 text-4xl font-bold tracking-normal text-zinc-950 sm:text-5xl">Onde assistir aos jogos</h1>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-zinc-600">
                            Agenda com partidas e transmissoes publicadas apos revisao. Horarios no fuso de Brasilia.
                        </p>
                    </div>

                    <div class="grid gap-3 text-sm text-zinc-700 sm:grid-cols-3">
                        <div class="border-l-4 border-emerald-600 bg-emerald-50 px-4 py-3">
                            <span class="block text-2xl font-bold text-zinc-950">{{ $fixtures->count() }}</span>
                            <span>jogos publicados</span>
                        </div>
                        <div class="border-l-4 border-sky-600 bg-sky-50 px-4 py-3">
                            <span class="block text-2xl font-bold text-zinc-950">{{ collect($fixturesByDate)->count() }}</span>
                            <span>dias com partidas</span>
                        </div>
                        <div class="border-l-4 border-zinc-700 bg-zinc-100 px-4 py-3">
                            <span class="block text-2xl font-bold text-zinc-950">BR</span>
                            <span>transmissoes no Brasil</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 py-8 sm:px-6 lg:px-8">
                @forelse ($fixturesByDate as $date => $dayFixtures)
                    @php
                        $dateForDisplay = \Illuminate\Support\Carbon::parse($date, config('app.timezone'));
                    @endphp

                    <section class="mb-8">
                        <div class="mb-4 flex flex-col gap-1 border-b border-zinc-200 pb-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-zinc-950">
                                    {{ $dateForDisplay->translatedFormat('l, d \d\e F') }}
                                </h2>
                                <p class="text-sm text-zinc-600">{{ $dayFixtures->count() }} jogo{{ $dayFixtures->count() === 1 ? '' : 's' }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach ($dayFixtures as $fixture)
                                @php
                                    $localStart = $fixture->starts_at->copy()->timezone(config('app.timezone'));
                                @endphp

                                <article class="border border-zinc-200 bg-white p-4 shadow-sm sm:p-5">
                                    <div class="grid gap-4 lg:grid-cols-[9rem_1fr_18rem] lg:items-center">
                                        <div>
                                            <p class="text-2xl font-bold tabular-nums text-zinc-950">{{ $localStart->format('H:i') }}</p>
                                            <p class="mt-1 text-sm text-zinc-600">{{ $fixture->round ?? $fixture->competition->season_name }}</p>
                                        </div>

                                        <div>
                                            <p class="text-sm font-medium text-emerald-700">{{ $fixture->competition->name }}</p>
                                            <h3 class="mt-1 text-xl font-bold leading-snug text-zinc-950">
                                                {{ $fixture->homeTeam->name }} x {{ $fixture->awayTeam->name }}
                                            </h3>
                                            @if ($fixture->venue || $fixture->city)
                                                <p class="mt-2 text-sm text-zinc-600">
                                                    {{ collect([$fixture->venue, $fixture->city])->filter()->join(' - ') }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="border-t border-zinc-200 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                                            @if ($fixture->fixtureBroadcasts->isNotEmpty())
                                                <ul class="space-y-2">
                                                    @foreach ($fixture->fixtureBroadcasts as $broadcast)
                                                        <li>
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="font-semibold text-zinc-950">{{ $broadcast->broadcaster->name }}</span>
                                                                <span class="bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">
                                                                    {{ $schedule->broadcasterTypeLabel($broadcast->broadcaster->type) }}
                                                                </span>
                                                            </div>
                                                            <p class="mt-1 text-sm text-zinc-600">{{ $schedule->accessLabel($broadcast) }}</p>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="font-semibold text-zinc-950">Transmissao ainda nao divulgada</p>
                                                <p class="mt-1 text-sm text-zinc-600">Aguardando fonte confiavel publicada.</p>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <section class="border border-dashed border-zinc-300 bg-white px-5 py-12 text-center">
                        <h2 class="text-2xl font-bold text-zinc-950">Nenhum jogo publicado no momento</h2>
                        <p class="mx-auto mt-3 max-w-xl text-base leading-7 text-zinc-600">
                            As partidas aparecem aqui depois que forem revisadas e publicadas no painel administrativo.
                        </p>
                    </section>
                @endforelse
            </section>
        </main>
    </body>
</html>
