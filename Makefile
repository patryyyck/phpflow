.PHONY: build up down composer shell scan test inspect impact impact-table impact-http impact-message impact-service impact-exception export-mermaid export-json

PROJECT_PATH ?= .
ROUTE ?=
METHOD ?= GET
MERMAID_OUTPUT ?= /tmp/phpflow.mmd
JSON_OUTPUT ?= /tmp/phpflow.json
MAX_DEPTH ?= 10
SUMMARY ?=
TABLE ?=
OPERATION ?=
HTTP ?=
MESSAGE ?=
SERVICE ?=
EXCEPTION ?=

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

impact:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact /workspace \
		$(if $(TABLE),--table="$(TABLE)",) \
		$(if $(OPERATION),--operation="$(OPERATION)",) \
		$(if $(HTTP),--http="$(HTTP)",) \
		$(if $(MESSAGE),--message="$(MESSAGE)",) \
		$(if $(SERVICE),--service="$(SERVICE)",) \
		$(if $(EXCEPTION),--exception="$(EXCEPTION)",) \
		$(if $(SUMMARY),--summary,)

impact-http:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact:http /workspace "$(HTTP)"

impact-message:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact:message /workspace "$(MESSAGE)"

impact-service:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact:service /workspace "$(SERVICE)"

impact-exception:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact:exception /workspace "$(EXCEPTION)"

impact-table:
	docker compose run --rm \
		-v "$(PROJECT_PATH):/workspace:ro" \
		php php bin/phpflow impact:table /workspace "$(TABLE)" 		$(if $(OPERATION),--operation="$(OPERATION)",)

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


export-json:
	@if [ -n "$(ROUTE)" ]; then \
		docker compose run --rm \
			-v "$(PROJECT_PATH):/workspace:ro" \
			-v "$$(dirname "$(JSON_OUTPUT)"):/output" \
			php php bin/phpflow export:json /workspace \
			--output="/output/$$(basename "$(JSON_OUTPUT)")" \
			--route="$(ROUTE)" \
			--method="$(METHOD)" \
			--max-depth="$(MAX_DEPTH)"; \
	else \
		docker compose run --rm \
			-v "$(PROJECT_PATH):/workspace:ro" \
			-v "$$(dirname "$(JSON_OUTPUT)"):/output" \
			php php bin/phpflow export:json /workspace \
			--output="/output/$$(basename "$(JSON_OUTPUT)")"; \
	fi
	@echo "JSON graph written to $(JSON_OUTPUT)"
