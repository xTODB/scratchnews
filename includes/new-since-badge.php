<?php
// Renders a dismissible "N new X since your last visit" badge. Caller must set
// $newSinceCount (int), $newSinceCookie (string, the cookie name used to compute it),
// and $newSinceLabel (string, e.g. "new articles") before including this file.
// The count only clears when the visitor explicitly clicks the dismiss button
// (handled by the delegated click listener in includes/footer.php) - never on
// ordinary page load, so it doesn't evaporate the instant the page is opened.
if (!empty($newSinceCount)):
?>
<div class="new-since-badge" data-cookie="<?= e($newSinceCookie) ?>">
    <span><?= (int)$newSinceCount ?> <?= e($newSinceLabel) ?> since your last visit</span>
    <button type="button" class="new-since-dismiss" aria-label="Dismiss">&times;</button>
</div>
<?php endif; ?>
