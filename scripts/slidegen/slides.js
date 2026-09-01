const { COLORS, FONTS, PAGE, MARGIN, addGlow, addBrandMark, addFooter, addEyebrow } = require("./brand");

// --- Slide 1: title -------------------------------------------------------
function titleSlide(pres, { moduleNum, moduleTitle, lessonNum, lessonTitle, totalLessons }) {
  const slide = pres.addSlide();
  slide.background = { color: COLORS.bg };
  addGlow(slide, { x: PAGE.W - 5, y: PAGE.H - 5.5, size: 8, color: COLORS.accent, transparency: 90 });
  addGlow(slide, { x: -3, y: -3, size: 5, color: COLORS.primary, transparency: 93 });
  addBrandMark(slide, { x: MARGIN, y: MARGIN, size: 0.55 });

  addEyebrow(slide, `Module ${moduleNum} · Lesson ${lessonNum} of ${totalLessons}`, { x: MARGIN, y: 2.6 });
  slide.addText(moduleTitle, {
    x: MARGIN, y: 3.0, w: PAGE.W - MARGIN * 2, h: 0.4,
    fontFace: FONTS.label, bold: true, fontSize: 11, color: COLORS.muted,
    charSpacing: 1, margin: 0, isTextBox: true,
  });
  slide.addText(lessonTitle, {
    x: MARGIN, y: 3.45, w: PAGE.W - MARGIN * 2, h: 2.0,
    fontFace: FONTS.display, bold: true, fontSize: 40, color: COLORS.ink,
    align: "left", valign: "top", margin: 0, lineSpacingMultiple: 1.05,
    isTextBox: true,
  });
  slide.addText("Digital Medical Science Liaison Course", {
    x: MARGIN, y: PAGE.H - 0.9, w: PAGE.W - MARGIN * 2, h: 0.35,
    fontFace: FONTS.body, fontSize: 12, color: COLORS.muted, margin: 0,
    isTextBox: true,
  });
  return slide;
}

// --- Bullet slide with numbered chips --------------------------------------
function bulletSlide(pres, { moduleNum, moduleTitle, pageLabel, eyebrow, title, bullets }) {
  const slide = pres.addSlide();
  slide.background = { color: COLORS.bg };
  addEyebrow(slide, eyebrow);
  slide.addText(title, {
    x: MARGIN, y: MARGIN + 0.45, w: PAGE.W - MARGIN * 2, h: 1.0,
    fontFace: FONTS.display, bold: true, fontSize: 28, color: COLORS.ink,
    margin: 0, valign: "top", lineSpacingMultiple: 1.05,
    isTextBox: true,
  });

  const startY = 2.35;
  const rowH = Math.min(0.92, (PAGE.H - startY - 0.75) / bullets.length);
  const chip = 0.42;
  bullets.forEach((b, i) => {
    const y = startY + i * rowH;
    slide.addShape("roundRect", {
      x: MARGIN, y: y + (rowH - chip) / 2, w: chip, h: chip,
      rectRadius: 0.09,
      fill: { color: i % 2 === 0 ? COLORS.primary : COLORS.accent },
      line: { type: "none" },
    });
    slide.addText(String(i + 1), {
      x: MARGIN, y: y + (rowH - chip) / 2, w: chip, h: chip,
      align: "center", valign: "middle", fontFace: FONTS.label, bold: true,
      fontSize: 14, color: COLORS.white, margin: 0, isTextBox: true,
    });
    slide.addText(b, {
      x: MARGIN + chip + 0.35, y, w: PAGE.W - MARGIN * 2 - chip - 0.35, h: rowH,
      fontFace: FONTS.body, fontSize: 15, color: COLORS.ink,
      valign: "middle", margin: 0, lineSpacingMultiple: 1.15,
      isTextBox: true,
    });
  });

  addFooter(slide, { moduleNum, moduleTitle, pageLabel });
  return slide;
}

