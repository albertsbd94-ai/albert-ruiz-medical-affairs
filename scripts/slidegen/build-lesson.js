// Usage: node build-lesson.js lessons/m1-l1.json out.pptx
const fs = require("fs");
const pptxgen = require("pptxgenjs");
const { setLayout } = require("./brand");
const { titleSlide, bulletSlide, statSlide, takeawaySlide } = require("./slides");

const [, , jsonPath, outPath] = process.argv;
if (!jsonPath || !outPath) {
  console.error("Usage: node build-lesson.js lessons/<file>.json <out>.pptx");
  process.exit(1);
}
const data = JSON.parse(fs.readFileSync(jsonPath, "utf8"));

const pres = new pptxgen();
setLayout(pres);
pres.title = `${data.lessonTitle} — DMSL Course`;
pres.author = "Albert Ruiz de la Oliva";

titleSlide(pres, {
  moduleNum: data.moduleNum, moduleTitle: data.moduleTitle,
  lessonNum: data.lessonNum, lessonTitle: data.lessonTitle,
  totalLessons: data.totalLessons,
});

const totalPages = data.slides.length + 2; // +title +takeaway
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
    throw new Error(`Unknown slide type: ${s.type}`);
  }
});

takeawaySlide(pres, {
  moduleNum: data.moduleNum, moduleTitle: data.moduleTitle,
  pageLabel: `${totalPages} / ${totalPages}`,
  title: data.takeaway.title, bullets: data.takeaway.bullets,
  nextLesson: data.takeaway.nextLesson,
});

pres.writeFile({ fileName: outPath }).then(() => {
  console.log("Wrote", outPath);
});
