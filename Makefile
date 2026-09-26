.DEFAULT_GOAL := run

.PHONY: run

run:
	docker compose up --build -d
	@for i in $$(seq 1 60); do docker logs d_form_app 2>&1 | grep -q "Starting Octane" && break; echo "menunggu app siap... ($$i)"; sleep 5; done
	docker logs d_form_app 2>&1 | grep -q "Starting Octane" || (echo "ERROR: app tidak siap. Cek: docker logs d_form_app"; exit 1)
	docker compose exec -T app php artisan migrate:fresh --force
	docker compose exec -T app php artisan db:seed --force
	docker compose exec -T app php artisan queue:work --tries=3 & docker compose exec -T app php artisan schedule:work & docker compose logs -f
