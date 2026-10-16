<?php if (isset($notice) && is_array($notice) && isset($notice['message'])): ?>
    <p
        class="admin-alert <?= escapeHtml((string) ($notice['type'] ?? 'error')) ?>"
        role="status">
        <?= escapeHtml((string) $notice['message']) ?>
    </p>
<?php endif; ?>