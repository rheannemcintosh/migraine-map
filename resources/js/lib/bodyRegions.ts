export type BodyRegionId =
    | 'forehead'
    | 'left-temple'
    | 'right-temple'
    | 'left-eye'
    | 'right-eye'
    | 'face'
    | 'crown'
    | 'back-of-head'
    | 'neck'
    | 'left-shoulder'
    | 'right-shoulder';

export type BodyRegion = {
    id: BodyRegionId;
    label: string;
    view: 'front' | 'back';
    shape: 'ellipse' | 'path';
    attributes: Record<string, string | number>;
};

export type RegionSelection = 'primary' | 'secondary' | null;

// Coarse regions only for now: the diagram is deliberately rough while the
// clickable-SVG approach is being evaluated.
export const bodyRegions: BodyRegion[] = [
    {
        id: 'forehead',
        label: 'Forehead',
        view: 'front',
        shape: 'path',
        attributes: {
            d: 'M 62 36 Q 100 18 138 36 L 138 62 L 62 62 Z',
        },
    },
    {
        id: 'left-temple',
        label: 'Left temple',
        view: 'front',
        shape: 'ellipse',
        attributes: { cx: 58, cy: 78, rx: 12, ry: 22 },
    },
    {
        id: 'right-temple',
        label: 'Right temple',
        view: 'front',
        shape: 'ellipse',
        attributes: { cx: 142, cy: 78, rx: 12, ry: 22 },
    },
    {
        id: 'left-eye',
        label: 'Left eye',
        view: 'front',
        shape: 'ellipse',
        attributes: { cx: 84, cy: 76, rx: 13, ry: 10 },
    },
    {
        id: 'right-eye',
        label: 'Right eye',
        view: 'front',
        shape: 'ellipse',
        attributes: { cx: 116, cy: 76, rx: 13, ry: 10 },
    },
    {
        id: 'face',
        label: 'Face and jaw',
        view: 'front',
        shape: 'path',
        attributes: {
            d: 'M 70 92 L 130 92 Q 132 130 100 138 Q 68 130 70 92 Z',
        },
    },
    {
        id: 'neck',
        label: 'Neck',
        view: 'front',
        shape: 'path',
        attributes: {
            d: 'M 84 136 L 116 136 L 118 166 L 82 166 Z',
        },
    },
    {
        id: 'left-shoulder',
        label: 'Left shoulder',
        view: 'front',
        shape: 'path',
        attributes: {
            d: 'M 82 166 L 20 178 Q 12 190 18 204 L 82 190 Z',
        },
    },
    {
        id: 'right-shoulder',
        label: 'Right shoulder',
        view: 'front',
        shape: 'path',
        attributes: {
            d: 'M 118 166 L 180 178 Q 188 190 182 204 L 118 190 Z',
        },
    },
    {
        id: 'crown',
        label: 'Top of head',
        view: 'back',
        shape: 'path',
        attributes: {
            d: 'M 262 36 Q 300 14 338 36 L 338 60 L 262 60 Z',
        },
    },
    {
        id: 'back-of-head',
        label: 'Back of head',
        view: 'back',
        shape: 'path',
        attributes: {
            d: 'M 262 60 L 338 60 Q 340 120 300 136 Q 260 120 262 60 Z',
        },
    },
];
