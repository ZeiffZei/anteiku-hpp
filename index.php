<?php
declare(strict_types=1);

/**
 * Anteiku HPP Calculator
 * Copyright (c) 2026 Galeh Said Tahdi. All rights reserved.
 *
 * Kalkulator Harga Pokok Penjualan (HPP) kopi bertema kedai Anteiku.
 * Satu file, tanpa database, tanpa dependensi. Butuh PHP 8.0+.
 * Jalankan: php -S localhost:8000
 */

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function qty(float $n): string { return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ','); }
function num(mixed $v): float { return max(0.0, (float) str_replace(',', '.', is_scalar($v) ? (string) $v : '0')); }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Resep bawaan. Format bahan: [nama, harga beli, isi kemasan, dipakai per batch, satuan]
$presets = [
    ['produk' => 'Anteiku Blend (kopi hitam)', 'porsi' => 10, 'kerja' => 15000, 'overhead' => 10000, 'kemasan' => 1500, 'margin' => 60, 'bahan' => [
        ['Biji kopi Anteiku Blend', 180000, 1000, 180, 'gr'],
        ['Gula pasir', 16000, 1000, 100, 'gr'],
        ['Air mineral', 20000, 19000, 2000, 'ml'],
        ['Kertas filter V60', 25000, 100, 10, 'pcs'],
    ]],
    ['produk' => 'Latte Kaneki', 'porsi' => 10, 'kerja' => 20000, 'overhead' => 12000, 'kemasan' => 2000, 'margin' => 65, 'bahan' => [
        ['Biji kopi espresso', 180000, 1000, 200, 'gr'],
        ['Susu UHT', 18000, 1000, 2500, 'ml'],
        ['Sirup gula aren', 45000, 750, 150, 'ml'],
        ['Es batu', 10000, 5000, 2000, 'gr'],
    ]],
    ['produk' => 'Mocha Touka', 'porsi' => 10, 'kerja' => 20000, 'overhead' => 12000, 'kemasan' => 2000, 'margin' => 65, 'bahan' => [
        ['Biji kopi espresso', 180000, 1000, 180, 'gr'],
        ['Susu UHT', 18000, 1000, 2000, 'ml'],
        ['Cokelat bubuk', 90000, 1000, 250, 'gr'],
        ['Whipped cream', 65000, 1000, 200, 'ml'],
    ]],
];

$state = $presets[0];
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';
$errors = [];
$hasil = null;

if ($posted) {
    $state = [
        'produk'   => trim(is_string($_POST['produk'] ?? null) ? $_POST['produk'] : '') ?: 'Produk tanpa nama',
        'porsi'    => num($_POST['porsi'] ?? 0),
        'kerja'    => num($_POST['kerja'] ?? 0),
        'overhead' => num($_POST['overhead'] ?? 0),
        'kemasan'  => num($_POST['kemasan'] ?? 0),
        'margin'   => min(90.0, num($_POST['margin'] ?? 0)),
        'bahan'    => [],
    ];
    $f = fn(string $k): array => is_array($_POST[$k] ?? null) ? $_POST[$k] : [];
    [$nama, $harga, $isi, $pakai, $satuan] = [$f('nama'), $f('harga'), $f('isi'), $f('pakai'), $f('satuan')];

    foreach ($nama as $i => $n) {
        $n = is_string($n) ? trim($n) : '';
        if ($n === '') continue;
        $state['bahan'][] = [$n, num($harga[$i] ?? 0), num($isi[$i] ?? 0), num($pakai[$i] ?? 0),
                             trim(is_string($satuan[$i] ?? null) ? $satuan[$i] : '')];
    }

    if ($state['porsi'] < 1) $errors[] = 'Isi hasil per batch minimal 1 porsi.';
    if (!$state['bahan']) $errors[] = 'Tambahkan minimal satu bahan baku yang punya nama.';
    foreach ($state['bahan'] as [$n, , $isiKemasan]) {
        if ($isiKemasan <= 0) $errors[] = "Isi kemasan \"$n\" harus lebih dari 0, misalnya 1000 untuk 1 kg.";
    }

    if (!$errors) {
        $rows = [];
        $totalBahan = 0.0;
        foreach ($state['bahan'] as [$n, $h, $k, $p, $s]) {
            $biaya = $h / $k * $p;
            $rows[] = ['n' => $n, 'pakai' => $p, 'sat' => $s, 'biaya' => $biaya];
            $totalBahan += $biaya;
        }
        $totalBatch = $totalBahan + $state['kerja'] + $state['overhead'];
        $perPorsi   = $totalBatch / $state['porsi'];
        $hpp        = $perPorsi + $state['kemasan'];
        $hargaJual  = ceil($hpp / (1 - $state['margin'] / 100) / 500) * 500;
        $laba       = $hargaJual - $hpp;
        $hasil = [
            'rows' => $rows, 'totalBahan' => $totalBahan, 'totalBatch' => $totalBatch,
            'perPorsi' => $perPorsi, 'hpp' => $hpp, 'hargaJual' => $hargaJual, 'laba' => $laba,
            'marginAktual' => $hargaJual > 0 ? $laba / $hargaJual * 100 : 0,
            'foodCost' => $hargaJual > 0 ? $totalBahan / $state['porsi'] / $hargaJual * 100 : 0,
        ];
    }
}

