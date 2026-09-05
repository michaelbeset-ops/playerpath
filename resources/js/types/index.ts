import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    /** De rollen van de ingelogde gebruiker: eigenaar, trainer, ouder of speler. */
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    section?: string;
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface School {
    id: number;
    name: string;
}

/** De huisstijl van de school die bij dit verzoek hoort. */
export interface Branding {
    name: string;
    logo: string | null;
    color: string | null;
    primary: string | null;
    primaryForeground: string | null;
}

export interface SharedData {
    name: string;
    /** Null op de publiek gedeelde spelerskaart: die verraadt geen school. */
    branding: Branding | null;
    auth: Auth;
    /** De actieve school van de ingelogde gebruiker; null als er geen is. */
    school: School | null;
    flash: { status: string | null };
    /** Het hoofdmenu, server-side bepaald op basis van wat je mag. */
    nav: { section: string; title: string; href: string; icon: string }[];
    unreadNotifications: number;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
