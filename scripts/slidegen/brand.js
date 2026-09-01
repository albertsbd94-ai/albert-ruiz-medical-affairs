// Brand system shared by every lesson deck — pulled directly from
// albertruiz.com's styles.css. Keep this the single source of truth so
// every module/lesson deck stays visually consistent.
const COLORS = {
  primary: "7A00E6",
  accent: "AB30E8",
  ink: "29232F",
  bg: "FFFFFF",
  surface: "F5F3F7",
  border: "E6E2E9",
  muted: "66616B",
  success: "389466",
  white: "FFFFFF",
};

const FONTS = {
  display: "Bricolage Grotesque", // headings
  body: "DM Sans",                // body copy
  label: "Poppins",               // eyebrows / labels (Bold)
};

// 16:9 widescreen, matches PowerPoint default
const PAGE = { W: 13.333, H: 7.5 };
const MARGIN = 0.7;

function setLayout(pres) {
  pres.defineLayout({ name: "DMSL_WIDE", width: PAGE.W, height: PAGE.H });
  pres.layout = "DMSL_WIDE";
}

// Soft decorative glow — the site's ".hero-glow" motif, reused as a subtle
// background texture. Never a stripe/bar; always a soft off-canvas blob.
function addGlow(slide, { x, y, size = 6.5, color = COLORS.accent, transparency = 90 }) {
  slide.addShape("ellipse", {
    x, y, w: size, h: size,
    fill: { color, transparency },
    line: { type: "none" },
  });
}

const path = require("path");
const DMSL_LOGO = path.join(__dirname, "assets_dmsl-logo.png");

// The DMSL Course logo — black-on-white circular mark (assets/img/dmsl-logo-on-light.webp).
function addBrandMark(slide, { x = MARGIN, y = MARGIN, size = 0.5 } = {}) {
  slide.addImage({ path: DMSL_LOGO, x, y, w: size, h: size });
}

function addFooter(slide, { moduleNum, moduleTitle, pageLabel }) {
  slide.addText(`DMSL COURSE · MODULE ${moduleNum} — ${moduleTitle.toUpperCase()}`, {
    x: MARGIN, y: PAGE.H - 0.5, w: PAGE.W - MARGIN * 2 - 1.2, h: 0.35,
    fontFace: FONTS.label, bold: true, fontSize: 8, color: COLORS.muted,
    charSpacing: 1, align: "left", valign: "middle", margin: 0,
    isTextBox: true,
  });
  slide.addText(pageLabel, {
    x: PAGE.W - MARGIN - 1.2, y: PAGE.H - 0.5, w: 1.2, h: 0.35,
    fontFace: FONTS.label, bold: true, fontSize: 8, color: COLORS.muted,
    align: "right", valign: "middle", margin: 0,
    isTextBox: true,
  });
}

function addEyebrow(slide, text, { x = MARGIN, y = MARGIN, w = PAGE.W - MARGIN * 2, color = COLORS.primary } = {}) {
  slide.addText(text.toUpperCase(), {
    x, y, w, h: 0.35,
    fontFace: FONTS.label, bold: true, fontSize: 12, color,
    charSpacing: 1.5, align: "left", valign: "middle", margin: 0,
    isTextBox: true,
  });
}

module.exports = { COLORS, FONTS, PAGE, MARGIN, setLayout, addGlow, addBrandMark, addFooter, addEyebrow };
