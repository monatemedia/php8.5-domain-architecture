#!/usr/bin/env bash
#
# git-setup.sh — create this repository's initial history
# ========================================================
#
#     bash git-setup.sh              # branch: main
#     bash git-setup.sh master       # or name it yourself
#
# Thirteen small commits rather than one large one, in dependency order:
# scaffolding, then the harness, then the documents, then the lessons. Each one
# is small enough to read in a terminal, and `git show <sha>` on any of them
# gives you one coherent thing.
#
# WHY A SCRIPT RATHER THAN PASTED COMMANDS
# -----------------------------------------
# Thirteen add/commit pairs is a lot of pasting, and a half-pasted sequence
# leaves the repo in a state that is annoying to unpick. This is idempotent in
# the only way that matters: it refuses to run at all if .git already exists.
#
# RUN IT ONCE. After that, commit normally.

set -euo pipefail

cd "$(dirname "$0")"

BRANCH="${1:-main}"

# ─────────────────────────────────────────────────────────────────────────────
# Guards
# ─────────────────────────────────────────────────────────────────────────────

if [ -d .git ]; then
    cat <<'MSG'
There is already a .git directory here, and this script will not touch it.

If it is the stale one left behind by a failed attempt (it contains a single
commit called "probe" and a HEAD.lock that could not be removed), delete it and
run this again:

    rm -rf .git
    bash git-setup.sh

If it is a real repository you care about, stop and look at it first:

    git log --oneline
MSG
    exit 1
fi

if [ ! -f check.php ] || [ ! -d module-0-when-and-when-not ]; then
    echo "This does not look like the course root — check.php is missing." >&2
    echo "Run the script from inside php8.5-domain-architecture." >&2
    exit 1
fi

if ! git config --get user.name >/dev/null 2>&1 || ! git config --get user.email >/dev/null 2>&1; then
    cat <<'MSG'
Git does not know who you are, so the commits would have no author.

Set it globally (once, for every repository):

    git config --global user.name  "Your Name"
    git config --global user.email "you@example.com"

Then run this again.
MSG
    exit 1
fi

# ─────────────────────────────────────────────────────────────────────────────
# Init
# ─────────────────────────────────────────────────────────────────────────────

echo "Initialising repository on branch '${BRANCH}'..."
git init -q
git symbolic-ref HEAD "refs/heads/${BRANCH}"

n=0

