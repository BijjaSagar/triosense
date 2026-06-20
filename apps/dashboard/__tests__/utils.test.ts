import { describe, expect, it } from 'vitest';
import { formatStatus, isStale } from '@/lib/utils';

describe('formatStatus', () => {
  it('formats snake_case status labels', () => {
    expect(formatStatus('approaching_cutoff')).toBe('APPROACHING CUTOFF');
    expect(formatStatus('open')).toBe('OPEN');
  });
});

describe('isStale', () => {
  it('returns true when timestamp is older than threshold', () => {
    const old = new Date(Date.now() - 10_000).toISOString();
    expect(isStale(old, 5)).toBe(true);
  });

  it('returns false for recent timestamps', () => {
    const recent = new Date().toISOString();
    expect(isStale(recent, 5)).toBe(false);
  });

  it('returns true for null timestamps', () => {
    expect(isStale(null)).toBe(true);
  });
});
