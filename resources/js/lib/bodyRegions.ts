export type BodySide = 'left' | 'right';

export type BodyView = 'front' | 'back';

export type RegionKey =
    | 'forehead'
    | 'temple'
    | 'eye'
    | 'sinus'
    | 'jaw'
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
    view: BodyView;
    shape: 'ellipse' | 'path';
    attributes: Record<string, string | number>;
};

export type RegionSelection = 'primary' | 'secondary' | null;

type EllipseShape = { cx: number; cy: number; rx: number; ry: number };

type RegionTemplate = {
    key: RegionKey;
    label: string;
    view: BodyView;
    shape: { path: string } | { ellipse: EllipseShape };
};

// Each figure is drawn in local coordinates with x = 0 down the midline and
// the head centred at (0, 108). Templates describe the patient's left side
// only; the right side is produced by mirroring across the midline.
export const figureOrigins: Record<BodyView, number> = {
    front: 120,
    back: 360,
};

export const headOutline: EllipseShape = { cx: 0, cy: 108, rx: 50, ry: 66 };

export const torsoOutline =
    'M -20 172 L -24 204 Q -70 206 -96 224 Q -104 236 -96 250 L 96 250 Q 104 236 96 224 Q 70 206 24 204 L 20 172';

const templates: RegionTemplate[] = [
    {
        key: 'forehead',
        label: 'Forehead',
        view: 'front',
        shape: { path: 'M 0 46 Q 30 46 46 70 L 46 84 L 0 84 Z' },
    },
    {
        key: 'temple',
        label: 'Temple',
        view: 'front',
        shape: { path: 'M 38 84 L 49 78 Q 52 104 48 130 L 38 128 Z' },
    },
    {
        key: 'eye',
        label: 'Eye',
        view: 'front',
        shape: { ellipse: { cx: 19, cy: 96, rx: 13, ry: 8 } },
    },
    {
        key: 'sinus',
        label: 'Sinus',
        view: 'front',
        shape: { path: 'M 3 106 L 34 108 L 34 126 L 3 128 Z' },
    },
    {
        key: 'jaw',
        label: 'Jaw',
        view: 'front',
        shape: { path: 'M 3 130 L 38 130 Q 38 158 3 172 Z' },
    },
    {
        key: 'neck',
        label: 'Neck',
        view: 'front',
        shape: { path: 'M 0 174 L 20 172 L 24 204 L 0 204 Z' },
    },
    {
        key: 'shoulder',
        label: 'Shoulder',
        view: 'front',
        shape: {
            path: 'M 0 204 L 24 204 Q 70 206 96 224 Q 104 236 96 250 L 0 250 Z',
        },
    },
    {
        key: 'crown',
        label: 'Top of head',
        view: 'back',
        shape: { path: 'M 0 44 Q 32 46 47 72 Q 24 80 0 82 Z' },
    },
    {
        key: 'upper-back-of-head',
        label: 'Upper back of head',
        view: 'back',
        shape: { path: 'M 0 82 Q 24 80 47 72 Q 52 96 50 118 L 0 118 Z' },
    },
    {
        key: 'lower-back-of-head',
        label: 'Lower back of head',
        view: 'back',
        shape: { path: 'M 0 118 L 36 118 L 34 158 Q 20 172 0 174 Z' },
    },
    {
        key: 'behind-ear',
        label: 'Behind ear',
        view: 'back',
        shape: { path: 'M 36 118 L 50 118 Q 48 148 30 166 L 34 158 Z' },
    },
    {
        key: 'back-of-neck',
        label: 'Back of neck',
        view: 'back',
        shape: { path: 'M 0 174 L 20 172 L 24 204 L 0 204 Z' },
    },
    {
        key: 'back-of-shoulder',
        label: 'Back of shoulder',
        view: 'back',
        shape: {
            path: 'M 0 204 L 24 204 Q 70 206 96 224 Q 104 236 96 250 L 0 250 Z',
        },
    },
];

// Paths only use absolute M/L/Q/Z commands, so every odd-numbered value is an
// x coordinate that can be flipped and shifted independently.
const transformPath = (d: string, flip: boolean, offset: number): string => {
    let isX = true;

    return d.replace(/-?\d+(\.\d+)?/g, (value) => {
        const number = Number(value);
        const result = isX ? (flip ? -number : number) + offset : number;
        isX = !isX;

        return String(result);
    });
};

// Anatomical left appears on the viewer's right when facing the figure, and
// on the viewer's left when looking at its back.
const isFlipped = (view: BodyView, side: BodySide): boolean =>
    view === 'front' ? side === 'right' : side === 'left';

const buildRegion = (template: RegionTemplate, side: BodySide): BodyRegion => {
    const flip = isFlipped(template.view, side);
    const offset = figureOrigins[template.view];
    const sideLabel = side === 'left' ? 'Left' : 'Right';

    const base = {
        id: `${side}-${template.key}` as BodyRegionId,
        key: template.key,
        side,
        label: `${sideLabel} ${template.label.toLowerCase()}`,
        view: template.view,
    };

    if ('ellipse' in template.shape) {
        const { cx, cy, rx, ry } = template.shape.ellipse;

        return {
            ...base,
            shape: 'ellipse',
            attributes: { cx: (flip ? -cx : cx) + offset, cy, rx, ry },
        };
    }

    return {
        ...base,
        shape: 'path',
        attributes: { d: transformPath(template.shape.path, flip, offset) },
    };
};

export const bodyRegions: BodyRegion[] = templates.flatMap((template) =>
    (['left', 'right'] as BodySide[]).map((side) =>
        buildRegion(template, side),
    ),
);
