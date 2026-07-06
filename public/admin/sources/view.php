<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

require_once $root . '/app/auth/require-login.php';
require_once $root . '/app/config/database.php';
require_once $root . '/app/repositories/ClaimRepository.php';
require_once $root . '/app/repositories/ResearchQuestionRepository.php';

function sourceClaimEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function sourceClaimCsrfToken(): string
{
    if (
        !isset($_SESSION['source_claim_csrf'])
        || !is_string($_SESSION['source_claim_csrf'])
    ) {
        $_SESSION['source_claim_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['source_claim_csrf'];
}

function sourceClaimCsrfIsValid(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['source_claim_csrf'])
        && is_string($_SESSION['source_claim_csrf'])
        && hash_equals($_SESSION['source_claim_csrf'], $token);
}

$sourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$sourceId || $sourceId < 1) {
    http_response_code(400);
    exit('A valid source ID is required.');
}

$pdo = db();

$repository = new ClaimRepository($pdo);
$questionRepository = new ResearchQuestionRepository($pdo);

$source = $repository->findSource($sourceId);

if ($source === null) {
    http_response_code(404);
    exit('Source not found.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sourceClaimCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'The form expired. Refresh the page and try again.';
    } else {
        try {
            $entryType = (string) ($_POST['entry_type'] ?? 'claims');

            if ($entryType === 'questions') {
                $createdCount = $questionRepository
                    ->createQuestionsFromSource(
                        $sourceId,
                        (string) ($_POST['questions_text'] ?? ''),
                        isset($_POST['question_fair_cycle_id'])
                            && (int) $_POST['question_fair_cycle_id'] > 0
                                ? (int) $_POST['question_fair_cycle_id']
                                : null,
                        (string) ($_POST['question_priority'] ?? 'normal'),
                        isset($_SESSION['admin_user_id'])
                            ? (int) $_SESSION['admin_user_id']
                            : null
                    );

                header(
                    'Location: /admin/sources/view.php?id='
                    . $sourceId
                    . '&added_questions='
                    . $createdCount
                );
                exit;
            }

            $createdCount = $repository->createClaimsFromSource(
                $sourceId,
                (string) ($_POST['claims_text'] ?? ''),
                [
                    'batch_label' => trim(
                        (string) ($_POST['batch_label'] ?? '')
                    ),
                    'source_excerpt' => trim(
                        (string) ($_POST['source_excerpt'] ?? '')
                    ),
                    'source_location' => trim(
                        (string) ($_POST['source_location'] ?? '')
                    ),
                    'verification_method' => trim(
                        (string) ($_POST['verification_method'] ?? '')
                    ),
                    'fair_cycle_id' => isset($_POST['fair_cycle_id'])
                        ? (int) $_POST['fair_cycle_id']
                        : null,
                ],
                isset($_SESSION['admin_user_id'])
                    ? (int) $_SESSION['admin_user_id']
                    : null
            );

            header(
                'Location: /admin/sources/view.php?id='
                . $sourceId
                . '&added_claims='
                . $createdCount
            );
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$claims = $repository->getClaimsForSource($sourceId);
$questions = $questionRepository->getQuestionsForSource($sourceId);
$fairCycles = $repository->getFairCycles();

$addedClaimsCount = filter_input(
    INPUT_GET,
    'added_claims',
    FILTER_VALIDATE_INT
);

$addedQuestionsCount = filter_input(
    INPUT_GET,
    'added_questions',
    FILTER_VALIDATE_INT
);

$pageTitle = 'Source Claims';

require $root . '/app/includes/admin-header.php';
?>

<style>
    .source-claim-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
        gap: 1.5rem;
        align-items: start;
    }

    .source-claim-panel {
        background: #fff;
        border: 1px solid rgba(36, 49, 43, .14);
        border-radius: 18px;
        padding: 1.4rem;
        box-shadow: 0 10px 28px rgba(36, 49, 43, .06);
    }

    .source-claim-list {
        display: grid;
        gap: .8rem;
        margin-top: 1rem;
    }

    .source-claim-item {
        border: 1px solid rgba(36, 49, 43, .12);
        border-radius: 14px;
        padding: 1rem;
    }

    .source-claim-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .65rem;
        font-size: .86rem;
        opacity: .78;
    }

    .source-claim-form label {
        display: block;
        font-weight: 700;
        margin: 1rem 0 .35rem;
    }

    .source-claim-form input,
    .source-claim-form select,
    .source-claim-form textarea {
        width: 100%;
        box-sizing: border-box;
    }

    .source-claim-form textarea {
        min-height: 120px;
    }

    .source-claim-form textarea.claims-box {
        min-height: 300px;
    }

    .source-claim-help {
        font-size: .9rem;
        opacity: .75;
        margin-top: .35rem;
    }

    .source-claim-notice {
        border-radius: 12px;
        padding: .85rem 1rem;
        margin-bottom: 1rem;
    }

    .source-claim-success {
        background: #edf8ef;
        border: 1px solid #b9dabc;
    }

    .source-claim-error {
        background: #fff0ef;
        border: 1px solid #e4b4af;
    }

    @media (max-width: 850px) {
        .source-claim-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page">
    <p>
        <a href="/admin/sources/">← Back to sources</a>
    </p>

    <div class="admin-page-header">
        <div>
            <p class="eyebrow">Source #<?= (int) $source['id'] ?></p>
            <h1><?= sourceClaimEscape($source['title']) ?></h1>

            <?php if (!empty($source['organization_name'])): ?>
                <p><?= sourceClaimEscape($source['organization_name']) ?></p>
            <?php endif; ?>

            <?php if (!empty($source['url'])): ?>
                <p>
                    <a
                        href="<?= sourceClaimEscape($source['url']) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Open original source ↗
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($addedClaimsCount): ?>
        <div class="source-claim-notice source-claim-success">
            <?= (int) $addedClaimsCount ?>
            <?= $addedClaimsCount === 1 ? 'claim was' : 'claims were' ?>
            created and linked to this source.
        </div>
    <?php endif; ?>

    <?php if ($addedQuestionsCount): ?>
        <div class="source-claim-notice source-claim-success">
            <?= (int) $addedQuestionsCount ?>
            <?= $addedQuestionsCount === 1 ? 'question was' : 'questions were' ?>
            created and linked to this source.
        </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="source-claim-notice source-claim-error">
            <?= sourceClaimEscape($error) ?>
        </div>
    <?php endif; ?>

    <div class="source-claim-layout">
        <section class="source-claim-panel">
            <h2>Claims from this source</h2>

            <p>
                Each claim is stored separately, but remains connected to
                this source and the evidence entered with it.
            </p>

            <?php if ($claims === []): ?>
                <p>No claims have been entered from this source yet.</p>
            <?php else: ?>
                <div class="source-claim-list">
                    <?php foreach ($claims as $claim): ?>
                        <article class="source-claim-item">
                            <strong>
                                <?= sourceClaimEscape($claim['claim_text']) ?>
                            </strong>

                            <div class="source-claim-meta">
                                <span>
                                    Status:
                                    <?= sourceClaimEscape(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $claim['status']
                                            )
                                        )
                                    ) ?>
                                </span>

                                <span>
                                    Category:
                                    <?= sourceClaimEscape(
                                        $claim['category_name']
                                        ?: 'Uncategorized'
                                    ) ?>
                                </span>

                                <span>
                                    Current sources:
                                    <?= (int) $claim['current_source_count'] ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="source-claim-panel">
            <h2>Add claims</h2>

            <form method="post" class="source-claim-form">
                <input type="hidden" name="entry_type" value="claims">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= sourceClaimEscape(sourceClaimCsrfToken()) ?>"
                >

                <label for="claims_text">Claims</label>

                <textarea
                    id="claims_text"
                    name="claims_text"
                    class="claims-box"
                    required
                    placeholder="Participants may be as young as 5 years old.

