-- Atomically apply an ENTER event to live Redis state.
-- KEYS[1] = queue_tail, KEYS[2] = last_event_at, KEYS[3] = arrival_rate_per_min
-- ARGV[1] = last_event_at_ms, ARGV[2] = arrival_rate_per_min

redis.call('INCR', KEYS[1])
redis.call('SET', KEYS[2], ARGV[1])
redis.call('SET', KEYS[3], ARGV[2])

return redis.call('GET', KEYS[1])
