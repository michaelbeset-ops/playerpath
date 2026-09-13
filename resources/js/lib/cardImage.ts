import type { Kaart } from '@/components/PlayerCardVisual.vue';

/**
 * De spelerskaart als afbeelding, in story-formaat (1080 × 1920).
 *
 * Getekend op een canvas, niet "gefotografeerd" uit de DOM: dat werkt op elke
 * telefoon hetzelfde (iOS Safari struikelt over foreignObject en webfonts) en
 * de uitkomst is altijd een scherpe PNG van dezelfde maat. De opbouw volgt de
 * kaart in de app - foto boven, cijfer groot, zes categorieën voluit, XP
 * onderaan - zodat wat je deelt is wat je in de app ziet.
 *
 * Er staat bewust hetzelfde op als op de kaart en niet meer: voornaam en
 * achternaam zoals de kaart ze toont, positie, cijfers, level en school.
 */
const BREEDTE = 1080;
const HOOGTE = 1920;

const FONT = "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif";

interface Tint {
    donker: string;
    licht: string;
    accent: string;
}

/** Het metaal per level, dezelfde families als het frame van de kaart. */
const TINTEN: Record<string, Tint> = {
    brons: { donker: '#6b3f1d', licht: '#d29a5b', accent: '#e8b87c' },
    zilver: { donker: '#5b6472', licht: '#d9dfe6', accent: '#eef1f4' },
    goud: { donker: '#8a6a17', licht: '#f0d266', accent: '#f6e08a' },
    elite: { donker: '#3b1d8a', licht: '#22e06b', accent: '#7cf5b0' },
    geen: { donker: '#3f4650', licht: '#b5bdc7', accent: '#cfd6de' },
};

const laadAfbeelding = (src: string): Promise<HTMLImageElement | null> =>
    new Promise((ok) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => ok(img);
        img.onerror = () => ok(null);
        img.src = src;
    });

const afgerond = (ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) => {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
};

/** Tekst inkorten tot hij past, met een beletselteken. */
const passend = (ctx: CanvasRenderingContext2D, tekst: string, max: number) => {
    if (ctx.measureText(tekst).width <= max) {
        return tekst;
    }

    let kort = tekst;

    while (kort.length > 1 && ctx.measureText(kort + '…').width > max) {
        kort = kort.slice(0, -1);
    }

    return kort.trimEnd() + '…';
};

export interface KaartAfbeeldingOpties {
    /** De regel onderaan, bijvoorbeeld de deel-link. */
    voet?: string | null;
    /** Een schuine sticker over de hoek: "NIEUW LEVEL", "+5 GEGROEID". */
    sticker?: string | null;
}

/**
 * De sticker die bij de kaart hoort op grond van het laatste rapport: de
 * gemiddelde groei over de categorieën, vanaf drie punten. Minder is ruis.
 */
export function groeiSticker(card: Kaart): string | null {
    const deltas = card.categories.map((c) => c.delta ?? 0);

    if (!deltas.some((d) => d !== 0)) {
        return null;
    }

    const gemiddeld = Math.round(deltas.reduce((a, b) => a + b, 0) / deltas.length);

    return gemiddeld >= 3 ? `+${gemiddeld} GEGROEID` : null;
}

