(function () {
  'use strict';

  function jsPDFCtor() {
    return window.jspdf && window.jspdf.jsPDF;
  }

  function number(value) {
    var n = Number(value || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function formatMoney(amount) {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(number(amount)).replace(/\u00A0|\u202F/g, ' ');
  }

  function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  function formatDateLong(value) {
    return new Date(value).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    });
  }

  function loadLogo() {
    return fetch('assets/images/logo.png')
      .then(function (response) {
        if (!response.ok) return null;
        return response.blob();
      })
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
    return client.company_name ||
      client.raison_sociale ||
      [client.contact_firstname || client.prenom, client.contact_lastname || client.nom].filter(Boolean).join(' ') ||
      'Client';
  }

  function normalizeInvoice(invoice, client) {
    return {
      numero: invoice.numero || invoice.number || '',
      date_facture: invoice.date_facture || invoice.date || new Date().toISOString(),
      date_echeance: invoice.date_echeance || invoice.due_date || '',
      client: {
        raison_sociale: client.company_name || client.raison_sociale || '',
        prenom: client.contact_firstname || client.prenom || '',
        nom: client.contact_lastname || client.nom || '',
        adresse: client.adresse || '',
        code_postal: client.code_postal || '',
        ville: client.ville || '',
        email: client.email || '',
        telephone: client.phone || client.telephone || '',
      },
      lignes: invoice.lignes || invoice.lines || [],
      montant_ht: number(invoice.montant_ht != null ? invoice.montant_ht : invoice.amount_ht),
      montant_tva: number(invoice.montant_tva != null ? invoice.montant_tva : invoice.amount_tva),
      montant_ttc: number(invoice.montant_ttc != null ? invoice.montant_ttc : invoice.amount),
      montant_paye: number(invoice.montant_paye != null ? invoice.montant_paye : invoice.paid_amount),
      remise: number(invoice.remise),
      notes: invoice.notes || '',
      conditions: invoice.conditions || '',
    };
  }

  function drawPaidStamp(pdf, cx, cy, dateStr) {
    var green = [31, 157, 107];
    pdf.saveGraphicsState && pdf.saveGraphicsState();
    pdf.setDrawColor(green[0], green[1], green[2]);
    pdf.setTextColor(green[0], green[1], green[2]);
    pdf.setLineWidth(1.6);
    pdf.roundedRect(cx - 39, cy - 16, 78, 32, 2, 2, 'S');
    pdf.setLineWidth(0.6);
    pdf.roundedRect(cx - 35, cy - 12, 70, 24, 2, 2, 'S');
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(34);
    pdf.text('PAYÉE', cx, cy + 3, { align: 'center', angle: 14 });
    if (dateStr) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(8);
      pdf.text('le ' + dateStr, cx, cy + 11, { align: 'center', angle: 14 });
    }
    pdf.restoreGraphicsState && pdf.restoreGraphicsState();
  }

  function lineType(line) {
    return line.type_ligne || line.type || 'produit';
  }

  function lineUnit(line) {
    if (line.produit && line.produit.unite) return line.produit.unite;
    return lineType(line) === 'deplacement' ? 'km' : 'unité';
  }

  function linePrice(line) {
    return lineType(line) === 'deplacement' ? 0.5 : number(line.prix_unitaire_ht);
  }

  function lineTotal(line) {
    var discount = number(line.remise);
    return number(line.quantite) * linePrice(line) * (1 - discount / 100);
  }

  function buildLineGroups(lines) {
    var detailsByParent = {};
    var main = [];
    lines.forEach(function (line) {
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

  async function downloadInvoice(invoiceInput, clientInput) {
    var JsPDF = jsPDFCtor();
    if (!JsPDF) {
      throw new Error('Module PDF indisponible.');
    }

    var facture = normalizeInvoice(invoiceInput || {}, clientInput || {});
    var pdf = new JsPDF();
    var pageWidth = pdf.internal.pageSize.getWidth();
    var pageHeight = pdf.internal.pageSize.getHeight();
    var margin = 20;
    var y = margin;

    var blue = [24, 119, 242];
    var darkBlue = [12, 68, 174];
    var lightBlue = [227, 242, 253];
    var gray = [108, 117, 125];
    var darkGray = [33, 37, 41];
    var white = [255, 255, 255];

    pdf.setFontSize(10);
    pdf.setFont('helvetica', 'normal');
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text('VANTINI Pacôme', margin, y);
    y += 5;
    pdf.setFontSize(9);
    pdf.text('114 AVENUE DE THIONVILLE', margin, y);
    y += 5;
    pdf.text('57050 METZ', margin, y);
    y += 5;
    pdf.setFontSize(8);
    pdf.setTextColor(gray[0], gray[1], gray[2]);
    pdf.text('SIREN : 101 274 983', margin, y);
    y += 5;
    pdf.setFontSize(7);
    pdf.text('Entreprise individuelle - Micro-entrepreneur', margin, y);

    var logo = await loadLogo();
    if (logo) {
      try {
        pdf.addImage(logo, 'PNG', pageWidth - margin - 40, margin, 40, 12, undefined, 'FAST');
      } catch (error) {
        // Ignore logo conversion issues.
      }
    }

    pdf.setFontSize(18);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(darkBlue[0], darkBlue[1], darkBlue[2]);
    pdf.text('FACTURE n°' + facture.numero, pageWidth - margin, margin + (logo ? 25 : 0), { align: 'right' });
    y = Math.max(y + 10, margin + (logo ? 40 : 15));

    var clientLines = 1;
    if (facture.client.adresse || (facture.client.code_postal && facture.client.ville)) clientLines += 1;
    if (facture.client.email) clientLines += 1;
    if (facture.client.telephone) clientLines += 1;
    var boxWidth = (pageWidth - 2 * margin - 10) / 2;
    var boxHeight = Math.max(35, 10 + clientLines * 5, facture.date_echeance ? 42 : 35);

    pdf.setFillColor(lightBlue[0], lightBlue[1], lightBlue[2]);
    pdf.roundedRect(margin, y, boxWidth, boxHeight, 2, 2, 'F');
    pdf.setFontSize(9);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(blue[0], blue[1], blue[2]);
    pdf.text('Client', margin + 6, y + 8);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(8);
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text(clientName(facture.client), margin + 6, y + 15);
    var clientY = y + 22;
    var address = [facture.client.adresse, [facture.client.code_postal, facture.client.ville].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    if (address) {
      pdf.setFontSize(7);
      pdf.text(address, margin + 6, clientY);
      clientY += 5;
    }
    if (facture.client.email) {
      pdf.text('Email: ' + facture.client.email, margin + 6, clientY);
      clientY += 5;
    }
    if (facture.client.telephone) {
      pdf.text('Tel: ' + facture.client.telephone, margin + 6, clientY);
    }

    var rightX = pageWidth - margin - boxWidth;
    pdf.setFillColor(lightBlue[0], lightBlue[1], lightBlue[2]);
    pdf.roundedRect(rightX, y, boxWidth, boxHeight, 2, 2, 'F');
    pdf.setFontSize(9);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(blue[0], blue[1], blue[2]);
    pdf.text('Informations', rightX + 6, y + 8);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(8);
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text('No: ' + facture.numero, rightX + 6, y + 16);
    pdf.text('Date: ' + formatDate(facture.date_facture), rightX + 6, y + 22);
    if (facture.date_echeance) {
      pdf.text('Échéance: ' + formatDate(facture.date_echeance), rightX + 6, y + 28);
    }

    y += boxHeight + 12;
    var headerH = 8;
    var rowHeight = 7;
    var footerHeight = 80;
    var totalsHeight = 60;
    var maxY = pageHeight - footerHeight;
    var tableWidth = pageWidth - 2 * margin;
    var wDesc = tableWidth * 0.45;
    var wQty = tableWidth * 0.10;
    var wUnit = tableWidth * 0.10;
    var wPrice = tableWidth * 0.17;
    var wTotal = tableWidth * 0.18;
    var descX = margin + 3;
    var qtyX = margin + wDesc + wQty / 2;
    var unitX = margin + wDesc + wQty + wUnit / 2;
    var priceX = margin + wDesc + wQty + wUnit + wPrice - 3;
    var totalXCol = pageWidth - margin - 3;

    function drawHeader() {
      pdf.setFillColor(blue[0], blue[1], blue[2]);
      pdf.rect(margin, y, tableWidth, headerH, 'F');
      pdf.setFontSize(8);
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(white[0], white[1], white[2]);
      pdf.text('Description', descX, y + 5);
      pdf.text('Qté', qtyX, y + 5, { align: 'center' });
      pdf.text('Unité', unitX, y + 5, { align: 'center' });
      pdf.text('P.U. HT', priceX, y + 5, { align: 'right' });
      pdf.text('Total HT', totalXCol, y + 5, { align: 'right' });
      y += headerH;
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(7.5);
      pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    }

    function checkNewPage(requiredHeight) {
      if (y + requiredHeight > maxY - 5) {
        pdf.addPage();
        y = margin + 10;
        drawHeader();
        y += 2;
      }
    }

    drawHeader();
    var groups = buildLineGroups(facture.lignes);
    if (!groups.length) {
      groups = [{ line: { libelle: facture.notes || 'Facture ' + facture.numero, quantite: 1, prix_unitaire_ht: facture.montant_ht, type_ligne: 'produit' }, details: [] }];
    }

    groups.forEach(function (group, index) {
      var line = group.line;
      var type = lineType(line);
      var isTitle = type === 'titre';
      var detailHeight = 5;
      var requiredHeight = rowHeight + 2;
      group.details.forEach(function (detail) {
        var lines = pdf.splitTextToSize('  - ' + (detail.libelle || ''), wDesc - 20);
        requiredHeight += lines.length * (detailHeight - 1) + 2;
      });
      checkNewPage(requiredHeight);
      if (index % 2 === 0 && !isTitle) {
        pdf.setFillColor(250, 250, 250);
        pdf.rect(margin, y - 2, tableWidth, rowHeight, 'F');
      }
      pdf.setDrawColor(220, 220, 220);
      pdf.setLineWidth(0.1);
      pdf.line(margin, y + 5, pageWidth - margin, y + 5);
      if (isTitle) {
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(8);
        pdf.setTextColor(darkBlue[0], darkBlue[1], darkBlue[2]);
        pdf.text(pdf.splitTextToSize(line.libelle || '', wDesc - 4)[0] || '', descX, y + 4);
        pdf.text('-', qtyX, y + 4, { align: 'center' });
        pdf.text('-', unitX, y + 4, { align: 'center' });
        pdf.text('-', priceX, y + 4, { align: 'right' });
        pdf.text('-', totalXCol, y + 4, { align: 'right' });
      } else {
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(7.5);
        pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
        pdf.text(pdf.splitTextToSize(line.libelle || '', wDesc - 4)[0] || '', descX, y + 4);
        pdf.text(String(number(line.quantite)), qtyX, y + 4, { align: 'center' });
        pdf.text(lineUnit(line), unitX, y + 4, { align: 'center' });
        pdf.text(formatMoney(linePrice(line)), priceX, y + 4, { align: 'right' });
        pdf.text(formatMoney(lineTotal(line)), totalXCol, y + 4, { align: 'right' });
      }
      pdf.setFont('helvetica', 'normal');
      y += rowHeight;

      group.details.forEach(function (detail) {
        var detailLines = pdf.splitTextToSize('  - ' + (detail.libelle || ''), wDesc - 20);
        var height = detailLines.length * (detailHeight - 1) + 2;
        checkNewPage(height + 5);
        pdf.setDrawColor(240, 240, 240);
        pdf.setLineWidth(0.05);
        pdf.line(margin, y + 2.5, pageWidth - margin, y + 2.5);
        pdf.setFontSize(6);
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(gray[0], gray[1], gray[2]);
        detailLines.forEach(function (lineText, lineIndex) {
          pdf.text(lineText, margin + 13, y + 3 + lineIndex * (detailHeight - 1));
        });
        y += height;
      });
    });

    if (y + totalsHeight > maxY) checkNewPage(totalsHeight);
    y += 8;

    var totalX = pageWidth - margin - wTotal - wPrice;
    var sousTotalHT = groups.reduce(function (sum, group) {
      return lineType(group.line) === 'titre' ? sum : sum + lineTotal(group.line);
    }, 0);
    if (!sousTotalHT && facture.montant_ht) sousTotalHT = facture.montant_ht;

    pdf.setFontSize(8);
    pdf.setFont('helvetica', 'normal');
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text('Sous-total HT:', totalX, y, { align: 'right' });
    pdf.setFont('helvetica', 'bold');
    pdf.text(formatMoney(sousTotalHT), pageWidth - margin, y, { align: 'right' });
    y += 6;

    if (facture.remise > 0) {
      pdf.setFont('helvetica', 'normal');
      pdf.setTextColor(gray[0], gray[1], gray[2]);
      pdf.text('Remise (' + facture.remise + '%):', totalX, y, { align: 'right' });
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
      pdf.text('-' + formatMoney(sousTotalHT * facture.remise / 100), pageWidth - margin, y, { align: 'right' });
      y += 6;
      sousTotalHT = sousTotalHT * (1 - facture.remise / 100);
      pdf.setFont('helvetica', 'normal');
      pdf.text('Sous-total HT (après remise):', totalX, y, { align: 'right' });
      pdf.setFont('helvetica', 'bold');
      pdf.text(formatMoney(sousTotalHT), pageWidth - margin, y, { align: 'right' });
      y += 6;
    }

    pdf.setDrawColor(blue[0], blue[1], blue[2]);
    pdf.setLineWidth(0.5);
    pdf.line(totalX - 5, y, pageWidth - margin, y);
    y += 8;
    var totalFinal = facture.montant_ttc || sousTotalHT;
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(11);
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text('TOTAL', totalX, y, { align: 'right' });
    pdf.setFontSize(14);
    pdf.text(formatMoney(totalFinal), pageWidth - margin, y, { align: 'right' });

    if (facture.montant_paye > 0) {
      y += 8;
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(8);
      pdf.setTextColor(gray[0], gray[1], gray[2]);
      pdf.text('Montant payé:', totalX, y, { align: 'right' });
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
      pdf.text(formatMoney(facture.montant_paye), pageWidth - margin, y, { align: 'right' });
      y += 6;
      pdf.setFont('helvetica', 'normal');
      pdf.setTextColor(gray[0], gray[1], gray[2]);
      pdf.text('Reste à payer:', totalX, y, { align: 'right' });
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
      pdf.text(formatMoney(Math.max(0, totalFinal - facture.montant_paye)), pageWidth - margin, y, { align: 'right' });
    }

    y += 20;
    if (facture.notes && y < pageHeight - 45) {
      pdf.setFontSize(9);
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(blue[0], blue[1], blue[2]);
      pdf.text('Notes:', margin, y);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(8);
      pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
      var noteLines = pdf.splitTextToSize(facture.notes, pageWidth - 2 * margin);
      pdf.text(noteLines, margin, y + 6);
    }

    var footerY = pageHeight - 60;
    pdf.setDrawColor(220, 220, 220);
    pdf.setLineWidth(0.3);
    pdf.line(margin, footerY - 30, pageWidth - margin, footerY - 30);
    pdf.setFontSize(8);
    pdf.setFont('helvetica', 'bold');
    pdf.setTextColor(darkBlue[0], darkBlue[1], darkBlue[2]);
    pdf.text('Coordonnées bancaires :', margin, footerY - 20);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7);
    pdf.setTextColor(darkGray[0], darkGray[1], darkGray[2]);
    pdf.text('Bénéficiaire : Pacôme VANTINI', margin, footerY - 14);
    pdf.text('IBAN : FR76 2823 3000 0193 4167 2443 302', margin, footerY - 8);
    pdf.text('BIC : REVOFRP2', margin, footerY - 2);
    pdf.setFontSize(7);
    pdf.setTextColor(gray[0], gray[1], gray[2]);
    pdf.text('Mentions obligatoires :', pageWidth - margin, footerY - 20, { align: 'right' });
    pdf.setFontSize(6);
    pdf.text('TVA non applicable, art. 293 B du CGI', pageWidth - margin, footerY - 14, { align: 'right' });
    pdf.text('En cas de retard de paiement, pénalités de 3 fois le taux légal', pageWidth - margin, footerY - 8, { align: 'right' });
    pdf.text('et indemnité forfaitaire de 40 EUR pour frais de recouvrement.', pageWidth - margin, footerY - 2, { align: 'right' });
    pdf.setFontSize(7);
    pdf.text('Document généré le ' + formatDateLong(new Date().toISOString()), pageWidth / 2, pageHeight - 12, { align: 'center' });

    if (facture.montant_ttc > 0 && facture.montant_paye >= facture.montant_ttc - 0.01) {
      pdf.setPage(1);
      drawPaidStamp(pdf, pageWidth * 0.62, pageHeight * 0.45, formatDate(facture.date_facture));
    }

    var totalPages = pdf.getNumberOfPages();
    for (var page = 1; page <= totalPages; page += 1) {
      pdf.setPage(page);
      pdf.setFontSize(7);
      pdf.setFont('helvetica', 'normal');
      pdf.setTextColor(gray[0], gray[1], gray[2]);
      pdf.text('Page ' + page + ' / ' + totalPages, pageWidth / 2, pageHeight - 5, { align: 'center' });
    }

    pdf.save('Facture-' + facture.numero + '.pdf');
  }

  window.Code4UInvoicePDF = {
    downloadInvoice: downloadInvoice,
  };
}());
