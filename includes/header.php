<header id="siteHeader">
    <div class="header-left">
        <a href="/" class="logo-link">
<svg viewBox="0,0,136.90609,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33195,-164.3336)"><g stroke-miterlimit="10"><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.64879h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88045,195.6664v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.40189,164.35122h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56653,164.33361v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.06459,189.91166l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
</a>
        <div class="header-quick-links">
            <a href="/submit" class="header-quick-link">Submit Article</a>
            <a href="/explore" class="header-quick-link">Explore</a>
            <a href="/download" class="header-quick-link">Download</a>
            <a href="/api.php" class="header-quick-link">API</a>
            <a href="https://ko-fi.com/scratchnews" class="header-quick-link">Donate</a>
        </div>
    </div>
<form method="get" action="/search" class="search-form">
    <input type="text" name="q" placeholder="Search articles...">
</form>
<nav>
    <?php if (!empty($_SESSION['reader_username'])):
        $navUser = getUserById($_SESSION['reader_id']);
        $navAvatar = $navUser['avatar_url'] ?? null;
        $unreadCount = getUnreadNotificationCount($_SESSION['reader_id']);
    ?>
        <a href="/messages" class="nav-messages-link" title="Messages">
            <img src="/assets/icons/message.svg" class="icon-svg" alt="Messages">
            <?php if ($unreadCount > 0): ?>
                <span class="nav-messages-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <div class="user-nav">
            <button class="user-nav-toggle" onclick="document.getElementById('userMenu').classList.toggle('open')">
                <?php if ($navAvatar): ?>
                    <img src="<?= e($navAvatar) ?>" alt="" class="user-nav-avatar">
                <?php else: ?>
                    <span class="user-nav-avatar user-nav-avatar-placeholder"><?= e(mb_strtoupper(mb_substr($_SESSION['reader_username'], 0, 1))) ?></span>
                <?php endif; ?>
                <?= e($_SESSION['reader_username']) ?> &#9662;
            </button>
            <div id="userMenu" class="user-nav-menu">
                <a href="/@<?= e($_SESSION['reader_username']) ?>">Profile</a>
                <a href="/my-articles">My Articles</a>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="/admin/">Admin</a>
                <?php elseif (!empty($_SESSION['is_moderator'])): ?>
                <a href="/moderator">Moderator</a>
                <?php endif; ?>
                <a href="/settings">Settings</a>
                <a href="/logout">Log Out</a>
            </div>
        </div>
    <?php else: ?>
        <a href="/login">Log In</a>
        <a href="/register">Sign Up</a>
    <?php endif; ?>
</nav>
<style>
.nav-messages-link { position: relative; display: inline-flex; align-items: center; align-self: center; vertical-align: middle; margin: 0 1.5rem 0 0.75rem; }
.nav-messages-link .icon-svg { width: 30px; height: 30px; vertical-align: middle; }
.nav-messages-badge {
    position: absolute; top: -9px; right: -11px;
    background: #ff9c2b; color: #fff;
    font-size: 0.8rem; font-weight: 700;
    line-height: 1; padding: 3px 7px;
    border-radius: 999px; min-width: 22px; text-align: center;
}
</style>
</header>