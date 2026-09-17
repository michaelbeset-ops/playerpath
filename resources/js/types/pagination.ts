/** De meta bij een lijst die per pagina komt; zie App\Support\Pagination\LoadMore. */
export interface PageMeta {
    page: number;
    perPage: number;
    total: number;
    shown: number;
    hasMore: boolean;
    nextPage: number | null;
}
