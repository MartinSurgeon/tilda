<?php /** @var array $users @var string $selected */ ?>
<label for="f-user" class="label">Who</label>
<select id="f-user" name="user" class="select">
    <option value="">Anyone</option>
    <option value="system" <?= $selected === 'system' ? 'selected' : '' ?>>System or direct database access</option>
    <?php foreach ($users as $u): ?>
        <option value="<?= (int) $u['id'] ?>" <?= $selected === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] . ' (' . $u['email'] . ')') ?></option>
    <?php endforeach; ?>
</select>
