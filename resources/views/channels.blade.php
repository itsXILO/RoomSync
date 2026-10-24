@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Channel Monitor & OTA Comparison</h1>
        <p class="page-subtitle">Verify inventory alignment between RoomSync source-of-truth and connected OTA channels.</p>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem;">
        ✅ {{ session('success') }}
    </div>
@endif

<!-- Channel Cards List -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    @foreach($channels as $c)
        @php $isSelected = ($selectedChannel && $selectedChannel->id === $c->id); @endphp
        <div class="card" style="margin-bottom: 0; padding: 1.25rem; border-color: {{ $isSelected ? '#3b82f6' : 'var(--border)' }}; background: {{ $isSelected ? 'rgba(59, 130, 246, 0.08)' : 'var(--bg-card)' }}; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div style="font-size: 1.1rem; font-weight: 700; color: #fff;">{{ $c->name }}</div>
                    <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 4px; {{ $c->active ? 'background: rgba(16, 185, 129, 0.2); color: #34d399;' : 'background: rgba(239, 68, 68, 0.2); color: #f87171;' }}">
                        {{ $c->active ? 'ACTIVE' : 'INACTIVE' }}
                    </span>
                </div>

                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1rem;">
                    <div>Format: <strong style="color: #cbd5e1;">{{ strtoupper($c->format) }}</strong></div>
                    <div style="font-family: monospace; font-size: 0.75rem; word-break: break-all;">Endpoint: {{ $c->endpoint_url }}</div>
                    <div style="font-family: monospace; font-size: 0.75rem;">API Key: {{ substr($c->api_key, 0, 8) }}&bull;&bull;&bull;&bull;</div>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 0.75rem;">
                <a href="/channels?channel_id={{ $c->id }}" class="btn {{ $isSelected ? 'btn-primary' : 'btn-outline' }}" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                    {{ $isSelected ? 'Viewing State' : 'Compare' }}
                </a>

                <form method="POST" action="/channels/{{ $c->id }}/sync" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; color: #60a5fa; border-color: rgba(59, 130, 246, 0.4);">
                        ⚡ Push Full Sync
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</div>

@if($selectedChannel)
    <!-- Comparison Banner -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 1.05rem; font-weight: 700;">
                Comparing: <span style="color: #60a5fa;">{{ $selectedChannel->name }}</span> ({{ strtoupper($selectedChannel->format) }})
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                Cross-checking RoomSync <code>inventory</code> table vs OTA's <code>mock_ota_inventory</code> for next 30 days.
            </div>
        </div>

        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.9rem; border-radius: 6px; {{ $mismatchCount > 0 ? 'background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171;' : 'background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399;' }}">
                <span style="font-size: 1.2rem; font-weight: 800;">{{ $mismatchCount }}</span>
                <span style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700;">
                    {{ $mismatchCount === 1 ? 'Mismatch Found' : 'Mismatches Found' }}
                </span>
            </div>

            <form method="POST" action="/channels/{{ $selectedChannel->id }}/sync">
                @csrf
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    Sync All 60 Days Now
                </button>
            </form>
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                <thead>
                    <tr style="background-color: #0e1526; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                        <th style="padding: 0.85rem 1rem;">Room Type</th>
                        <th style="padding: 0.85rem 1rem;">Date</th>
                        <th style="padding: 0.85rem 1rem; background: rgba(59, 130, 246, 0.05); text-align: center;">RoomSync Available</th>
                        <th style="padding: 0.85rem 1rem; background: rgba(59, 130, 246, 0.05); text-align: center;">RoomSync Rate</th>
                        <th style="padding: 0.85rem 1rem; background: rgba(168, 85, 247, 0.05); text-align: center;">OTA Available</th>
                        <th style="padding: 0.85rem 1rem; background: rgba(168, 85, 247, 0.05); text-align: center;">OTA Rate</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comparison as $row)
                        <tr style="border-bottom: 1px solid var(--border); {{ $row['is_mismatch'] ? 'background-color: rgba(239, 68, 68, 0.08);' : '' }}">
                            <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                {{ $row['room_type'] }}
                            </td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace; color: var(--text-muted);">
                                {{ $row['date'] }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: #60a5fa; background: rgba(59, 130, 246, 0.03);">
                                {{ $row['rs_available'] }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-family: monospace; background: rgba(59, 130, 246, 0.03);">
                                ${{ number_format($row['rs_rate'], 2) }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: #c084fc; background: rgba(168, 85, 247, 0.03);">
                                {{ $row['ota_available'] !== null ? $row['ota_available'] : 'None' }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-family: monospace; background: rgba(168, 85, 247, 0.03);">
                                {{ $row['ota_rate'] !== null ? '$' . number_format($row['ota_rate'], 2) : 'None' }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700;">
                                @if($row['is_mismatch'])
                                    <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.72rem;">
                                        MISMATCH
                                    </span>
                                @else
                                    <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.72rem;">
                                        IN SYNC
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                No inventory rows available for comparison.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
