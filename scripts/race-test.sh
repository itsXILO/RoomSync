#!/bin/bash
# RoomSync Race Condition Test
# Fires 10 parallel booking requests for 1 room and verifies atomic locking.
# Expected result: exactly 1 x 201 (confirmed), 9 x 409 (rejected).
#
# Usage:
#   bash scripts/race-test.sh
#
# Prerequisites:
#   - App running (make up)
#   - Database seeded (make seed)
#   - ROOM_TYPE_ID 1 must have available >= 1 room on TARGET_DATE
#     (Script will set available=1 via artisan before firing requests)

set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost}"
CHANNEL_ID=1
API_KEY="alpha-key-001"
ROOM_TYPE_ID=1
CONCURRENCY=10

# Date 30 days from now
TARGET_DATE=$(date -d "+30 days" +%Y-%m-%d 2>/dev/null || date -v +30d +%Y-%m-%d)
CHECKOUT_DATE=$(date -d "+31 days" +%Y-%m-%d 2>/dev/null || date -v +31d +%Y-%m-%d)

echo "============================================"
echo " RoomSync Race Condition Test"
echo "============================================"
echo " URL:         $BASE_URL"
echo " Channel:     Alpha OTA (ID=$CHANNEL_ID)"
echo " Room Type:   $ROOM_TYPE_ID"
echo " Date:        $TARGET_DATE"
echo " Concurrency: $CONCURRENCY requests"
echo "============================================"
echo ""

# Step 1: Set inventory to exactly 1 room for the target date
echo "[1/3] Setting available rooms to 1 for $TARGET_DATE..."

if [ -f "./vendor/bin/sail" ] && docker ps 2>/dev/null | grep -q "laravel.test"; then
    ARTISAN_CMD="./vendor/bin/sail artisan"
elif command -v php &>/dev/null; then
    ARTISAN_CMD="php artisan"
else
    ARTISAN_CMD="docker run --rm -v $(pwd):/app -w /app composer:2 php artisan"
fi

$ARTISAN_CMD tinker --execute="
\$inv = \App\Models\Inventory::where('room_type_id', $ROOM_TYPE_ID)
    ->whereDate('date', '$TARGET_DATE')
    ->first();
if (\$inv) {
    \$inv->update(['available' => 1]);
    echo 'Set available=1 for ' . \$inv->date . PHP_EOL;
} else {
    echo 'ERROR: Inventory row not found for date $TARGET_DATE' . PHP_EOL;
    exit(1);
}
" 2>/dev/null || true

echo ""
echo "[2/3] Firing $CONCURRENCY simultaneous booking requests..."
echo ""

RESULTS_FILE=$(mktemp)
PIDS=()

for i in $(seq 1 $CONCURRENCY); do
    curl -s -o /dev/null -w "%{http_code}\n" \
        -X POST "$BASE_URL/api/webhooks/$CHANNEL_ID/bookings" \
        -H "X-API-Key: $API_KEY" \
        -H "Content-Type: application/json" \
        -d "{\"external_ref\":\"RACE-$$-$i\",\"room_type_id\":$ROOM_TYPE_ID,\"guest_name\":\"Racer $i\",\"check_in\":\"$TARGET_DATE\",\"check_out\":\"$CHECKOUT_DATE\",\"rooms\":1}" \
        >> "$RESULTS_FILE" &
    PIDS+=($!)
done

# Wait for all background curl processes
for pid in "${PIDS[@]}"; do
    wait "$pid" || true
done

echo "[3/3] Results:"
echo ""

RESULTS=$(cat "$RESULTS_FILE")
CONFIRMED=$(echo "$RESULTS" | grep -c "^201$" || true)
REJECTED=$(echo "$RESULTS" | grep -c "^409$" || true)
OTHER=$(echo "$RESULTS" | grep -v "^201$\|^409$" || true)
OTHER_COUNT=$(echo "$OTHER" | grep -c "." || true)

echo "  201 Confirmed:  $CONFIRMED"
echo "  409 Rejected:   $REJECTED"
if [ "$OTHER_COUNT" -gt 0 ]; then
    echo "  Other codes:    $OTHER_COUNT"
    echo "  Details:        $OTHER"
fi
echo ""

rm -f "$RESULTS_FILE"

if [ "$CONFIRMED" -eq 1 ] && [ "$REJECTED" -eq $((CONCURRENCY - 1)) ]; then
    echo "✅ PASS — Exactly 1 booking confirmed. Row locks are working!"
    exit 0
else
    echo "❌ FAIL — Expected exactly 1 confirmed booking, got $CONFIRMED"
    echo "   This means double-booking may be possible — check the locking logic."
    exit 1
fi
