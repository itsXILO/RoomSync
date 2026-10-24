@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sync Logs & Audit Trail</h1>
        <p class="page-subtitle">Append-only audit ledger of every inbound webhook and outbound channel push.</p>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem;">
        ✅ {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem;">
        ⚠️ {{ session('error') }}
    </div>
@endif

<!-- Filters Card -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
    <form method="GET" action="/logs" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.78rem;">Direction</label>
            <select name="direction" class="form-control" style="padding: 0.45rem 0.75rem;">
                <option value="">All Directions</option>
                <option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>Inbound (OTA &rarr; RoomSync)</option>
                <option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>Outbound (RoomSync &rarr; OTA)</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.78rem;">Status</label>
            <select name="status" class="form-control" style="padding: 0.45rem 0.75rem;">
                <option value="">All Statuses</option>
                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label class="form-label" style="font-size: 0.78rem;">Channel</label>
            <select name="channel_id" class="form-control" style="padding: 0.45rem 0.75rem;">
                <option value="">All Channels</option>
                @foreach($channels as $c)
                    <option value="{{ $c->id }}" {{ request('channel_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
            <a href="/logs" class="btn btn-outline" style="padding: 0.5rem 0.85rem;">Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
            <thead>
                <tr style="background-color: #0e1526; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.85rem 1rem;">Timestamp</th>
                    <th style="padding: 0.85rem 1rem;">Channel</th>
                    <th style="padding: 0.85rem 1rem;">Direction</th>
                    <th style="padding: 0.85rem 1rem;">Event</th>
                    <th style="padding: 0.85rem 1rem;">HTTP</th>
                    <th style="padding: 0.85rem 1rem;">Status</th>
                    <th style="padding: 0.85rem 1rem;">Tries</th>
                    <th style="padding: 0.85rem 1rem;">Error / Diagnostic</th>
                    <th style="padding: 0.85rem 1rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr style="border-bottom: 1px solid var(--border); transition: background-color 0.1s ease;">
                        <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.78rem; color: var(--text-muted); white-space: nowrap;">
                            {{ $log->created_at ? $log->created_at->format('M j H:i:s') : 'N/A' }}
                        </td>
                        <td style="padding: 0.75rem 1rem; font-weight: 600; white-space: nowrap;">
                            {{ $log->channel->name ?? 'Channel #' . $log->channel_id }}
                        </td>
                        <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                            @if($log->direction === 'in')
                                <span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.72rem;">
                                    &darr; INBOUND
                                </span>
                            @else
                                <span style="background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4); padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.72rem;">
                                    &uarr; OUTBOUND
                                </span>
                            @endif
                        </td>
                        <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.8rem;">
                            {{ $log->event }}
                        </td>
                        <td style="padding: 0.75rem 1rem; font-family: monospace; font-weight: 700;">
                            @php
                                $codeColor = '#94a3b8';
                                if ($log->response_code >= 200 && $log->response_code < 300) $codeColor = '#34d399';
                                elseif ($log->response_code == 409) $codeColor = '#fbbf24';
                                elseif ($log->response_code >= 400) $codeColor = '#f87171';
                            @endphp
                            <span style="color: {{ $codeColor }};">
                                {{ $log->response_code ?: '—' }}
                            </span>
                        </td>
                        <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                            @if($log->status === 'success')
                                <span style="color: #34d399; font-weight: 600;">&bull; Success</span>
                            @elseif($log->status === 'failed')
                                <span style="color: #f87171; font-weight: 600;">&bull; Failed</span>
                            @else
                                <span style="color: #fbbf24; font-weight: 600;">&bull; Pending</span>
                            @endif
                        </td>
                        <td style="padding: 0.75rem 1rem; font-family: monospace;">
                            {{ $log->attempts }}
                        </td>
                        <td style="padding: 0.75rem 1rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.78rem; color: #cbd5e1;">
                            {{ $log->error ?: '—' }}
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right; white-space: nowrap;">
                            <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;" onclick="viewPayload({{ $log->id }})">
                                    Payload
                                </button>
                                @if($log->direction === 'out' && $log->status === 'failed')
                                    <form method="POST" action="/logs/{{ $log->id }}/retry" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; background-color: #8b5cf6;">
                                            Retry
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div id="payload-{{ $log->id }}" style="display: none;">{{ $log->payload }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            No synchronization logs match the selected filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
            <div style="color: var(--text-muted);">
                Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} entries
            </div>
            <div>
                {{ $logs->links() }}
            </div>
        </div>
    @endif
</div>

<!-- Payload Inspection Modal -->
<div class="modal-overlay" id="payloadModal">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Raw Log Payload</h3>
            <button type="button" class="close-btn" onclick="closePayloadModal()">&times;</button>
        </div>
        <pre id="modalPayloadContent" style="background: #090d16; border: 1px solid var(--border); border-radius: 6px; padding: 1rem; font-size: 0.8rem; color: #cbd5e1; overflow-x: auto; max-height: 380px;"></pre>
        <div style="margin-top: 1rem; text-align: right;">
            <button type="button" class="btn btn-outline" onclick="closePayloadModal()">Close</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function viewPayload(logId) {
        const raw = document.getElementById('payload-' + logId).textContent;
        let formatted = raw;
        try {
            const parsed = JSON.parse(raw);
            formatted = JSON.stringify(parsed, null, 2);
        } catch(e) {
            // keep raw XML or text
        }
        document.getElementById('modalPayloadContent').textContent = formatted;
        document.getElementById('payloadModal').classList.add('active');
    }

    function closePayloadModal() {
        document.getElementById('payloadModal').classList.remove('active');
    }

    document.getElementById('payloadModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('payloadModal')) closePayloadModal();
    });
</script>
@endpush
@endsection
