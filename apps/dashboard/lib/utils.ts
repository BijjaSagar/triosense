import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

export function formatStatus(status: string): string {
  return status.replace(/_/g, ' ').toUpperCase();
}

export function isStale(asOf: string | null | undefined, thresholdSeconds = 5): boolean {
  if (!asOf) return true;
  const ms = Date.now() - new Date(asOf).getTime();
  return ms > thresholdSeconds * 1000;
}
