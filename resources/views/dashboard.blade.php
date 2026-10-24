@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $hotel->name ?? 'RoomSync Hotel' }}</h1>
        <p class="page-subtitle">30-Day Master Inventory & Rate Calendar ({{ $startDate }} to {{ $endDate }})</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-card); padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid var(--border); font-size: 0.8rem;">
            <span style="color: var(--text-muted);">Channels Connected:</span>
            @if(isset($hotel->channels) && $hotel->channels->count() > 0)
                @foreach($hotel->channels as $c)
                    <span style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 600;">
                        {{ $c->name }} ({{ strtoupper($c->format) }})
                    </span>
                @endforeach
            @else
                <span style="color: var(--text-muted);">None</span>
            @endif
        </div>
    </div>
</div>

<div style="display: flex; gap: 1.5rem; margin-bottom: 1rem; align-items: center; font-size: 0.85rem;">
    <div style="display: flex; align-items: center; gap: 0.4rem;">
        <span style="width: 12px; height: 12px; border-radius: 3px; background: #10b981; display: inline-block;"></span>
        <span style="color: var(--text-muted);">Plenty (&ge;3 available)</span>
    </div>
    <div style="display: flex; align-items: center; gap: 0.4rem;">
        <span style="width: 12px; height: 12px; border-radius: 3px; background: #f59e0b; display: inline-block;"></span>
        <span style="color: var(--text-muted);">Low (1&ndash;2 left)</span>
    </div>
    <div style="display: flex; align-items: center; gap: 0.4rem;">
        <span style="width: 12px; height: 12px; border-radius: 3px; background: #ef4444; display: inline-block;"></span>
        <span style="color: var(--text-muted);">Sold Out (0 available)</span>
    </div>
    <span style="color: var(--text-muted); margin-left: auto;">💡 Click any calendar cell to edit availability or rate & trigger immediate outbound sync.</span>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto; width: 100%;">
        <table style="width: 100%; border-collapse: collapse; min-width: 1200px; text-align: center;">
            <thead>
                <tr style="background-color: #0e1526; border-bottom: 1px solid var(--border);">
                    <th style="position: sticky; left: 0; background-color: #0e1526; z-index: 10; padding: 1rem; text-align: left; width: 220px; min-width: 220px; font-weight: 600; color: var(--text-main); border-right: 1px solid var(--border);">
                        Room Type
                    </th>
                    @foreach($dates as $d)
                        <th style="padding: 0.6rem 0.5rem; min-width: 65px; border-right: 1px solid rgba(255,255,255,0.05); font-weight: normal; {{ $d['isWeekend'] ? 'background: rgba(139, 92, 246, 0.08);' : '' }}">
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: {{ $d['isWeekend'] ? '#c4b5fd' : 'var(--text-muted)' }};">{{ $d['dayName'] }}</div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">{{ $d['dayNum'] }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted);">{{ $d['month'] }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($roomTypes as $roomType)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="position: sticky; left: 0; background-color: var(--bg-card); z-index: 5; padding: 1rem; text-align: left; border-right: 1px solid var(--border);">
                            <div style="font-weight: 700; font-size: 0.95rem; color: #fff;">{{ $roomType->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                Max: {{ $roomType->total_rooms }} rooms &bull; Base: ${{ number_format($roomType->base_rate, 2) }}
                            </div>
                        </td>
                        @foreach($dates as $d)
                            @php
                                $dateStr = $d['dateString'];
                                $inv = $grid[$roomType->id][$dateStr] ?? null;
                                $available = $inv ? $inv->available : 0;
                                $rate = $inv ? $inv->rate : $roomType->base_rate;
                                $invId = $inv ? $inv->id : '';

                                $bgClass = 'background: rgba(16, 185, 129, 0.18); border-color: rgba(16, 185, 129, 0.35); color: #34d399;';
                                if ($available == 0) {
                                    $bgClass = 'background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.35); color: #f87171;';
                                } elseif ($available <= 2) {
                                    $bgClass = 'background: rgba(245, 158, 11, 0.18); border-color: rgba(245, 158, 11, 0.35); color: #fbbf24;';
                                }
                            @endphp
                            <td style="padding: 0.35rem; border-right: 1px solid rgba(255,255,255,0.03);">
                                @if($inv)
                                    <div class="inv-cell"
                                         data-id="{{ $inv->id }}"
                                         data-room-name="{{ $roomType->name }}"
                                         data-date="{{ $dateStr }}"
                                         data-available="{{ $available }}"
                                         data-rate="{{ $rate }}"
                                         style="padding: 0.45rem 0.2rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; transition: all 0.15s ease; {{ $bgClass }}">
                                        <div class="cell-avail" style="font-size: 1rem; font-weight: 800; line-height: 1;">{{ $available }}</div>
                                        <div class="cell-rate" style="font-size: 0.68rem; margin-top: 0.2rem; opacity: 0.85;">${{ number_format($rate, 0) }}</div>
                                    </div>
                                @else
                                    <div style="font-size: 0.75rem; color: var(--text-muted); opacity: 0.4;">&mdash;</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($dates) + 1 }}" style="padding: 2rem; color: var(--text-muted); text-align: center;">
                            No room types or inventory seeded. Run <code>php artisan db:seed</code>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Inventory Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="modalRoomName">Edit Inventory</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;" id="modalDateLabel">Date: </p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal()">&times;</button>
        </div>

        <form id="editForm">
            <input type="hidden" id="editInventoryId">

            <div class="form-group">
                <label class="form-label" for="editAvailable">Available Rooms</label>
                <input type="number" id="editAvailable" class="form-control" min="0" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="editRate">Nightly Rate ($)</label>
                <input type="number" id="editRate" class="form-control" step="0.01" min="0" required>
            </div>

            <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; color: #93c5fd; margin-bottom: 1.25rem;">
                ⚡ Saving will update inventory and immediately push changes to all connected OTA channels.
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save & Sync</button>
            </div>
        </form>
    </div>
