/* lib/manifest.js — brand data only. Classic script, no imports/exports. */
(function () {
  "use strict";

  window.__BRAND__ = {
    name: "Albert Ruiz de la Oliva",
    shortName: "Albert Ruiz",
    initials: "AR",
    role: "Medical Science Liaison · Medical Affairs · AI",
    email: "hello@albertruizdelaoliva.com",
    location: "Spain",
    social: {
      linkedin: "https://www.linkedin.com/in/albertruizdelaoliva/",
      twitter: "https://x.com/albertruizdo"
    },

    nav: [
      { label: "About", href: "/about-en.html" },
      { label: "Insights", href: "/insights-en.html" },
      { label: "AI Lab", href: "/lab-en.html" },
      { label: "Projects", href: "/projects-en.html" },
      { label: "Community", href: "/community-en.html" },
      { label: "Speaking", href: "/speaking-en.html" }
    ],

    footerColumns: {
      explore: [
        { label: "About", href: "/about-en.html" },
        { label: "AI in Medical Affairs", href: "/ai-medical-affairs-en.html" },
        { label: "Digital Medical Affairs", href: "/digital-medical-affairs-en.html" },
        { label: "Digital Opinion Leaders", href: "/digital-opinion-leaders-en.html" },
        { label: "Medical Affairs AI Lab", href: "/lab-en.html" }
      ],
      content: [
        { label: "Insights", href: "/insights-en.html" },
        { label: "Resources", href: "/resources-en.html" },
        { label: "Projects", href: "/projects-en.html" },
        { label: "Tools", href: "/tools-en.html" },
        { label: "Speaking & Education", href: "/speaking-en.html" }
      ],
      connect: [
        { label: "Community", href: "/community-en.html" },
        { label: "Work With Me", href: "/work-with-me-en.html" },
        { label: "Newsletter", href: "/#newsletter" },
        { label: "Contact", href: "/#connect" }
      ]
    },

    newsletter: {
      name: "The Future of Medical Affairs",
      subtitle: "Science, AI and digital innovation shaping the next generation of Medical Affairs.",
      cta: "Join professionals exploring what's next."
    },

    disclaimers: {
      views: "The views expressed on this website are my own and do not necessarily represent those of my current or previous employers.",
      medical: "Content shared on this website is intended for professional and educational purposes and should not be interpreted as medical advice."
    }
  };
})();
