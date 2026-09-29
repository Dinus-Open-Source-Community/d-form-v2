.DEFAULT_GOAL := run

.PHONY: run

run:
	podman compose up --build -d
	@for i in $$(seq 1 60); do podman logs d_form_app 2>&1 | grep -q "Starting Octane" && break; echo "menunggu app siap... ($$i)"; sleep 5; done
	podman logs d_form_app 2>&1 | grep -q "Starting Octane" || (echo "ERROR: app tidak siap. Cek: podman logs d_form_app"; exit 1)
	podman compose exec -T app php artisan migrate:fresh --force
	podman compose exec -T app php artisan db:seed --force
	podman compose exec -T app php artisan queue:work --tries=3 & podman compose exec -T app php artisan schedule:work & podman compose logs -f
