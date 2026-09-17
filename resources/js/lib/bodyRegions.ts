export type BodySide = 'left' | 'right' | 'centre';

export type BodyView = 'front' | 'back' | 'left-profile' | 'right-profile';

export type RegionKey =
    | 'forehead'
    | 'between-eyebrows'
    | 'temple'
    | 'eye'
    | 'sinus'
    | 'cheek'
    | 'jaw'
    | 'ear'
    | 'neck'
    | 'shoulder'
    | 'crown'
    | 'upper-back-of-head'
    | 'lower-back-of-head'
    | 'behind-ear'
    | 'back-of-neck'
    | 'back-of-shoulder';

export type BodyRegionId = `${BodySide}-${RegionKey}`;

export type BodyRegion = {
    id: BodyRegionId;
    key: RegionKey;
    side: BodySide;
    label: string;
    term: string;
};

export type RegionShape = {
    region: BodyRegionId;
    view: BodyView;
    shape: 'ellipse' | 'path';
    attributes: Record<string, string | number>;
};

export type RegionSelection = 'primary' | 'secondary' | null;

type Point = { x: number; y: number };

type EllipseShape = { cx: number; cy: number; rx: number; ry: number };

type Geometry = { path: string } | { ellipse: EllipseShape };

type RegionTemplate = {
    key: RegionKey;
    label: string;
    term: string;
    midline?: boolean;
    // Shapes are drawn for the patient's left side (or the midline) on the
    // front/back figures and for the left profile; the right side and right
    // profile are produced by mirroring across the figure's midline.
    front?: Geometry;
    back?: Geometry;
    profile?: Geometry;
};

// Every figure is drawn in local coordinates with x = 0 down the midline and
// the head centred at (0, 108), then translated to its origin.
export const figureOrigins: Record<BodyView, Point> = {
    front: { x: 120, y: 0 },
    back: { x: 360, y: 0 },
    'left-profile': { x: 120, y: 270 },
    'right-profile': { x: 360, y: 270 },
};

export const viewLabels: Record<BodyView, string> = {
    front: 'Front',
    back: 'Back',
    'left-profile': 'Left side',
    'right-profile': 'Right side',
};

export const headOutline: EllipseShape = { cx: 0, cy: 108, rx: 50, ry: 66 };

export const profileHeadOutline =
    'M -10 42 Q -50 44 -56 90 Q -60 100 -54 104 Q -58 112 -52 130 Q -50 150 -30 172 L 20 172 Q 52 150 50 100 Q 46 46 -10 42 Z';

export const torsoOutline =
    'M -20 172 L -24 204 Q -70 206 -96 224 Q -104 236 -96 250 L 96 250 Q 104 236 96 224 Q 70 206 24 204 L 20 172';

export const profileTorsoOutline =
    'M -22 172 L -24 204 Q -60 208 -60 214 Q -70 230 -60 250 L 60 250 Q 70 230 60 214 Q 60 208 28 204 L 22 170';

const templates: RegionTemplate[] = [
    {
        key: 'forehead',
        label: 'Forehead',
        term: 'Frontal',
        front: { path: 'M 0 46 Q 30 46 46 70 L 46 84 L 0 84 Z' },
        profile: { path: 'M -10 44 Q -46 48 -56 84 L -10 84 Z' },
    },
    {
        key: 'between-eyebrows',
        label: 'Between the eyebrows',
        term: 'Glabella / frontal sinus',
        midline: true,
        front: { path: 'M -8 86 L 8 86 L 5 128 L -5 128 Z' },
    },
    {
        key: 'temple',
        label: 'Temple',
        term: 'Temporal',
        front: { path: 'M 38 84 L 49 78 Q 52 104 48 130 L 38 128 Z' },
        profile: {
            path: 'M -30 86 L 18 86 L 18 98 L -2 98 L -2 128 L -30 128 Z',
        },
    },
    {
        key: 'eye',
        label: 'Eye',
        term: 'Orbital / periorbital',
        front: { ellipse: { cx: 21, cy: 97, rx: 12, ry: 7 } },
        profile: { ellipse: { cx: -44, cy: 96, rx: 9, ry: 5 } },
    },
    {
        key: 'sinus',
        label: 'Sinus',
        term: 'Maxillary sinus',
        front: { path: 'M 8 108 L 34 108 L 34 128 L 7 128 Z' },
        profile: { path: 'M -56 104 L -32 104 L -32 128 L -54 128 Z' },
    },
    {
        key: 'cheek',
        label: 'Cheek',
        term: 'Zygomatic',
        front: { path: 'M 3 130 L 38 130 L 36 148 L 3 150 Z' },
        profile: { path: 'M -54 130 L -2 130 L -2 148 L -48 150 Z' },
    },
    {
        key: 'jaw',
        label: 'Jaw',
        term: 'Mandible / TMJ',
        front: { path: 'M 3 152 L 36 150 Q 30 168 3 172 Z' },
        profile: {
            path: 'M -48 152 L -2 150 L -2 130 L 18 130 L 18 150 Q 12 172 -30 172 Q -46 164 -48 152 Z',
        },
    },
    {
        key: 'ear',
        label: 'Ear',
        term: 'Auricular',
        profile: { ellipse: { cx: 8, cy: 112, rx: 9, ry: 14 } },
    },
    {
        key: 'neck',
        label: 'Neck',
        term: 'Anterior cervical',
        front: { path: 'M 0 174 L 20 172 L 24 204 L 0 204 Z' },
        profile: { path: 'M -22 174 L 2 172 L 4 204 L -24 204 Z' },
    },
    {
        key: 'shoulder',
        label: 'Shoulder',
        term: 'Trapezius',
        front: {
            path: 'M 0 204 L 24 204 Q 70 206 96 224 Q 104 236 96 250 L 0 250 Z',
        },
        profile: {
            path: 'M -24 206 L 28 206 Q 60 208 60 214 Q 70 230 60 250 L -60 250 Q -70 230 -60 214 Q -60 208 -24 206 Z',
        },
    },
    {
        key: 'crown',
        label: 'Top of head',
        term: 'Vertex / parietal',
        back: { path: 'M 0 44 Q 32 46 47 72 Q 24 80 0 82 Z' },
        profile: { path: 'M -8 44 Q 30 44 48 76 L 48 84 L -8 84 Z' },
    },
    {
        key: 'upper-back-of-head',
        label: 'Upper back of head',
        term: 'Parietal / occipital',
        back: { path: 'M 0 82 Q 24 80 47 72 Q 52 96 50 118 L 0 118 Z' },
        profile: { path: 'M 20 86 L 48 86 Q 50 100 48 118 L 20 118 Z' },
    },
    {
        key: 'lower-back-of-head',
        label: 'Lower back of head',
        term: 'Occipital',
        back: { path: 'M 0 118 L 36 118 L 34 158 Q 20 172 0 174 Z' },
        profile: {
            path: 'M 34 120 L 48 120 Q 48 150 30 170 L 20 172 L 20 160 L 34 158 Z',
        },
    },
    {
        key: 'behind-ear',
        label: 'Behind ear',
        term: 'Mastoid',
        back: { path: 'M 36 118 L 50 118 Q 48 148 30 166 L 34 158 Z' },
        profile: { path: 'M 20 120 L 34 120 L 34 158 L 20 160 Z' },
    },
    {
        key: 'back-of-neck',
        label: 'Back of neck',
        term: 'Posterior cervical / suboccipital',
        back: { path: 'M 0 174 L 20 172 L 24 204 L 0 204 Z' },
        profile: { path: 'M 2 172 L 22 170 L 28 204 L 4 204 Z' },
    },
    {
        key: 'back-of-shoulder',
        label: 'Back of shoulder',
        term: 'Upper trapezius',
        back: {
            path: 'M 0 204 L 24 204 Q 70 206 96 224 Q 104 236 96 250 L 0 250 Z',
        },
    },
];

