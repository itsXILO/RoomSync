# RoomSync — Technical Interview Guide & Architecture Cheatsheet

> **Purpose:** This document is designed to help you confidently present, explain, and defend **RoomSync** during technical system design and backend engineering interviews. It translates the project's technical implementations into clear, high-impact concepts and interview answers.

---

## 1. The 30-Second Elevator Pitch

> *"RoomSync is a distributed hotel channel manager built with Laravel, MySQL, and Docker. It solves the classic multi-channel overbooking problem where multiple online travel agencies (like Expedia or Booking.com) sell the exact same room inventory simultaneously.
> 
> By combining **atomic row-level database locks (`SELECT ... FOR UPDATE`)**, **asynchronous queues with exponential backoff**, and the **Adapter Pattern** for heterogeneous protocols (JSON and XML), RoomSync guarantees **zero double-bookings** under high concurrency and isolates channel-specific quirks from core business logic."*

---

## 2. The Core Problem: Distributed Inventory & Race Conditions

### The Real-World Scenario
Imagine a boutique hotel with only **1 Deluxe Suite left** for New Year's Eve:
- Channel A (Alpha OTA) and Channel B (Beta Travel) both display availability: **1 room**.
- At **00:00:00.100**, User A clicks "Book" on Channel A.
- At **00:00:00.105** (5 milliseconds later), User B clicks "Book" on Channel B.
- Both requests hit the hotel backend almost simultaneously.

### The Naive Implementation (Double Booking Bug)
```php
// ❌ NAIVE CODE: Vulnerable to race conditions
$inventory = Inventory::where('date', $date)->first();
if ($inventory->available_rooms > 0) {
    // Both requests read available_rooms = 1 at the same time!
    Booking::create([...]);
    $inventory->decrement('available_rooms'); // Both decrement, dropping to 0 or -1!
}
```
**Outcome:** Both guests arrive at the hotel. One guest has no room. The hotel pays penalties, damages its reputation, and faces manual relocation costs.

### The RoomSync Solution (Atomic Pessimistic Locking)
```php
// ✅ ROOMSYNC CODE: Fully serialized and ACID-compliant
DB::transaction(function () use ($payload) {
    $inventory = Inventory::where('date', $date)
        ->lockForUpdate() // 🔒 Holds exclusive row lock until transaction commits
        ->first();

    if ($inventory->available_rooms < $requestedRooms) {
        throw new RoomUnavailableException("No rooms remaining");
    }

    $booking = Booking::create([...]);
    $inventory->decrement('available_rooms', $requestedRooms);
    
    // Outbound sync is dispatched asynchronously
    InventorySyncService::syncAvailabilityToChannels($inventory);
    
    return $booking;
});
```

---

## 3. Architecture & System Design Overview

```
                                      ┌─────────────────────────────────┐
                                      │        Inbound Webhooks         │
                                      │ (Alpha JSON / Beta XML / Gamma) │
                                      └────────────────┬────────────────┘
                                                       │
                                                       ▼
                                      ┌─────────────────────────────────┐
                                      │    ChannelAdapterFactory        │
                                      │  (Normalizes payload to DTO)    │
                                      └────────────────┬────────────────┘
                                                       │
                                                       ▼
                                      ┌─────────────────────────────────┐
                                      │   Atomic DB Transaction (ACID)  │
                                      │  - SELECT ... FOR UPDATE        │
                                      │  - Decrement inventory          │
                                      │  - Insert Booking record        │
                                      └────────────────┬────────────────┘
                                                       │
                                                       ▼
                                      ┌─────────────────────────────────┐
                                      │   Outbound Event Dispatch       │
                                      │  (InventorySyncService)         │
                                      └────────────────┬────────────────┘
                                                       │
                                                       ▼
                                      ┌─────────────────────────────────┐
                                      │   Database Queue Worker         │
                                      │  (PushInventoryToChannel Jobs)  │
                                      │  - 3 Retries                    │
                                      │  - Exponential Backoff          │
                                      └───────┬─────────────────┬───────┘
                                              │                 │
                         HTTP POST (JSON)     ▼                 ▼     HTTP POST (XML)
                                       ┌──────────────┐ ┌──────────────┐
                                       │  Alpha OTA   │ │ Beta Travel  │
                                       │ (Mock Server)│ │(Mock Server) │
                                       └──────────────┘ └──────────────┘
```

---

## 4. Key Design Patterns & Engineering Decisions

