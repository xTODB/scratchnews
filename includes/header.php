<header id="siteHeader">
    <div class="header-left">
        <a href="/" class="logo-link">
<svg viewBox="0,0,136.90609,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33195,-164.3336)"><g stroke-miterlimit="10"><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.64879h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88045,195.6664v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.40189,164.35122h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56653,164.33361v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.06459,189.91166l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
</a>
    </div>
<?php
    // Which nav icon is "active" - based on the physical PHP file being run,
    // which stays reliable even through the clean-URL rewrites in .htaccess.
    $__navPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $__unreadCount = !empty($_SESSION['reader_id']) ? getUnreadNotificationCount($_SESSION['reader_id']) : 0;
?>
<div class="header-icon-nav">
    <a href="/" class="header-icon-link <?= $__navPage === 'index.php' ? 'active' : '' ?>" title="Home">
        <span class="header-icon-mask icon-home"></span>
    </a>
    <a href="/explore" class="header-icon-link <?= $__navPage === 'explore.php' ? 'active' : '' ?>" title="Explore">
        <span class="header-icon-mask icon-explore"></span>
    </a>
    <a href="/groups" class="header-icon-link <?= in_array($__navPage, ['groups.php', 'group.php', 'create-group.php', 'profiles.php'], true) ? 'active' : '' ?>" title="Groups">
        <span class="header-icon-mask icon-groups"></span>
    </a>
    <?php if (!empty($_SESSION['reader_username'])): ?>
    <a href="/submit" class="header-icon-link <?= $__navPage === 'submit.php' ? 'active' : '' ?>" title="Submit Article">
        <span class="header-icon-mask icon-submit"></span>
    </a>
    <a href="/messages" class="header-icon-link header-icon-messages <?= $__navPage === 'messages.php' ? 'active' : '' ?>" title="Messages">
        <span class="header-icon-mask icon-message"></span>
        <?php if ($__unreadCount > 0): ?>
            <span class="nav-messages-badge"><?= $__unreadCount > 99 ? '99+' : $__unreadCount ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="/search" class="header-icon-link <?= $__navPage === 'search.php' ? 'active' : '' ?>" title="Search">
        <span class="header-icon-mask icon-search"></span>
    </a>
