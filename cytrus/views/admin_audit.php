<?php
use function App\e;

/** @var array $logs */
?>
<h1>Dziennik zdarzeń</h1>
<table class="file-table">
<thead>
<tr><th>Data</th><th>Użytkownik</th><th>Akcja</th><th>Szczegóły</th><th>IP</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
    <tr>
        <td><?= e(date('Y-m-d H:i:s', (int) $log['created_at'])) ?></td>
        <td><?= e($log['username'] ?? '-') ?></td>
        <td><?= e($log['action']) ?></td>
        <td><?= e($log['detail'] ?? '') ?></td>
        <td><?= e($log['ip'] ?? '') ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
