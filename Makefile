.DEFAULT_GOAL := run

# --- Aplikasi ---------------------------------------------------------------
# Semua perintah PHP dijalankan di container (proyek ini memakai podman).

.PHONY: run

run:
	podman compose up --build -d
	@for i in $$(seq 1 60); do podman logs d_form_app 2>&1 | grep -q "Starting Octane" && break; echo "menunggu app siap... ($$i)"; sleep 5; done
	podman logs d_form_app 2>&1 | grep -q "Starting Octane" || (echo "ERROR: app tidak siap. Cek: podman logs d_form_app"; exit 1)
	podman compose exec -T app php artisan migrate:fresh --force
	podman compose exec -T app php artisan db:seed --force
	podman compose exec -T app php artisan queue:work --tries=3 & podman compose exec -T app php artisan schedule:work & podman compose logs -f

# --- Harness stress (pengganti scripts/stress/run.sh) ------------------------
# Seed → generate target → k6 → ringkasan query. k6 tetap di host, PHP di container.
# Jangan pakai `make -j` untuk target stress: urutannya bagian dari alurnya.

STRESS_CONTAINER ?= d_form_app
STRESS_SCALE ?= 1000000
STRESS_BASE_URL ?= http://localhost:3000
STRESS_LOGIN_PATH ?= /auth/login
STRESS_ENABLE_WRITES ?= 0
# YES=1 melewati konfirmasi seed (wajib untuk non-interaktif/CI).
YES ?= 0

RUNTIME := $(shell command -v podman >/dev/null 2>&1 && echo podman || echo docker)
STRESS_EXEC := $(RUNTIME) exec -i -w /app $(STRESS_CONTAINER)
REPORT_DIR := scripts/stress/reports/$(shell date +%Y%m%dT%H%M%S)

# --- Orkestrasi run lengkap (Task 7) ----------------------------------------
# Satu perintah: baseline status endpoint → profil stress → beban k6 (stream.ndjson)
# → snapshot/diff digest → report.html. Urutan langkah bagian dari kontrak harness.
# DURATION (string k6) menimpa LOAD_DURATION; sampler selalu diberi batas integer.
TARGET_RPS ?= 50
LOAD_DURATION ?= $(if $(DURATION),$(DURATION),60s)
WORKERS ?= 4
# VU k6 untuk constant-arrival-rate: butuh VU ≈ rps × latensi. Naikkan untuk target tinggi.
PRE_VUS ?= 100
MAX_VUS ?= 5000
# 1 = biarkan container dalam profil stress setelah run (lewati stress-restore).
KEEP_STRESS_PROFILE ?= 0
RUN_DIR ?= $(REPORT_DIR)
AB_RPS ?= 300
AB_DURATION ?= 10s
# Baseline mengunjungi ±83 endpoint berurutan; timeout kecil menjaga run tetap < 15 menit.
BASELINE_HTTP_TIMEOUT ?= 5s
LOAD_HTTP_TIMEOUT ?= 30s
STRESS_REDIS_CONTAINER ?= d_form_redis
K6_IMAGE ?= grafana/k6:latest
# handleSummary menulis dari dalam container: butuh --user root + mount :z.
K6 := $(RUNTIME) run --rm --user root --network host -v "$(CURDIR)/scripts/stress:/scripts:z" $(K6_IMAGE) run
# Kredensial default dari manifest seeder agar stress-load cukup satu perintah.
STRESS_EMAIL ?= $(shell sed -n 's/.*"admin_email": *"\([^"]*\)".*/\1/p' storage/app/stress/manifest.json)
STRESS_PASSWORD ?= $(shell sed -n 's/.*"admin_password": *"\([^"]*\)".*/\1/p' storage/app/stress/manifest.json)

.PHONY: stress stress-guard stress-seed stress-generate stress-k6 stress-queries stress-all stress-profile \
	stress-sample stress-load stress-baseline stress-ab stress-report stress-restore