export async function renderKaartStory(card: Kaart, opties: KaartAfbeeldingOpties = {}): Promise<Blob> {
    // Zonder dit tekent Safari de eerste keer met de terugvalfont.
    try {
        await document.fonts?.ready;
    } catch {
        // Geen Font Loading API: dan gewoon tekenen.
    }

    const canvas = document.createElement('canvas');
    canvas.width = BREEDTE;
    canvas.height = HOOGTE;
    const ctx = canvas.getContext('2d')!;

    const tier = card.overall === null ? 'geen' : card.level.key;
    const tint = TINTEN[tier] ?? TINTEN.geen;

    // --- Achtergrond: de donkere kant van het merk, met een zachte gloed in de levelkleur ---
    const achtergrond = ctx.createLinearGradient(0, 0, 0, HOOGTE);
    achtergrond.addColorStop(0, '#0d0f12');
    achtergrond.addColorStop(1, '#111a2e');
    ctx.fillStyle = achtergrond;
    ctx.fillRect(0, 0, BREEDTE, HOOGTE);

    const gloed = ctx.createRadialGradient(BREEDTE / 2, 900, 80, BREEDTE / 2, 900, 900);
    gloed.addColorStop(0, tint.licht + '55');
    gloed.addColorStop(1, 'transparent');
    ctx.fillStyle = gloed;
    ctx.fillRect(0, 0, BREEDTE, HOOGTE);

    // --- Kop: het logo van de school (of de naam), met PlayerPath klein eronder ---
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'center';

    const logo = card.school_logo ? await laadAfbeelding(card.school_logo) : null;

    if (logo) {
        // Past in een vak van 420 × 120, in verhouding.
        const schaal = Math.min(420 / logo.width, 120 / logo.height, 1);
        const lw = logo.width * schaal;
        const lh = logo.height * schaal;
        ctx.drawImage(logo, (BREEDTE - lw) / 2, 70 + (120 - lh) / 2, lw, lh);

        if (card.school) {
            ctx.fillStyle = '#99a3b3';
            ctx.font = `500 30px ${FONT}`;
            ctx.fillText(passend(ctx, card.school, 900), BREEDTE / 2, 232);
        }
    } else {
        ctx.fillStyle = '#f1f5f9';
        ctx.font = `800 44px ${FONT}`;
        ctx.fillText(card.school ? passend(ctx, card.school, 900) : 'PlayerPath', BREEDTE / 2, 150);

        if (card.school) {
            ctx.fillStyle = '#99a3b3';
            ctx.font = `500 30px ${FONT}`;
            ctx.fillText('Spelerskaart', BREEDTE / 2, 205);
        }
    }

    // --- Het frame: metaal met een facet, dan de donkere binnenkant ---
    const kx = 100;
    const ky = 270;
    const kw = BREEDTE - 200;
    const kh = 1430;
    const rand = 16;

    const metaal = ctx.createLinearGradient(kx, ky, kx + kw, ky + kh);
    metaal.addColorStop(0, tint.licht);
    metaal.addColorStop(0.35, tint.donker);
    metaal.addColorStop(0.55, tint.accent);
    metaal.addColorStop(0.8, tint.donker);
    metaal.addColorStop(1, tint.licht);

    ctx.save();
    ctx.shadowColor = tint.licht + '66';
    ctx.shadowBlur = 60;
    afgerond(ctx, kx, ky, kw, kh, 48);
    ctx.fillStyle = metaal;
    ctx.fill();
    ctx.restore();

    const bx = kx + rand;
    const by = ky + rand;
    const bw = kw - rand * 2;
    const bh = kh - rand * 2;

    afgerond(ctx, bx, by, bw, bh, 36);
    ctx.fillStyle = '#0a0f1c';
    ctx.fill();

    // --- Foto (bovenste helft), met een fade naar de body ---
    const fotoH = 640;
    ctx.save();
    afgerond(ctx, bx, by, bw, bh, 36);
    ctx.clip();

    const foto = card.photo ? await laadAfbeelding(card.photo) : null;

    if (foto) {
        // Cover: vul het vak, snij wat overblijft af rond het midden.
        const schaal = Math.max(bw / foto.width, fotoH / foto.height);
        const fw = foto.width * schaal;
        const fh = foto.height * schaal;
        ctx.drawImage(foto, bx + (bw - fw) / 2, by + (fotoH - fh) / 2, fw, fh);
    } else {
        ctx.fillStyle = '#131c30';
        ctx.fillRect(bx, by, bw, fotoH);
        // Silhouet: hoofd en schouders.
        ctx.fillStyle = '#25282d';
        ctx.beginPath();
        ctx.arc(bx + bw / 2, by + 270, 110, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.ellipse(bx + bw / 2, by + 560, 260, 170, 0, Math.PI, 0);
        ctx.fill();
    }

    const fade = ctx.createLinearGradient(0, by + fotoH - 260, 0, by + fotoH);
    fade.addColorStop(0, 'rgba(10, 15, 28, 0)');
    fade.addColorStop(1, 'rgba(10, 15, 28, 1)');
    ctx.fillStyle = fade;
    ctx.fillRect(bx, by + fotoH - 260, bw, 260);
    ctx.restore();

    // --- Overall, linksboven op de foto ---
    ctx.textAlign = 'left';
    ctx.fillStyle = '#f1f5f9';
    ctx.font = `800 150px ${FONT}`;
    ctx.fillText(card.overall === null ? '-' : String(card.overall), bx + 44, by + 170);

    ctx.fillStyle = tint.accent;
    ctx.font = `700 34px ${FONT}`;
    ctx.fillText(card.position.toUpperCase(), bx + 48, by + 222);

    if (card.age_category) {
        ctx.fillStyle = '#99a3b3';
        ctx.font = `600 30px ${FONT}`;
        ctx.fillText(card.age_category.key, bx + 48, by + 266);
    }

    // Het rugnummer, linksonder op de foto, in het metaal van het level.
    if (card.shirt_number) {
        ctx.textAlign = 'left';
        ctx.fillStyle = tint.accent;
        ctx.font = `900 120px ${FONT}`;
        ctx.shadowColor = 'rgba(0,0,0,0.6)';
        ctx.shadowBlur = 24;
        ctx.fillText(String(card.shirt_number), bx + 48, by + fotoH - 80);
        ctx.shadowBlur = 0;
    }

    // --- Naam ---
    let y = by + fotoH - 40;
    ctx.fillStyle = '#99a3b3';
    ctx.font = `600 36px ${FONT}`;
    ctx.fillText(passend(ctx, card.first_name, bw - 96), bx + 48, y);
    y += 74;
    ctx.fillStyle = '#f1f5f9';
    ctx.font = `800 70px ${FONT}`;
    ctx.fillText(passend(ctx, card.last_name || card.first_name, bw - 96), bx + 48, y);

    // --- Zes categorieën, twee kolommen ---
    y += 70;
    const kolomB = (bw - 96 - 40) / 2;
    const rijH = 118;

    if (card.overall !== null) {
        card.categories.forEach((c, i) => {
            const kolom = i % 2;
            const rij = Math.floor(i / 2);
            const x = bx + 48 + kolom * (kolomB + 40);
            const ry = y + rij * rijH;

            ctx.textAlign = 'left';
            ctx.fillStyle = '#99a3b3';
            ctx.font = `500 30px ${FONT}`;
            ctx.fillText(passend(ctx, c.label, kolomB - 90), x, ry);

            ctx.textAlign = 'right';
            ctx.fillStyle = '#f1f5f9';
            ctx.font = `800 40px ${FONT}`;
            ctx.fillText(c.rating === null ? '-' : String(c.rating), x + kolomB, ry + 4);

            afgerond(ctx, x, ry + 22, kolomB, 12, 6);
            ctx.fillStyle = '#25282d';
            ctx.fill();

            if (c.rating !== null) {
                afgerond(ctx, x, ry + 22, Math.max(12, (kolomB * c.rating) / 100), 12, 6);
                ctx.fillStyle = tint.accent;
                ctx.fill();
            }
        });

        y += rijH * 3 + 10;
    } else {
        ctx.textAlign = 'left';
        ctx.fillStyle = '#99a3b3';
        ctx.font = `500 32px ${FONT}`;
        ctx.fillText('Zodra het eerste rapport binnen is, komt deze kaart tot leven.', bx + 48, y + 20);
        y += 130;
    }

    // --- XP ---
    ctx.textAlign = 'left';
    ctx.fillStyle = '#99a3b3';
    ctx.font = `600 28px ${FONT}`;
    ctx.fillText('XP', bx + 48, y);
    ctx.textAlign = 'right';
    ctx.fillStyle = '#f1f5f9';
    ctx.font = `800 34px ${FONT}`;
    ctx.fillText(String(card.level.xp), bx + bw - 48, y);

    afgerond(ctx, bx + 48, y + 20, bw - 96, 16, 8);
    ctx.fillStyle = '#25282d';
    ctx.fill();
    afgerond(ctx, bx + 48, y + 20, Math.max(16, ((bw - 96) * card.level.progress) / 100), 16, 8);
    ctx.fillStyle = '#22e06b';
    ctx.fill();

    const upgrade =
        card.overall === null
            ? 'Je eerste rapport zet de kaart aan'
            : card.level.next === null
              ? 'Het hoogste level bereikt'
              : `Nog ${card.level.next.remaining} ${card.level.next.remaining === 1 ? 'punt' : 'punten'} tot je volgende upgrade`;

    ctx.textAlign = 'left';
    ctx.fillStyle = '#99a3b3';
    ctx.font = `500 26px ${FONT}`;
    ctx.fillText(upgrade, bx + 48, y + 74);

    // --- Voet van de kaart ---
    const voetY = by + bh - 44;
    ctx.fillStyle = '#99a3b3';
    ctx.font = `600 26px ${FONT}`;
    ctx.textAlign = 'left';
    ctx.fillText((card.season_label ?? `Seizoen ${card.season}`) + (card.overall !== null ? `  ·  Level ${card.level.label}` : ''), bx + 48, voetY);

    const rechts = [card.school ? passend(ctx, card.school, 320) : null, card.card_number ?? null].filter(Boolean).join('  ·  ');

    if (rechts) {
        ctx.textAlign = 'right';
        ctx.fillText(rechts, bx + bw - 48, voetY);
    }

    // --- De sticker: schuin over de rechterbovenhoek, goud met donkere letters ---
    if (opties.sticker) {
        ctx.save();
        ctx.translate(bx + bw - 60, by + 130);
        ctx.rotate(-Math.PI / 14);
        ctx.font = `900 40px ${FONT}`;
        const tb = ctx.measureText(opties.sticker).width + 64;
        afgerond(ctx, -tb, -38, tb, 76, 38);
        ctx.fillStyle = '#d4af37';
        ctx.shadowColor = 'rgba(0,0,0,0.5)';
        ctx.shadowBlur = 20;
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.fillStyle = '#0d0f12';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(opties.sticker, -tb / 2, 2);
        ctx.restore();
        ctx.textBaseline = 'alphabetic';
    }

    // --- Onder de kaart: de link of een zinnetje ---
    ctx.textAlign = 'center';
    ctx.fillStyle = '#99a3b3';
    ctx.font = `500 28px ${FONT}`;
    ctx.fillText(opties.voet ? passend(ctx, opties.voet, 900) : 'Gemaakt met PlayerPath', BREEDTE / 2, HOOGTE - 130);

    // Het watermerk: klein, rechtsonder, altijd.
    ctx.textAlign = 'right';
    ctx.fillStyle = 'rgba(241, 245, 249, 0.55)';
    ctx.font = `600 26px ${FONT}`;
    ctx.fillText('PlayerPath.nl', BREEDTE - 60, HOOGTE - 60);

    return new Promise((ok, fout) => {
        canvas.toBlob((blob) => (blob ? ok(blob) : fout(new Error('Kon de afbeelding niet maken.'))), 'image/png');
    });
}

export type DeelUitkomst = 'gedeeld' | 'gedownload' | 'geannuleerd' | 'mislukt';

const bestandsnaam = (card: Kaart) => `spelerskaart-${card.first_name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;

const download = (blob: Blob, naam: string) => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = naam;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10_000);
};

/**
 * De kaart opslaan als afbeelding, zonder deelmenu: voor "Opslaan" en voor
 * de weg naar Snapchat (opslaan, dan daar uit je galerij kiezen).
 */
export async function slaKaartOp(card: Kaart, link: string | null, sticker: string | null = null): Promise<DeelUitkomst> {
    try {
        download(await renderKaartStory(card, { voet: link, sticker }), bestandsnaam(card));

        return 'gedownload';
    } catch {
        return 'mislukt';
    }
}

/**
 * De kaart delen als afbeelding: via het deelmenu van de telefoon als dat
 * kan (Web Share API met bestanden), anders als download.
 */
export async function deelKaartAlsAfbeelding(card: Kaart, link: string | null, sticker: string | null = null): Promise<DeelUitkomst> {
    let blob: Blob;

    try {
        blob = await renderKaartStory(card, { voet: link, sticker });
    } catch {
        return 'mislukt';
    }

    const naam = bestandsnaam(card);
    const bestand = new File([blob], naam, { type: 'image/png' });
    const tekst = `De spelerskaart van ${card.first_name}` + (card.overall !== null ? ` · rating ${card.overall}` : '') + (link ? `\n${link}` : '');

    const nav = navigator as Navigator & { canShare?: (data: ShareData) => boolean };

    if (nav.share && nav.canShare?.({ files: [bestand] })) {
        try {
            await nav.share({ files: [bestand], title: `Spelerskaart van ${card.first_name}`, text: tekst });

            return 'gedeeld';
        } catch (e) {
            if ((e as DOMException)?.name === 'AbortError') {
                return 'geannuleerd';
            }
            // Anders: doorvallen naar de download.
        }
    }

    download(blob, naam);

    return 'gedownload';
}