// Paths only use absolute M/L/Q/Z commands, so values alternate x, y and each
// x can be flipped and shifted independently.
const transformPath = (d: string, flip: boolean, origin: Point): string => {
    let isX = true;

    return d.replace(/-?\d+(\.\d+)?/g, (value) => {
        const number = Number(value);
        const result = isX
            ? (flip ? -number : number) + origin.x
            : number + origin.y;
        isX = !isX;

        return String(result);
    });
};

const transformGeometry = (
    geometry: Geometry,
    flip: boolean,
    origin: Point,
): Pick<RegionShape, 'shape' | 'attributes'> => {
    if ('ellipse' in geometry) {
        const { cx, cy, rx, ry } = geometry.ellipse;

        return {
            shape: 'ellipse',
            attributes: {
                cx: (flip ? -cx : cx) + origin.x,
                cy: cy + origin.y,
                rx,
                ry,
            },
        };
    }

    return {
        shape: 'path',
        attributes: { d: transformPath(geometry.path, flip, origin) },
    };
};

// Anatomical left appears on the viewer's right when facing the figure and on
// the viewer's left when looking at its back.
const isFlipped = (view: 'front' | 'back', side: BodySide): boolean =>
    view === 'front' ? side === 'right' : side === 'left';

const regionId = (side: BodySide, key: RegionKey): BodyRegionId =>
    `${side}-${key}`;

const regionLabel = (side: BodySide, label: string): string => {
    if (side === 'centre') {
        return label;
    }

    return `${side === 'left' ? 'Left' : 'Right'} ${label.toLowerCase()}`;
};

const sidesFor = (template: RegionTemplate): BodySide[] =>
    template.midline ? ['centre'] : ['left', 'right'];

export const bodyRegions: BodyRegion[] = templates.flatMap((template) =>
    sidesFor(template).map((side) => ({
        id: regionId(side, template.key),
        key: template.key,
        side,
        label: regionLabel(side, template.label),
        term: template.term,
    })),
);

export const regionShapes: RegionShape[] = templates.flatMap((template) => {
    const shapes: RegionShape[] = [];

    for (const view of ['front', 'back'] as const) {
        const geometry = template[view];

        if (geometry === undefined) {
            continue;
        }

        for (const side of sidesFor(template)) {
            shapes.push({
                region: regionId(side, template.key),
                view,
                ...transformGeometry(
                    geometry,
                    side !== 'centre' && isFlipped(view, side),
                    figureOrigins[view],
                ),
            });
        }
    }

    if (template.profile !== undefined && !template.midline) {
        shapes.push(
            {
                region: regionId('left', template.key),
                view: 'left-profile',
                ...transformGeometry(
                    template.profile,
                    false,
                    figureOrigins['left-profile'],
                ),
            },
            {
                region: regionId('right', template.key),
                view: 'right-profile',
                ...transformGeometry(
                    template.profile,
                    true,
                    figureOrigins['right-profile'],
                ),
            },
        );
    }

    return shapes;
});

const regionsById = new Map(bodyRegions.map((region) => [region.id, region]));

export const findRegion = (id: BodyRegionId): BodyRegion | undefined =>
    regionsById.get(id);