stress:
	@echo "Harness stress d-form-v2 (semua PHP lewat container):"
	@echo "  make stress-load [TARGET_RPS=n] [DURATION=60s] [WORKERS=4]  beban + baseline + query + resources → report.html"
	@echo "  make stress-baseline [RUN_DIR=<run>]  kunjungan status tiap endpoint → baseline.json"
	@echo "  make stress-ab [RUN_DIR=<run>]        kapasitas generator k6 ke /up → ab/summary.json"
	@echo "  make stress-report RUN=<run>          rakit ulang report.html dari artefak run"
	@echo "  make stress-seed [SCALE=n] [YES=1]   seed dataset stress (default $(STRESS_SCALE) baris/tabel)"
	@echo "  make stress-generate                 bangun scripts/stress/targets.json dari route:list + manifest"
	@echo "  make stress-k6                       k6 smoke → api-flood → spike, report per timestamp"
	@echo "  make stress-queries                  rekam ringkasan query (EXPLAIN + N+1 probe)"
	@echo "  make stress-all [YES=1]              seed → generate → k6 → queries"
	@echo "  make stress-profile WORKERS=n        recreate app dengan --workers=n (tanpa --watch)"
	@echo ""
	@echo "Variabel: SCALE=$(STRESS_SCALE) BASE_URL=$(STRESS_BASE_URL) CONTAINER=$(STRESS_CONTAINER)"
	@echo "k6: STRESS_EMAIL, STRESS_PASSWORD, STRESS_ENABLE_WRITES=$(STRESS_ENABLE_WRITES)"

stress-guard:
	@test -n "$(RUNTIME)" || { echo "[stress] butuh Podman atau Docker di PATH"; exit 1; }
	@$(RUNTIME) ps --format '{{.Names}}' 2>/dev/null | grep -qx '$(STRESS_CONTAINER)' || { echo "[stress] container '$(STRESS_CONTAINER)' belum berjalan — jalankan: $(RUNTIME) compose up -d"; exit 1; }
	@if grep -q '^APP_ENV=production' .env 2>/dev/null; then echo "[stress] APP_ENV=production terdeteksi — harness ini hanya untuk lokal/staging"; exit 1; fi
	@echo "[stress] runtime=$(RUNTIME) container=$(STRESS_CONTAINER) scale=$(STRESS_SCALE) base-url=$(STRESS_BASE_URL)"

stress-seed: stress-guard
	@if [ "$(YES)" != "1" ]; then printf '[stress] Seed %s baris per tabel domain (TRUNCATE 28 tabel). Lanjut? [y/N] ' '$(STRESS_SCALE)'; read -r a || exit 1; case "$$a" in y|Y) ;; *) echo "[stress] dibatalkan"; exit 1;; esac; fi
	@$(STRESS_EXEC) php artisan stress:seed --scale=$(STRESS_SCALE) --force

stress-generate: stress-guard
	@$(STRESS_EXEC) php scripts/stress/generate-targets.php

stress-queries: stress-guard
	@mkdir -p $(REPORT_DIR)
	@$(STRESS_EXEC) php artisan stress:queries --json | tee $(REPORT_DIR)/queries.json
	@echo "[stress] ringkasan query: $(REPORT_DIR)/queries.json"

stress-k6: stress-guard
	@test -f scripts/stress/targets.json || { echo "[stress] scripts/stress/targets.json belum ada — jalankan: make stress-generate"; exit 1; }
	@test -n "$(STRESS_EMAIL)" || { echo "[stress] STRESS_EMAIL belum diset (pakai admin_email dari storage/app/stress/manifest.json)"; exit 1; }
	@test -n "$(STRESS_PASSWORD)" || { echo "[stress] STRESS_PASSWORD belum diset (pakai admin_password dari manifest)"; exit 1; }
	@mkdir -p $(REPORT_DIR)
	@codes=""; for s in smoke api-flood spike; do \
		echo "[stress] k6 $$s"; \
		$(K6) --summary-export=/scripts/reports/$(notdir $(REPORT_DIR))/$$s.json \
			-e BASE_URL=$(STRESS_BASE_URL) \
			-e STRESS_EMAIL="$(STRESS_EMAIL)" \
			-e STRESS_PASSWORD="$(STRESS_PASSWORD)" \
			-e STRESS_LOGIN_PATH="$(STRESS_LOGIN_PATH)" \
			-e STRESS_ENABLE_WRITES="$(STRESS_ENABLE_WRITES)" \
			/scripts/k6/$$s.js; \
		codes="$$codes $$s=$$?"; \
	done; \
	$(STRESS_EXEC) php scripts/stress/summary.php /app/$(REPORT_DIR) $$codes

