#!/usr/bin/env bash
# AI tools are tools, not contributors. GitHub lists a commit's author, committer
# and every Co-authored-by trailer as contributors, so this fails when any of them,
# or a "Generated with" line, credits an AI tool. Usage: check-attribution.sh [rev]
set -euo pipefail

ai_tools='claude|anthropic|copilot|openai|chatgpt|codex|cursor|gemini|devin'
status=0

while read -r commit; do
  credits=$(git show -s --format='%an <%ae>%n%cn <%ce>%n%(trailers:key=Co-authored-by,valueonly)' "$commit")
  generated=$(git show -s --format=%B "$commit" | grep -iE "generated (with|by) .*($ai_tools)" || true)
  if grep -qiE "$ai_tools" <<<"$credits" || [ -n "$generated" ]; then
    echo "::error::commit $commit credits an AI tool as author, committer, co-author or generator"
    status=1
  fi
done < <(git rev-list "${1:-HEAD}")

exit "$status"
