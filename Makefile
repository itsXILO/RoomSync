.PHONY: up down seed test race fresh logs shell

# Start all containers in the background
up:
	./vendor/bin/sail up -d

# Stop all containers
down:
	./vendor/bin/sail down

# Fresh migrate + seed (wipes all data)
seed:
	./vendor/bin/sail artisan migrate:fresh --seed

# Alias for seed
fresh:
	./vendor/bin/sail artisan migrate:fresh --seed

# Run PHPUnit tests
test:
	./vendor/bin/sail artisan test

# Run the race-condition concurrency test
race:
	bash scripts/race-test.sh

# Tail the Laravel log
logs:
	./vendor/bin/sail artisan pail

# Open a shell inside the app container
shell:
	./vendor/bin/sail shell
