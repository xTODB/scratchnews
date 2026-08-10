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
<title>Submission Guidelines - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2>Submission Guidelines</h2>
    <h3>Welcome to the submission guide!</h3>
    <p>
        This is a guide on how to make good articles on ScratchNews.
        Good articles get approved and directly posted to the Homepage, Explore and Search for everyone to see. You will get a "Writer" rank for writing 1 approved article and a "Featured Writer" for 3. 
        Here's all the steps to make a good ScratchNews article, step-by-step:
    </p>

    <h3>1: Get a good article idea.</h3>
    <p>When first writing an article, remember that the idea is what will get it approved and what will get your article many likes, comments and shares. ScratchNews has only around 25 articles, but there's so much more we 
haven't talked about!</p>
    <strong>The history of Scratchers, tutorials, events, projects, studios, or even the community itself: ScratchNews has lots of potential for articles, and you can become a writer yourself.</strong>
    <em>Get an idea, go on <a href="https://scratchnews.freedev.app/submit">the submission article page</a> and write out the basics: title, summary, and maybe even a nice thumbnail image. <strong>The world is waiting for your article and you have all the tools to write it!</strong></em>
    <h3>2: Use proper formatting and don't make these common mistakes.</h3>
    <ul>
     <li>When making an article, it is recommended to not use two big formats (e.g. Heading 1 + Huge). This applies to bold (don't make Unicode bold text and bold it again), italics and every formatting method that can be formatted using Unicode. Use Heading 1 for opening text (or don't use it at all), Heading 2 for chapter titles, Heading 3 for the last chaper/unchaptered text that's important, but not in the content and Normal for the content itself.</li>
     <li>Do not copy and paste text from somewhere else unedited unless it is from a raw text editor (like Notepad or your notes app), it will probably either use your theme as the outline color, or it will use formatting effects automatically. To convert text to its default form, press the "default" (reload icon) button. Use your own words when making an article and do not use AI to write or "help".</li>
     <li>Use newlines to indicate slight context changed in content or 2 newlines for different sections. Use images or screenshots to provide more context about the article itself.</li>
     <li>If you made a long article but want to go offline, make sure to click "Save as Draft" - it will automatically save your article in your My Articles > Drafts section.</li>
     <li>If you made a long article but formatting is hard because you have to scroll up to the toolbar, try the "⇕" (toolbar direction changer) button. It will put the toolbar at the bottom of the article, giving you better access to formatting.</li>
     <li>If you're using VPNs or have multiple ScratchNews tabs that use logins it is advised to copy your article (click the copy button/icon) or click anywhere in the article > Ctrl+A (Select all) > Ctrl+C (Copy) before pressing any submit/save button. This can save your article if the account is wrong or if ScratchNews has bugs.</li>
     <li>When using the linking tool, it's recommended to make the link text blue and/or italic. This indicates to users that it's a proper link.</li>
    </ul>
    <h3>3: Submit!</h3>
    <p>Press the "Submit" / "Submit Article" button once you're done writing it. We'll approve or reject it pretty fast and update you on your profile comments. Thanks for reading this guide and for deciding to help inform Scratch, one article at a time!</p>
    
    <h3>What gets rejected</h3>
    <ul>
        <li>Anything targeting or harassing a specific person or group.</li>
        <li>Spam, self-promotion with no news value, or ads.</li>
        <li>Misinformation, or claims you can't back up.</li>
        <li>Content unrelated to Scratch or its community.</li>
        <li>Anything that wouldn't be allowed under Scratch's own community guidelines.</li>
    </ul>
    <h3>What happens after you submit</h3>
    <p>
        Your submission goes into a review queue. A moderator or admin will approve it (it goes
        live as a published article, credited to you) or reject it. Rejected submissions aren't
        published, but you're welcome to revise and resubmit if the issue was fixable — poor
        formatting or an unclear source, for instance, rather than a guideline violation.
    </p>

    <h3>Why submit?</h3>
    <p>
        Every article you write counts toward your writer rank on your profile: 1 published
        article makes you a <strong>Writer</strong>, 3 makes you a <strong>Featured Writer</strong>.
        It's also just the most direct way to shape what ScratchNews actually covers.
    </p>
    <p><a href="/submit" class="btn">Submit an Article</a></p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>