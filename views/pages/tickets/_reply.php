<?php
/** @var array $t @var bool $canInternal @var bool $isRequester */
use App\Services\Uploads;
?>
<section class="card" id="reply" aria-labelledby="reply-title">
    <div class="card-header"><h2 id="reply-title" class="card-title"><?= $canInternal ? 'Reply or add a note' : 'Send a message to IT' ?></h2></div>
    <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/comments')) ?>" enctype="multipart/form-data" class="card-body space-y-4" novalidate data-validate>
        <?= csrf_field() ?>
        <div>
            <label for="body" class="label">Message</label>
            <textarea class="textarea" data-phi-check <?= field_attrs('body', true) ?> rows="4" maxlength="5000" required
                      data-msg-required="Please write a message before sending."><?= e(old('body')) ?></textarea>
            <p id="body-hint" class="hint"><?= $canInternal
                ? 'Replies are seen by the requester. Never include patient information.'
                : 'IT will be notified. Never include patient information.' ?></p>
            <?= field_error('body') ?>
        </div>

        <?php if ($canInternal): ?>
            <label class="flex min-h-touch cursor-pointer items-center gap-3 rounded-lg border border-line px-3 has-[:checked]:border-warning/40 has-[:checked]:bg-warning-tint">
                <input type="checkbox" name="is_internal" value="1" class="checkbox" <?= old('is_internal') ? 'checked' : '' ?>>
                <span class="text-sm"><span class="font-semibold text-ink">Internal note</span>: only IT staff will see it</span>
            </label>
        <?php endif; ?>

        <details <?= error('attachments') ? 'open' : '' ?>>
            <summary class="inline-flex min-h-touch cursor-pointer items-center gap-2 text-sm font-semibold text-midnight"><?= icon('paperclip', 'h-4 w-4') ?> Attach files</summary>
            <div class="mt-2">
                <label for="reply-attachments" class="sr-only">Files</label>
                <input type="file" id="reply-attachments" name="attachments[]" multiple accept="<?= e(Uploads::acceptAttribute()) ?>"
                       class="block w-full text-sm text-charcoal file:mr-3 file:min-h-touch file:cursor-pointer file:rounded-lg file:border file:border-midnight file:bg-white file:px-4 file:font-semibold file:text-midnight hover:file:bg-midnight-tint"
                       aria-describedby="attachments-hint<?= error('attachments') ? ' attachments-error' : '' ?>" data-max-files="<?= Uploads::MAX_FILES ?>" data-max-bytes="<?= Uploads::maxBytes() ?>">
                <p id="attachments-hint" class="hint">Up to <?= Uploads::MAX_FILES ?> files, <?= (int) config('uploads.max_mb') ?> MB each (JPG, PNG, WebP or PDF).</p>
                <?= field_error('attachments') ?>
            </div>
        </details>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary w-full sm:w-auto" data-loading-text="Sending…"><?= icon('check') ?> Send</button>
        </div>
    </form>
</section>
