# Sapto Changes — 30 Oktober 2026 (DFORM-RULES)

## Ringkasan

Memperbarui aturan engineering proyek agar konsisten antara `AGENTS.md`,
aturan general, frontend, dan backend. Perubahan mencakup workflow planning,
test minimum berbasis klasifikasi, evidence level, error contract, frontend
folder boundaries, authorization, migration safety, queue reliability,
dependency governance, dan observability.

| Issue | Commit |
| ----- | ------ |
| Konsolidasi engineering rules | `TBD` |

## Perubahan

- Commit wajib English-only, jelas, Conventional Commits, dan tanpa emoji.
- Stateful composable baru dipusatkan di `resources/js/hooks/`.
- Pure function baru dipusatkan di `resources/js/lib/`.
- Kontrak tipe lintas file dipusatkan di `resources/js/types/`.
- Runtime constant immutable dipusatkan di `resources/js/constants/`.
- Component feature dikelompokkan di `components/modules/<domain>/`.
- `resources/js/adapters/` ditetapkan sebagai seam third-party dan browser
  API.
- `plugins/` dan `directives/` ditetapkan sebagai folder conditional.
- Folder global `services/`, `repositories/`, `helpers/`, `common/`, `misc/`,
  `api/`, `mappers/`, `transformers/`, `managers/`, dan `controllers/`
  dilarang sebagai dumping ground.
- Struktur target, dependency direction, naming, dan larangan per folder
  ditulis di `docs/rules/front-end-structure.md`.
- `resources/js/utils/` ditetapkan sebagai legacy-only.
- `unknown` dibatasi pada boundary third-party yang benar-benar tidak
  diketahui dan wajib dipersempit segera.
- Non-null assertion `value!` dilarang; `as const` tetap diperbolehkan.
- Typed emits diwajibkan untuk event yang memiliki payload.
- Authorization layer, after-commit side effect, queue idempotency, dan
  migration safety didokumentasikan.
- HTTP status contract 1xx–5xx dirujuk ke registry resmi IANA.
- Docker dan Podman diperbolehkan sesuai environment, dengan PHP tetap
  dijalankan di container.
- Definition of Done, evidence level, waiver process, dependency governance,
  dan observability ditambahkan.

## Keputusan

Keputusan arsitektur dan trade-off dicatat di
`docs/adr/0001-engineering-rules-and-frontend-boundaries.md`.

## Verifikasi

- Perubahan hanya menyentuh dokumentasi dan ADR.
- Tidak ada kode produksi, dependency, database, atau data runtime yang
  diubah.
- Status code diverifikasi terhadap [IANA HTTP Status Code Registry](https://www.iana.org/assignments/http-status-codes).
- `git diff --check` wajib dijalankan sebelum commit.
- Struktur folder frontend tidak mengubah behavior dan tidak memindahkan file
  legacy secara massal.
