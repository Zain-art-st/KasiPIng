@php
    use App\Services\Seating\SeatingLayout;
    $people = $state['people'];
    $orderIndex = array_flip($state['order']);
    $labelFor = fn ($key) => $people[$key]['name'].($people[$key]['title'] ? ' — '.$people[$key]['title'] : '');
@endphp

<x-filament-panels::page>
    <style>
        .sp { --sp-bg: #f8fafc; --sp-card: #ffffff; --sp-line: #d4d4d8; --sp-muted: #71717a; --sp-text: #18181b;
              --sp-table: #e4e4e7; --sp-chair: #f59e0b; --sp-host: #3b82f6; --sp-warn: #fef3c7; --sp-warn-line: #d97706; --sp-empty: #fafafa; }
        .dark .sp { --sp-bg: #0f0f12; --sp-card: #1c1c21; --sp-line: #3f3f46; --sp-muted: #a1a1aa; --sp-text: #f4f4f5;
              --sp-table: #2a2a31; --sp-warn: #3a2a07; --sp-empty: #16161a; }
        .sp { color: var(--sp-text); }
        .sp-bar { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; align-items: end; }
        .sp-field { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; color: var(--sp-muted); }
        .sp-field input, .sp-field select { width: 6.5rem; border: 1px solid var(--sp-line); border-radius: .5rem; padding: .35rem .5rem;
              background: var(--sp-card); color: var(--sp-text); font-size: .875rem; }
        .sp-field select.wide { width: 16rem; }
        .sp-link { font-size: .75rem; color: #d97706; cursor: pointer; text-align: left; }
        .sp-layouts { display: flex; gap: .5rem; flex-wrap: wrap; }
        .sp-layout { border: 1px solid var(--sp-line); border-radius: .75rem; padding: .5rem .9rem; font-size: .875rem; background: var(--sp-card); cursor: pointer; }
        .sp-layout.on { border-color: #d97706; box-shadow: 0 0 0 2px rgba(217,119,6,.25); font-weight: 600; }
        .sp-main { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; margin-top: 1rem; }
        @media (min-width: 1200px) { .sp-main { grid-template-columns: minmax(0, 1fr) 320px; } }
        .sp-canvas-wrap { overflow: auto; border: 1px solid var(--sp-line); border-radius: .75rem; background: var(--sp-bg); max-height: 78vh; }
        .sp-canvas { position: relative; margin: 0 auto; }
        .sp-shape-rect { position: absolute; background: var(--sp-table); border-radius: 4px; }
        .sp-shape-circle { position: absolute; border-radius: 9999px; background: var(--sp-table); display: flex; align-items: center; justify-content: center;
              font-size: .75rem; color: var(--sp-muted); font-weight: 600; }
        .sp-shape-label { position: absolute; transform: translateX(-50%); font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--sp-muted); }
        .sp-seat { position: absolute; box-sizing: border-box; width: {{ SeatingLayout::SEAT_W }}px; height: {{ SeatingLayout::SEAT_H }}px; border: 1px solid var(--sp-line);
              border-radius: .5rem; background: var(--sp-card); padding: 3px 6px 3px 24px; font-size: .72rem; line-height: 1.15; overflow: hidden; cursor: grab; }
        .sp-seat.empty { background: var(--sp-empty); border-style: dashed; cursor: default; color: var(--sp-muted); }
        .sp-seat.chair { border: 2px solid var(--sp-chair); }
        .sp-seat.host { border: 2px solid var(--sp-host); }
        .sp-seat.warn { background: var(--sp-warn); border: 2px dashed var(--sp-warn-line); }
        .sp-seat .no { position: absolute; left: 4px; top: 4px; font-size: .68rem; font-weight: 700; color: var(--sp-muted); }
        .sp-seat .nm { font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sp-seat .sub { color: var(--sp-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sp-seat .tools { position: absolute; right: 1px; top: 1px; display: none; gap: 1px; background: var(--sp-card); border-radius: .4rem; }
        .sp-seat:hover .tools { display: flex; }
        .sp-tag { display: inline-block; font-size: .6rem; font-weight: 700; padding: 0 4px; border-radius: 4px; color: #fff; margin-right: 2px; }
        .sp-panel { border: 1px solid var(--sp-line); border-radius: .75rem; background: var(--sp-card); padding: .75rem; font-size: .8rem; }
        .sp-panel + .sp-panel { margin-top: .75rem; }
        .sp-panel h3 { font-weight: 600; font-size: .85rem; margin-bottom: .25rem; }
        .sp-panel p.hint { color: var(--sp-muted); font-size: .72rem; margin-bottom: .5rem; }
        .sp-item { display: flex; gap: .4rem; align-items: center; padding: .3rem .35rem; border-radius: .4rem; cursor: grab; border: 1px solid transparent; }
        .sp-item:hover { border-color: var(--sp-line); }
        .sp-item .seatno { min-width: 1.6rem; text-align: center; font-weight: 700; color: var(--sp-muted); font-size: .7rem; }
        .sp-item .who { flex: 1; min-width: 0; }
        .sp-item .who div { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sp-item .who .sub { color: var(--sp-muted); font-size: .7rem; }
        .sp-item.out { opacity: .55; }
        .sp-mini { font-size: .7rem; color: #d97706; cursor: pointer; white-space: nowrap; }
        .sp-drop { border: 1px dashed var(--sp-line); border-radius: .5rem; padding: .4rem; min-height: 2.2rem; }
        .sp-empty-state { text-align: center; padding: 2.5rem 1rem; border: 1px dashed var(--sp-line); border-radius: .75rem; }
        .sp-norank { font-size: .6rem; color: var(--sp-warn-line); font-weight: 700; }
        .sp-legend { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .72rem; color: var(--sp-muted); margin-top: .5rem; }
        .sp-legend span::before { content: ''; display: inline-block; width: .7rem; height: .7rem; border-radius: 3px; margin-right: .3rem; vertical-align: -1px; }
        .sp-legend .l-chair::before { border: 2px solid var(--sp-chair); }
        .sp-legend .l-host::before { border: 2px solid var(--sp-host); }
        .sp-legend .l-warn::before { background: var(--sp-warn); border: 2px dashed var(--sp-warn-line); }
    </style>

    <div class="sp" x-data="{ drag: null }">
        <x-filament::section>
            <div class="sp-bar">
                <div class="sp-field">
                    Seating type
                    <div class="sp-layouts">
                        @foreach (SeatingLayout::LABELS as $value => $label)
                            <button type="button" class="sp-layout {{ $state['layout'] === $value ? 'on' : '' }}" wire:click="chooseLayout('{{ $value }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($state['layout'] === SeatingLayout::LINE)
                    <label class="sp-field">Seats per row <input type="number" min="1" max="30" wire:model.live.debounce.600ms="plan.config.per_row"></label>
                    <label class="sp-field">Rows <input type="number" min="1" max="20" wire:model.live.debounce.600ms="plan.config.rows"></label>
                @elseif ($state['layout'] === SeatingLayout::U_SHAPE)
                    <label class="sp-field">Head table seats <input type="number" min="1" max="15" wire:model.live.debounce.600ms="plan.config.head"></label>
                    <label class="sp-field">Seats per side <input type="number" min="0" max="30" wire:model.live.debounce.600ms="plan.config.arm"></label>
                @elseif ($state['layout'] === SeatingLayout::ROUND)
                    <label class="sp-field">Tables <input type="number" min="1" max="40" wire:model.live.debounce.600ms="plan.config.tables"></label>
                    <label class="sp-field">Seats per table <input type="number" min="2" max="16" wire:model.live.debounce.600ms="plan.config.per_table"></label>
                @endif

                @if ($state['layout'])
                    <div class="sp-field">
                        {{ $seatCount }} seats for {{ $needed }} {{ $needed === 1 ? 'person' : 'people' }}
                        @if ($seatCount !== $needed)
                            <button type="button" class="sp-link" wire:click="fitSeats">Fit seats to people</button>
                        @endif
                    </div>
                @endif
            </div>

            <div class="sp-bar" style="margin-top: 1rem;">
                <label class="sp-field">
                    Chair (Pengerusi / tetamu kehormat) · seat 1
                    <select class="wide" wire:model.live="plan.chair">
                        <option value="">— Highest rank present —</option>
                        @foreach ($state['order'] as $key)
                            <option value="{{ $key }}" wire:key="chair-{{ $key }}">{{ $labelFor($key) }}{{ $people[$key]['present'] ? '' : ' (not attending)' }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="sp-link" x-on:click="$wire.mountAction('addPerson', { role: 'chair' })">+ Someone not on the list</button>
                </label>
                <label class="sp-field">
                    Tuan rumah (host) · seat 2
                    <select class="wide" wire:model.live="plan.host">
                        <option value="">— None —</option>
                        @foreach ($state['order'] as $key)
                            <option value="{{ $key }}" wire:key="host-{{ $key }}">{{ $labelFor($key) }}{{ $people[$key]['present'] ? '' : ' (not attending)' }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="sp-link" x-on:click="$wire.mountAction('addPerson', { role: 'host' })">+ Someone not on the list</button>
                </label>

                <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-left:auto;">
                    {{ $this->addPersonAction }}
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-bars-arrow-down" wire:click="resortByRank">Re-sort by rank</x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshGuests">Refresh guest list</x-filament::button>
                    {{ $this->downloadPdfAction }}
                    @if ($state['layout'])
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="startOver" wire:confirm="Clear the layout and all seats?">Start over</x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div class="sp-main">
            <div>
                @if (! $layout)
                    <div class="sp-empty-state">
                        <p style="font-weight:600; font-size:1rem;">Choose a seating type to start</p>
                        <p style="color:var(--sp-muted); margin-top:.35rem;">
                            {{ count(array_filter($people, fn ($p) => $p['present'])) }} attending ·
                            {{ count(array_filter($people, fn ($p) => ! $p['present'])) }} invited but not confirmed.
                            Seats are filled by kekananan once you pick a layout.
                        </p>
                    </div>
                @else
                    <div class="sp-canvas-wrap">
                        <div class="sp-canvas" style="width: {{ $layout['width'] }}px; height: {{ $layout['height'] }}px;">
                            @foreach ($layout['shapes'] as $shape)
                                @if ($shape['type'] === 'rect')
                                    <div class="sp-shape-rect" style="left:{{ $shape['x'] }}px; top:{{ $shape['y'] }}px; width:{{ $shape['w'] }}px; height:{{ $shape['h'] }}px;"></div>
                                @elseif ($shape['type'] === 'circle')
                                    <div class="sp-shape-circle" style="left:{{ $shape['cx'] - $shape['r'] }}px; top:{{ $shape['cy'] - $shape['r'] }}px; width:{{ 2 * $shape['r'] }}px; height:{{ 2 * $shape['r'] }}px;">{{ $shape['text'] }}</div>
                                @else
                                    <div class="sp-shape-label" style="left:{{ $shape['x'] }}px; top:{{ $shape['y'] }}px;">{{ $shape['text'] }}</div>
                                @endif
                            @endforeach

                            @foreach ($layout['seats'] as $seat)
                                @php
                                    $seq = $seat['seq'];
                                    $key = $state['seats'][(string) $seq] ?? null;
                                    $p = $key ? $people[$key] : null;
                                    $classes = ! $p ? 'empty' : (($key === $state['chair'] ? 'chair ' : '').($key === $state['host'] ? 'host ' : '').(in_array($seq, $misplaced, true) ? 'warn' : ''));
                                @endphp
                                <div wire:key="seat-{{ $seq }}"
                                     class="sp-seat {{ $classes }}"
                                     style="left: {{ $seat['x'] }}px; top: {{ $seat['y'] }}px; width: {{ $seat['w'] ?? SeatingLayout::SEAT_W }}px; height: {{ $seat['h'] ?? SeatingLayout::SEAT_H }}px;"
                                     @if ($p) draggable="true" x-on:dragstart="drag = { kind: 'seat', value: '{{ $seq }}' }" @endif
                                     x-on:dragover.prevent
                                     x-on:drop.prevent="drag && $wire.dropOnSeat({{ $seq }}, drag.kind, drag.value); drag = null"
                                     @if ($p) title="{{ $p['name'] }}{{ $p['title'] ? ' — '.$p['title'] : '' }}{{ $p['org'] ? ' · '.$p['org'] : '' }}{{ in_array($seq, $misplaced, true) ? ' ⚠ Someone more senior sits further from the chair' : '' }}" @endif>
                                    <span class="no">{{ $seq }}</span>
                                    @if ($p)
                                        <div class="nm">
                                            @if ($key === $state['chair'])<span class="sp-tag" style="background:var(--sp-chair)">PENGERUSI</span>@endif
                                            @if ($key === $state['host'])<span class="sp-tag" style="background:var(--sp-host)">TUAN RUMAH</span>@endif
                                            {{ $p['name'] }}
                                        </div>
                                        <div class="sub">{{ $p['note'] ?: ($p['title'] ?: $p['org']) }}</div>
                                        <div class="tools">
                                            {{ ($this->replaceAction)(['seq' => $seq]) }}
                                            <x-filament::icon-button icon="heroicon-m-user-minus" size="xs" color="gray" tooltip="Not present" wire:click="setPresent('{{ $key }}', false)" />
                                        </div>
                                    @else
                                        <div class="sub" style="margin-top:.6rem;">Empty</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="sp-legend">
                        <span class="l-chair">Chair</span>
                        <span class="l-host">Tuan rumah</span>
                        <span class="l-warn">Someone more senior sits further from the chair</span>
                        <span>Seat numbers follow protocol order (1 = most senior).</span>
                    </div>
                @endif
            </div>

            <aside>
                <div class="sp-panel">
                    <h3>Running order (kekananan)</h3>
                    <p class="hint">Most senior first. Drag a name onto another to put it just above; seats re-fill to match.</p>
                    @foreach ($state['order'] as $key)
                        @php $p = $people[$key]; @endphp
                        <div wire:key="order-{{ $key }}" class="sp-item {{ $p['present'] ? '' : 'out' }}"
                             draggable="true"
                             x-on:dragstart="drag = { kind: 'person', value: @js($key) }"
                             x-on:dragover.prevent
                             x-on:drop.prevent="drag && $wire.reorder(drag.kind, drag.value, @js($key)); drag = null">
                            <span class="seatno">{{ $seatOf[$key] ?? '·' }}</span>
                            <div class="who">
                                <div>{{ $p['name'] }}</div>
                                <div class="sub">
                                    @if ($p['rank_sort'] >= \App\Services\Seating\SeatingPlanner::UNRANKED)<span class="sp-norank">NO RANK</span>@endif
                                    {{ $p['note'] ?: ($p['title'] ?: $p['org']) }}
                                </div>
                            </div>
                            @if ($p['present'])
                                <button type="button" class="sp-mini" wire:click="setPresent(@js($key), false)" title="Mark as not present">Absent</button>
                            @else
                                <button type="button" class="sp-mini" wire:click="setPresent(@js($key), true)" title="Mark as present">Present</button>
                            @endif
                            @if (str_starts_with($key, 'm:'))
                                <button type="button" class="sp-mini" style="color:var(--sp-muted)" wire:click="removeManual(@js($key))" title="Remove">✕</button>
                            @endif
                        </div>
                    @endforeach
                    <div class="sp-drop" style="margin-top:.4rem; text-align:center; color:var(--sp-muted); font-size:.7rem;"
                         x-on:dragover.prevent
                         x-on:drop.prevent="drag && $wire.reorder(drag.kind, drag.value, null); drag = null">Drop here to move to the end</div>
                </div>

                @if ($layout)
                    <div class="sp-panel">
                        <h3>Present but no seat ({{ count($unseated) }})</h3>
                        <p class="hint">Drag a seat here to empty it; drag a name onto a seat to seat them.</p>
                        <div class="sp-drop" x-on:dragover.prevent x-on:drop.prevent="drag && $wire.dropOnTray(drag.kind, drag.value); drag = null">
                            @forelse ($unseated as $key)
                                <div wire:key="tray-{{ $key }}" class="sp-item" draggable="true" x-on:dragstart="drag = { kind: 'person', value: @js($key) }">
                                    <div class="who"><div>{{ $people[$key]['name'] }}</div><div class="sub">{{ $people[$key]['title'] ?: $people[$key]['org'] }}</div></div>
                                </div>
                            @empty
                                <span style="color:var(--sp-muted); font-size:.72rem;">Everyone present has a seat.</span>
                            @endforelse
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-filament-panels::page>
