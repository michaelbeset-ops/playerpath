import {
    Award,
    Building2,
    Cake,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    Contact,
    CreditCard,
    FileCheck2,
    FileDown,
    Inbox,
    LayoutGrid,
    MapPin,
    Megaphone,
    Palette,
    Receipt,
    Settings,
    Tag,
    UserCog,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-vue-next';

/**
 * De iconennaam van de server omgezet naar een component.
 *
 * De server (MainNavigation, QuickActions) stuurt alleen een naam mee; welk
 * plaatje daarbij hoort is presentatie. Dit staat op één plek omdat er twee
 * balken zijn die hetzelfde menu tekenen - de balk bovenin en de tabbalk
 * onderin in de app. Twee losse lijstjes lopen vroeg of laat uit elkaar, en dan
 * heeft hetzelfde menu-item op je telefoon een ander icoon dan op je laptop.
 */
export const navIconen: Record<string, LucideIcon> = {
    dashboard: LayoutGrid,
    players: Users,
    guardians: Contact,
    groups: UsersRound,
    trainings: CalendarDays,
    calendar: CalendarRange,
    reports: ClipboardList,
    subscriptions: Receipt,
    payments: CreditCard,
    products: Tag,
    exports: FileDown,
    enrollments: Inbox,
    branding: Palette,
    accountability: FileCheck2,
    badges: Award,
    announcements: Megaphone,
    birthdays: Cake,
    business: Building2,
    staff: UserCog,
    locations: MapPin,
    availability: CalendarCheck,
    settings: Settings,
};

export const navIcoon = (naam: string): LucideIcon => navIconen[naam] ?? LayoutGrid;
