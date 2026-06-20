-- Atomically apply FIFO decision outputs for one location.
-- KEYS[1] = status, KEYS[2] = tokens_remaining, KEYS[3] = last_event_at, KEYS[4] = cutoff
-- ARGV[1] = status, ARGV[2] = tokens_remaining, ARGV[3] = last_event_at_ms, ARGV[4] = cutoff or empty string to delete

redis.call('SET', KEYS[1], ARGV[1])
redis.call('SET', KEYS[2], ARGV[2])
redis.call('SET', KEYS[3], ARGV[3])

if ARGV[4] == '' then
    redis.call('DEL', KEYS[4])
else
    redis.call('SET', KEYS[4], ARGV[4])
end

return 1
