Build "RoomSync", a mini channel manager for hotels, similar to a site controller that keeps room inventory in sync across multiple booking sites (OTAs). Stack: PHP 8.3, Laravel 11, MySQL 8, Blade + vanilla JS (jQuery allowed), Docker (use Laravel Sail for dev). Queue driver: database. Keep the code simple and readable, I must be able to explain every file.

CORE PROBLEM: A hotel keeps its room counts in RoomSync. RoomSync pushes availability and rates to every connected channel. When a booking arrives from one channel, RoomSync must reduce inventory atomically and push the new availability to all OTHER channels so no room can be double booked.

DATABASE (migrations + seeders):
- hotels(id, name)
- room_types(id, hotel_id, name, total_rooms, base_rate)
- inventory(id, room_type_id, date, available, rate, unique(room_type_id, date))
- channels(id, hotel_id, name, format ENUM('json','xml'), endpoint_url, api_key, active)
- bookings(id, room_type_id, channel_id, external_ref, guest_name, check_in, check_out, rooms, status ENUM('confirmed','rejected','cancelled'), reason nullable, unique(channel_id, external_ref))
- sync_logs(id, channel_id, direction ENUM('in','out'), event, payload LONGTEXT, status ENUM('success','failed','pending'), attempts, response_code nullable, error nullable, created_at)
- mock_ota_inventory(id, channel_id, room_type_id, date, available, rate) used by the mock OTAs to store what they last received.
Seeder: 1 hotel, 2 room types (Standard total 5, Deluxe total 3), inventory for the next 60 days, 3 channels (Alpha OTA = json, Beta Travel = xml, Gamma Stays = json).

ADAPTER PATTERN:
- interface ChannelAdapter { buildInventoryPayload(array $rows): string; parseBookingPayload(string $raw): array; contentType(): string }
- JsonAdapter and XmlAdapter implement it. The channel's `format` decides which one is used. This shows that every OTA has a different format.

BOOKING WEBHOOK (most important part):
- POST /api/webhooks/{channel}/bookings, authenticated with the channel's api_key in the X-API-Key header, body parsed with that channel's adapter. Fields: external_ref, room_type_id, guest_name, check_in, check_out, rooms.
- Idempotent: if (channel_id, external_ref) already exists, return the original result without changing inventory.
- Inside DB::transaction: select the inventory rows for every night from check_in (inclusive) to check_out (exclusive) with lockForUpdate(), ordered by date to avoid deadlocks. If ANY night has available < rooms, create a booking with status 'rejected' and a reason, return 409. Otherwise decrement every night, create booking 'confirmed', return 201.
- After commit, dispatch a PushInventoryToChannel job for every active channel EXCEPT the source channel (or all channels, your choice, but document it).
- POST /api/webhooks/{channel}/cancellations restores inventory the same way, then pushes updates.
- Log every inbound call in sync_logs.

OUTBOUND SYNC:
- PushInventoryToChannel job: builds the payload with the channel's adapter and POSTs it to the channel's endpoint_url. tries=5, backoff [5,15,30,60]. Write a sync_logs row per attempt with status, attempts, response code, error.
- When the hotel edits availability or rate on the dashboard, dispatch the same jobs for all active channels.

MOCK OTAs (inside the same app):
- POST /mock/{channel}/inventory receives an update (JSON or XML per channel), stores it in mock_ota_inventory, returns 200. Support ?fail=0.3 style failure injection via a config value so I can demo retries.
- GET /mock/{channel}/state shows what that OTA currently believes.

UI (Blade, clean and responsive, works on mobile):
- / : calendar grid, next 30 days by room type, cells show available and rate. Click a cell to edit availability and rate, saving triggers outbound sync.
- /simulator : pick a channel, room type, dates, rooms, and send a booking as that OTA. Also a button "Fire 5 simultaneous bookings for the last room" that triggers the race scenario.
- /logs : table of sync_logs with filters for direction, status, channel. Failed rows show a Retry button.
- /channels : list channels and a side-by-side view of RoomSync's inventory vs what each mock OTA believes, highlighting mismatches.

TESTS:
- PHPUnit feature tests: booking succeeds and decrements all nights, booking over capacity is rejected, multi-night booking where one night is full is rejected entirely, same external_ref twice does not double decrement, cancellation restores inventory, outbound jobs are dispatched to the other channels.
- scripts/race-test.sh: uses curl to fire 10 parallel POSTs for the last available room against the running app, then prints how many returned 201 vs 409. Expected: exactly one 201.

DEVOPS:
- docker-compose with app, nginx, mysql, and a queue worker container.
- .env.example, a Makefile with make up, make seed, make test, make race.
- GitHub Actions workflow that runs the tests on push.

README: architecture diagram (mermaid), the overbooking explanation, how to run, how to demo, design decisions (why row locks, why idempotency, why adapters), limitations (mock OTAs, no auth for the dashboard, no real OTA protocols).

Work step by step: scaffolding and migrations first, then the booking webhook with tests, then outbound sync, then UI. Run the tests after each step.