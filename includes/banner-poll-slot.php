<?php
// Renders whichever the weighted draw picked: a banner (existing markup/dismissal
// behavior, unchanged) or a poll widget. See getBannerOrPollSlot() in functions.php.
$slot = getBannerOrPollSlot();
?>
<?php if ($slot && $slot['type'] === 'banner'): $b = $slot['banner']; ?>
<div id="promoBanners">
    <div class="promo-banner" data-banner-id="<?= (int)$b['id'] ?>" data-banner-key="<?= (int)$b['id'] ?>|<?= e($b['image_url']) ?>">
        <button type="button" class="promo-banner-close" aria-label="Close">&times;</button>
        <a href="<?= e($b['link']) ?>" class="promo-banner-link">
            <img src="<?= e($b['image_url']) ?>" alt="" class="promo-banner-img">
        </a>
    </div>
</div>
<script>
(function() {
    // Dismissal is keyed by id+image_url, not just id: InfinityFree's MySQL can
    // recalculate AUTO_INCREMENT as MAX(id)+1 (MyISAM behavior), so deleting the
    // highest-numbered banner and creating a new one can silently reuse that numeric
    // id. Keying on id alone meant a brand new banner could inherit an old, already-
    // dismissed one's id and never show up. image_url is unique per upload, so pairing
    // it with the id tells a genuinely new banner apart from a reused id.
    var dismissed = [];
    try { dismissed = JSON.parse(localStorage.getItem('dismissedBanners') || '[]'); } catch (e) {}
    document.querySelectorAll('.promo-banner').forEach(function(el) {
        var key = el.getAttribute('data-banner-key');
        if (dismissed.indexOf(key) !== -1) { el.remove(); return; }
        el.querySelector('.promo-banner-close').addEventListener('click', function() {
            dismissed.push(key);
            try { localStorage.setItem('dismissedBanners', JSON.stringify(dismissed)); } catch (e) {}
            el.remove();
        });
    });
})();
</script>
<?php elseif ($slot && $slot['type'] === 'poll' && !empty($slot['poll']['expired'])): $p = $slot['poll'];
    $results = $p['results'] ?? [];
    $totalVotes = array_sum(array_column($results, 'votes'));
?>
<div id="promoPoll" class="promo-poll promo-poll-results" data-poll-id="<?= (int)$p['id'] ?>">
    <p class="promo-poll-question"><?= e($p['question']) ?></p>
    <p class="promo-poll-ended-label">Poll ended - here's how it went:</p>
    <?php foreach ($results as $opt): $pct = $totalVotes > 0 ? round(($opt['votes'] / $totalVotes) * 100) : 0; ?>
        <div class="promo-poll-result-row">
            <div class="promo-poll-result-label"><?= e($opt['option_text']) ?> <span class="promo-poll-result-pct"><?= $pct ?>% (<?= (int)$opt['votes'] ?>)</span></div>
            <div class="promo-poll-result-bar-track"><div class="promo-poll-result-bar" style="width:<?= $pct ?>%;"></div></div>
        </div>
    <?php endforeach; ?>
</div>
<?php elseif ($slot && $slot['type'] === 'poll'): $p = $slot['poll']; ?>
<div id="promoPoll" class="promo-poll" data-poll-id="<?= (int)$p['id'] ?>">
    <form class="promo-poll-form" data-poll-type="<?= e($p['poll_type']) ?>">
        <?= csrfField() ?>
        <p class="promo-poll-question"><?= e($p['question']) ?></p>
        <?php foreach ($p['options'] as $opt): ?>
            <label class="promo-poll-option">
                <input type="<?= $p['poll_type'] === 'multi' ? 'checkbox' : 'radio' ?>" name="option_ids[]" value="<?= (int)$opt['id'] ?>">
                <?= e($opt['option_text']) ?>
            </label>
        <?php endforeach; ?>
        <button type="submit" class="btn promo-poll-submit">Vote</button>
        <p class="promo-poll-message" hidden></p>
    </form>
</div>
<script>
(function() {
    var form = document.querySelector('#promoPoll .promo-poll-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var pollId = document.getElementById('promoPoll').getAttribute('data-poll-id');
        var msg = form.querySelector('.promo-poll-message');
        var body = new FormData(form);
        body.append('poll_id', pollId);
        fetch('/vote-poll.php', { method: 'POST', body: body })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                msg.hidden = false;
                if (data.success) {
                    msg.textContent = 'Thanks for voting!';
                    form.querySelectorAll('input, button').forEach(function(el) { el.disabled = true; });
                } else {
                    msg.textContent = data.error || 'Vote failed. Please try again.';
                }
            })
            .catch(function() {
                msg.hidden = false;
                msg.textContent = 'Vote failed. Please try again.';
            });
    });
})();
</script>
<?php endif; ?>