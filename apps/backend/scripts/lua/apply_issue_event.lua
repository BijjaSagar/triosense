-- Atomically apply an ISSUE event to live Redis state.
-- KEYS[1] = issued, KEYS[2] = queue_head, KEYS[3] = tokens_remaining
-- KEYS[4] = last_event_at, KEYS[5] = issuance_rate_per_min
-- ARGV[1] = quota, ARGV[2] = last_event_at_ms, ARGV[3] = issuance_rate_per_min

local issued = redis.call('INCR', KEYS[1])
redis.call('INCR', KEYS[2])
local remaining = tonumber(ARGV[1]) - issued
if remaining < 0 then
    remaining = 0
end
redis.call('SET', KEYS[3], tostring(remaining))
redis.call('SET', KEYS[4], ARGV[2])
redis.call('SET', KEYS[5], ARGV[3])

return issued
