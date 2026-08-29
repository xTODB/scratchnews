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
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Changelog</h2>
    <p>0.25.1 - QOL changes and features!<br><ul>
        <li>Toggle to turn off group activity messages in Settings or per individual group.</li>
        <li>Changed invites for efficiency, no page reaccess when inviting someone (just a message).<li>
        <li>Included replies, group comments, profile comments and profile replies as comments in statistics and comments by a profile.</li>
        <li>Added/Fixed/Closed feedback tags</li>
        <li>Messages and comment actions directly send you to the comment / parent comment after doing the comment action</li>
        <li>Changed article attachment to addition, added panel to add your articles into a group or via link</li>
    <p>[Aug29] v0.25 - 🥳 BIGGEST. UPDATE. EVER.<br><ul>
        <li>Backups for ScratchNews (just in case)</li>
        <li>Changes to articles: "Featured" article list, minimum 250 words for articles, changed UI for how article and homepage looks<li>
        <li>Changes to UI: Changed header icons to SVG, replaced banners with a welcome banner, replaced share banner below article with a join user banner</li>
        <li>Font changes on articles</li>
        <li>Homepage redesign with groups and profiles</li>
        <li>Fixed ban bug (commenting links used to ban you) and profiles bug (profile descriptions on homepage were too wide)</li>
        <li>Fixed comment thread bug (on mobile, comment threads were so long that the website went off-screen)</li>
        <li>Retention changes: website notifications for articles, bounce rate stats etc.</li>
    </ul>
    <p>[Aug24] v0.24.1 - Featured tag to API (will add featured articles soon), Moderators can approve Group Requests, Moderators can only see 5 articles with their titles and summaries: "View All Submissions" button and "View Full Article" button, Moderators only see first 5 feedbacks, "View All" button for all of them, fixed critical bug (group members could promote themselves to managers), Put groups in public Stats, "Group Activity" message, fixed Search text bug (when switching from ?type=articles to ?type=profiles or ?type=groups, the "Search articles..." text still remained), More Article Pages in Homepage per Tiny Article Section (5), fixed UI bug: /profiles doesn't show Groups icon selected even if Groups is there, Article Explanation why Submitted Article was rejected (with rejection, is optional), Word List Download (for security and for another login method), Change password, Expand ScratchNews API further, Back-and-Forth infinite feedback replies</p>
    <p>[Aug23] v0.24 - It. Is. Out.<br><b>Groups.</b> A new way for ScratchNews users to connect (and one of the most worked on features yet) are <em>Groups.</em> Create, edit and delete your own groups, invite people to groups, public group invites and group invite links. Members, managers, host, wall of comments, attach articles to Groups, personalize comment and image permissions. Managers and hosts can comment with images, time out, kick and promote users. There's so much stuff that I can't say it all in a single sentence - view the <a href="https://scratchnews.freedev.app/groups">public group page</a> for yourself.<br><b>Integration of groups and search update.</b> Profiles is now in the same page with Groups. Search not only articles, but profiles and groups.<br><b><em>This update will change the site forever.</b></em></p>
    <p>[Aug22] v0.23.3 - Fixed Comment Thread bug, Edit and Unpublish your Articles once they're Posted, Reply to Feedback, Images on Feedback, Polls (only I can make them right now haha)</p>
    <p>[Aug21] v0.23.2 - Maintenance mode, moved site to IFastNet, Comment Auth, API works normally</p>
    <p>[Aug19] v0.23.1 - Comma formatting on numbers bigger than 999, Related Articles + Extra Articles, hover toolbar on the three big on the homepage and Explore (like/dislike/share/comment/writer's profile), three-dot menu on tiny explore and search articles.</p>
    <p>[Aug16] v0.23 - Share ID (SID) system when sharing articles, badges when shares get clicks, share banners, share badges, fresh new Stats and Admin Stats pages, icon nav changed on mobile to bottom bar</p>
    <p>[Aug15] v0.23 Beta - Fixed hover-overlay hangover bug in Profiles, polished header and profile card, public API working (just with many bypasses XD)</p>
    <p>[Aug14] v0.23 Alpha - Added icons instead of text in header, Profiles page, new simple Search page</p>
    <p>[Aug12] v0.22 - Added automatic translations to the site (8 languages), "Translate Articles" feature in Settings and language bar at the footer. Next update gon be a lil fire trust 🤞</p>
    <p>[Aug11] v0.21 - Fixed nav bar cutoff for some smaller PCs, clear formatting button now also just resets all formatting when clicked and nothing's selected, Autosave (autosaves your article), Auto-Color Links (color links blue when making article, available in settings)</p>
    <p>[Aug10] v0.2 - Mild update! Articles FINALLY linked to user, profile picture next to article/profile comments/replies, and...DESKTOP APP! That's right, ScratchNews has an app now! Scroll down to the footer, More and Download! The app updates sync with the website's. It's free, like everything ScratchNews-related!</p>
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
    <p>v0.14 - Like v0.14 Beta but with bug fixes, removed search icon cuz it made bugs on mobile</p>
    <p>[Jul26] v0.14 Beta - To congratulate hitting 15 users... HUGE update! Explore page, filter in Explore page, categories, and email subscription!<br>Submit Article and Explore buttons added!<br>We know that there still are some bugs (like search icon in wrong position for mobile, explore text looking weird). We promise to fix them today!! Stay tuned and check ScratchNews every now and then to see new updates.</p>
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