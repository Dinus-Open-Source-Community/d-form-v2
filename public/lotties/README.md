# Lottie animations

This folder hosts Lottie / Bodymovin JSON files served as static assets at
`/lotties/<name>.json`. They power the `<LocalLottie>` component.

## How animations are picked up

`resources/js/lib/lotties.ts` is the registry that maps a camelCase key to a
public path. Components reference animations by key:

```vue
<LocalLottie name="heroProduct" :height="320" width="100%" />
```

If you want to load a one-off file without registering it, pass `src` directly:

```vue
<LocalLottie src="/lotties/my-custom.json" :height="240" />
```

## Adding a new animation from LottieFiles

1. Open <https://lottiefiles.com/free-animations> and pick a free animation
   (CC0 or CC-BY are safest). Brand colors should match the D-Form palette
   (primary `#2457D6`).
2. Click **Download → Lottie JSON** and save it to this folder using a
   kebab-case filename, e.g. `confetti-burst.json`.
3. Register it in [`resources/js/lib/lotties.ts`](../../resources/js/lib/lotties.ts):
   ```ts
   confettiBurst: {
       src: '/lotties/confetti-burst.json',
       label: 'Confetti celebration',
   },
   ```
4. Use it from a component:
   ```vue
   <LocalLottie name="confettiBurst" :height="200" width="200" />
   ```

## Replacing an existing animation

The default JSON files in this folder were generated with a small script to
keep parity with our blue-primary design tokens. If you prefer a richer
animation from LottieFiles, simply overwrite the matching file (e.g.
`hero-product.json`) — the registry key stays the same.

## Tips

- Keep files under ~30 KB if possible. Strip unused metadata before committing.
- Prefer `loop: true` for ambient illustrations (hero, empty states).
- Use `loop: false` and a one-off `speed: 1.2` for celebratory moments
  (e.g. successful submission).
- All animations are lazy-loaded with an `IntersectionObserver` rootMargin of
  120px, so it's safe to drop multiple on a single page.
