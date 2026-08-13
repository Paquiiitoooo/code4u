(function () {
  'use strict';

  var COMPANY = {
    name: 'VANTINI Pac\u00f4me',
    address: '114 AVENUE DE THIONVILLE',
    city: '57050 METZ',
    email: 'contact@code4u.fr',
    siren: '101 274 983',
    legal: 'Entreprise individuelle - Micro-entrepreneur',
    iban: 'FR76 2823 3000 0193 4167 2443 302',
    bic: 'REVOFRP2',
  };

  var COLORS = {
    ink: [15, 23, 42],
    muted: [91, 103, 124],
    blue: [0, 113, 227],
    navy: [10, 20, 35],
    pale: [245, 249, 255],
    line: [219, 226, 237],
    white: [255, 255, 255],
    green: [23, 150, 91],
    red: [190, 48, 48],
  };

  function jsPDFCtor() {
    return window.jspdf && window.jspdf.jsPDF;
  }

  function number(value) {
    var n = Number(value || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function fixText(value) {
    var text = String(value == null ? '' : value);
    if (!/[\u00c3\u00c2\u00e2]/.test(text)) return text;
    try {
      return decodeURIComponent(escape(text));
    } catch (error) {
      return text;
    }
  }

  function money(amount) {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(number(amount)).replace(/\u00a0|\u202f/g, ' ');
  }

  function formatDate(value) {
    if (!value) return '-';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function loadLogo() {
    return fetch('assets/images/logo.png')
      .then(function (response) { return response.ok ? response.blob() : null; })
      .then(function (blob) {
        if (!blob) return null;
        return new Promise(function (resolve) {
          var reader = new FileReader();
          reader.onloadend = function () { resolve(reader.result); };
          reader.readAsDataURL(blob);
        });
      })
      .catch(function () { return null; });
  }

  function clientName(client) {
    client = client || {};
    return fixText(client.company_name || client.raison_sociale ||
      [client.contact_firstname || client.prenom, client.contact_lastname || client.nom].filter(Boolean).join(' ') ||
      'Client');
  }

  function normalizeClient(client) {
    client = client || {};
    return {
      raison_sociale: fixText(client.company_name || client.raison_sociale || ''),
      prenom: fixText(client.contact_firstname || client.prenom || ''),
      nom: fixText(client.contact_lastname || client.nom || ''),
      adresse: fixText(client.adresse || ''),
      code_postal: fixText(client.code_postal || ''),
      ville: fixText(client.ville || ''),
      email: fixText(client.email || ''),
      telephone: fixText(client.phone || client.telephone || ''),
    };
  }

  function normalizeDocument(input, client, kind) {
    input = input || {};
    var isQuote = kind === 'quote';
    return {
      isQuote: isQuote,
      numero: fixText(input.numero || input.number || ''),
      date: input.date_facture || input.date_devis || input.date || new Date().toISOString(),
      dueDate: input.date_echeance || input.date_validite || input.due_date || '',
      client: normalizeClient(client || {}),
      lignes: Array.isArray(input.lignes) ? input.lignes : (Array.isArray(input.lines) ? input.lines : []),
      montant_ht: number(input.montant_ht != null ? input.montant_ht : input.amount_ht),
      montant_tva: number(input.montant_tva != null ? input.montant_tva : input.amount_tva),
      montant_ttc: number(input.montant_ttc != null ? input.montant_ttc : input.amount),
      montant_paye: number(input.montant_paye != null ? input.montant_paye : input.paid_amount),
      remise: number(input.remise),
      notes: fixText(input.notes || ''),
      conditions: fixText(input.conditions || ''),
      status: input.statut || input.status || '',
    };
  }

  function lineType(line) {
    return line.type_ligne || line.type || 'produit';
  }

  function lineUnit(line) {
    if (line.produit && line.produit.unite) return fixText(line.produit.unite);
    return lineType(line) === 'deplacement' ? 'km' : 'unit\u00e9';
  }

  function unitPrice(line) {
    return lineType(line) === 'deplacement' ? 0.5 : number(line.prix_unitaire_ht);
  }

  function lineTotalHt(line) {
    if (line.montant_ht != null) return number(line.montant_ht);
    var discount = Math.max(0, Math.min(100, number(line.remise)));
    return number(line.quantite) * unitPrice(line) * (1 - discount / 100);
  }

  function isDiscountLine(line) {
    var label = fixText(line.libelle || '').toLowerCase();
    return lineType(line) === 'remise' || label.indexOf('remise') !== -1 || lineTotalHt(line) < 0;
  }

  function buildLineGroups(lines) {
    var detailsByParent = {};
    var main = [];
    (lines || []).forEach(function (line) {
      if (isDiscountLine(line)) return;
      if (line.parent_id && lineType(line) === 'detail') {
        detailsByParent[String(line.parent_id)] = detailsByParent[String(line.parent_id)] || [];
        detailsByParent[String(line.parent_id)].push(line);
      } else if (lineType(line) !== 'detail') {
        main.push(line);
      }
    });
    return main.map(function (line) {
      return {
        line: line,
        details: Array.isArray(line.details) && line.details.length ? line.details : (detailsByParent[String(line.id)] || []),
      };
    });
  }

  function calcTotals(document, groups) {
    var gross = groups.reduce(function (sum, group) {
      return lineType(group.line) === 'titre' ? sum : sum + lineTotalHt(group.line);
    }, 0);
    if (!gross && document.montant_ht) gross = document.montant_ht;

    var officialHt = number(document.montant_ht) || gross;
    var discount = 0;
    if (document.remise > 0) {
      discount = gross * Math.max(0, Math.min(100, document.remise)) / 100;
    } else if (gross > officialHt + 0.009) {
      discount = gross - officialHt;
    }

    return {
      gross: gross,
      discount: discount,
      ht: officialHt || Math.max(0, gross - discount),
      tva: number(document.montant_tva),
      ttc: number(document.montant_ttc) || officialHt + number(document.montant_tva),
    };
  }

  function textLines(pdf, text, maxWidth) {
    return pdf.splitTextToSize(fixText(text || ''), maxWidth);
  }

  function drawFooter(pdf, pageWidth, pageHeight, page) {
    var y = pageHeight - 34;
    pdf.setDrawColor.apply(pdf, COLORS.line);
    pdf.setLineWidth(0.3);
    pdf.line(14, y - 8, pageWidth - 14, y - 8);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(7.5);
    pdf.setTextColor.apply(pdf, COLORS.ink);
    pdf.text('Coordonn\u00e9es bancaires', 14, y);
    pdf.text('Mentions obligatoires', pageWidth - 14, y, { align: 'right' });
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(6.6);
    pdf.setTextColor.apply(pdf, COLORS.muted);
    pdf.text('B\u00e9n\u00e9ficiaire : ' + COMPANY.name, 14, y + 5);
    pdf.text('IBAN : ' + COMPANY.iban, 14, y + 10);
    pdf.text('BIC : ' + COMPANY.bic, 14, y + 15);
    pdf.text('TVA non applicable, art. 293 B du CGI', pageWidth - 14, y + 5, { align: 'right' });
    pdf.text('P\u00e9nalit\u00e9s de retard : 3 fois le taux l\u00e9gal', pageWidth - 14, y + 10, { align: 'right' });
    pdf.text('Indemnit\u00e9 forfaitaire recouvrement : 40 EUR', pageWidth - 14, y + 15, { align: 'right' });
    pdf.text('Page ' + page + ' / {total_pages}', pageWidth / 2, pageHeight - 7, { align: 'center' });
  }

  function drawPaidStamp(pdf, pageWidth, pageHeight, dateStr) {
    pdf.saveGraphicsState && pdf.saveGraphicsState();
    pdf.setDrawColor.apply(pdf, COLORS.green);
    pdf.setTextColor.apply(pdf, COLORS.green);
    pdf.setLineWidth(1.4);
    pdf.roundedRect(pageWidth * 0.57, pageHeight * 0.39, 66, 27, 2, 2, 'S');
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(27);
    pdf.text('PAY\u00c9E', pageWidth * 0.57 + 33, pageHeight * 0.39 + 17, { align: 'center', angle: 14 });
    if (dateStr) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(7);
      pdf.text('le ' + dateStr, pageWidth * 0.57 + 33, pageHeight * 0.39 + 23, { align: 'center', angle: 14 });
    }
    pdf.restoreGraphicsState && pdf.restoreGraphicsState();
  }

  async function downloadDocument(documentInput, clientInput, kind) {
    var JsPDF = jsPDFCtor();
    if (!JsPDF) throw new Error('Module PDF indisponible.');

    var doc = normalizeDocument(documentInput, clientInput, kind);
    var isQuote = doc.isQuote;
    var pdf = new JsPDF({ unit: 'mm', format: 'a4' });
    var logo = await loadLogo();
    var pageWidth = pdf.internal.pageSize.getWidth();
    var pageHeight = pdf.internal.pageSize.getHeight();
    var margin = 14;
    var contentWidth = pageWidth - margin * 2;
    var y = 18;

    pdf.setFillColor.apply(pdf, COLORS.navy);
    pdf.rect(0, 0, pageWidth, 40, 'F');
    if (logo) {
      try {
        pdf.addImage(logo, 'PNG', margin, 12, 43, 13, undefined, 'FAST');
      } catch (error) {}
    }
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(18);
    pdf.setTextColor.apply(pdf, COLORS.white);
    pdf.text((isQuote ? 'DEVIS' : 'FACTURE'), pageWidth - margin, 17, { align: 'right' });
    pdf.setFontSize(11);
    pdf.text('N\u00b0 ' + doc.numero, pageWidth - margin, 25, { align: 'right' });
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.text(COMPANY.email, pageWidth - margin, 32, { align: 'right' });

    y = 50;
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(9);
    pdf.setTextColor.apply(pdf, COLORS.ink);
    pdf.text(COMPANY.name, margin, y);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.setTextColor.apply(pdf, COLORS.muted);
    [COMPANY.address, COMPANY.city, 'SIREN : ' + COMPANY.siren, COMPANY.legal].forEach(function (line) {
      y += 4.3;
      pdf.text(line, margin, y);
    });

    var infoY = 50;
    var rightX = pageWidth - margin - 72;
    pdf.setFillColor.apply(pdf, COLORS.pale);
    pdf.roundedRect(rightX, infoY - 4, 72, 38, 2, 2, 'F');
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(8);
    pdf.setTextColor.apply(pdf, COLORS.blue);
    pdf.text('CLIENT', rightX + 5, infoY + 2);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(8);
    pdf.setTextColor.apply(pdf, COLORS.ink);
    pdf.text(textLines(pdf, clientName(doc.client), 61).slice(0, 2), rightX + 5, infoY + 8);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7);
    pdf.setTextColor.apply(pdf, COLORS.muted);
    var clientLines = [
      [doc.client.adresse, [doc.client.code_postal, doc.client.ville].filter(Boolean).join(' ')].filter(Boolean).join(', '),
      doc.client.email,
      doc.client.telephone,
    ].filter(Boolean);
    var clientY = infoY + 17;
    clientLines.forEach(function (line) {
      pdf.text(textLines(pdf, line, 61).slice(0, 1), rightX + 5, clientY);
      clientY += 4.2;
    });

    y = 95;
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(8);
    pdf.setTextColor.apply(pdf, COLORS.blue);
    pdf.text(isQuote ? 'PROPOSITION COMMERCIALE' : 'DOCUMENT DE FACTURATION', margin, y);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.setTextColor.apply(pdf, COLORS.muted);
    pdf.text('Date : ' + formatDate(doc.date), margin, y + 6);
    pdf.text((isQuote ? 'Valable jusqu\u2019au : ' : '\u00c9ch\u00e9ance : ') + formatDate(doc.dueDate), margin + 48, y + 6);
    y += 18;

    var headerH = 9;
    var rowH = 9;
    var footerReserved = 48;
    var descW = contentWidth * 0.49;
    var qtyW = contentWidth * 0.09;
    var unitW = contentWidth * 0.10;
    var priceW = contentWidth * 0.16;
    var descX = margin + 3;
    var qtyX = margin + descW + qtyW / 2;
    var unitX = margin + descW + qtyW + unitW / 2;
    var priceX = margin + descW + qtyW + unitW + priceW - 3;
    var totalX = pageWidth - margin - 3;

    function drawTableHeader() {
      pdf.setFillColor.apply(pdf, COLORS.blue);
      pdf.roundedRect(margin, y, contentWidth, headerH, 1.4, 1.4, 'F');
      pdf.setFont('helvetica', 'bold');
      pdf.setFontSize(7.5);
      pdf.setTextColor.apply(pdf, COLORS.white);
      pdf.text('Description', descX, y + 5.8);
      pdf.text('Qt\u00e9', qtyX, y + 5.8, { align: 'center' });
      pdf.text('Unit\u00e9', unitX, y + 5.8, { align: 'center' });
      pdf.text('P.U. HT', priceX, y + 5.8, { align: 'right' });
      pdf.text('Total HT', totalX, y + 5.8, { align: 'right' });
      y += headerH + 2;
    }

    function ensurePage(requiredHeight) {
      if (y + requiredHeight > pageHeight - footerReserved) {
        pdf.addPage();
        y = 18;
        drawTableHeader();
      }
    }

    drawTableHeader();
    var groups = buildLineGroups(doc.lignes);
    if (!groups.length) {
      groups = [{ line: { libelle: doc.notes || (isQuote ? 'Devis ' : 'Facture ') + doc.numero, quantite: 1, prix_unitaire_ht: doc.montant_ht, type_ligne: 'produit' }, details: [] }];
    }

    groups.forEach(function (group, index) {
      var line = group.line;
      var type = lineType(line);
      var isTitle = type === 'titre';
      var descLines = textLines(pdf, line.libelle || '', descW - 6).slice(0, 2);
      var detailLines = [];
      (group.details || []).forEach(function (detail) {
        detailLines = detailLines.concat(textLines(pdf, '- ' + (detail.libelle || ''), descW - 13).slice(0, 2));
      });
      var h = Math.max(rowH, 5 + descLines.length * 4 + detailLines.length * 3.5);
      ensurePage(h + 2);
      if (index % 2 === 0 && !isTitle) {
        pdf.setFillColor(249, 251, 254);
        pdf.rect(margin, y - 1.5, contentWidth, h, 'F');
      }
      pdf.setDrawColor.apply(pdf, COLORS.line);
      pdf.setLineWidth(0.15);
      pdf.line(margin, y + h - 1.5, pageWidth - margin, y + h - 1.5);

      pdf.setFont('helvetica', isTitle ? 'bold' : 'bold');
      pdf.setFontSize(isTitle ? 8.5 : 7.5);
      pdf.setTextColor.apply(pdf, isTitle ? COLORS.blue : COLORS.ink);
      pdf.text(descLines, descX, y + 4.5);

      if (!isTitle) {
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(7);
        pdf.setTextColor.apply(pdf, COLORS.muted);
        detailLines.forEach(function (lineText, detailIndex) {
          pdf.text(lineText, descX + 4, y + 11 + detailIndex * 3.5);
        });
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(7.2);
        pdf.setTextColor.apply(pdf, COLORS.ink);
        pdf.text(String(number(line.quantite)), qtyX, y + 4.5, { align: 'center' });
        pdf.text(lineUnit(line), unitX, y + 4.5, { align: 'center' });
        pdf.text(money(unitPrice(line)), priceX, y + 4.5, { align: 'right' });
        pdf.setFont('helvetica', 'bold');
        pdf.text(money(lineTotalHt(line)), totalX, y + 4.5, { align: 'right' });
      }
      y += h + 1;
    });

    var totals = calcTotals(doc, groups);
    ensurePage(54);
    y += 8;
    var totalsLabelX = pageWidth - margin - 78;
    var totalsValueX = pageWidth - margin;

    function totalRow(label, amount, options) {
      options = options || {};
      pdf.setFont('helvetica', options.bold ? 'bold' : 'normal');
      pdf.setFontSize(options.large ? 10.5 : 8);
      pdf.setTextColor.apply(pdf, options.red ? COLORS.red : (options.muted ? COLORS.muted : COLORS.ink));
      pdf.text(label, totalsLabelX, y, { align: 'right' });
      pdf.text((options.negative ? '-' : '') + money(amount), totalsValueX, y, { align: 'right' });
      y += options.large ? 8 : 6;
    }

    totalRow('SOUS-TOTAL PRESTATIONS', totals.gross, { muted: true });
    if (totals.discount > 0.009) {
      totalRow('REMISE COMMERCIALE', totals.discount, { red: true, negative: true });
    }
    totalRow('TOTAL HORS TAXE', totals.ht, { bold: true });
    totalRow('TVA 0 %', totals.tva, { muted: true });
    pdf.setDrawColor.apply(pdf, COLORS.blue);
    pdf.setLineWidth(0.5);
    pdf.line(totalsLabelX - 8, y - 2, totalsValueX, y - 2);
    totalRow(isQuote ? 'TOTAL TTC' : 'NET \u00c0 PAYER', totals.ttc, { bold: true, large: true });

    if (!isQuote) {
      if (doc.montant_paye > 0) totalRow('MONTANT PAY\u00c9', doc.montant_paye, { muted: true });
      var remaining = Math.max(0, totals.ttc - doc.montant_paye);
      if (doc.montant_paye > 0 || remaining !== totals.ttc) totalRow('RESTE \u00c0 PAYER', remaining, { bold: true });
    }

    var noteParts = [];
    if (doc.notes) noteParts.push(['Notes', doc.notes]);
    if (doc.conditions) noteParts.push(['Conditions', doc.conditions]);
    noteParts.forEach(function (part) {
      ensurePage(24);
      y += 5;
      pdf.setFont('helvetica', 'bold');
      pdf.setFontSize(8);
      pdf.setTextColor.apply(pdf, COLORS.blue);
      pdf.text(part[0].toUpperCase(), margin, y);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(7.2);
      pdf.setTextColor.apply(pdf, COLORS.ink);
      var lines = textLines(pdf, part[1], contentWidth).slice(0, 8);
      pdf.text(lines, margin, y + 5);
      y += 7 + lines.length * 3.7;
    });

    var pages = pdf.getNumberOfPages();
    for (var page = 1; page <= pages; page += 1) {
      pdf.setPage(page);
      drawFooter(pdf, pageWidth, pageHeight, page);
    }
    if (typeof pdf.putTotalPages === 'function') pdf.putTotalPages('{total_pages}');

    if (!isQuote && totals.ttc > 0 && doc.montant_paye >= totals.ttc - 0.01) {
      pdf.setPage(1);
      drawPaidStamp(pdf, pageWidth, pageHeight, formatDate(doc.date));
    }

    pdf.save((isQuote ? 'Devis-' : 'Facture-') + doc.numero + '.pdf');
  }

  window.Code4UInvoicePDF = {
    downloadInvoice: function (invoiceInput, clientInput) {
      return downloadDocument(invoiceInput, clientInput, 'invoice');
    },
    downloadQuote: function (quoteInput, clientInput) {
      return downloadDocument(quoteInput, clientInput, 'quote');
    },
  };
}());
