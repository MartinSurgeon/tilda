<?php
/** @var array $categories @var array $subcategories @var array $departments @var array $priorities */
use App\Services\Sla;
use App\Services\TicketMeta;
use App\Services\Uploads;

$me = user();
$defaultPriority = (string) (array_values(array_filter($priorities, static fn ($p) => (int) $p['is_default'] === 1))[0]['id'] ?? $priorities[0]['id']);
$selectedCategory = (string) old('category_id');
?>
<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="page-title">Report a problem</h1>
        <p class="page-subtitle">Tell IT what is wrong. It takes about a minute.</p>
    </header>

    <div class="alert-warning mb-6" role="note">
        <?= icon('shield', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <p><strong>Do not include patient information.</strong> No patient names, hospital numbers or medical details. Describe the equipment or system instead.</p>
    </div>

    <?php if (errors()): ?>
        <div class="alert-danger mb-6" role="alert" tabindex="-1" data-error-summary>
            <?= icon('alert-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
            <p>Almost there. Please check the <?= count(errors()) === 1 ? 'highlighted field' : count(errors()) . ' highlighted fields' ?> below.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/tickets')) ?>" enctype="multipart/form-data" class="space-y-6" novalidate data-validate>
        <?= csrf_field() ?>

        <!-- 1. What -->
        <section class="card card-body space-y-5" aria-labelledby="what-title">
            <h2 id="what-title" class="card-title">What is the problem?</h2>

            <!-- Category and its sub-type form one step; the sub-type list slides open once a category is picked. -->
            <div>
            <fieldset <?= error('category_id') ? 'aria-describedby="category_id-error"' : '' ?>>
                <legend class="label">It is about…</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" data-category-tiles>
                    <?php foreach ($categories as $c): ?>
                        <label class="group relative flex min-h-[4.5rem] cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-line-strong bg-surface p-2 text-center text-sm font-semibold text-ink hover:bg-smoke-2 has-[:checked]:border-teal has-[:checked]:bg-teal-tint has-[:checked]:text-teal-darker has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-midnight">
                            <input type="radio" name="category_id" value="<?= (int) $c['id'] ?>" class="sr-only" required
                                   <?= $selectedCategory === (string) $c['id'] ? 'checked' : '' ?>>
                            <span class="absolute right-1.5 top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-teal text-white group-has-[:checked]:flex" aria-hidden="true"><?= icon('check', 'h-3.5 w-3.5') ?></span>
                            <?= icon($c['icon'], 'h-6 w-6') ?>
                            <span><?= e($c['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error('category_id') ?>
            </fieldset>

            <div data-subcategory-wrap><div class="reveal-inner"><div class="pt-5">
                <label for="subcategory_id" class="label">Type of problem <span class="label-optional">(optional)</span></label>
                <select class="select" <?= field_attrs('subcategory_id') ?> data-subcategory>
                    <option value="">Not sure / other</option>
                    <?php foreach ($categories as $c): if (empty($subcategories[(int) $c['id']])) { continue; } ?>
                        <optgroup label="<?= e($c['name']) ?>" data-parent="<?= (int) $c['id'] ?>">
                            <?php foreach ($subcategories[(int) $c['id']] as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (string) old('subcategory_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <?= field_error('subcategory_id') ?>
            </div></div></div>
            </div>

            <div>
                <label for="title" class="label">Short summary</label>
                <input type="text" class="input" data-phi-check <?= field_attrs('title', true) ?> value="<?= e(old('title')) ?>" required minlength="5" maxlength="150"
                       autocomplete="off" data-msg-required="Please give the problem a short summary." data-msg-min="Please add a few more words to the summary.">
                <p id="title-hint" class="hint">For example: “Printer in Ward 3 not printing”</p>
                <?= field_error('title') ?>
            </div>

            <div>
                <label for="description" class="label">What happened?</label>
                <textarea class="textarea" data-phi-check <?= field_attrs('description', true) ?> rows="4" required minlength="10" maxlength="5000"
                          data-msg-required="Please describe what happened." data-msg-min="Please add a little more detail so IT can help."><?= e(old('description')) ?></textarea>
                <p id="description-hint" class="hint">What were you doing, what did you see, and any error message.</p>
                <?= field_error('description') ?>
            </div>
        </section>

        <!-- 2. How urgent -->
        <section class="card card-body" aria-labelledby="urgency-title">
            <fieldset <?= error('priority_id') ? 'aria-describedby="priority_id-error"' : '' ?>>
                <legend id="urgency-title" class="card-title mb-1">How much does it affect your work?</legend>
                <p class="hint mb-4 mt-0">IT may adjust this after looking at the problem.</p>
                <div class="space-y-2">
                    <?php foreach ($priorities as $p): ?>
                        <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-lg border border-line p-3 hover:bg-smoke-2 has-[:checked]:border-teal has-[:checked]:bg-teal-tint">
                            <input type="radio" name="priority_id" value="<?= (int) $p['id'] ?>" class="mt-0.5 h-5 w-5 border-line-strong text-teal focus:ring-teal/30"
                                   <?= (string) old('priority_id', $defaultPriority) === (string) $p['id'] ? 'checked' : '' ?>>
                            <span class="flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <?= TicketMeta::priorityBadge($p['name'], $p['tone'], $p['icon']) ?>
                                    <span class="text-sm font-semibold text-ink"><?= e($p['description']) ?></span>
                                </span>
                                <?php if ($p['response_minutes']): ?>
                                    <span class="mt-1 block text-sm text-muted">IT responds within <?= e(Sla::humanMinutes((int) $p['response_minutes'])) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error('priority_id') ?>
            </fieldset>
        </section>

        <!-- 3. Where + attachments -->
        <section class="card card-body space-y-5" aria-labelledby="where-title">
            <h2 id="where-title" class="card-title">Where is it?</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="location" class="label">Room or location <span class="label-optional">(optional)</span></label>
                    <input type="text" class="input" <?= field_attrs('location') ?> value="<?= e(old('location')) ?>" maxlength="120" placeholder="e.g. Ward 3, Room 12">
                    <?= field_error('location') ?>
                </div>
                <div>
                    <label for="department_id" class="label">Department</label>
                    <select class="select" <?= field_attrs('department_id') ?> required data-msg-required="Please choose your department.">
                        <option value="">Choose…</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (string) old('department_id', (string) $me['department_id']) === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('department_id') ?>
                </div>
            </div>

            <div>
                <label for="attachments" class="label">Photo or document <span class="label-optional">(optional)</span></label>
                <input type="file" id="attachments" name="attachments[]" multiple accept="<?= e(Uploads::acceptAttribute()) ?>"
                       class="block w-full text-sm text-charcoal file:mr-3 file:min-h-touch file:cursor-pointer file:rounded-lg file:border file:border-midnight file:bg-surface file:px-4 file:font-semibold file:text-midnight hover:file:bg-midnight-tint"
                       aria-describedby="attachments-hint<?= error('attachments') ? ' attachments-error' : '' ?>" data-max-files="<?= Uploads::MAX_FILES ?>" data-max-bytes="<?= Uploads::maxBytes() ?>">
                <p id="attachments-hint" class="hint">A photo of the screen or error helps. Up to <?= Uploads::MAX_FILES ?> files, <?= (int) config('uploads.max_mb') ?> MB each (JPG, PNG, WebP or PDF).</p>
                <?= field_error('attachments') ?>
            </div>
        </section>

        <!-- Phones and tablets: the send button stays in reach above the bottom navigation while scrolling. -->
        <div class="sticky-actions">
            <a href="<?= e(url('/')) ?>" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-primary flex-1 text-base lg:flex-none lg:px-8" data-loading-text="Sending…"><?= icon('check') ?> Send to IT</button>
        </div>
    </form>
</div>
