# File-type icons — attribution

Vendored icons in this folder come from **vscode-icons**, converted to SVG from the
Iconify set export.

- Upstream: <https://github.com/vscode-icons/vscode-icons>
- Icon set page: <https://icon-sets.iconify.design/vscode-icons/>
- Package used for extraction: `@iconify-json/vscode-icons` **v1.2.97**
- License: MIT (see upstream `LICENSE`)

Vendored files (each a 32×32 `viewBox` SVG with `role="img" aria-hidden="true"`):

- `pdf.svg`
- `excel.svg`
- `word.svg`
- `powerpoint.svg`
- `csv.svg`
- `zip.svg`
- `image.svg`
- `text.svg`
- `generic.svg`

The package is **not** a runtime dependency; the static assets are served from
`public/file-types/` at `/file-types/*.svg` and selected by
`resources/js/lib/fileTypeIcon.ts`.
