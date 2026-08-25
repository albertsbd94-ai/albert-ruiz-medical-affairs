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
      { label: "About", href: "/about.html" },
      { label: "Insights", href: "/insights.html" },
      { label: "AI Lab", href: "/lab.html" },
      { label: "Projects", href: "/projects.html" },
      { label: "Community", href: "/community.html" },
      { label: "Speaking", href: "/speaking.html" }
    ],

    footerColumns: {
      explore: [
        { label: "About", href: "/about.html" },
        { label: "AI in Medical Affairs", href: "/ai-medical-affairs.html" },
        { label: "Digital Medical Affairs", href: "/digital-medical-affairs.html" },
        { label: "Digital Opinion Leaders", href: "/digital-opinion-leaders.html" },
        { label: "Medical Affairs AI Lab", href: "/lab.html" }
      ],
      content: [
        { label: "Insights", href: "/insights.html" },
        { label: "Resources", href: "/resources.html" },
        { label: "Projects", href: "/projects.html" },
        { label: "Tools", href: "/tools.html" },
        { label: "Speaking & Education", href: "/speaking.html" }
      ],
      connect: [
        { label: "Community", href: "/community.html" },
        { label: "Work With Me", href: "/work-with-me.html" },
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
