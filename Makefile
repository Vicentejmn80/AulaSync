.PHONY: dev-db db-up db-down migrate seed

dev-db:
	docker compose -f docker-compose.yml up -d
	cd backend && php artisan migrate --seed

db-up:
	docker compose -f docker-compose.yml up -d

db-down:
	docker compose -f docker-compose.yml down

migrate:
	cd backend && php artisan migrate

seed:
	cd backend && php artisan db:seed
