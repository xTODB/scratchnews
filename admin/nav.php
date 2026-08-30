<header>
    <a href="/" class="logo-link">
<svg viewBox="0,0,98.76611,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33196,-164.33361)"><g stroke-miterlimit="10"><text transform="translate(217.16809,185.696) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">Mod</tspan></text><text transform="translate(217.16809,185.696) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">Mod</tspan></text><path d="M181.04509,195.6488h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88046,195.66641v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.4019,164.35123h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56654,164.33362v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.0646,189.91167l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
</a>
    <nav class="admin-nav">
        <button class="admin-nav-toggle" onclick="document.getElementById('modMenu').classList.toggle('open')">Menu &#9662;</button>
        <div id="modMenu" class="admin-nav-menu">
            <a href="/moderator">Overview</a>
            <a href="/admin/submissions">Submissions<?php $c = getPendingSubmissionsCount(); if ($c > 0): ?><span class="admin-nav-badge"><?= $c ?></span><?php endif; ?></a>
            <a href="/moderator/reports">Reported Comments<?php $c = getPendingReportsCount(); if ($c > 0): ?><span class="admin-nav-badge"><?= $c ?></span><?php endif; ?></a>
            <a href="/moderator/chat">Chat<?php $c = getChatUnreadCountForUser((int)($_SESSION['reader_id'] ?? 0)); if ($c > 0): ?><span class="admin-nav-badge"><?= $c ?></span><?php endif; ?></a>
            <a href="/admin/group-requests">Group Requests<?php $c = getPendingGroupRequestsCount(); if ($c > 0): ?><span class="admin-nav-badge"><?= $c ?></span><?php endif; ?></a>
            <a href="/admin/feedback">Feedback<?php $c = getPendingFeedbackCount(); if ($c > 0): ?><span class="admin-nav-badge"><?= $c ?></span><?php endif; ?></a>
            <a href="/moderator/polls">Polls</a>
            <a href="/admin/review-log">Review Log</a>
            <a href="/moderator/clear-timeout">Clear Timeout</a>
            <a href="/moderator-guidelines.php">Guidelines</a>
            <?php if (!empty($isHeadModerator)): ?>
            <a href="/moderator/users">Users</a>
            <a href="/admin/stats">Admin Stats</a>
            <?php endif; ?>
            <a href="/logout">Log Out</a>
        </div>
    </nav>
</header>
<style>
.admin-nav-menu a { position: relative; }
.admin-nav-badge {
    display: inline-flex; align-items: center; justify-content: center;
    background: #ff9c2b; color: #fff; font-size: 0.7rem; font-weight: 700;
    min-width: 18px; height: 18px; border-radius: 999px; padding: 0 5px;
    margin-left: 0.4rem; vertical-align: middle;
}
</style>