/**
 * Een korte regen van confetti over het scherm, één keer.
 *
 * Voor de momenten die er voor een kind toe doen: een nieuw level, de eerste
 * foto op de kaart. Bewust kort (anderhalve seconde) en zonder bibliotheek:
 * een canvas over de pagina dat zichzelf opruimt. Wie "minder beweging" heeft
 * aanstaan krijgt niets - dan is stilte het feestje.
 */
const KLEUREN = ['#22e06b', '#d4af37', '#f1f5f9', '#60a5fa', '#f472b6'];

interface Snipper {
    x: number;
    y: number;
    vx: number;
    vy: number;
    hoek: number;
    draai: number;
    breedte: number;
    hoogte: number;
    kleur: string;
}

export function strooiConfetti(duur = 1600): void {
    if (typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:70';
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = window.innerWidth * dpr;
    canvas.height = window.innerHeight * dpr;
    document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d')!;
    ctx.scale(dpr, dpr);

    const w = window.innerWidth;
    const snippers: Snipper[] = Array.from({ length: 140 }, () => ({
        x: w / 2 + (Math.random() - 0.5) * w * 0.6,
        y: window.innerHeight * 0.35,
        vx: (Math.random() - 0.5) * 14,
        vy: -Math.random() * 14 - 4,
        hoek: Math.random() * Math.PI,
        draai: (Math.random() - 0.5) * 0.3,
        breedte: 6 + Math.random() * 6,
        hoogte: 4 + Math.random() * 4,
        kleur: KLEUREN[Math.floor(Math.random() * KLEUREN.length)],
    }));

    const start = performance.now();

    const stap = (nu: number) => {
        const t = nu - start;
        ctx.clearRect(0, 0, w, window.innerHeight);
        ctx.globalAlpha = Math.max(0, 1 - Math.max(0, t - duur) / 500);

        for (const s of snippers) {
            s.vy += 0.35;
            s.vx *= 0.99;
            s.x += s.vx;
            s.y += s.vy;
            s.hoek += s.draai;

            ctx.save();
            ctx.translate(s.x, s.y);
            ctx.rotate(s.hoek);
            ctx.fillStyle = s.kleur;
            ctx.fillRect(-s.breedte / 2, -s.hoogte / 2, s.breedte, s.hoogte);
            ctx.restore();
        }

        if (t < duur + 500) {
            requestAnimationFrame(stap);
        } else {
            canvas.remove();
        }
    };

    requestAnimationFrame(stap);
}