$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anteiku HPP: Kalkulator Harga Pokok Penjualan Kopi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@500;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#1a110e;--wood:#26180f;--line:#4a3426;--paper:#efe3cb;--ink:#2b1c14;--red:#b3121d;--mute:#a39382;--text:#f0e4cf}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:16px/1.55 'Zen Kaku Gothic New',system-ui,sans-serif;font-variant-numeric:tabular-nums}
.wrap{max-width:820px;margin:0 auto;padding:0 16px 56px}
header{position:relative;text-align:center;padding:40px 0 28px;overflow:hidden}
header::before{content:"";position:absolute;left:50%;top:-70px;width:260px;height:260px;margin-left:-130px;border:2px solid var(--red);border-radius:50%;opacity:.55;box-shadow:inset 0 0 0 24px rgba(179,18,29,.09)}
header>*{position:relative}
.jp{margin:0;font:700 15px 'Shippori Mincho',serif;letter-spacing:.6em;color:var(--red)}
h1{margin:8px 0 10px;font:700 clamp(28px,7vw,44px)/1.15 'Shippori Mincho',Georgia,serif}
.tag{max-width:46ch;margin:0 auto;color:var(--mute)}
h2{margin:26px 0 4px;font:700 20px 'Shippori Mincho',Georgia,serif}
.board{background:var(--wood);border:1px solid var(--line);border-radius:4px;padding:20px 18px}
label{display:block;font-size:14px;color:var(--mute);margin-bottom:14px}
input,select{display:block;width:100%;margin-top:4px;padding:10px 12px;background:#150d0a;color:var(--text);border:1px solid var(--line);border-radius:4px;font:inherit}
.grid{display:grid;gap:0 14px;grid-template-columns:1fr 1fr}
.hint{margin:0 0 10px;font-size:14px;color:var(--mute)}
.row{display:grid;gap:8px;grid-template-columns:1fr 1fr;padding:12px 0;border-top:1px dashed var(--line)}
.row input{margin:0}.row input:first-child{grid-column:1/-1}
.x{background:none;border:1px solid var(--line);color:var(--mute);border-radius:4px;cursor:pointer;font:inherit}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
button{padding:11px 20px;border:0;border-radius:4px;background:var(--red);color:var(--text);font:700 16px 'Zen Kaku Gothic New',sans-serif;cursor:pointer}
button.ghost{background:none;border:1px solid var(--red)}
:focus-visible{outline:2px solid var(--text);outline-offset:2px}
.err{margin:0 0 16px;padding:14px 18px;border-left:4px solid var(--red);background:#2d1114}
.err ul{margin:6px 0 0;padding-left:20px}
.slip{position:relative;margin:36px auto 0;max-width:460px;padding:26px 22px 30px;background:var(--paper);color:var(--ink);animation:print .7s ease-out}
.slip::after{content:"";position:absolute;left:0;right:0;top:100%;height:12px;background:linear-gradient(135deg,var(--paper) 25%,transparent 25%) -6px 0/12px 12px,linear-gradient(225deg,var(--paper) 25%,transparent 25%) -6px 0/12px 12px}
.slip h2{margin:0;text-align:center}.sub{margin:2px 0 14px;text-align:center;font-size:14px;color:#6b5646}
.ln{display:flex;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px dotted #bba98c}
.ln small{color:#6b5646}.sum{font-weight:700;border-bottom:1px solid var(--ink)}
.hpp{margin:14px 0;padding:12px;border:2px solid var(--red);text-align:center}
.hpp span{display:block;font-size:14px}.hpp strong{font:700 34px 'Shippori Mincho',Georgia,serif;color:var(--red)}
.note{margin:12px 0 0;font-size:13px;color:#6b5646}
footer{margin-top:40px;text-align:center;font-size:13px;color:var(--mute)}
@keyframes print{from{clip-path:inset(0 0 100% 0)}to{clip-path:inset(0)}}
@media (min-width:720px){.row{grid-template-columns:2.2fr 1.3fr 1fr 1.1fr .8fr auto}.row input:first-child{grid-column:auto}.grid{grid-template-columns:repeat(3,1fr)}}
@media (prefers-reduced-motion:reduce){.slip{animation:none}}
</style>
</head>
<body>
<main class="wrap">
<header>
  <p class="jp" lang="ja">あんていく</p>
  <h1>Kalkulator HPP Kopi Anteiku</h1>
  <p class="tag">Hitung biaya per cangkir sebelum menulis harga di menu. Margin yang sehat menjaga kedai tetap buka sampai pagi.</p>
</header>

<?php if ($errors): ?>
<div class="err" role="alert"><b>Perhitungan belum bisa dijalankan.</b>
  <ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<form method="post" class="board">
  <label>Resep
    <select id="preset">
      <option value=""<?= $posted ? ' selected' : '' ?>>Resep sendiri</option>
      <?php foreach ($presets as $i => $p): ?>
      <option value="<?= $i ?>"<?= !$posted && $i === 0 ? ' selected' : '' ?>><?= e($p['produk']) ?></option>
      <?php endforeach ?>
    </select>
  </label>
  <label>Nama produk <input name="produk" maxlength="80" required></label>
  <div class="grid">
    <label>Hasil per batch (porsi) <input name="porsi" type="number" min="1" step="1" required></label>
    <label>Tenaga kerja per batch (Rp) <input name="kerja" type="number" min="0" step="any"></label>
    <label>Overhead per batch (Rp) <input name="overhead" type="number" min="0" step="any"></label>
    <label>Kemasan per porsi (Rp) <input name="kemasan" type="number" min="0" step="any"></label>
    <label>Target margin laba (%) <input name="margin" type="number" min="0" max="90" step="any"></label>
  </div>

  <h2>Bahan baku</h2>
  <p class="hint">Isi nama, harga beli, isi kemasan, jumlah yang dipakai per batch, dan satuannya.</p>
  <div id="rows"></div>
  <div class="actions">
    <button type="button" id="add" class="ghost">Tambah bahan</button>
    <button type="submit">Hitung HPP</button>
  </div>
</form>

<?php if ($hasil): ?>
<section class="slip" id="struk" aria-live="polite">
  <h2>Struk HPP</h2>
  <p class="sub"><?= e($state['produk']) ?>, <?= qty($state['porsi']) ?> porsi per batch</p>
  <?php foreach ($hasil['rows'] as $r): ?>
  <div class="ln"><span><?= e($r['n']) ?> <small><?= qty($r['pakai']) ?> <?= e($r['sat']) ?></small></span><span><?= rp($r['biaya']) ?></span></div>
  <?php endforeach ?>
  <div class="ln sum"><span>Total bahan baku</span><span><?= rp($hasil['totalBahan']) ?></span></div>
  <div class="ln"><span>Tenaga kerja</span><span><?= rp($state['kerja']) ?></span></div>
  <div class="ln"><span>Overhead</span><span><?= rp($state['overhead']) ?></span></div>
  <div class="ln sum"><span>Total biaya per batch</span><span><?= rp($hasil['totalBatch']) ?></span></div>
  <div class="ln"><span>Dibagi <?= qty($state['porsi']) ?> porsi</span><span><?= rp($hasil['perPorsi']) ?></span></div>
  <div class="ln"><span>Kemasan</span><span><?= rp($state['kemasan']) ?></span></div>
  <div class="hpp"><span>HPP per porsi</span><strong><?= rp($hasil['hpp']) ?></strong></div>
  <div class="ln"><span>Harga jual saran (margin <?= qty($state['margin']) ?>%)</span><b><?= rp($hasil['hargaJual']) ?></b></div>
  <div class="ln"><span>Laba per porsi</span><span><?= rp($hasil['laba']) ?></span></div>
  <div class="ln"><span>Margin aktual</span><span><?= qty($hasil['marginAktual']) ?>%</span></div>
  <div class="ln"><span>Food cost</span><span><?= qty($hasil['foodCost']) ?>%</span></div>
  <p class="note">Harga jual dibulatkan ke atas per Rp 500. HPP = (bahan + tenaga kerja + overhead) ÷ porsi + kemasan.</p>
</section>
<?php endif ?>

<footer>&copy; 2026 Galeh Said Tahdi. Proyek penggemar, tidak berafiliasi dengan pemilik resmi Tokyo Ghoul.</footer>
</main>

<script>
const STATE = <?= json_encode($state, $flags) ?>;
const PRESETS = <?= json_encode($presets, $flags) ?>;
const $ = s => document.querySelector(s);
const box = $('#rows');

function row(b = ['', '', '', '', 'gr']) {
  const d = document.createElement('div');
  d.className = 'row';
  d.innerHTML = `
    <input name="nama[]" placeholder="Nama bahan" aria-label="Nama bahan" required>
    <input name="harga[]" type="number" min="0" step="any" placeholder="Harga beli (Rp)" aria-label="Harga beli">
    <input name="isi[]" type="number" min="0.0001" step="any" placeholder="Isi kemasan" aria-label="Isi kemasan" required>
    <input name="pakai[]" type="number" min="0" step="any" placeholder="Dipakai" aria-label="Jumlah dipakai per batch">
    <input name="satuan[]" placeholder="Satuan" aria-label="Satuan">
    <button type="button" class="x" aria-label="Hapus bahan">Hapus</button>`;
  d.querySelectorAll('input').forEach((el, i) => el.value = b[i]);
  d.querySelector('.x').onclick = () => d.remove();
  box.append(d);
}

function fill(s) {
  ['produk', 'porsi', 'kerja', 'overhead', 'kemasan', 'margin'].forEach(k => $('[name=' + k + ']').value = s[k]);
  box.innerHTML = '';
  s.bahan.forEach(b => row(b));
}

fill(STATE);
$('#add').onclick = () => row();
$('#preset').onchange = e => { if (e.target.value !== '') fill(PRESETS[e.target.value]); };
$('#struk')?.scrollIntoView();
</script>
</body>
</html>
