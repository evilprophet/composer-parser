# Composer Parser

## Overview

It's a simple project which allows to parser multiple composer.json files into one single file (currently only to .xlsx) to easy compare differences between them.

## How to use

1. Create app via composer `composer create-project evilstudio/composer-parser`.
2. Copy `config/parameters.yaml.template` to `config/parameters.yaml` and set the correct values.
3. Run `php bin/console app:run` to parse composer.json files and generate result.
4. Run `php bin/console app:clean` to clean the workspace.
