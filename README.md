# Anteiku HPP Calculator

Kalkulator Harga Pokok Penjualan (HPP) untuk produk kopi, bertema kedai Anteiku dari Tokyo Ghoul. Satu file PHP, tanpa database, tanpa dependensi.

## Fitur

- Hitung biaya tiap bahan baku dari harga beli, isi kemasan, dan jumlah yang dipakai.
- Tambahkan tenaga kerja, overhead, dan kemasan.
- Dapatkan HPP per porsi, harga jual saran, laba, margin aktual, dan food cost.
- Tiga resep bawaan yang bisa diubah: Anteiku Blend, Latte Kaneki, Mocha Touka.
- Tampilan responsif untuk ponsel.

## Cara menjalankan

Butuh PHP 8.0 atau lebih baru.

```bash
git clone https://github.com/USERNAME/anteiku-hpp.git
cd anteiku-hpp
php -S localhost:8000
```

Buka `http://localhost:8000`. Untuk hosting biasa, unggah `index.php` ke folder web server.

## Rumus

```
Biaya bahan   = harga beli ÷ isi kemasan × jumlah dipakai
HPP per porsi = (total bahan + tenaga kerja + overhead) ÷ porsi + kemasan
Harga jual    = HPP ÷ (1 − margin%), dibulatkan ke atas per Rp 500
Food cost     = (total bahan ÷ porsi) ÷ harga jual
```

## Catatan

Copyright (c) 2026 Galeh Said Tahdi. All rights reserved.

Proyek penggemar. Tidak berafiliasi dengan pemilik resmi Tokyo Ghoul.