stress-all: stress-seed stress-generate stress-k6 stress-queries

# Recreate app dengan jumlah worker Octane tertentu (tanpa --watch) untuk sweep kapasitas.
# DB_EMULATE_PREPARES=1 ditempel sementara ke .env: compose hanya meneruskan env_file,
# jadi variabel shell tidak sampai ke container. .env dipulihkan setelah container dibuat;
# env container sudah tersalin saat create sehingga profil tetap memakai emulated prepares.
stress-profile: stress-guard
	@test -n "$(WORKERS)" || { echo "pakai: make stress-profile WORKERS=8"; exit 1; }
	@set -eu; \
	old_line=$$(grep '^DB_EMULATE_PREPARES=' .env || true); \
	restore() { \
		grep -v '^DB_EMULATE_PREPARES=' .env > .env.stress-tmp || true; \
		mv .env.stress-tmp .env; \
		if [ -n "$$old_line" ]; then printf '%s\n' "$$old_line" >> .env; fi; \
	}; \
	trap restore EXIT INT TERM HUP; \
	grep -v '^DB_EMULATE_PREPARES=' .env > .env.stress-tmp; \
	printf 'DB_EMULATE_PREPARES=1\n' >> .env.stress-tmp; \
	mv .env.stress-tmp .env; \
	OCTANE_ARGS="--workers=$(WORKERS)" $(RUNTIME) compose up -d --force-recreate app; \
	for i in $$(seq 1 30); do curl -sf -m 5 -o /dev/null $(STRESS_BASE_URL)/up && break; sleep 2; done; \
	curl -sf -m 5 -o /dev/null $(STRESS_BASE_URL)/up || { echo "[stress] $(STRESS_BASE_URL)/up tidak merespons"; exit 1; }; \
	line=$$($(STRESS_EXEC) ps -eo args | grep -m1 octane); echo "$$line"; \
	case "$$line" in \
		*--workers=$(WORKERS)*) ;; \
		*) echo "[stress] gagal: container tidak memakai --workers=$(WORKERS) — jalankan ulang: $(RUNTIME) compose up -d app"; exit 1;; \
	esac; \
	case "$$line" in \
		*--watch*) echo "[stress] gagal: --watch masih aktif di profil stress — jalankan ulang: $(RUNTIME) compose up -d app"; exit 1;; \
	esac; \
	echo "[stress] profil stress siap: --workers=$(WORKERS) DB_EMULATE_PREPARES=1"

# --- Sampler resource cgroup (Task 5) ----------------------------------------
# Satu baris CSV per sampel dari cgroup v2: CPU/RAM container app + DB.
# - Cadence riil ±$(SAMPLER_INTERVAL) detik + overhead `podman exec`; app dan db dibaca
#   berurutan (bukan simultan), jadi satu `ts` mewakili dua pembacaan berdekatan.
# - `usage_usec`/`throttled_usec` adalah counter KUMULATIF; delta per interval dihitung
#   saat analisis, bukan oleh sampler.
# - Berhenti saat file $(RUN_DIR)/stop muncul (stop basi dihapus di awal) atau setelah
#   DURATION detik. Container DB divalidasi lebih dulu; sampel tidak valid tidak ditulis.

STRESS_DB_CONTAINER ?= d_form_db
RUN_DIR ?= $(REPORT_DIR)
SAMPLER_INTERVAL ?= 1
DURATION ?=
SAMPLER_HEADER := ts,app_usage_usec,app_throttled_usec,app_mem_current,app_mem_peak,db_usage_usec,db_mem_current,db_mem_peak

