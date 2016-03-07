#!/usr/bin/env bash

mkdir -p Core/K10rVersionCentralTracker

for path in `git ls-files`; do
	file=$(basename "$path")
	dir=$(dirname "$path")

	if [ "$file" == "package.sh" ]; then continue; fi

	mkdir -p "Core/K10rVersionCentralTracker/$dir"
	cp "$path" "Core/K10rVersionCentralTracker/$path"

	zip -r K10rVersionCentralTracker.zip Core

	rm -r Core
done
