@page {
    size: A4 portrait;
    margin: 14mm 14mm 18mm 14mm;
}
* { box-sizing: border-box; }
body {
    font-family: sans-serif;
    font-size: 10pt;
    color: #000000 !important;
    margin: 0;
    padding: 0;
    line-height: 1.4;
    background: #ffffff;
}
table { border-collapse: collapse; }

/* Company header block (shared partial) */
.doc-header {
    width: 100%;
    border-bottom: 1.5pt solid #000000;
    padding-bottom: 8pt;
    margin-bottom: 10pt;
    overflow: hidden;
}
.company-logo {
    max-height: 40pt;
    max-width: 160pt;
    margin-bottom: 4pt;
}
.company-name {
    font-size: 13pt;
    font-weight: bold;
}
.company-line {
    font-size: 8.5pt;
    color: #333333;
}
.doc-meta { text-align: right; }
.doc-title {
    font-size: 15pt;
    font-weight: bold;
    letter-spacing: 1pt;
}
.doc-meta .field {
    font-size: 9pt;
    color: #222222;
    margin-top: 2pt;
}
.doc-subtitle {
    font-size: 9pt;
    color: #333333;
    margin-bottom: 8pt;
}

/* Sections */
.section {
    margin-bottom: 10pt;
    page-break-inside: avoid;
}
.section-title {
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    border-bottom: 0.75pt solid #000000;
    padding-bottom: 2pt;
    margin-bottom: 5pt;
}
.grid-2 {
    width: 100%;
}
.grid-2 td {
    vertical-align: top;
    padding: 0 8pt 6pt 0;
}
.label {
    font-size: 8pt;
    text-transform: uppercase;
    color: #333333;
}
.value {
    font-size: 10pt;
    color: #000000;
}
.complaint { padding: 2pt 0; }

/* Data tables */
table.data {
    width: 100%;
    font-size: 9pt;
}
table.data th {
    background: #f2f2f2;
    border: 1pt solid #555555;
    padding: 4pt 6pt;
    text-align: left;
    font-size: 8pt;
    text-transform: uppercase;
}
table.data td {
    border: 1pt solid #555555;
    padding: 4pt 6pt;
    vertical-align: top;
    color: #000000 !important;
    background: #ffffff;
}
.right { text-align: right; }
.center { text-align: center; }

/* Totals */
.totals {
    width: 55%;
    margin-left: auto;
    font-size: 9.5pt;
}
.totals td {
    padding: 3pt 6pt;
}
.totals .grand td {
    border-top: 1.5pt solid #000000;
    font-weight: bold;
    font-size: 11pt;
}
.amount-in-words {
    margin-top: 8pt;
    padding-top: 4pt;
    border-top: 0.75pt solid #000000;
    font-size: 9.5pt;
    font-weight: bold;
}

/* Terms / declaration */
.terms {
    margin-top: 12pt;
    font-size: 9pt;
    color: #222222;
}
.terms .title {
    font-weight: bold;
}
.signatory {
    margin-top: 16pt;
    width: 40%;
    margin-left: auto;
    text-align: center;
    font-size: 9pt;
    color: #000000;
}
.signatory .sign-line {
    border-top: 1pt solid #000000;
    margin-bottom: 3pt;
}

/* Statement note */
.note {
    margin-top: 10pt;
    font-size: 8.5pt;
    color: #333333;
}

/* Footer */
.footer {
    position: fixed;
    bottom: -14mm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 8pt;
    color: #333333;
}