.PHONY: stress-sample

stress-sample: stress-guard
	@set -eu; \
	csv="$(RUN_DIR)/resources.csv"; \
	is_posint() { case "$$1" in ''|*[!0-9]*) return 1;; esac; [ "$$1" -ge 1 ]; }; \
	comma_count() { printf '%s' "$$1" | tr -cd ',' | wc -c | tr -d ' '; }; \
	is_posint "$(SAMPLER_INTERVAL)" || { echo "[stress] sampler gagal: SAMPLER_INTERVAL='$(SAMPLER_INTERVAL)' bukan bilangan bulat >= 1" >&2; exit 1; }; \
	if [ -n "$(DURATION)" ]; then \
		is_posint "$(DURATION)" || { echo "[stress] sampler gagal: DURATION='$(DURATION)' bukan bilangan bulat >= 1" >&2; exit 1; }; \
	fi; \
	if ! $(RUNTIME) ps --format '{{.Names}}' 2>/dev/null | grep -qx '$(STRESS_DB_CONTAINER)'; then \
		echo "[stress] sampler gagal: container DB '$(STRESS_DB_CONTAINER)' tidak berjalan — set STRESS_DB_CONTAINER" >&2; \
		exit 1; \
	fi; \
	mkdir -p "$(RUN_DIR)"; \
	rm -f "$(RUN_DIR)/stop"; \
	probe() { \
		$(RUNTIME) exec -i "$$1" sh -c 'for f in cpu.stat memory.current memory.peak; do echo "==$$f"; cat /sys/fs/cgroup/$$f 2>/dev/null || true; done' | \
		awk '$$0=="==cpu.stat"{s=1; next} $$0=="==memory.current"{s=2; next} $$0=="==memory.peak"{s=3; next} s==1 && $$1=="usage_usec"{u=$$2} s==1 && $$1=="throttled_usec"{t=$$2} s==2 && NF>0 && !mc{mc=$$1} s==3 && NF>0 && !mp{mp=$$1} END{if (u=="" && t=="" && mc=="" && mp=="") exit; printf "%s,%s,%s,%s", u, t, mc, mp}'; \
	}; \
	for c in "$(STRESS_CONTAINER)" "$(STRESS_DB_CONTAINER)"; do \
		$(RUNTIME) exec -i "$$c" test -r /sys/fs/cgroup/memory.peak || echo "[stress] peringatan: memory.peak tidak terbaca di $$c — kolom peak akan kosong" >&2; \
	done; \
	limit=0; \
	if [ -n "$(DURATION)" ]; then limit=$$(($(DURATION) / $(SAMPLER_INTERVAL))); [ "$$limit" -ge 1 ] || limit=1; fi; \
	printf '%s\n' "$(SAMPLER_HEADER)" > "$$csv"; \
	n=0; fails=0; \
	while :; do \
		if [ -f "$(RUN_DIR)/stop" ]; then break; fi; \
		if [ "$$limit" -gt 0 ] && [ "$$n" -ge "$$limit" ]; then break; fi; \
		ts=$$(date +%s); \
		app=$$(probe "$(STRESS_CONTAINER)"); \
		db=$$(probe "$(STRESS_DB_CONTAINER)" | cut -d, -f1,3,4); \
		if [ "$$(comma_count "$$app")" -ne 3 ] || [ "$$(comma_count "$$db")" -ne 2 ]; then \
			fails=$$((fails + 1)); \
			echo "[stress] sampler: sampel tidak valid ($$fails/3) app='$$app' db='$$db'" >&2; \
			if [ "$$fails" -ge 3 ]; then \
				echo "[stress] sampler gagal: 3 sampel berurutan tidak valid — cek container app/db dan cgroup v2" >&2; \
				exit 1; \
			fi; \
			sleep $(SAMPLER_INTERVAL); \
			continue; \
		fi; \
		fails=0; \
		printf '%s,%s,%s\n' "$$ts" "$$app" "$$db" >> "$$csv"; \
		n=$$((n + 1)); \
		sleep $(SAMPLER_INTERVAL); \
	done; \
	echo "[stress] sampler: $$n baris → $$csv"

