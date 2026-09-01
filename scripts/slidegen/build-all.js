// Builds every lessons/mX-lY.json into assets/slides/mX-lY.pptx.
// Usage: node build-all.js [--only m3]   (optional module filter)
const fs = require("fs");
const path = require("path");
const pptxgen = require("pptxgenjs");
const { setLayout } = require("./brand");
const { titleSlide, bulletSlide, statSlide, takeawaySlide } = require("./slides");

const onlyArgIdx = process.argv.indexOf("--only");
const only = onlyArgIdx >= 0 ? process.argv[onlyArgIdx + 1] : null; // e.g. "m3"

const lessonsDir = path.join(__dirname, "lessons");
const outDir = path.join(__dirname, "..", "..", "assets", "slides");
fs.mkdirSync(outDir, { recursive: true });

let files = fs.readdirSync(lessonsDir).filter(f => f.endsWith(".json"));
if (only) files = files.filter(f => f.startsWith(only + "-"));
files.sort((a, b) => {
  const pa = a.match(/m(\d+)-l(\d+)/), pb = b.match(/m(\d+)-l(\d+)/);
  return (+pa[1] - +pb[1]) || (+pa[2] - +pb[2]);
});

async function buildOne(file) {
  const data = JSON.parse(fs.readFileSync(path.join(lessonsDir, file), "utf8"));
  const pres = new pptxgen();
  setLayout(pres);
  pres.title = `${data.lessonTitle} — DMSL Course`;
  pres.author = "Albert Ruiz de la Oliva";

  titleSlide(pres, {
    moduleNum: data.moduleNum, moduleTitle: data.moduleTitle,
    lessonNum: data.lessonNum, lessonTitle: data.lessonTitle,
    totalLessons: data.totalLessons,
  });

  const totalPages = data.slides.length + 2;
  data.slides.forEach((s, i) => {
    const pageLabel = `${i + 2} / ${totalPages}`;
    if (s.type === "bullets") {
      bulletSlide(pres, {
        moduleNum: data.moduleNum, moduleTitle: data.moduleTitle, pageLabel,
        eyebrow: s.eyebrow, title: s.title, bullets: s.bullets,
      });
    } else if (s.type === "stats") {
      statSlide(pres, {
        moduleNum: data.moduleNum, moduleTitle: data.moduleTitle, pageLabel,
        eyebrow: s.eyebrow, title: s.title, stats: s.stats, caption: s.caption,
      });
    } else {
      throw new Error(`${file}: unknown slide type "${s.type}"`);
    }
  });

  takeawaySlide(pres, {
    moduleNum: data.moduleNum, moduleTitle: data.moduleTitle,
    pageLabel: `${totalPages} / ${totalPages}`,
    title: data.takeaway.title, bullets: data.takeaway.bullets,
    nextLesson: data.takeaway.nextLesson,
  });

  const outName = file.replace(/\.json$/, ".pptx");
  const outPath = path.join(outDir, outName);
  await pres.writeFile({ fileName: outPath });
  return outName;
}

(async () => {
  let ok = 0, fail = 0;
  for (const file of files) {
    try {
      const out = await buildOne(file);
      console.log("OK  ", out);
      ok++;
    } catch (e) {
      console.error("FAIL", file, "-", e.message);
      fail++;
    }
  }
  console.log(`\nBuilt ${ok} decks, ${fail} failures, out of ${files.length} outline files.`);
  if (fail > 0) process.exit(1);
})();