</div>
<nav>
    <div class="app-menu-nav">
        <button class="app-menu-toggle" onclick="document.getElementById('appMenu').classList.toggle('open')" title="Menu">
            <span class="header-icon-mask icon-appmenu"></span>
        </button>
        <div id="appMenu" class="app-menu-dropdown">
            <div class="app-menu-title">Menu</div>
            <div class="app-menu-list">
                <a href="/explore" class="app-menu-item primary">Explore <span class="app-menu-arrow">&#8250;</span></a>
                <a href="/groups" class="app-menu-item primary">Groups <span class="app-menu-arrow">&#8250;</span></a>
                <a href="/profiles" class="app-menu-item primary">Profiles <span class="app-menu-arrow">&#8250;</span></a>
                <a href="/writers-contest" class="app-menu-item secondary">Writers' Contest <span class="app-menu-arrow">&#8250;</span></a>
                <span class="app-menu-item secondary disabled" title="Forums haven't launched yet">Forums <small>(coming soon)</small></span>
                <div class="app-menu-divider"></div>
                <a href="/about" class="app-menu-item secondary">About</a>
                <?php if (!empty($_SESSION['reader_username'])): ?>
                <a href="/settings" class="app-menu-item secondary">Settings</a>
                <a href="/@<?= e($_SESSION['reader_username']) ?>" class="app-menu-item secondary">My Profile</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!empty($_SESSION['reader_username'])):
        $navUser = getUserById($_SESSION['reader_id']);
        $navAvatar = $navUser['avatar_url'] ?? null;
    ?>
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
                <div class="user-nav-menu-profile">
                    <?php if ($navAvatar): ?>
                        <img src="<?= e($navAvatar) ?>" alt="" class="user-nav-menu-avatar">
                    <?php else: ?>
                        <span class="user-nav-menu-avatar user-nav-avatar-placeholder"><?= e(mb_strtoupper(mb_substr($_SESSION['reader_username'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div class="user-nav-menu-profile-info">
                        <strong>@<?= e($_SESSION['reader_username']) ?></strong>
                        <a href="/@<?= e($_SESSION['reader_username']) ?>" class="btn inline user-nav-visit-btn">Visit Profile</a>
                    </div>
                </div>
                <div class="user-nav-menu-divider"></div>
                <a href="/my-articles"><span class="header-icon-mask icon-articles"></span> My Articles</a>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="/admin/"><span class="header-icon-mask icon-moderator"></span> Admin</a>
                <?php elseif (!empty($_SESSION['is_moderator'])): ?>
                <a href="/moderator"><span class="header-icon-mask icon-moderator"></span> Moderator</a>
                <?php endif; ?>
                <a href="/settings"><span class="header-icon-mask icon-settings"></span> Settings</a>
                <a href="https://ko-fi.com/scratchnews"><span class="header-icon-mask icon-donate"></span> Donate</a>
                <a href="/logout"><span class="header-icon-mask icon-logout"></span> Log Out</a>
            </div>
        </div>
    <?php else: ?>
        <a href="/login">Log In</a>
        <a href="/register">Sign Up</a>
    <?php endif; ?>
</nav>
<style>
.header-icon-nav {
    display: flex; align-items: center; gap: 0.9rem;
    position: absolute; left: 50%; top: 50%;
    transform: translate(-50%, -50%);
}
.header-icon-link {
    position: relative;
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px;
    border-radius: 10px;
    padding-bottom: 4px;
    background: rgba(0,0,0,0.12);
    border-bottom: 2px solid transparent;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.header-icon-link:hover { background: rgba(0,0,0,0.22); }
.header-icon-link.active { background: rgba(255,255,255,0.28); border-bottom-color: #fff; }
.header-icon-messages { overflow: visible; }
.nav-messages-badge {
    position: absolute; top: -8px; right: -10px;
    background: #ff9c2b; color: #fff;
    font-size: 0.8rem; font-weight: 700;
    line-height: 1; padding: 3px 6px;
    border-radius: 999px; min-width: 20px; text-align: center;
}

/* --- Outline icon system: single SVG shape recolored via CSS mask, so the
   same file can sit at "ink" color normally and flip to brand orange on
   hover/active without needing separate colored SVG variants. --- */
.header-icon-mask {
    display: inline-block;
    width: 30px; height: 30px;
    background-color: #14181c;
    -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat;
    -webkit-mask-position: center; mask-position: center;
    -webkit-mask-size: contain; mask-size: contain;
    transition: background-color 0.15s ease;
    background-color: #ffb957
}
.header-icon-link:hover .header-icon-mask,
.header-icon-link.active .header-icon-mask { background-color: var(--brand-bright, #ffaa33); }
.icon-home { -webkit-mask-image: url(/assets/icons/nav-home.svg); mask-image: url(/assets/icons/nav-home.svg); }
.icon-explore { -webkit-mask-image: url(/assets/icons/nav-explore.svg); mask-image: url(/assets/icons/nav-explore.svg); }
.icon-groups { -webkit-mask-image: url(/assets/icons/nav-groups.svg); mask-image: url(/assets/icons/nav-groups.svg); }
.icon-submit { -webkit-mask-image: url(/assets/icons/nav-submit.svg); mask-image: url(/assets/icons/nav-submit.svg); }
.icon-message { -webkit-mask-image: url(/assets/icons/nav-message.svg); mask-image: url(/assets/icons/nav-message.svg); }
.icon-search { -webkit-mask-image: url(/assets/icons/nav-search.svg); mask-image: url(/assets/icons/nav-search.svg); }
.icon-articles { -webkit-mask-image: url(/assets/icons/nav-articles.svg); mask-image: url(/assets/icons/nav-articles.svg); }
.icon-settings { -webkit-mask-image: url(/assets/icons/nav-settings.svg); mask-image: url(/assets/icons/nav-settings.svg); }
.icon-moderator { -webkit-mask-image: url(/assets/icons/nav-moderator.svg); mask-image: url(/assets/icons/nav-moderator.svg); }
.icon-donate { -webkit-mask-image: url(/assets/icons/nav-donate.svg); mask-image: url(/assets/icons/nav-donate.svg); }
.icon-logout { -webkit-mask-image: url(/assets/icons/nav-logout.svg); mask-image: url(/assets/icons/nav-logout.svg); }
.icon-appmenu { -webkit-mask-image: url(/assets/icons/nav-menu.svg); mask-image: url(/assets/icons/nav-menu.svg); }

/* --- App Menu (3x3 grid dropdown) --- */
.app-menu-nav { position: relative; margin-right: 0.6rem; }
.app-menu-toggle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px;
    border: none; border-radius: 50%;
    background: rgba(0,0,0,0.14);
    cursor: pointer;
    transition: background 0.15s ease;
}
.app-menu-toggle:hover { background: rgba(0,0,0,0.26); }
.app-menu-toggle .header-icon-mask { width: 20px; height: 20px; }
.app-menu-dropdown {
    display: none;
    position: absolute; top: calc(100% + 10px); right: 0;
    width: 240px;
    background: #fff; color: #14181c;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    padding: 0.6rem 0;
    z-index: 900;
}
.app-menu-dropdown.open { display: block; }
body.dark .app-menu-dropdown { background: #22262b; color: #f0f0f0; }
.app-menu-title {
    font-weight: 700; font-size: 1.05rem;
    padding: 0.3rem 1rem 0.6rem;
    border-bottom: 1px solid #eee;
    margin-bottom: 0.3rem;
}
body.dark .app-menu-title { border-bottom-color: #333; }
/* Scrollable + easy to extend: new items just need another .app-menu-item row,
   the list grows/scrolls on its own rather than needing a redesign. */
.app-menu-list { max-height: 60vh; overflow-y: auto; }
.app-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    color: inherit; text-decoration: none;
    font-size: 1rem;
}
.app-menu-item:hover { background: rgba(255,170,51,0.12); }
.app-menu-item.primary { font-size: 1.15rem; font-weight: 600; }
.app-menu-item.secondary { font-size: 0.95rem; }
.app-menu-item.disabled { color: #999; cursor: default; }
.app-menu-item.disabled:hover { background: none; }
.app-menu-item.disabled small { font-size: 0.8rem; }
.app-menu-arrow { opacity: 0.5; }
.app-menu-divider { height: 1px; background: #eee; margin: 0.4rem 0.9rem; }
body.dark .app-menu-divider { background: #333; }

/* Dropdown-menu icon rows: 20px, sit on currentColor so they auto-match the
   link's text color in both light and dark theme without a separate override. */
.user-nav-menu a .header-icon-mask {
    width: 20px; height: 20px;
    background-color: currentColor;
    vertical-align: -5px;
    margin-right: 0.5rem;
}
.user-nav-menu a:hover .header-icon-mask { background-color: var(--brand-bright, #ffaa33); }

.user-nav-menu-profile {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.8rem 1rem;
}
.user-nav-menu-avatar {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.user-nav-menu-profile-info { display: flex; flex-direction: column; gap: 0.35rem; }
.user-nav-menu-profile-info .user-nav-visit-btn {
    display: inline-flex; align-items: center; justify-content: center;
    height: 28px; width: auto; padding: 0 0.7rem; font-size: 0.8rem; margin-top: 0;
    color: #fff; text-decoration: none;
}
.user-nav-menu-divider { height: 1px; background: #eee; margin: 0 0.2rem; }
body.dark .user-nav-menu-divider { background: #333; }

@media (max-width: 700px) {
    .header-icon-nav {
        position: fixed; left: 0; right: 0; bottom: 0; top: auto;
        transform: none;
        margin: 0;
        gap: 0;
        justify-content: space-around;
        padding: 0.35rem 0.25rem calc(0.35rem + env(safe-area-inset-bottom));
        background: #d99d4a;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
        z-index: 500;
    }
    .header-icon-link { width: 42px; height: 42px; border-radius: 8px; }
    .header-icon-mask { width: 26px; height: 26px; }
    body { padding-bottom: calc(58px + env(safe-area-inset-bottom)); }
}
</style>
<script>
document.addEventListener('click', function (e) {
    var menu = document.getElementById('appMenu');
    if (menu && !e.target.closest('.app-menu-nav')) menu.classList.remove('open');
});
</script>
</header>
<?php
    $__navBanStatus = !empty($_SESSION['reader_id']) ? getUserBanStatus((int)$_SESSION['reader_id']) : ['banned' => false, 'reason' => null];
    if ($__navBanStatus['banned'] && !in_array($__navPage, ['banned.php', 'signout.php'], true)):
?>
<div class="alert error" style="border-radius:0;margin:0;text-align:center;">
    Your account has been banned<?= $__navBanStatus['reason'] ? ': ' . e($__navBanStatus['reason']) : '.' ?>
    <a href="/banned">View details</a>
</div>
<?php endif; ?>