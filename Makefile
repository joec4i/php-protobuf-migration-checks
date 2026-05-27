PHP ?= php
REPORT ?= REPORT.md

.PHONY: update check lint

update:
	$(PHP) runner.php --markdown=$(REPORT)

check:
	$(PHP) runner.php

lint:
	find . -path ./protobuf -prune -o -name '*.php' -exec $(PHP) -l {} +
