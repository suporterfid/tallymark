.DEFAULT_GOAL := help

.PHONY: help up down bootstrap composer artisan npm test e2e load release deploy shell

help up down bootstrap composer artisan npm test e2e load release deploy shell:
	@bash scripts/tm.sh $@
