import { cn, formatStatus } from '@/lib/utils';

const STATUS_STYLES: Record<string, string> = {
  open: 'bg-emerald-100 text-emerald-800',
  approaching_cutoff: 'bg-amber-100 text-amber-900',
  cutoff_declared: 'bg-red-100 text-red-800',
  closed: 'bg-gray-200 text-gray-700',
};

interface StatusBadgeProps {
  status: string;
  className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const style = STATUS_STYLES[status] ?? 'bg-muted text-muted-foreground';

  return (
    <span
      className={cn(
        'inline-flex rounded-full px-3 py-1 text-xs font-semibold tracking-wide',
        style,
        className,
      )}
    >
      {formatStatus(status)}
    </span>
  );
}
