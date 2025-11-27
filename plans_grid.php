<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
$pdo=db();
$plans=$pdo->query("SELECT id,name,price,duration_days,max_streams FROM plans ORDER BY price")->fetchAll();
?>
<div class="grid">
<?php foreach($plans as $p):
      // skip trial plans from paid list
      if((isset($p['is_trial']) && $p['is_trial']) || stripos($p['name'],'trial')!==false){ continue; }
?>
  <div class="card">
    <div class="badge"><?=e($p['name'])?></div>
    <div class="price">$<?=e($p['price'])?></div>
    <ul>
      <li><?= (int)$p['duration_days'] ?> days access</li>
      <li><?= (int)$p['max_streams'] ?> connections</li>
      <li>Adult content optional</li>
      <li>Instant delivery after PayPal</li>
    </ul>
    <a class="btn" href="checkout.php?plan=<?=$p['id']?>">Choose Plan</a>
  </div>
<?php endforeach; ?>
<?php if(empty($plans)): ?>
  <div class="card"><p class="muted">No plans available yet.</p></div>
<?php endif; ?>
</div>
