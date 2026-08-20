<?php
/** @var array $campaign @var array $tree @var array $data */
$cid = (int) $campaign['id'];
$link = fn($row) => '/campaign/' . $cid . '/page/' . rawurlencode($row['slug']);
$anyData = $data['party'] || $data['quests'] || $data['sessions'] || $data['enemies'] || $data['items'];
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <h1 class="page-title"><?= e($campaign['name']) ?></h1>
        <div class="page-title-rule">&#10022; &#10022; &#10022;</div>

        <?php if (!$anyData): ?>
            <p class="muted">Nothing to summarise yet. This hub fills itself in as you add party
            members, quests, sessions, NPCs and items (using the category templates).</p>
        <?php endif; ?>

        <?php if ($data['party']): ?>
            <h2>🛡️ The party</h2>
            <table class="dash-table">
                <thead><tr><th>Character</th><th>Player</th><th>Class</th><th>Race</th><th>Level</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($data['party'] as $p): ?>
                    <tr>
                        <td><a href="<?= $link($p) ?>"><?= e($p['title']) ?></a></td>
                        <td><?= e($p['meta']['player']) ?></td>
                        <td><?= e($p['meta']['class']) ?></td>
                        <td><?= e($p['meta']['race']) ?></td>
                        <td><?= e($p['meta']['level']) ?></td>
                        <td><?= e($p['meta']['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($data['quests']): ?>
            <h2>📌 Quests</h2>
            <?php foreach ($data['quests'] as $status => $rows): ?>
                <h3><?= e($status) ?></h3>
                <table class="dash-table">
                    <thead><tr><th>Quest</th><th>From</th><th>Reward</th><th>Since / done</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $q): ?>
                        <tr>
                            <td><a href="<?= $link($q) ?>"><?= e($q['title']) ?></a></td>
                            <td><?= e($q['meta']['quest-giver']) ?></td>
                            <td><?= e($q['meta']['reward']) ?></td>
                            <td><?= e($q['meta']['completed-session'] ?: $q['meta']['started-session']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($data['enemies']): ?>
            <h2>⚔️ Who's still out there</h2>
            <p class="muted">Enemies still alive or unaccounted for:</p>
            <ul>
                <?php foreach ($data['enemies'] as $en): ?>
                    <li><a href="<?= $link($en) ?>"><?= e($en['title']) ?></a>
                        <?= $en['meta']['race'] ? '<span class="muted">— ' . e($en['meta']['race']) . '</span>' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($data['sessions']): ?>
            <h2>📜 Sessions</h2>
            <table class="dash-table">
                <thead><tr><th>#</th><th>Title</th><th>Date</th><th>Note-taker</th></tr></thead>
                <tbody>
                <?php foreach ($data['sessions'] as $s): ?>
                    <tr>
                        <td><?= e($s['meta']['session-number']) ?></td>
                        <td><a href="<?= $link($s) ?>"><?= e($s['title']) ?></a></td>
                        <td><?= e($s['meta']['played-on']) ?></td>
                        <td><?= e($s['meta']['note-taker']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($data['items']): ?>
            <h2>💎 Items</h2>
            <table class="dash-table">
                <thead><tr><th>Item</th><th>Rarity</th><th>Owner</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($data['items'] as $it): ?>
                    <tr>
                        <td><a href="<?= $link($it) ?>"><?= e($it['title']) ?></a></td>
                        <td><?= e($it['meta']['rarity']) ?></td>
                        <td><?= e($it['meta']['owner']) ?></td>
                        <td><?= e($it['meta']['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</div>
