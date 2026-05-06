import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-transparent text-sm font-semibold tracking-[-0.01em] shadow-sm transition-[background-color,border-color,color,box-shadow,transform] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] outline-none hover:-translate-y-px disabled:pointer-events-none disabled:translate-y-0 disabled:opacity-50 active:scale-[0.98] [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 focus-visible:ring-ring/30 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
    {
        variants: {
            variant: {
                default: 'border-primary/10 bg-primary text-primary-foreground hover:bg-primary/92',
                destructive:
                    'border-destructive/10 bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40',
                outline: 'border-border bg-background text-foreground shadow-xs hover:border-primary/25 hover:bg-accent',
                secondary: 'border-border bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
                ghost: 'border-transparent bg-transparent shadow-none hover:bg-accent hover:text-accent-foreground hover:shadow-xs',
                link: 'border-transparent bg-transparent text-primary shadow-none underline-offset-4 hover:translate-y-0 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2 has-[>svg]:px-3',
                sm: 'h-9 gap-1.5 rounded-lg px-3 has-[>svg]:px-2.5',
                lg: 'h-12 rounded-2xl px-6 has-[>svg]:px-4',
                icon: 'size-10',
                'icon-sm': 'size-9 rounded-lg',
                'icon-lg': 'size-12 rounded-2xl',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);
export type ButtonVariants = VariantProps<typeof buttonVariants>;
