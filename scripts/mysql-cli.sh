#!/bin/bash
docker compose exec mysql mysql -u jutform -pjutform_secret jutform "$@"
