#!/bin/bash

export MYBB_INSTALL_DB_ENGINE="sqlite"
export MYBB_INSTALL_DB_PATH="${containerWorkspaceFolder}/db.sqlite"
export MYBB_INSTALL_ADMINEMAIL="admin@example.localhost"
export MYBB_INSTALL_BBURL="https://${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"

if [[ -f bin/cli ]]; then
    echo $(php bin/cli status)

    if [[ $(php bin/cli status --code) != '3' ]]; then
        php bin/cli install --fast --no-interaction
    fi
fi
