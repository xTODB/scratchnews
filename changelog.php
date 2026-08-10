<?php
require_once __DIR__ . '/functions.php';
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>About - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2>Changelog</h2>
    <p>0.19.1 - Super duper tiny, made message number and orange margin bigger and fixed moderation words margin in admin</p>
    <p>[Aug9] v0.19 - You know it's good when TODB blesses us with a new update 🌹 this took soo long. Follower auth, changed banner from multiple banners all at once to random, FINALLY FIXED USER ID BUG, fixed copy glitch not copying formatting and making it both select and unselect, default (reload icon) in article toolbar, resets selected text to its normal form, article submission guidelines, ranks "Writer" (1 article) "Featured Writer" (3 articles) "Fan" (donate) "Moderator" (ScratchNews moderators) "Dev" (well, me), updated about page with image, featured writers and fans section in it. Wow.</p>
    <p>[Aug6] v0.18 - 3 days of work, worth it! Image banners, @mentions and @mentions notifications in comments, "Collective Time" Admin Stat, revamped profile comments and clear URLs sitewide (no more .php)</p>
    <p>[Aug3] v0.17.1 - Fixed Submit Page, added images and save as draft (you can see it in My Articles)</p>
    <p>[Aug2] v0.17 - Pretty good update! Messages, Moderation Filter</p>
    <p>v0.16.1 - Username changing (Settings > Customization), fixed dark mode footer staying dark on light mode, copy button turned to simple icon, added article saving, "My Articles" page (your articles plus the saved ones)</p>
    <p>[Aug1] v0.16 - SUPER MASSIVE UPDATE! Profile pics, banners, descriptions, new account signup page, signin and login with Google, settings page, copy article content to clipboard (so that the content is saved even if there's an error while saving), donation page, refreshed profile pages, and PROFILE COMMENTS!!! This is a great update.</p>
    <p>[Jul30] v0.15.2 - Added Fallback API</p>
    <p>v0.15.1 - Session tracking for "Time On Site", exclude/delete/flag sessions</p>
    <p>[Jul29] v0.15 - Changed API IP Allow to API Key Allow, everyone gets free 30 a minute API requests. Added views and article-to-user tying for admins.</p>
    <p>[Jul27/28] v0.14.2 - ScratchNews Bot</p>
    <p>[Jul27] v0.14.1 - Added Read-only API</p>
    <p>v0.14 - Like v0.14 Preview but with bug fixes, removed search icon cuz it made bugs on mobile</p>
    <p>[Jul26] v0.14 Preview - To congratulate hitting 15 users... HUGE update! Explore page, filter in Explore page, categories, and email subscription!<br>Submit Article and Explore buttons added!<br>We know that there still are some bugs (like search icon in wrong position for mobile, explore text looking weird). We promise to fix them today!! Stay tuned and check ScratchNews every now and then to see new updates.</p>
    <p>[Jul25] v0.13.4 - Added articles showing in profile pages</p>
    <p>[Jul22] v0.13.3 - SEO</p>
    <p>[Jul20] v0.13.2 - Admin 'log in as' feature to protect against bots. But more importantly, first ever non-admin article and 5 users.</p>
    <p>[Jul18?] v0.13.1 - CSRF, no purple links when accessing links</p>
    <p>[Jul16/17?] v0.13 - Images.</p>
    <p>[Jul15/16?] v0.12.1 - Static Pages (about, changelog, community guidelines) added to replace admin pages</p>
    <p>[Jul14] v0.12 - Added Dislike, Share, new icons for social features, and moved social features at the top</p>
    <p>[Jul12] v0.11 - Moderation features: report comments, ban users, delete users and ban IPs.</p>  
    <p>v0.1 - Biggest update YET! Reply, reply to replies, website redesign (articles in different boxes), smooth size change, SEARCH BAR, unified menu for admins...</p>
    <p>v0.09 - Added Feedback page, moving ID articles for admins</p>
    <p>[Jul10] v0.08 - Delete Account at bottom of page, non-admin users can submit articles and get results via email, fixed /article/id linking before id is defined</p>
    <p>[Jul9] v0.07 - Email Verification</p>
    <p>[Jul8] v0.06 - Users, Delete Account at /delete-account</p>
    <p>[Jul7/8?] v0.05 - Link Text, fixed Admin tab showing to non-admin users</p>
    <p>[Jul7] v0.04 - Introduced account creation beyond admin, likes and comments. You will see a test comment below showing the new feature</p>
    <p>[Jul6] v0.03 - Formatting! Bold, Italic, Strikethrough, Headers, Colors, Color text and highlight color text!</p>
    <p>v0.02 - Branding. Logo and top page color.</p>
    <p>v0.01 - View articles and edit/create articles. Only the dev can create articles.</p>
    <p>[Jul5, 2026] v0.00 - Initial website, launched at scratchnews.freedev.app</p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>