Participants may participate through age 19.

Ionia County offers a meat goat project."

                ><?= sourceClaimEscape($_POST['claims_text'] ?? '') ?></textarea>

                <p class="source-claim-help">
                    Enter one claim per paragraph. Separate claims with a
                    blank line.
                </p>

                <label for="batch_label">Evidence group</label>

                <input
                    id="batch_label"
                    name="batch_label"
                    type="text"
                    value="<?= sourceClaimEscape(
                        $_POST['batch_label'] ?? ''
                    ) ?>"
                    placeholder="Example: Eligibility and available projects"
                >

                <label for="source_location">
                    Page, heading, section, or timestamp
                </label>

                <input
                    id="source_location"
                    name="source_location"
                    type="text"
                    value="<?= sourceClaimEscape(
                        $_POST['source_location'] ?? ''
                    ) ?>"
                    placeholder="Example: Eligibility section, page 4"
                >

                <label for="source_excerpt">Supporting source excerpt</label>

                <textarea
                    id="source_excerpt"
                    name="source_excerpt"
                    placeholder="Paste the source language that supports these claims."
                ><?= sourceClaimEscape(
                    $_POST['source_excerpt'] ?? ''
                ) ?></textarea>

                <label for="fair_cycle_id">Applies to fair cycle</label>

                <select id="fair_cycle_id" name="fair_cycle_id">
                    <option value="">Not assigned yet</option>

                    <?php foreach ($fairCycles as $cycle): ?>
                        <option
                            value="<?= (int) $cycle['id'] ?>"
                            <?= (
                                (int) ($_POST['fair_cycle_id'] ?? 0)
                                === (int) $cycle['id']
                            ) ? 'selected' : '' ?>
                        >
                            <?= sourceClaimEscape(
                                $cycle['fair_name']
                                . ' — '
                                . $cycle['label']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="verification_method">Verification method</label>

                <select
                    id="verification_method"
                    name="verification_method"
                >
                    <option value="manual_review">
                        Read and reviewed manually
                    </option>

                    <option value="current_official_website">
                        Published on current official website
                    </option>

                    <option value="current_handbook">
                        Listed in current handbook
                    </option>

                    <option value="official_confirmation">
                        Confirmed by an official
                    </option>

                    <option value="email_confirmation">
                        Confirmed by email
                    </option>

                    <option value="interview_confirmation">
                        Confirmed during interview
                    </option>
                </select>

                <p style="margin-top: 1.25rem;">
                    <button type="submit" class="button button-primary">
                        Save claims
                    </button>
                </p>
            </form>
        </section>
    </div>

    <div class="source-claim-layout" style="margin-top: 1.5rem;">
        <section class="source-claim-panel">
            <h2>Questions raised by this source</h2>

            <p>
                Record questions that need additional research, confirmation,
                or another source.
            </p>

            <?php if ($questions === []): ?>
                <p>No research questions have been recorded from this source.</p>
            <?php else: ?>
                <div class="source-claim-list">
                    <?php foreach ($questions as $question): ?>
                        <article class="source-claim-item">
                            <strong>
                                <?= sourceClaimEscape(
                                    $question['question_text']
                                ) ?>
                            </strong>

                            <div class="source-claim-meta">
                                <span>
                                    Status:
                                    <?= sourceClaimEscape(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $question['status']
                                            )
                                        )
                                    ) ?>
                                </span>

                                <span>
                                    Priority:
                                    <?= sourceClaimEscape(
                                        ucfirst($question['priority'])
                                    ) ?>
                                </span>

                                <span>
                                    Category:
                                    <?= sourceClaimEscape(
                                        $question['category_name']
                                        ?: 'Uncategorized'
                                    ) ?>
                                </span>

                                <?php if (!empty($question['fair_cycle_label'])): ?>
                                    <span>
                                        Fair cycle:
                                        <?= sourceClaimEscape(
                                            $question['fair_name']
                                            . ' — '
                                            . $question['fair_cycle_label']
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="source-claim-panel">
            <h2>Add questions</h2>

            <form method="post" class="source-claim-form">
                <input type="hidden" name="entry_type" value="questions">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= sourceClaimEscape(sourceClaimCsrfToken()) ?>"
                >

                <label for="questions_text">Questions</label>

                <textarea
                    id="questions_text"
                    name="questions_text"
                    class="claims-box"
                    required
                    placeholder="Does the minimum age apply to Cloverbuds or competitive exhibitors?

    Is the deadline the same for every animal project?

    Who approves late entries?"
                ><?= sourceClaimEscape(
                    $_POST['questions_text'] ?? ''
                ) ?></textarea>

                <p class="source-claim-help">
                    Enter one question per paragraph. Separate questions with
                    a blank line.
                </p>

                <label for="question_fair_cycle_id">
                    Applies to fair cycle
                </label>

                <select
                    id="question_fair_cycle_id"
                    name="question_fair_cycle_id"
                >
                    <option value="">Not assigned yet</option>

                    <?php foreach ($fairCycles as $cycle): ?>
                        <option value="<?= (int) $cycle['id'] ?>">
                            <?= sourceClaimEscape(
                                $cycle['fair_name']
                                . ' — '
                                . $cycle['label']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="question_priority">Priority</label>

                <select id="question_priority" name="question_priority">
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>

                <p style="margin-top: 1.25rem;">
                    <button type="submit" class="button button-primary">
                        Save questions
                    </button>
                </p>
            </form>
        </section>
    </div>

</div>

<?php require $root . '/app/includes/admin-footer.php'; ?>