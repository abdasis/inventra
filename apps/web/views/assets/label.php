<?php
/**
 * Halaman label QR aset. Artefak cetak, jadi dirender mandiri tanpa layout
 * dashboard: HTML dan gayanya sendiri, tanpa pustaka tampilan tambahan supaya
 * hasil cetak bersih. Data aset dan QR diisi assets-label.js.
 *
 * @var int $assetId
 */

$assetId = (int)($assetId ?? 0);
$scriptVersion = (int) @filemtime(dirname(__DIR__, 2) . '/public/assets/js/assets-label.js');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Label Aset — Inventra</title>
  <style>
    /* Nilai warna dan radius mengikuti token di style.css. */
    :root {
      --label-primary: #0f172a;
      --label-accent: #2563eb;
      --label-muted: #64748b;
      --label-border: #cbd5e1;
      --label-subtle: #f1f5f9;
      --label-ease: cubic-bezier(0.23, 1, 0.32, 1);
    }

    body {
      margin: 0;
      padding: 1.5rem;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      background-color: var(--label-subtle);
      color: var(--label-primary);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .label-card {
      width: 100%;
      max-width: 420px;
      background: #ffffff;
      border: 1px solid var(--label-border);
      border-radius: 12px;
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .label-brand {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .label-body {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    /* Tepi QR harus tetap persis supaya tidak goyang saat dicetak. */
    .label-qr {
      flex: 0 0 auto;
      width: 132px;
      height: 132px;
    }

    .label-qr img,
    .label-qr canvas {
      display: block;
      width: 132px;
      height: 132px;
    }

    .label-info {
      min-width: 0;
    }

    .label-code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 1.05rem;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
    }

    .label-name {
      font-weight: 600;
      text-wrap: balance;
    }

    .label-meta {
      font-size: 0.8rem;
      color: var(--label-muted);
      text-wrap: pretty;
    }

    .label-error {
      max-width: 420px;
      margin: 0;
      padding: 0.5rem 0.75rem;
      border: 1px solid #fecaca;
      border-radius: 8px;
      background: #fef2f2;
      color: #b91c1c;
      font-size: 0.85rem;
    }

    .label-toolbar {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.5rem;
    }

    .label-btn {
      border: 1px solid transparent;
      border-radius: 8px;
      padding: 0.55rem 1.1rem;
      font-size: 0.9rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      transition: transform 160ms var(--label-ease);
    }

    .label-btn-primary {
      background-color: var(--label-accent);
      color: #ffffff;
    }

    .label-btn-primary:active {
      transform: scale(0.97);
    }

    .label-btn-ghost {
      background-color: transparent;
      border-color: var(--label-border);
      color: #334155;
    }

    .label-btn-ghost:active {
      transform: scale(0.97);
    }

    .label-btn:focus-visible {
      outline: 2px solid var(--label-accent);
      outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
      .label-btn {
        transition: none;
      }

      .label-btn:active {
        transform: none;
      }
    }

    /* Cetak: hanya label yang tersisa, tanpa latar aplikasi. */
    @media print {
      body {
        padding: 0;
        min-height: 0;
        display: block;
        background: #ffffff;
      }

      .label-card {
        max-width: none;
        border-color: #000000;
        border-radius: 8px;
      }

      .label-toolbar,
      .label-error {
        display: none !important;
      }
    }
  </style>
</head>
<body>

  <main class="label-card" id="assetLabel">
    <div class="label-brand">INVENTRA</div>
    <div class="label-body">
      <div class="label-qr" id="labelQr" aria-hidden="true"></div>
      <div class="label-info">
        <div class="label-code" id="labelCode">-</div>
        <div class="label-name" id="labelName">Memuat data aset...</div>
        <div class="label-meta" id="labelMeta"></div>
      </div>
    </div>
  </main>

  <p class="label-error" id="labelError" role="alert" hidden></p>

  <div class="label-toolbar">
    <button type="button" class="label-btn label-btn-primary" id="btnPrintLabel">Cetak</button>
    <a class="label-btn label-btn-ghost" href="/assets/<?= $assetId ?>">Kembali ke detail</a>
  </div>

  <!-- QR code dibuat di klien; hanya dimuat di halaman label. -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    window.INVENTRA_ASSET = <?= json_encode(
        ['id' => $assetId],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
  </script>
  <script src="/assets/js/assets-label.js?v=<?= $scriptVersion ?>"></script>
</body>
</html>
