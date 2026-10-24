@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">OTA Booking Simulator & Concurrency Lab</h1>
        <p class="page-subtitle">Emulate inbound OTA booking webhooks and test atomic concurrency protection.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; align-items: start;">

    <!-- Left Column: Single Booking Simulator -->
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>🏨</span> Manual Booking Dispatcher
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Dispatches an authenticated webhook payload (JSON or XML) to <code>/api/webhooks/{channel}/bookings</code>.
        </p>

        <form id="singleBookingForm">
            <div class="form-group">
                <label class="form-label" for="channelSelect">Originating Channel (OTA)</label>
                <select id="channelSelect" class="form-control" required>
                    @foreach($channels as $channel)
                        <option value="{{ $channel->id }}"
                                data-format="{{ $channel->format }}"
                                data-key="{{ $channel->api_key }}"
                                data-name="{{ $channel->name }}">
                            {{ $channel->name }} ({{ strtoupper($channel->format) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="roomTypeSelect">Room Type</label>
                <select id="roomTypeSelect" class="form-control" required>
                    @foreach($roomTypes as $rt)
                        <option value="{{ $rt->id }}">{{ $rt->name }} (Max: {{ $rt->total_rooms }} rooms, ${{ number_format($rt->base_rate, 2) }}/night)</option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="checkIn">Check-In Date</label>
                    <input type="date" id="checkIn" class="form-control" value="{{ $today }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="checkOut">Check-Out Date</label>
                    <input type="date" id="checkOut" class="form-control" value="{{ $tomorrow }}" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="guestName">Guest Name</label>
                    <input type="text" id="guestName" class="form-control" value="M. Gustave H." required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="roomsCount">Rooms</label>
                    <input type="number" id="roomsCount" class="form-control" value="1" min="1" max="10" required>
                </div>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label class="form-label" for="externalRef" style="margin-bottom: 0;">External Booking Ref</label>
                    <button type="button" onclick="randomizeRef()" style="background: none; border: none; color: #60a5fa; font-size: 0.75rem; cursor: pointer; text-decoration: underline;">
                        Generate New Ref
                    </button>
                </div>
                <input type="text" id="externalRef" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary" id="sendBtn" style="width: 100%; padding: 0.75rem; font-size: 1rem;">
                Send Booking Webhook
            </button>
        </form>

        <!-- Response Container -->
        <div id="bookingResultContainer" style="display: none; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">HTTP Response:</span>
                <span id="responseStatusBadge" style="font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px;"></span>
            </div>
            <pre id="responseJson" style="background: #090d16; border: 1px solid var(--border); border-radius: 6px; padding: 0.85rem; font-size: 0.8rem; color: #cbd5e1; overflow-x: auto; max-height: 240px;"></pre>
        </div>
    </div>

    <!-- Right Column: Race Condition Demo Lab -->
    <div class="card" style="border-color: rgba(139, 92, 246, 0.4); background: linear-gradient(180deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; color: #e2e8f0;">
                <span>⚡</span> Race Condition Lab
            </h2>
            <span style="background: rgba(139, 92, 246, 0.2); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.4); font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; text-transform: uppercase;">
                Row-Level Locking
            </span>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.45;">
            Tests database concurrency by firing <strong>5 simultaneous booking requests</strong> for the <strong>last remaining room</strong>. With atomic <code>lockForUpdate()</code>, exactly <strong>1</strong> booking will be confirmed (201) and <strong>4</strong> will be rejected (409) without overbooking.
        </p>

        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.6rem;">Target Test Date & Room:</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label" style="font-size: 0.78rem;">Test Date</label>
                    <input type="date" id="raceDate" class="form-control" value="{{ $tomorrow }}">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.78rem;">Room Type</label>
                    <select id="raceRoomType" class="form-control">
                        @foreach($roomTypes as $rt)
                            <option value="{{ $rt->id }}" data-name="{{ $rt->name }}">{{ $rt->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <button type="button" id="fireRaceBtn" class="btn btn-danger" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35);">
            ⚡ Fire 5 Simultaneous Bookings For 1 Room
        </button>

        <!-- Race Results Display -->
        <div id="raceResultsArea" style="display: none; margin-top: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
                <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.75rem; text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 800; color: #34d399;" id="statConfirmed">0</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Confirmed (201)</div>
                </div>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 0.75rem; text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 800; color: #f87171;" id="statRejected">0</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Rejected (409)</div>
                </div>
                <div style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 0.75rem; text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 800; color: #60a5fa;" id="statDoubleBookings">0</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Double Bookings</div>
                </div>
            </div>

            <div id="raceRequestsList" style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8rem; max-height: 250px; overflow-y: auto;">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function randomizeRef() {
        const rand = Math.floor(1000 + Math.random() * 9000);
        document.getElementById('externalRef').value = 'OTA-' + rand;
    }
    randomizeRef();

    // 1. Single Booking Handler
    document.getElementById('singleBookingForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        sendBtn.textContent = 'Transmitting to Webhook...';

        const channelSelect = document.getElementById('channelSelect');
        const channelId = channelSelect.value;
        const channelOpt = channelSelect.options[channelSelect.selectedIndex];
        const format = channelOpt.dataset.format;
        const apiKey = channelOpt.dataset.key;

        const roomTypeId = parseInt(document.getElementById('roomTypeSelect').value, 10);
        const checkIn = document.getElementById('checkIn').value;
        const checkOut = document.getElementById('checkOut').value;
        const guestName = document.getElementById('guestName').value;
        const rooms = parseInt(document.getElementById('roomsCount').value, 10);
        const externalRef = document.getElementById('externalRef').value;

        let bodyPayload = '';
        let contentType = 'application/json';

        if (format === 'xml') {
            contentType = 'application/xml';
            bodyPayload = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n' +
`<booking>
    <external_ref>${externalRef}</external_ref>
    <room_type_id>${roomTypeId}</room_type_id>
    <guest_name>${guestName}</guest_name>
    <check_in>${checkIn}</check_in>
    <check_out>${checkOut}</check_out>
    <rooms>${rooms}</rooms>
</booking>`;
        } else {
            contentType = 'application/json';
            bodyPayload = JSON.stringify({
                external_ref: externalRef,
                room_type_id: roomTypeId,
                guest_name: guestName,
                check_in: checkIn,
                check_out: checkOut,
                rooms: rooms
            });
        }

        try {
            const res = await fetch(`/api/webhooks/${channelId}/bookings`, {
                method: 'POST',
                headers: {
                    'Content-Type': contentType,
                    'X-API-Key': apiKey,
                    'Accept': 'application/json'
                },
                body: bodyPayload
            });

            const data = await res.json();
            const container = document.getElementById('bookingResultContainer');
            const badge = document.getElementById('responseStatusBadge');
            const pre = document.getElementById('responseJson');

            container.style.display = 'block';
            badge.textContent = `HTTP ${res.status} ${res.status === 201 ? 'CONFIRMED' : (res.status === 409 ? 'REJECTED' : 'ERROR')}`;

            if (res.status === 201) {
                badge.style.background = 'rgba(16, 185, 129, 0.2)';
                badge.style.color = '#34d399';
            } else {
                badge.style.background = 'rgba(239, 68, 68, 0.2)';
                badge.style.color = '#f87171';
            }

            pre.textContent = JSON.stringify(data, null, 2);
            randomizeRef();
        } catch (err) {
            alert('Failed to send webhook: ' + err.message);
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Booking Webhook';
        }
    });

    // 2. Race Condition Handler
    document.getElementById('fireRaceBtn').addEventListener('click', async () => {
        const btn = document.getElementById('fireRaceBtn');
        const channelSelect = document.getElementById('channelSelect');
        const channelId = channelSelect.value;
        const channelOpt = channelSelect.options[channelSelect.selectedIndex];
        const apiKey = channelOpt.dataset.key;

        const roomTypeId = parseInt(document.getElementById('raceRoomType').value, 10);
        const checkIn = document.getElementById('raceDate').value;

        // Calculate checkout as checkin + 1 day
        const cin = new Date(checkIn);
        cin.setDate(cin.getDate() + 1);
        const checkOut = cin.toISOString().split('T')[0];

        btn.disabled = true;
        btn.textContent = 'Executing 5 Concurrent Webhook Requests...';

        const resultsArea = document.getElementById('raceResultsArea');
        const list = document.getElementById('raceRequestsList');
        resultsArea.style.display = 'block';
        list.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 0.5rem;">Transmitting parallel HTTP requests via Promise.all()...</div>';

        const batchId = Math.floor(1000 + Math.random() * 9000);
        const requests = [];

        for (let i = 1; i <= 5; i++) {
            const externalRef = `RACE-${batchId}-00${i}`;
            const req = fetch(`/api/webhooks/${channelId}/bookings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': apiKey,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    external_ref: externalRef,
                    room_type_id: roomTypeId,
                    guest_name: `Concurrent Tester #${i}`,
                    check_in: checkIn,
                    check_out: checkOut,
                    rooms: 1
                })
            }).then(async res => {
                const json = await res.json();
                return { status: res.status, ref: externalRef, data: json, clientNum: i };
            }).catch(err => {
                return { status: 0, ref: externalRef, error: err.message, clientNum: i };
            });

            requests.push(req);
        }

        const outcomes = await Promise.all(requests);

        let confirmedCount = 0;
        let rejectedCount = 0;
        list.innerHTML = '';

        outcomes.forEach(out => {
            const isConfirmed = out.status === 201;
            if (isConfirmed) confirmedCount++;
            else rejectedCount++;

            const item = document.createElement('div');
            item.style.padding = '0.5rem 0.75rem';
            item.style.borderRadius = '6px';
            item.style.display = 'flex';
            item.style.justifyContent = 'space-between';
            item.style.alignItems = 'center';
            item.style.background = isConfirmed ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)';
            item.style.border = isConfirmed ? '1px solid rgba(16, 185, 129, 0.3)' : '1px solid rgba(239, 68, 68, 0.3)';

            item.innerHTML = `
                <div>
                    <span style="font-weight: 700; color: ${isConfirmed ? '#34d399' : '#f87171'};">
                        ${isConfirmed ? '✔ CONFIRMED' : '✖ REJECTED'}
                    </span>
                    <span style="color: var(--text-muted); margin-left: 0.5rem;">${out.ref}</span>
                </div>
                <div style="font-family: monospace; font-size: 0.75rem; color: #cbd5e1;">
                    HTTP ${out.status} ${out.data?.reason ? `(${out.data.reason.substring(0, 32)}...)` : ''}
                </div>
            `;
            list.appendChild(item);
        });

        document.getElementById('statConfirmed').textContent = confirmedCount;
        document.getElementById('statRejected').textContent = rejectedCount;
        document.getElementById('statDoubleBookings').textContent = Math.max(0, confirmedCount - 1);

        btn.disabled = false;
        btn.textContent = '⚡ Fire 5 Simultaneous Bookings For 1 Room';
    });
</script>
@endpush
@endsection