// --- Stat callout slide -----------------------------------------------------
function statSlide(pres, { moduleNum, moduleTitle, pageLabel, eyebrow, title, stats, caption }) {
  const slide = pres.addSlide();
  slide.background = { color: COLORS.bg };
  addEyebrow(slide, eyebrow);
  slide.addText(title, {
    x: MARGIN, y: MARGIN + 0.45, w: PAGE.W - MARGIN * 2, h: 1.0,
    fontFace: FONTS.display, bold: true, fontSize: 28, color: COLORS.ink,
    margin: 0, valign: "top", lineSpacingMultiple: 1.05,
    isTextBox: true,
  });

  const n = stats.length;
  const gap = 0.5;
  const cardW = (PAGE.W - MARGIN * 2 - gap * (n - 1)) / n;
  const cardY = 2.6, cardH = 2.9;
  stats.forEach((s, i) => {
    const x = MARGIN + i * (cardW + gap);
    slide.addShape("roundRect", {
      x, y: cardY, w: cardW, h: cardH, rectRadius: 0.12,
      fill: { color: COLORS.surface }, line: { type: "none" },
    });
    slide.addText(s.value, {
      x, y: cardY + 0.35, w: cardW, h: 1.3,
      align: "center", fontFace: FONTS.display, bold: true, fontSize: 44,
      color: i === n - 1 && s.emphasize ? COLORS.success : COLORS.primary,
      margin: 0, isTextBox: true,
    });
    slide.addText(s.label, {
      x, y: cardY + 1.75, w: cardW - 0.4, h: 0.9,
      align: "center", fontFace: FONTS.body, fontSize: 13, color: COLORS.ink,
      margin: 0, valign: "top", x_offset: 0.2, isTextBox: true,
    });
  });
  if (caption) {
    slide.addText(caption, {
      x: MARGIN, y: cardY + cardH + 0.3, w: PAGE.W - MARGIN * 2, h: 0.5,
      align: "center", fontFace: FONTS.body, italic: true, fontSize: 12.5,
      color: COLORS.muted, margin: 0, isTextBox: true,
    });
  }

  addFooter(slide, { moduleNum, moduleTitle, pageLabel });
  return slide;
}

// --- Closing / takeaway slide (full-bleed surface tint) ---------------------
function takeawaySlide(pres, { moduleNum, moduleTitle, pageLabel, title, bullets, nextLesson }) {
  const slide = pres.addSlide();
  slide.background = { color: COLORS.surface };
  addGlow(slide, { x: PAGE.W - 4.5, y: -2.5, size: 7, color: COLORS.primary, transparency: 88 });

  addEyebrow(slide, "Key Takeaway", { color: COLORS.primary });
  slide.addText(title, {
    x: MARGIN, y: MARGIN + 0.45, w: PAGE.W - MARGIN * 2, h: 1.1,
    fontFace: FONTS.display, bold: true, fontSize: 26, color: COLORS.ink,
    margin: 0, valign: "top", lineSpacingMultiple: 1.05,
    isTextBox: true,
  });

  const startY = 2.55;
  bullets.forEach((b, i) => {
    const y = startY + i * 0.85;
    slide.addShape("roundRect", {
      x: MARGIN, y: y + 0.06, w: 0.16, h: 0.16, rectRadius: 0.04,
      fill: { color: COLORS.success }, line: { type: "none" },
    });
    slide.addText(b, {
      x: MARGIN + 0.4, y, w: PAGE.W - MARGIN * 2 - 0.4, h: 0.75,
      fontFace: FONTS.body, fontSize: 15, color: COLORS.ink,
      valign: "top", margin: 0, lineSpacingMultiple: 1.15,
      isTextBox: true,
    });
  });

  if (nextLesson) {
    slide.addText(`NEXT LESSON  →  ${nextLesson}`, {
      x: MARGIN, y: PAGE.H - 1.0, w: PAGE.W - MARGIN * 2, h: 0.4,
      fontFace: FONTS.label, bold: true, fontSize: 11, color: COLORS.primary,
      charSpacing: 1, margin: 0, isTextBox: true,
    });
  }
  addFooter(slide, { moduleNum, moduleTitle, pageLabel });
  return slide;
}

module.exports = { titleSlide, bulletSlide, statSlide, takeawaySlide };
