import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground shadow hover:bg-primary/90',
                destructive: 'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90',
                outline: 'border border-input bg-background shadow-sm hover:bg-accent hover:text-accent-foreground',
                secondary: 'bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            /*
             * Op een telefoon is elke knop minstens 44 pixels; op een groot
             * scherm blijft alles precies zoals het was.
             *
             * Dat gaat via `min-h-11` met `sm:min-h-0` erachter, en niet door
             * de hoogte zelf te veranderen. Reden: tailwind-merge gooit een
             * `h-*` uit de basis zodra een aanroeper zelf een hoogte meegeeft
             * (`class="h-12"`), en dan zou de mobiele ondergrens stilzwijgend
             * verdwijnen. `min-h-*` is een andere groep en overleeft dat.
             */
            size: {
                default: 'h-9 min-h-11 px-4 py-2 sm:min-h-0',
                sm: 'h-8 min-h-11 rounded-md px-3 text-xs sm:min-h-0',
                lg: 'h-10 min-h-11 rounded-md px-8 sm:min-h-0',
                icon: 'h-9 min-h-11 w-9 min-w-11 sm:min-h-0 sm:min-w-0',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
