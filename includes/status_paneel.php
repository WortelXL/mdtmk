<?php
/**
 * Statusknoppen (OW/TP/IR/...) -- sinds V0.0.20 op de melding zelf (melding.php)
 * i.p.v. op het beginscherm. Verwacht: $instellingen, $mijn_status, $mijn_statussen,
 * $mijn_teams_namen en $status_paneel_melding_id (terug naar deze melding na het zetten).
 * De status zelf blijft 1 per persoon (zie zet_eenheidsstatus() in functions.php).
 */
?>
<?php if ($instellingen['toon_status_overzicht'] && $instellingen['rol_id']): ?>
<div class="panel status-panel">
    <input type="checkbox" id="status-toggle" class="status-toggle-checkbox">
    <label for="status-toggle" class="status-toggle-wrap">
        <h2>Mijn status<?= $mijn_teams_namen !== '' ? ' · ' . e($mijn_teams_namen) : '' ?></h2>
        <span class="status-toggle-chevron">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </span>
    </label>
    <div class="status-compact">
        <?php foreach ($mijn_statussen as $s): ?>
            <form method="post" action="/status.php">
                <input type="hidden" name="eenheidsstatus_id" value="<?= $s['id'] ?>">
                <input type="hidden" name="melding_id" value="<?= (int) $status_paneel_melding_id ?>">
                <button type="submit" class="status-btn-compact <?= $mijn_status && $mijn_status['id'] === $s['id'] ? 'actief' : '' ?>" title="<?= e($s['naam']) ?>">
                    <?= e($s['afkorting']) ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
    <div class="status-grid">
        <?php foreach ($mijn_statussen as $s): ?>
            <form method="post" action="/status.php">
                <input type="hidden" name="eenheidsstatus_id" value="<?= $s['id'] ?>">
                <input type="hidden" name="melding_id" value="<?= (int) $status_paneel_melding_id ?>">
                <button type="submit" class="status-btn <?= $mijn_status && $mijn_status['id'] === $s['id'] ? 'actief' : '' ?>">
                    <span class="afk"><?= e($s['afkorting']) ?></span>
                    <span class="naam"><?= e($s['naam']) ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
    <?php if (!$mijn_statussen): ?>
        <p class="log-leeg">Nog geen eenheidsstatussen ingesteld voor jouw rol (Beheer &gt; Eenheidsstatussen in MKAPP).</p>
    <?php endif; ?>
</div>
<?php endif; ?>
