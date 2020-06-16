.DEFAULT_GOAL := help

ifdef NO_DOCKER
	PHP = $(shell which php)
else
	PHP = docker run \
		--interactive \
		--tty \
		--rm \
		--volume $(shell pwd):/usr/src/app:z \
		--workdir /usr/src/app \
		--name freshrss-extension-php-cli \
		freshrss-extension-php-cli \
		php
endif

############
## Docker ##
############
.PHONY: build
build: ## Build a Docker image
	docker build \
		--pull \
		--tag freshrss-extension-php-cli \
		--file Docker/Dockerfile .

###########
## TOOLS ##
###########
.PHONY: generate
generate: ## Generate the extensions.json file
	@$(PHP) ./generate.php

##########
## HELP ##
##########
.PHONY: help
help:
	@grep --extended-regexp '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
