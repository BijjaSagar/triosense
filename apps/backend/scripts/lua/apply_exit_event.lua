-- Atomically apply an EXIT event to live Redis state.
-- KEYS[1] = queue_tail, KEYS[2] = queue_head, KEYS[3] = last_event_at
-- ARGV[1] = last_event_at_ms

local tail = tonumber(redis.call('GET', KEYS[1]) or '0')
local head = tonumber(redis.call('GET', KEYS[2]) or '0')

if tail > head then
    redis.call('DECR', KEYS[1])
end

redis.call('SET', KEYS[3], ARGV[1])

return redis.call('GET', KEYS[1])
