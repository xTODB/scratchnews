<?php if (empty($_SESSION['reader_username'])): ?>
<section class="welcome-banner">
    <div class="welcome-banner-text">
        <h1>Welcome to ScratchNews</h1>
        <p>ScratchNews is a place for Scratchers to connect and a place to get informed about the latest Scratch News.</p>
        <div class="welcome-banner-actions">
            <a href="/explore" class="btn inline welcome-banner-btn">Explore Articles</a>
            <a href="/groups" class="btn inline welcome-banner-btn">Surf Groups</a>
            <a href="/register" class="btn inline welcome-banner-btn welcome-banner-join">Join</a>
        </div>
    </div>
    <div class="welcome-banner-media">
        <img src="/assets/welcome-banner.gif" alt="" class="welcome-banner-img">
    </div>
</section>
<?php endif; ?>
