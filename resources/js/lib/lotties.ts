type LottieColor = [number, number, number, number]

interface LottieShapeAnimation {
    readonly v: string
    readonly fr: number
    readonly ip: number
    readonly op: number
    readonly w: number
    readonly h: number
    readonly nm: string
    readonly ddd: 0
    readonly assets: readonly []
    readonly layers: readonly Record<string, unknown>[]
}

function shapeLayer(name: string, color: LottieColor, x: number, y: number, delay = 0): Record<string, unknown> {
    return {
        ddd: 0,
        ind: delay + 1,
        ty: 4,
        nm: name,
        sr: 1,
        ks: {
            o: { a: 0, k: 100 },
            r: { a: 0, k: 0 },
            p: {
                a: 1,
                k: [
                    { t: 0 + delay, s: [x, y + 10, 0], e: [x, y - 6, 0], i: { x: [0.45], y: [1] }, o: { x: [0.2], y: [0] } },
                    { t: 45 + delay, s: [x, y - 6, 0], e: [x, y + 10, 0], i: { x: [0.45], y: [1] }, o: { x: [0.2], y: [0] } },
                    { t: 90 + delay, s: [x, y + 10, 0] },
                ],
            },
            a: { a: 0, k: [0, 0, 0] },
            s: { a: 0, k: [100, 100, 100] },
        },
        ao: 0,
        shapes: [
            {
                ty: 'gr',
                nm: `${name} group`,
                it: [
                    { ty: 'rc', d: 1, s: { a: 0, k: [72, 46] }, p: { a: 0, k: [0, 0] }, r: { a: 0, k: 14 }, nm: `${name} rect` },
                    { ty: 'fl', c: { a: 0, k: color }, o: { a: 0, k: 100 }, r: 1, nm: `${name} fill` },
                    { ty: 'tr', p: { a: 0, k: [0, 0] }, a: { a: 0, k: [0, 0] }, s: { a: 0, k: [100, 100] }, r: { a: 0, k: 0 }, o: { a: 0, k: 100 } },
                ],
            },
        ],
        ip: 0,
        op: 90,
        st: 0,
        bm: 0,
    }
}

function createLottie(name: string, layers: readonly Record<string, unknown>[]): LottieShapeAnimation {
    return {
        v: '5.10.2',
        fr: 30,
        ip: 0,
        op: 90,
        w: 240,
        h: 240,
        nm: name,
        ddd: 0,
        assets: [],
        layers,
    }
}

export const lotties = {
    builderEmpty: createLottie('Builder empty state', [
        shapeLayer('Primary field', [0.141, 0.341, 0.839, 1], 120, 86),
        shapeLayer('Accent field', [0.918, 0.714, 0.31, 1], 94, 136, 8),
        shapeLayer('Success field', [0.149, 0.663, 0.471, 1], 146, 136, 16),
    ]),
    fieldSelected: createLottie('Field selected state', [
        shapeLayer('Inspector card', [0.97, 0.925, 0.745, 1], 120, 98),
        shapeLayer('Inspector action', [0.141, 0.341, 0.839, 1], 120, 148, 12),
    ]),
    comingSoon: createLottie('Coming soon state', [
        shapeLayer('Roadmap card', [0.141, 0.341, 0.839, 1], 92, 112),
        shapeLayer('Launch card', [0.918, 0.714, 0.31, 1], 148, 124, 10),
    ]),
    errorState: createLottie('Error state', [
        shapeLayer('Error panel', [0.824, 0.294, 0.482, 1], 120, 94),
        shapeLayer('Recovery panel', [0.149, 0.663, 0.471, 1], 120, 148, 14),
    ]),
} as const

export type LottieName = keyof typeof lotties