# --- Baseline status endpoint (Task 7) ---------------------------------------
# Satu VU mengunjungi tiap target baca sekali → <run>/baseline.json (kunci = nama target).
# Target tulis TIDAK dijalankan (lib.js everyTarget memfilter writes kecuali STRESS_ENABLE_WRITES=1):
# baseline tidak boleh membuat registrasi/email.
# Wajib sebelum load: report memakai status baseline sebagai kolom pembanding.
stress-baseline: stress-guard
	@test -f scripts/stress/targets.json || { echo "[stress] scripts/stress/targets.json belum ada — jalankan: make stress-generate"; exit 1; }
	@test -n "$(STRESS_EMAIL)" || { echo "[stress] STRESS_EMAIL kosong — isi manifest stress (storage/app/stress/manifest.json)"; exit 1; }
	@test -n "$(STRESS_PASSWORD)" || { echo "[stress] STRESS_PASSWORD kosong — isi manifest stress (storage/app/stress/manifest.json)"; exit 1; }
	@set -eu; \
	run="$(RUN_DIR)"; rel=$${run#scripts/stress/}; \
	if [ "$$rel" = "$$run" ]; then echo "[stress] RUN_DIR harus di bawah scripts/stress agar bisa di-mount ke k6"; exit 1; fi; \
	mkdir -p "$$run"; \
	$(K6) \
		-e BASELINE=1 \
		-e BASE_URL=$(STRESS_BASE_URL) \
		-e STRESS_EMAIL="$(STRESS_EMAIL)" \
		-e STRESS_PASSWORD="$(STRESS_PASSWORD)" \
		-e STRESS_LOGIN_PATH="$(STRESS_LOGIN_PATH)" \
		-e STRESS_ENABLE_WRITES="$(STRESS_ENABLE_WRITES)" \
		-e HTTP_TIMEOUT=$(BASELINE_HTTP_TIMEOUT) \
		-e OUT_DIR=/scripts/$$rel \
		/scripts/k6/endpoints.js; \
	echo "[stress] baseline: $$run/baseline.json"

# --- Kapasitas generator (Task 7) --------------------------------------------
# k6 menembak /up saja tanpa login pada laju tinggi: plafon host generator.
# run-meta menandai generator_bound bila rps di sini < target_rps × 1.5.
stress-ab: stress-guard
	@set -eu; \
	run="$(RUN_DIR)"; rel=$${run#scripts/stress/}; \
	if [ "$$rel" = "$$run" ]; then echo "[stress] RUN_DIR harus di bawah scripts/stress agar bisa di-mount ke k6"; exit 1; fi; \
	mkdir -p "$$run/ab"; \
	$(K6) \
		-e BASE_URL=$(STRESS_BASE_URL) \
		-e TARGET_RPS=$(TARGET_RPS) \
		-e DURATION=$${DURATION:-$(AB_DURATION)} \
		-e PRE_VUS=$(PRE_VUS) \
		-e MAX_VUS=$(MAX_VUS) \
		-e OUT_DIR=/scripts/$$rel/ab \
		/scripts/k6/generator.js; \
	echo "[stress] kapasitas generator: $$run/ab/summary.json"

# --- Satu perintah run lengkap (Task 7) --------------------------------------
# Urutan wajib: guard → kunci run → lock seed → validasi DURATION → run dir →
# config:clear → profil stress → kapasitas generator → baseline (baca saja) →
# run-meta → stop queue (dengan trap pemulihan) → snapshot digest → sampler →
# k6 (stream.ndjson) → stop sampler → diff digest → queue hidup → report.
stress-load: stress-guard
	@test -f scripts/stress/targets.json || { echo "[stress] scripts/stress/targets.json belum ada — jalankan: make stress-generate"; exit 1; }
	@set -eu; \
	case "$${MAKEFLAGS%% *}" in *n*|*t*) echo "[stress] tolak: stress-load tidak mendukung make -n/-t (resep ini mengeksekusi sub-make)"; exit 1;; esac; \
	lock_dir=scripts/stress/.stress-load.lock.d; \
	if ! mkdir "$$lock_dir" 2>/dev/null; then \
		owner=$$(cat "$$lock_dir/pid" 2>/dev/null || true); \
		if [ -n "$$owner" ] && kill -0 "$$owner" 2>/dev/null; then echo "[stress] run stress lain masih berjalan (pid $$owner)"; exit 1; fi; \
		rm -rf "$$lock_dir"; mkdir "$$lock_dir"; \
	fi; \
	printf '%s\n' "$$$$" > "$$lock_dir/pid"; \
	held=; \
	if ! held=$$($(RUNTIME) exec $(STRESS_REDIS_CONTAINER) redis-cli --scan --pattern '*stress:seed*' 2>&1); then \
		echo "[stress] gagal memeriksa lock stress:seed di '$(STRESS_REDIS_CONTAINER)': $$held"; exit 1; \
	fi; \
	if [ -n "$$held" ]; then echo "[stress] lock stress:seed dipegang ($$held) — tunggu seed stress selesai dulu"; exit 1; fi; \
	duration_input="$(LOAD_DURATION)"; \
	if printf '%s' "$$duration_input" | grep -Eq '^[0-9]+s?$$'; then \
		load_seconds=$${duration_input%s}; \
	elif printf '%s' "$$duration_input" | grep -Eq '^[0-9]+m$$'; then \
		load_seconds=$$(( $${duration_input%m} * 60 )); \
	else \
		echo "[stress] DURATION='$(LOAD_DURATION)' tidak didukung — pakai 60s, 2m, atau 90 (detik)"; exit 1; \
	fi; \
	if [ "$$load_seconds" -lt 1 ]; then echo "[stress] DURATION harus > 0 detik"; exit 1; fi; \
	k6_duration="$${load_seconds}s"; \
	queue_was_running=0; queue_restored=0; sampler_pid=; harness_failed=0; profile_done=0; \
	if $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1; then queue_was_running=1; fi; \
	start_queue() { \
		$(RUNTIME) exec -d $(STRESS_CONTAINER) sh -c 'nohup php artisan queue:work --tries=3 > /tmp/queue.log 2>&1 &'; \
	}; \
	run="$(RUN_DIR)"; rel=$${run#scripts/stress/}; \
	if [ "$$rel" = "$$run" ]; then echo "[stress] RUN_DIR harus di bawah scripts/stress agar bisa di-mount ke k6"; exit 1; fi; \
	mkdir -p "$$run"; \
	cp scripts/stress/targets.json "$$run/targets.json"; \
	$(STRESS_EXEC) php artisan config:clear >/dev/null; \
	$(MAKE) --no-print-directory stress-profile WORKERS=$(WORKERS); \
	if [ "$$queue_was_running" = "1" ]; then start_queue; sleep 2; fi; \
	$(MAKE) --no-print-directory stress-ab RUN_DIR="$$run" TARGET_RPS=$(AB_RPS) DURATION=$(AB_DURATION); \
	$(MAKE) --no-print-directory stress-baseline RUN_DIR="$$run"; \
	echo "[stress] segarkan profil setelah baseline agar worker tidak sibuk sisa baseline"; \
	$(MAKE) --no-print-directory stress-profile WORKERS=$(WORKERS); \
	rows=$$($(STRESS_EXEC) php artisan tinker --execute='echo (int) DB::table("users")->count();' | tail -1 | tr -cd '0-9'); \
	$(STRESS_EXEC) env RUN_DIR="$$run" TARGET_RPS=$(TARGET_RPS) WORKERS=$(WORKERS) DURATION="$$k6_duration" \
		DATASET_ROWS="$${rows:-0}" GIT_COMMIT="$$(git rev-parse --short HEAD)" STARTED_AT="$$(date -Iseconds)" \
		php scripts/stress/run-meta.php start; \
	restore_queue() { \
		if [ "$$queue_restored" = "1" ] || [ "$$queue_was_running" = "0" ]; then return 0; fi; \
		queue_restored=1; \
		start_queue >/dev/null 2>&1 || true; \
		for i in $$(seq 1 20); do $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1 && break; sleep 1; done; \
	}; \
	restore_profile() { \
		if [ "$$profile_done" = "1" ]; then return 0; fi; \
		profile_done=1; \
		if [ "$(KEEP_STRESS_PROFILE)" = "1" ]; then \
			echo "[stress] peringatan: KEEP_STRESS_PROFILE=1 — container masih dalam profil stress (emulated prepares/workers stress tetap aktif)"; \
			return 0; \
		fi; \
		$(MAKE) --no-print-directory stress-restore; \
	}; \
	cleanup() { \
		status=$${1:-0}; trap - EXIT INT TERM HUP; \
		if [ -n "$$sampler_pid" ]; then kill $$sampler_pid 2>/dev/null || true; fi; \
		restore_queue; \
		if ! restore_profile; then status=1; echo "[stress] gagal memulihkan profil dev — container masih dalam profil stress"; fi; \
		rm -rf "$$lock_dir"; \
		exit $$status; \
	}; \
	finish() { cleanup $$?; }; \
	echo "[stress] hentikan queue:work (sebelumnya hidup=$$queue_was_running)"; \
	$(STRESS_EXEC) sh -c 'pkill -f "[q]ueue:work" || true'; \
	for i in $$(seq 1 20); do $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1 || break; sleep 1; done; \
	if $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1; then echo "[stress] queue:work tidak berhenti"; exit 1; fi; \
	trap finish EXIT; trap 'cleanup 130' INT; trap 'cleanup 143' TERM HUP; \
	$(STRESS_EXEC) php artisan stress:digests --snapshot="$$run/digests-before.json"; \
	sampler_limit=$$(( load_seconds + 120 )); \
	$(MAKE) --no-print-directory stress-sample RUN_DIR="$$run" DURATION="$$sampler_limit" & sampler_pid=$$!; \
	sleep 2; \
	echo "[stress] k6 beban $(TARGET_RPS) rps selama $$k6_duration"; \
	set +e; \
	$(K6) \
		-e BASE_URL=$(STRESS_BASE_URL) \
		-e STRESS_EMAIL="$(STRESS_EMAIL)" \
		-e STRESS_PASSWORD="$(STRESS_PASSWORD)" \
		-e STRESS_LOGIN_PATH="$(STRESS_LOGIN_PATH)" \
		-e STRESS_ENABLE_WRITES="$(STRESS_ENABLE_WRITES)" \
		-e TARGET_RPS=$(TARGET_RPS) \
		-e DURATION="$$k6_duration" \
		-e PRE_VUS=$(PRE_VUS) \
		-e MAX_VUS=$(MAX_VUS) \
		-e HTTP_TIMEOUT=$(LOAD_HTTP_TIMEOUT) \
		-e OUT_DIR=/scripts/$$rel \
		--out json=/scripts/$$rel/stream.ndjson \
		/scripts/k6/endpoints.js; \
	k6_status=$$?; set -e; \
	echo "[stress] k6 exit=$$k6_status (99 = threshold 5xx terlampaui; angka tetap dilaporkan)"; \
	if [ "$$k6_status" != "0" ] && [ "$$k6_status" != "99" ]; then echo "[stress] k6 gagal: exit $$k6_status"; harness_failed=1; fi; \
	touch "$$run/stop"; \
	wait $$sampler_pid || echo "[stress] peringatan: sampler berhenti tidak normal"; \
	kill $$sampler_pid 2>/dev/null || true; \
	rm -f "$$run/stop"; \
	if [ ! -s "$$run/stream.ndjson" ]; then echo "[stress] stream.ndjson tidak ada atau 0 byte — kapasitas jatuh ke proksi CPU"; harness_failed=1; fi; \
	$(STRESS_EXEC) php artisan stress:digests --diff="$$run/digests-before.json" --out="$$run/queries.json" --limit=20; \
	echo "[stress] nyalakan kembali queue:work"; \
	start_queue; queue_restored=1; \
	for i in $$(seq 1 20); do $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1 && break; sleep 1; done; \
	if ! $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1; then echo "[stress] queue:work gagal dinyalakan"; exit 1; fi; \
	if [ "$$queue_was_running" = "1" ]; then echo "[stress] queue:work hidup kembali"; fi; \
	$(STRESS_EXEC) env RUN_DIR="$$run" FINISHED_AT="$$(date -Iseconds)" php scripts/stress/run-meta.php finish; \
	$(STRESS_EXEC) php artisan stress:report "$$run"; \
	echo "[stress] report: $$run/report.html"; \
	if ! restore_profile; then echo "[stress] gagal: stress-restore tidak berhasil — container masih dalam profil stress"; exit 1; fi; \
	exit $$harness_failed

# Rakit ulang report dari artefak run yang sudah ada.
stress-report: stress-guard
	@test -n "$(RUN)" || { echo "pakai: make stress-report RUN=<run>"; exit 1; }
	@$(STRESS_EXEC) php artisan stress:report "$(RUN)"
	@echo "[stress] report: $(RUN)/report.html"

# --- Pulihkan profil dev (Fix round 2) ---------------------------------------
# Recreate app ke profil dev: OCTANE_ARGS="--watch --workers=1" dan TANPA
# DB_EMULATE_PREPARES. Dipanggil stress-load di akhir run dan di trap abort
# supaya lingkungan dev tidak ditinggalkan dalam mode stress.
stress-restore: stress-guard
	@if [ "$(KEEP_STRESS_PROFILE)" = "1" ]; then echo "[stress] peringatan: KEEP_STRESS_PROFILE=1 — container masih dalam profil stress (emulated prepares/workers stress tetap aktif)"; exit 0; fi; \
	set -eu; \
	if grep -q '^DB_EMULATE_PREPARES=' .env; then echo "[stress] tolak: .env masih memuat DB_EMULATE_PREPARES — hapus dulu agar profil dev tidak memakai emulated prepares"; exit 1; fi; \
	OCTANE_ARGS="--watch --workers=1" $(RUNTIME) compose up -d app; \
	for i in $$(seq 1 30); do curl -sf -m 5 -o /dev/null $(STRESS_BASE_URL)/up && break; sleep 2; done; \
	curl -sf -m 5 -o /dev/null $(STRESS_BASE_URL)/up || { echo "[stress] gagal: $(STRESS_BASE_URL)/up tidak merespons setelah restore"; exit 1; }; \
	line=$$($(STRESS_EXEC) ps -eo args | grep -m1 octane); echo "$$line"; \
	case "$$line" in *--watch*) ;; *) echo "[stress] gagal: --watch tidak aktif setelah restore"; exit 1;; esac; \
	case "$$line" in *--workers=1*) ;; *) echo "[stress] gagal: --workers=1 tidak aktif setelah restore"; exit 1;; esac; \
	emulate=$$($(STRESS_EXEC) sh -c 'echo $${DB_EMULATE_PREPARES:-}' 2>/dev/null || true); \
	if [ -n "$$emulate" ]; then echo "[stress] gagal: DB_EMULATE_PREPARES=$$emulate masih aktif di container"; exit 1; fi; \
	if ! $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1; then \
		$(RUNTIME) exec -d $(STRESS_CONTAINER) sh -c 'nohup php artisan queue:work --tries=3 > /tmp/queue.log 2>&1 &'; \
	fi; \
	for i in $$(seq 1 20); do $(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1 && break; sleep 1; done; \
	$(STRESS_EXEC) pgrep -f '[q]ueue:work' >/dev/null 2>&1 || { echo "[stress] gagal: queue:work tidak hidup setelah restore"; exit 1; }; \
	echo "[stress] profil dev dipulihkan (workers=1, watch, emulated prepares off)"
