import { cn } from '@/lib/utils';
import type { ButtonHTMLAttributes } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'outline' | 'ghost' | 'destructive';
}

export function Button({
  className,
  variant = 'default',
  ...props
}: ButtonProps) {
  return (
    <button
      className={cn(
        'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50',
        variant === 'default' && 'bg-maroon-700 text-white hover:bg-maroon-800',
        variant === 'outline' &&
          'border border-border bg-card hover:bg-muted text-foreground',
        variant === 'ghost' && 'hover:bg-muted text-foreground',
        variant === 'destructive' && 'bg-destructive text-white hover:bg-red-800',
        className,
      )}
      {...props}
    />
  );
}
