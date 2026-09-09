{{-- Shared by pdf.invoice and pdf.partner-group-bundle, so one change reaches both. --}}
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #18181b; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #71717a; }
        .right { text-align: right; }
        .center { text-align: center; }
        .small { font-size: 8.5pt; }
        .title { font-size: 14pt; font-weight: bold; }
        /* Never a fixed height: an SVG keeps its ratio, so a wide logo scaled to
           42px tall would be metres wide and run off the page. */
        .logo { max-width: 180px; max-height: 42px; margin-bottom: 5px; }
        .parties td { vertical-align: top; width: 50%; padding: 0 6px 0 0; }
        .box { border: 1px solid #d4d4d8; padding: 8px 10px; }
        .box h3 { margin: 0 0 4px; font-size: 8.5pt; text-transform: uppercase; color: #71717a; letter-spacing: .04em; }
        .box .name { font-weight: bold; font-size: 11pt; }
        .meta td { padding: 1px 0; }
        .meta .label { color: #71717a; padding-right: 10px; }
        .items { margin-top: 14px; }
        .items th { background: #f4f4f5; border-bottom: 1px solid #d4d4d8; padding: 6px 5px; font-size: 8.5pt; text-align: left; }
        .items td { border-bottom: 1px solid #e4e4e7; padding: 6px 5px; }
        .items .desc { color: #71717a; font-size: 8.5pt; }
        .totals { margin-top: 12px; }
        .totals td { padding: 3px 5px; }
        .totals .grand td { border-top: 2px solid #18181b; font-weight: bold; font-size: 11.5pt; padding-top: 6px; }
        .recap th, .recap td { border-bottom: 1px solid #e4e4e7; padding: 4px 5px; font-size: 8.5pt; }
        .ips { margin-top: 10px; }
        .ips td { vertical-align: top; padding: 0; }
        /* Roughly 26 mm — the National Bank asks for a code that still scans off paper. */
        .ips img { width: 98px; height: 98px; }
        .ips .caption { padding-left: 8px; font-size: 8pt; color: #71717a; }
        .footer { margin-top: 26px; }
        .sign { margin-top: 34px; }
        .sign td { width: 50%; }
        .sign .line { border-top: 1px solid #a1a1aa; padding-top: 4px; width: 60%; }
        .cancelled { color: #dc2626; border: 2px solid #dc2626; padding: 4px 10px; font-weight: bold; display: inline-block; }
