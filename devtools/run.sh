#!/bin/bash
# Run getQuestionInformation devtools command in a LimeSurvey instance
# Usage : devtools/run.sh <LimeSurvey directory> check|bench|dump <arguments>
# See devtools/README.md
if [ $# -lt 2 ]; then
    echo "Usage: $0 <LimeSurvey directory> check <sid> [<sid> …] | bench <sid> <function> | dump <sid> <file.json>" >&2
    exit 1
fi
COMMANDS="$(cd "$(dirname "$0")/commands" && pwd)"
cd "$1" || exit 1
shift
YII_CONSOLE_COMMANDS="$COMMANDS" php application/commands/console.php gqitest "$@"