| Pattern / Concept | Where It Is Used | Why We Used It (Interview Justification) |
|---|---|---|
| **Adapter Pattern** | [`ChannelAdapters/`](file:///home/rajath/Projects/RoomSync/app/Services/ChannelAdapters) (`JsonAdapter.php`, `XmlAdapter.php`) | **Open/Closed Principle:** External OTAs have wildly different APIs (REST/JSON, SOAP/XML). By mapping external payloads into a unified internal format, our controllers never know or care whether a booking originated as XML or JSON. Adding a 4th channel requires zero edits to existing controllers. |
| **Pessimistic Locking** | [`BookingController.php`](file:///home/rajath/Projects/RoomSync/app/Http/Controllers/Api/BookingController.php#L63) (`lockForUpdate()`) | Under high contention for a scarce physical asset (rooms), pessimistic locking guarantees strict serialization at the database layer without relying on external lock brokers. |
| **Idempotency** | Database constraint: `unique(['channel_id', 'channel_booking_ref'])` | In distributed systems, webhooks often retry upon network timeouts. If Channel A sends the same booking webhook twice, the unique constraint catches it, returning `HTTP 200/409` without double-decrementing inventory. |
| **Eventual Consistency via Queues** | [`PushInventoryToChannel.php`](file:///home/rajath/Projects/RoomSync/app/Jobs/PushInventoryToChannel.php) | An inbound booking should respond in **<50ms**. If we made synchronous HTTP calls to 5 different OTAs during the booking transaction, our endpoint would be slow, fragile, and fail if any external OTA timed out. Queues decouple inbound confirmation from outbound propagation. |
| **Exponential Backoff** | `$backoff = [5, 30, 120];` in Queue Job | If an external OTA is experiencing high traffic or temporary downtime, immediate retries cause "thundering herd" problems. Exponential backoff gives the external server breathing room to recover. |

---

## 5. Common Interview Questions & How to Answer Them

### Q1: "Why did you use Pessimistic Locking instead of Optimistic Locking (versioning)?"
> **Answer:** 
> *"Optimistic locking (e.g. using a `version` column) works well when read traffic vastly exceeds write traffic and collisions are rare. However, for flash sales or high-demand dates (like concert weekends or New Year's), contention on the last few rooms is extremely high. 
> 
> With optimistic locking, 9 out of 10 concurrent requests would fail their version check and force retries, creating unnecessary CPU and database thrashing. Pessimistic locking (`lockForUpdate`) queues concurrent transactions cleanly at the row level; each transaction evaluates the actual remaining capacity in order, making the first successful and rejecting subsequent ones immediately."*

### Q2: "Why not use Redis for inventory counters instead of MySQL?"
> **Answer:** 
> *"Redis with `DECR` or Lua scripts is exceptionally fast, but it introduces a dual-write distributed data problem: how to keep Redis in sync with the relational database that holds financial records, customer details, and bookings. 
> 
> If Redis decrements successfully but the database transaction fails due to a constraint violation or power outage, inventory and bookings become inconsistent without complex two-phase commit (2PC) or reconciliation jobs. For a mini channel manager managing boutique hotels, an ACID-compliant relational transaction guarantees 100% consistency with single-digit millisecond latency."*

### Q3: "What happens if an external OTA is completely down?"
> **Answer:** 
> *"RoomSync isolates outbound syncs in background jobs with a dedicated queue worker:
> 1. The guest's booking is confirmed immediately without blocking.
> 2. The sync job attempts to reach the OTA up to 3 times with exponential backoff (5s, 30s, 120s).
> 3. Each attempt and error response is logged in the `sync_logs` table.
> 4. If all retries fail, the job lands in Laravel's failed jobs table, and the hotel manager can inspect the payload and trigger a manual retry or full channel resync directly from the Channels dashboard."*

### Q4: "How does the system scale if you have 10,000 hotels instead of 1?"
> **Answer:** 
> *"Several architectural evolutions would take place:
> 1. **Horizontal Queue Scaling:** Move queues from the database driver to Redis or Amazon SQS, running dedicated worker pools per tier.
> 2. **Database Sharding / Partitioning:** The `inventory` table is naturally partitionable by `hotel_id` or date ranges. Since lock contention only occurs on the same hotel and room type, different hotels never block each other.
> 3. **Read-Replicas & Caching:** Calendar read views can be cached with short TTLs or Redis hashes and invalidated on write, keeping the primary database dedicated to booking transactions."*

---

## 6. How to Demo This Project in an Interview

If given the opportunity to share your screen or demonstrate RoomSync live:

1. **Show the Race Test Script (`scripts/race-test.sh`)**:
   - Explain: *"Here is a shell script that sets up 1 room remaining and launches 10 background curl requests in a single millisecond."*
   - Run it: `bash scripts/race-test.sh`
   - Point out the terminal output: Exactly **1 HTTP 201 Created** and **9 HTTP 409 Conflict**.
2. **Show the Web Simulator (`http://localhost:8080/simulator`)**:
   - Click the **"⚡ Fire 5 Simultaneous Bookings"** button.
   - Show how the UI visually renders the race test results and displays the concurrent responses.
3. **Show the Sync Logs (`http://localhost:8080/logs`)**:
   - Click **"View Payload"** on both an Alpha OTA log (JSON) and a Beta Travel log (XML).
   - Explain how the Adapter Pattern formatted each payload differently according to channel requirements.
4. **Show Automated Test Suite**:
   - Run `./vendor/bin/sail test` or `php artisan test`.
   - Show **30 tests passing with 122 assertions**, covering capacity, atomicity, idempotency, authentication, and XML parsing.

---

## 7. Key Code Files for Quick Review

If asked to open specific code files during an interview:

- [BookingController.php](file:///home/rajath/Projects/RoomSync/app/Http/Controllers/Api/BookingController.php): Contains atomic transaction logic, `lockForUpdate()`, and error handling.
- [ChannelAdapterFactory.php](file:///home/rajath/Projects/RoomSync/app/Services/ChannelAdapters/ChannelAdapterFactory.php): Implements factory and adapter selection.
- [PushInventoryToChannel.php](file:///home/rajath/Projects/RoomSync/app/Jobs/PushInventoryToChannel.php): Demonstrates queue management, retries, and audit logging.
- [race-test.sh](file:///home/rajath/Projects/RoomSync/scripts/race-test.sh): Practical demonstration of concurrency testing with curl.
- [BookingWebhookTest.php](file:///home/rajath/Projects/RoomSync/tests/Feature/BookingWebhookTest.php): Test cases verifying capacity limits, duplicate payloads, and cancellations.
