.PHONY: build up down composer shell scan test inspect export-mermaid

PROJECT_PATH ?= .
ROUTE ?=
METHOD ?= GET
MERMAID_OUTPUT ?= /tmp/phpflow.mmd
MAX_DEPTH ?= 10
SUMMARY ?=

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down --remove-orphans

composer:
	docker compose run --rm php composer update --no-interaction

shell:
	docker compose run --rm php bash

scan:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow scan /workspace

test:
	docker compose run --rm php composer test

inspect:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow inspect /workspace "$(ROUTE)" "$(METHOD)" \
		$(if $(SUMMARY),--summary,)

export-mermaid:
	@if [ -n "$(ROUTE)" ]; then \
		docker compose run --rm \
			-v "$(PROJECT_PATH):/workspace:ro" \
			-v "$$(dirname "$(MERMAID_OUTPUT)"):/output" \
			php php bin/phpflow export:mermaid /workspace \
			--output="/output/$$(basename "$(MERMAID_OUTPUT)")" \
			--route="$(ROUTE)" \
			--method="$(METHOD)" \
			--max-depth="$(MAX_DEPTH)"; \
	else \
		docker compose run --rm \
			-v "$(PROJECT_PATH):/workspace:ro" \
			-v "$$(dirname "$(MERMAID_OUTPUT)"):/output" \
			php php bin/phpflow export:mermaid /workspace \
			--output="/output/$$(basename "$(MERMAID_OUTPUT)")"; \
	fi
	@echo "Mermaid graph written to $(MERMAID_OUTPUT)"