# stage_and_commit <subject> <body> <path>...
stage_and_commit() {
    local subject="$1"
    local body="$2"
    shift 2

    # Only stage paths that exist. A missing path would abort the whole run
    # under `set -e`, leaving a half-built history — much more annoying to
    # unpick than one commit that reports itself empty.
    local present=()
    local path
    for path in "$@"; do
        if [ -e "${path}" ]; then
            present+=("${path}")
        else
            echo "  !! missing, skipped: ${path}" >&2
        fi
    done

    if [ ${#present[@]} -eq 0 ]; then
        echo "  !! nothing to stage for: ${subject}" >&2
        return 0
    fi

    git add -- "${present[@]}"

    # Nothing staged means the paths did not exist. Say so rather than
    # committing an empty change and pretending the history is complete.
    if git diff --cached --quiet; then
        echo "  !! nothing staged for: ${subject}" >&2
        return 0
    fi

    git commit -q -m "${subject}" -m "${body}"
    n=$((n + 1))
    printf '  %2d. %s\n' "${n}" "${subject}"
}

echo
echo "Committing:"

# ── 1. Scaffolding ───────────────────────────────────────────────────────────
stage_and_commit \
"Add project scaffolding: composer, phpunit, gitignore, cover page" \
"PHP 8.5, no dependencies until Module 3 — Modules 0 to 2 are plain PHP,
which is the point being made rather than an accident.

Autoload maps the Workshop namespace to domain/workshop/, the directory
the student fills in from Lesson 1.1 onwards." \
.gitignore composer.json phpunit.xml index.php git-setup.sh

# ── 2. Harness: the library ──────────────────────────────────────────────────
stage_and_commit \
"Add acceptance harness: assertion helpers and domain autoloader" \
"Challenges are judged by assertions, not by comparing printed text
against a hand-written expected-output block. Each challenge ships a
fixed checks.php that ends with a machine-readable sentinel, so a
student's echo statements can neither fail them nor fool the checker.

autoload-domain.php is a nine-line PSR-4 loader taking the root as a
parameter, so the same checks can run against the student's code or the
reference implementation." \
lib

# ── 3. Harness: verify ───────────────────────────────────────────────────────
stage_and_commit \
"Add verify.php: syntax sweep, feature probes, solution runner" \
"Answers 'is the COURSE sound', not 'am I'. Runs every reference
solution against its own acceptance checks on every invocation — the
previous course shipped solutions that failed their own assertions
because nothing ever ran them.

Probes distinguish 'this is broken' from 'your runtime is a version
behind' and report the second as unavailable rather than failed." \
verify.php

# ── 4. Harness: check and test runner ────────────────────────────────────────
stage_and_commit \
"Add check.php and run-tests.php" \
"check.php walks the lessons in order and stops at the first unsolved
one, so there is always exactly one next action. Before blaming a
failure on the student it runs the reference solution against the same
checks; if that fails too, it says plainly that it is a course bug and
does not count it.

run-tests.php gives each PHPUnit file its own process, because lessons
deliberately reuse class names." \
check.php run-tests.php

# ── 5. Core documents ────────────────────────────────────────────────────────
stage_and_commit \
"Add course philosophy and the workshop domain reference" \
"Six rules, with 'ceremony must be paid for' at the top rather than as a
footnote — a course that teaches only the patterns produces developers
who apply them everywhere.

DOMAIN.md is the running domain: glossary, lifecycle with its illegal
transitions, ten numbered invariants, and an explicit note on what is a
policy rather than an invariant." \
COURSE_PHILOSOPHY.md DOMAIN.md

# ── 6. Supporting documents ──────────────────────────────────────────────────
stage_and_commit \
"Add README, progress template, gates and verification record" \
"GATES.md carries forward across modules on purpose: half of each gate
covers the module just finished, half reaches back several modules.

The tracker ships as PROGRESS.template.md and is copied to PROGRESS.md,
which is git-ignored — one learner's ticks and scores are not course
material. It has a 'modelling decisions I disagreed with' table, because
tactical DDD has fewer objectively correct answers than it appears to." \
README.md PROGRESS.template.md GATES.md RUN-THIS-FIRST.md domain

# ── 7. Module 0, lesson 1 ────────────────────────────────────────────────────
stage_and_commit \
"Add Module 0 overview and Lesson 0.1: the cost of DDD" \
"When NOT to do this, first, deliberately. An itemised ledger of what
tactical DDD costs and who pays it, the three options described
honestly, and a decision procedure.

Three runnable examples, including one that models the crossover point
and prints where it lands, and says out loud that its own weights are
opinions." \
module-0-when-and-when-not/README.md \
module-0-when-and-when-not/lesson-0.1-the-cost-of-ddd

# ── 8. Module 0, lesson 2 ────────────────────────────────────────────────────
stage_and_commit \
"Add Lesson 0.2: ubiquitous language and bounded contexts" \
"The patterns are meaningless without the words. Includes the
shared-model failure run step by step, counting nullable fields as three
context models are unified into one — the step where invariants die
produces no error, no failing test and no review comment.

The challenge is a transcript with at least six words doing two jobs." \
module-0-when-and-when-not/lesson-0.2-ubiquitous-language

# ── 9. Module 1, lesson 1 ────────────────────────────────────────────────────
stage_and_commit \
"Add Module 1 overview and Lesson 1.1: rich vs anemic models" \
"Anemic models framed as the default rather than as carelessness — every
ORM and every layered-architecture tutorial hands you that shape for
free, and for shallow rules it is correct.

Example 02 shows the rule being bypassed by five callers, none of them
unreasonable. First challenge: CourtesyVehicle." \
module-1-domain-modelling/README.md \
module-1-domain-modelling/lesson-1.1-rich-vs-anemic

# ── 10. Module 1, lesson 2 ───────────────────────────────────────────────────
stage_and_commit \
"Add Lesson 1.2: value objects" \
"Immutability, structural equality, self-validation — each demonstrated
by building a version without it and running the bug that follows.

Currency carries its own minor-unit precision, so JPY and KWD work.
Example 04 is the counterweight: six candidates scored, two unqualified
yeses, and the cost of the ones that do not earn their file." \
module-1-domain-modelling/lesson-1.2-value-objects

# ── 11. Module 1, lesson 3 ───────────────────────────────────────────────────
stage_and_commit \
"Add Lesson 1.3: entities and identity" \
"Identity as a thread through change, and the same concept being an
entity in one bounded context and a value in another.

Application-generated identity, for Rule 1: if the database assigns it,
the domain cannot construct a complete object without infrastructure,
and that hits every test you will ever write." \
module-1-domain-modelling/lesson-1.3-entities-and-identity

# ── 12. Module 1, lesson 4 ───────────────────────────────────────────────────
stage_and_commit \
"Add Lesson 1.4: where invariants live" \
"Invariant, policy, or sanity check — the distinction that decides where
a guard goes before anything else is argued about. A sanity check with
no override path becomes a business rule by accident and produces
plausible invented data, which is worse than a visible failure.

Relocating scattered checks makes the check count go DOWN." \
module-1-domain-modelling/lesson-1.4-where-invariants-live

# ── 13. Stubs ────────────────────────────────────────────────────────────────
stage_and_commit \
"Add stub outlines for Modules 2 to 6" \
"Planned from FUTURE_COURSE_Domain_Architecture.md. Not built yet, and
check.php counts only what exists so the percentage stays honest." \
module-2-aggregates-and-consistency \
module-3-persistence-without-leakage \
module-4-domain-exception-trees \
module-5-living-with-a-framework \
module-6-capstone

# ─────────────────────────────────────────────────────────────────────────────
# Anything left over
# ─────────────────────────────────────────────────────────────────────────────

if [ -n "$(git status --porcelain --untracked-files=all)" ]; then
    echo
    echo "Not committed by the script above:"
    git status --short --untracked-files=all
    echo
    echo "Add them yourself if they should be tracked:"
    echo "    git add -A && git commit"
fi

echo
echo "Done — ${n} commits on '${BRANCH}'."
echo
git --no-pager log --oneline
echo
echo "To read any one of them:   git show <sha>"