</div>

<div id="toast" style="position: fixed; bottom: 2rem; right: 2rem; background: #1e293b; color: #fff; border: 1px solid #334155; padding: 0.85rem 1.25rem; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: none; align-items: center; gap: 0.5rem; z-index: 200; font-size: 0.88rem;">
    <span id="toastIcon">✅</span>
    <span id="toastMsg">Updated successfully!</span>
</div>

@push('scripts')
<script>
    let activeCell = null;

    document.querySelectorAll('.inv-cell').forEach(cell => {
        cell.addEventListener('click', () => {
            activeCell = cell;
            document.getElementById('editInventoryId').value = cell.dataset.id;
            document.getElementById('modalRoomName').textContent = cell.dataset.roomName;
            document.getElementById('modalDateLabel').textContent = 'Date: ' + cell.dataset.date;
            document.getElementById('editAvailable').value = cell.dataset.available;
            document.getElementById('editRate').value = parseFloat(cell.dataset.rate).toFixed(2);
            document.getElementById('editModal').classList.add('active');
        });
    });

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
        activeCell = null;
    }

    document.getElementById('editModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('editModal')) closeModal();
    });

    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editInventoryId').value;
        const available = parseInt(document.getElementById('editAvailable').value, 10);
        const rate = parseFloat(document.getElementById('editRate').value);
        const btn = document.getElementById('saveBtn');

        btn.disabled = true;
        btn.textContent = 'Syncing...';

        try {
            const res = await fetch(`/api/inventory/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ available, rate })
            });

            if (!res.ok) {
                const errData = await res.json();
                throw new Error(errData.message || 'Failed to update inventory');
            }

            const updated = await res.json();

            // Update cell in-place
            if (activeCell) {
                activeCell.dataset.available = updated.available;
                activeCell.dataset.rate = updated.rate;
                activeCell.querySelector('.cell-avail').textContent = updated.available;
                activeCell.querySelector('.cell-rate').textContent = '$' + Math.round(updated.rate);

                // Update styling
                if (updated.available == 0) {
                    activeCell.style.background = 'rgba(239, 68, 68, 0.18)';
                    activeCell.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                    activeCell.style.color = '#f87171';
                } else if (updated.available <= 2) {
                    activeCell.style.background = 'rgba(245, 158, 11, 0.18)';
                    activeCell.style.borderColor = 'rgba(245, 158, 11, 0.35)';
                    activeCell.style.color = '#fbbf24';
                } else {
                    activeCell.style.background = 'rgba(16, 185, 129, 0.18)';
                    activeCell.style.borderColor = 'rgba(16, 185, 129, 0.35)';
                    activeCell.style.color = '#34d399';
                }
            }

            showToast('✅ Inventory updated & outbound push dispatched to active channels!');
            closeModal();
        } catch (err) {
            alert('Error updating inventory: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save & Sync';
        }
    });

    function showToast(msg) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        toast.style.display = 'flex';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3500);
    }
</script>
@endpush
@endsection
